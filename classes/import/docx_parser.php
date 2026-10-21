<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_vocabmastery\import;

/**
 * Reads structured vocabulary Word documents (.docx).
 *
 * Understands curriculum overviews such as "Wortschatzüberblick" files: a level
 * line, a lesson title, and tables grouped under headings (core nouns, optional
 * nouns, verb groups, verb + preposition). Any other .docx with tables is read
 * generically: each table becomes a set, columns are mapped from the header row.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docx_parser {
    /** Words that can end a reflexive verb and actually are its fixed preposition. */
    const PREPOSITIONS = [
        'an', 'auf', 'aus', 'bei', 'durch', 'für', 'gegen', 'in', 'mit', 'nach', 'über', 'um',
        'unter', 'von', 'vor', 'zu', 'zwischen',
    ];

    /** Largest word/document.xml we unpack (bytes). */
    const MAX_DOCUMENT_XML_BYTES = 20 * 1024 * 1024;

    /** Known article-style answer sets, in their natural order. */
    const ORDERED_OPTIONS = [
        ['der', 'die', 'das'],
        ['le', 'la', "l'", 'les'],
        ['el', 'la', 'los', 'las'],
        ['il', 'lo', 'la', "l'"],
        ['de', 'het'],
    ];

    /**
     * Read the body of a .docx as a sequence of paragraphs and tables.
     *
     * @param string $path
     * @return array list of ['type' => 'p', 'text' => string] | ['type' => 'table', 'rows' => array]
     */
    public static function read_blocks(string $path): array {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \moodle_exception('importdocxinvalid', 'mod_vocabmastery');
        }
        // Refuse documents that unpack to something huge (zip bombs) before reading them into memory.
        $stat = $zip->statName('word/document.xml');
        if ($stat && $stat['size'] > self::MAX_DOCUMENT_XML_BYTES) {
            $zip->close();
            throw new \moodle_exception('importtoolarge', 'mod_vocabmastery');
        }
        $xml = $stat ? $zip->getFromName('word/document.xml') : false;
        $zip->close();
        if ($xml === false) {
            throw new \moodle_exception('importdocxinvalid', 'mod_vocabmastery');
        }
        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xp->query('//w:body')->item(0);
        $blocks = [];
        if (!$body) {
            return $blocks;
        }
        foreach ($body->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if ($node->localName === 'p') {
                $t = self::para_text($xp, $node);
                if ($t !== '') {
                    $blocks[] = ['type' => 'p', 'text' => $t];
                }
            } else if ($node->localName === 'tbl') {
                $rows = [];
                foreach ($xp->query('./w:tr', $node) as $tr) {
                    $cells = [];
                    foreach ($xp->query('./w:tc', $tr) as $tc) {
                        $parts = [];
                        foreach ($xp->query('.//w:p', $tc) as $p) {
                            $pt = self::para_text($xp, $p);
                            if ($pt !== '') {
                                $parts[] = $pt;
                            }
                        }
                        $cells[] = trim(implode(' ', $parts));
                    }
                    if (array_filter($cells, fn($c) => $c !== '')) {
                        $rows[] = $cells;
                    }
                }
                if ($rows) {
                    $blocks[] = ['type' => 'table', 'rows' => $rows];
                }
            } else if ($node->localName === 'sdt') {
                foreach ($xp->query('.//w:p', $node) as $p) {
                    $t = self::para_text($xp, $p);
                    if ($t !== '') {
                        $blocks[] = ['type' => 'p', 'text' => $t];
                    }
                }
            }
        }
        return $blocks;
    }

    /**
     * Text of a paragraph.
     *
     * @param \DOMXPath $xp
     * @param \DOMElement $p
     * @return string
     */
    protected static function para_text(\DOMXPath $xp, \DOMElement $p): string {
        $text = '';
        foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $p) as $n) {
            $text .= $n->localName === 't' ? $n->textContent : ' ';
        }
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    /**
     * Is a value an empty marker (–, --, —).
     *
     * @param string $v
     * @return bool
     */
    public static function is_blank(string $v): bool {
        return trim($v) === '' || (bool)preg_match('/^[-–—\s]+$/u', $v);
    }

    /**
     * Parse one document into a structured import proposal.
     *
     * @param string $path
     * @param string $filename
     * @return array
     */
    public static function parse(string $path, string $filename): array {
        $blocks = self::read_blocks($path);
        $doc = [
            'filename' => $filename,
            'level' => '',
            'topic' => '',
            'pathkey' => 0,
            'notes' => '',
            'sections' => [],
            'warnings' => [],
        ];
        $major = '';
        $sub = '';
        $optional = false;
        $innotes = false;
        $notes = [];
        $headingcandidates = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'p') {
                $t = $b['text'];
                if (
                    !$doc['level'] && preg_match(
                        '/\b(?:KURSSTUFE|KURS-?STUFE|NIVEAU|STUFE|LEVEL)\s*:?\s*([A-C][12](?:\.\d)?\+?)/iu',
                        $t,
                        $m
                    )
                ) {
                    $doc['level'] = strtoupper($m[1]);
                    continue;
                }
                if (
                    !$doc['topic'] && preg_match(
                        '/^(Lektion|Lesson|Unit|Kapitel|Chapter|Topic|Thema|Einheit|Leçon|Lección)\s+(\d+)\s*[:–—\-]*\s*(.*)$/iu',
                        $t,
                        $m
                    )
                ) {
                    $doc['pathkey'] = (int)$m[2];
                    $title = trim($m[3]);
                    $doc['topic'] = $m[1] . ' ' . $m[2] . ($title !== '' ? ' – ' . $title : '');
                    continue;
                }
                if (preg_match('/^(Hinweise|Notes|Anmerkungen|Hinweis)\b/iu', $t)) {
                    $innotes = true;
                    continue;
                }
                if ($innotes) {
                    $notes[] = $t;
                    continue;
                }
                if (preg_match('/^\d+\s*[·.)]\s*(.+)$/u', $t, $m) && mb_strlen($t) < 90) {
                    $major = self::clean_heading($m[1]);
                    $sub = '';
                    $optional = self::is_optional_heading($t);
                    continue;
                }
                if (preg_match('/^[A-H]\)\s*(.+)$/u', $t, $m) && mb_strlen($t) < 90) {
                    $sub = self::clean_heading($m[1]);
                    $optional = self::is_optional_heading($t);
                    continue;
                }
                if (self::is_optional_heading($t) && mb_strlen($t) < 60) {
                    $optional = true;
                    continue;
                }
                if (mb_strlen($t) < 60) {
                    $headingcandidates[] = $t;
                }
                continue;
            }
            // A table.
            foreach (self::parse_table($b['rows'], $major, $sub, $optional, end($headingcandidates) ?: '') as $section) {
                $section['key'] = 's' . count($doc['sections']);
                $doc['sections'][] = $section;
            }
        }
        if (!$doc['level'] && preg_match('/[_\-\s]([A-C][12])[_\-\s.]/i', $filename, $m)) {
            $doc['level'] = strtoupper($m[1]);
        }
        if (!$doc['topic']) {
            if (preg_match('/(Lektion|Lesson|Unit|Topic)[_\-\s]*(\d+)/i', $filename, $m)) {
                $doc['pathkey'] = (int)$m[2];
                $doc['topic'] = ucfirst(strtolower($m[1])) . ' ' . $m[2];
            } else {
                $doc['topic'] = pathinfo($filename, PATHINFO_FILENAME);
            }
        }
        $ignore = self::ignored_note_prefixes();
        $doc['notes'] = implode(
            "\n",
            array_filter(
                $notes,
                function ($n) use ($ignore) {
                    foreach ($ignore as $prefix) {
                        if (\core_text::strpos(\core_text::strtolower($n), $prefix) === 0) {
                            return false;
                        }
                    }
                    return true;
                }
            )
        );
        self::merge_and_validate($doc);
        return $doc;
    }

    /**
     * Lines starting with these (lower case) are never kept as teacher notes: source lines,
     * copyright footers and whatever the administrator listed in the plugin settings.
     *
     * @return string[]
     */
    protected static function ignored_note_prefixes(): array {
        $configured = preg_split('/\r\n|\r|\n/', (string)get_config('mod_vocabmastery', 'importignorelines'));
        $prefixes = array_merge(['quelle:', 'source:', '©', '(c) ', 'copyright'], $configured);
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        fn($p) => \core_text::strtolower(trim($p)),
                        $prefixes
                    ),
                    fn($p) => $p !== ''
                )
            )
        );
    }

    /**
     * Clean a heading into a set name.
     *
     * @param string $h
     * @return string
     */
    protected static function clean_heading(string $h): string {
        $h = preg_replace('/\s*[—–-]\s*(Kern|Core)\b.*$/iu', '', $h);
        $h = preg_replace('/\s*\((?:neu ab|new from|ab|from)?\s*[A-C][12][^)]*\)\s*$/iu', '', $h);
        return trim($h);
    }

    /**
     * Heading marks optional (not scored) items.
     *
     * @param string $t
     * @return bool
     */
    protected static function is_optional_heading(string $t): bool {
        return (bool)preg_match('/\b(optional|nicht gewertet|not scored|extra)\b/iu', $t);
    }

    /**
     * Turn one table into a section.
     *
     * @param array $rows
     * @param string $major
     * @param string $sub
     * @param bool $optional
     * @param string $fallbackheading
     * @return array list of sections (usually one)
     */
    protected static function parse_table(
        array $rows,
        string $major,
        string $sub,
        bool $optional,
        string $fallbackheading
    ): array {
        $header = array_map(fn($c) => \core_text::strtolower(trim($c)), $rows[0]);
        $col = function (array $names) use ($header) {
            foreach ($header as $i => $h) {
                foreach ($names as $n) {
                    if ($h === $n || str_starts_with($h, $n)) {
                        return $i;
                    }
                }
            }
            return null;
        };
        $article = $col(['artikel', 'article', 'genus', 'gender']);
        $noun = $col(['nomen', 'noun', 'substantiv', 'wort', 'word']);
        $prep = $col(['präposition', 'praeposition', 'preposition']);
        $verb = $col(['verb']);
        $trans = $col(['englisch', 'english', 'translation', 'übersetzung', 'bedeutung', 'meaning']);
        $plural = $col(['plural']);
        $source = $col(['quelle', 'source']);

        $name = $major ?: $fallbackheading ?: get_string('defaultset', 'mod_vocabmastery');
        if ($sub !== '') {
            $name .= ': ' . $sub;
        }
        $section = [
            'setname' => $name,
            'label' => $name . ($optional ? ' (' . get_string('optional', 'mod_vocabmastery') . ')' : ''),
            'core' => !$optional,
            'kind' => 'generic',
            'items' => [],
            'skipped' => [],
            'settings' => [],
        ];
        $body = array_slice($rows, 1);
        $meaning = [];
        $get = fn($r, $i) => $i === null ? '' : trim((string)($r[$i] ?? ''));

        if ($article !== null && $noun !== null) {
            $section['kind'] = 'noun';
            foreach ($body as $r) {
                $section['items'][] = [
                    'prompt' => $get($r, $noun),
                    'correctvalue' => $get($r, $article),
                    'translation' => $get($r, $trans),
                    'plural' => self::is_blank($get($r, $plural)) ? '' : $get($r, $plural),
                    'metadata' => array_filter(['source' => $get($r, $source), 'type' => 'noun']),
                ];
            }
        } else if ($prep !== null && ($verb !== null || $noun !== null)) {
            $section['kind'] = 'verbprep';
            $pcol = $verb ?? $noun;
            foreach ($body as $r) {
                $prompt = $get($r, $pcol);
                $answer = $get($r, $prep);
                // Split e.g. "sich ärgern über" + "+ Akk." => prompt "sich ärgern", answer "über + Akk.".
                if (preg_match('/^\+/u', $answer)) {
                    $words = preg_split('/\s+/u', $prompt);
                    $last = \core_text::strtolower((string)end($words));
                    if (count($words) > 1 && in_array($last, self::PREPOSITIONS, true)) {
                        array_pop($words);
                        $prompt = implode(' ', $words);
                        $answer = $last . ' ' . $answer;
                    }
                }
                if (self::is_blank($answer)) {
                    // No fixed preposition: still worth learning, as a meaning drill.
                    if ($get($r, $trans) !== '') {
                        $meaning[] = [
                            'prompt' => $prompt,
                            'correctvalue' => $get($r, $trans),
                            'translation' => '',
                            'plural' => '',
                            'metadata' => ['type' => 'verb', 'verbtype' => 'reflexive'],
                        ];
                    }
                    continue;
                }
                $section['items'][] = [
                    'prompt' => $prompt,
                    'correctvalue' => $answer,
                    'translation' => $get($r, $trans),
                    'plural' => '',
                    'metadata' => ['type' => 'verbprep'],
                ];
            }
        } else if ($verb !== null && $trans !== null) {
            $section['kind'] = 'verb';
            $type = preg_match('/trennbar|separable/iu', $sub . $major) ? 'separable'
                : (preg_match('/reflexiv|reflexive/iu', $sub . $major) ? 'reflexive'
                : (preg_match('/unregelm|irregular/iu', $sub . $major) ? 'irregular' : 'regular'));
            foreach ($body as $r) {
                $section['items'][] = [
                    'prompt' => $get($r, $verb),
                    'correctvalue' => $get($r, $trans),
                    'translation' => '',
                    'plural' => '',
                    'metadata' => ['type' => 'verb', 'verbtype' => $type],
                ];
            }
        } else {
            // Generic table: use the paste importer's mapping.
            $guess = table_parser::guess_mapping($rows);
            if (!in_array('prompt', $guess['mapping'], true) || !in_array('correctvalue', $guess['mapping'], true)) {
                return [];
            }
            foreach (table_parser::to_records($rows, $guess['mapping'], $guess['hasheader']) as $rec) {
                $section['items'][] = [
                    'prompt' => $rec['prompt'] ?? '',
                    'correctvalue' => $rec['correctvalue'] ?? '',
                    'translation' => $rec['translation'] ?? '',
                    'plural' => $rec['plural'] ?? '',
                    'metadata' => array_filter(['source' => $rec['source'] ?? '']),
                ];
            }
        }
        $out = [];
        $section['items'] = array_values(array_filter($section['items'], fn($i) => $i['prompt'] !== ''));
        if ($section['items']) {
            $out[] = $section;
        }
        if ($meaning) {
            $mname = get_string('meaningset', 'mod_vocabmastery', $name);
            $out[] = array_merge(
                $section,
                ['setname' => $mname, 'label' => $mname . ($optional ? ' (' .
                    get_string('optional', 'mod_vocabmastery') . ')' : ''), 'kind' => 'verb', 'items' => $meaning]
            );
        }
        return $out;
    }

    /**
     * Fold optional sections into their core set, choose set settings, flag problems.
     *
     * @param array $doc
     */
    protected static function merge_and_validate(array &$doc): void {
        // Optional tables directly following a core table of the same kind belong to that set.
        $lastcore = [];
        foreach ($doc['sections'] as &$s) {
            if ($s['core']) {
                $lastcore[$s['kind']] = $s['setname'];
            } else if (isset($lastcore[$s['kind']])) {
                $s['setname'] = $lastcore[$s['kind']];
            }
        }
        unset($s);

        // Answer values per set name.
        $values = [];
        foreach ($doc['sections'] as $s) {
            foreach ($s['items'] as $i) {
                if ($i['correctvalue'] !== '') {
                    $values[$s['setname']][] = $i['correctvalue'];
                }
            }
        }
        $seen = [];
        foreach ($doc['sections'] as &$s) {
            $vals = array_values(array_unique($values[$s['setname']] ?? []));
            $s['settings'] = self::settings_for($s['kind'], $vals);
            $fixed = $s['settings']['answermode'] === 'fixed'
                ? array_map('mb_strtolower', explode("\n", $s['settings']['options'])) : null;
            $kept = [];
            foreach ($s['items'] as $item) {
                $key = $s['setname'] . '|' . \mod_vocabmastery\engine::normalise($item['prompt']) . '|' .
                    \mod_vocabmastery\engine::normalise($item['correctvalue']);
                if ($item['correctvalue'] === '') {
                    $s['skipped'][] = ['prompt' => $item['prompt'], 'reason' => 'noanswer'];
                    continue;
                }
                if (isset($seen[$key])) {
                    $s['skipped'][] = ['prompt' => $item['prompt'], 'reason' => 'duplicate'];
                    continue;
                }
                if ($fixed && !in_array(mb_strtolower($item['correctvalue']), $fixed, true)) {
                    $doc['warnings'][] = [
                        'type' => 'unknownanswer', 'prompt' => $item['prompt'],
                        'value' => $item['correctvalue'],
                    ];
                }
                $seen[$key] = true;
                $item['iscore'] = $s['core'] ? 1 : 0;
                $kept[] = $item;
            }
            $s['items'] = $kept;
        }
        unset($s);
    }

    /**
     * Default set settings for a detected kind.
     *
     * @param string $kind
     * @param array $values distinct answer values
     * @param int $itemcount number of words going into the set (0 = unknown)
     * @return array
     */
    public static function settings_for(string $kind, array $values, int $itemcount = 0): array {
        $lower = array_map('mb_strtolower', $values);
        $options = null;
        foreach (self::ORDERED_OPTIONS as $set) {
            if ($lower && !array_diff($lower, $set)) {
                $options = array_values(
                    array_filter(
                        $set,
                        fn($o) => in_array($o, $lower, true)
                            || count($set) <= 3
                    )
                );
                break;
            }
        }
        switch ($kind) {
            case 'noun':
                $options = $options ?? $values;
                return [
                    'itemtype' => 'choice', 'answermode' => 'fixed', 'options' => implode("\n", $options),
                    'numoptions' => count($options), 'showtranslation' => 1, 'gated' => 1,
                    'instruction' => get_string('instruction_article', 'mod_vocabmastery'),
                ];
            case 'verbprep':
                return [
                    'itemtype' => 'choice', 'answermode' => 'pool', 'options' => '', 'numoptions' => 4,
                    'showtranslation' => 1, 'gated' => 0,
                    'instruction' => get_string('instruction_preposition', 'mod_vocabmastery'),
                ];
            case 'verb':
                return [
                    'itemtype' => 'choice', 'answermode' => 'pool', 'options' => '', 'numoptions' => 4,
                    'showtranslation' => 0, 'gated' => 0,
                    'instruction' => get_string('instruction_meaning', 'mod_vocabmastery'),
                ];
            default:
                // Fixed buttons only for a known article set, or a few answers repeated across the words.
                // Unique answers (e.g. verb meanings) use a pool.
                $repeats = $itemcount > count($values);
                if ($options || ($values && count($values) <= 6 && $repeats)) {
                    $opts = $options ?? $values;
                    return [
                        'itemtype' => 'choice', 'answermode' => 'fixed', 'options' => implode("\n", $opts),
                        'numoptions' => count($opts), 'showtranslation' => 1, 'gated' => 1, 'instruction' => '',
                    ];
                }
                return [
                    'itemtype' => 'choice', 'answermode' => 'pool', 'options' => '', 'numoptions' => 4,
                    'showtranslation' => 1, 'gated' => 1, 'instruction' => '',
                ];
        }
    }
}
