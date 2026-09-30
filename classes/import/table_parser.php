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
 * Parses pasted tables, CSV and Excel files into rows and guesses column mappings.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_parser {
    /** Most rows read from one file. */
    const MAX_ROWS = 10000;
    /** Most columns read from one file. */
    const MAX_COLUMNS = 30;

    /** Fields a column can map to. */
    const FIELDS = ['ignore', 'correctvalue', 'prompt', 'translation', 'plural', 'altanswers', 'hint', 'explanation',
        'iscore', 'level', 'topic', 'set', 'source', 'gated', 'answermode', 'instruction'];

    /** Fields that describe the set rather than the word. */
    const SET_FIELDS = ['gated', 'answermode', 'instruction'];

    /** Header synonyms (lower case) => field. */
    const HEADERS = [
        'correctvalue' => ['answer', 'antwort', 'artikel', 'article', 'correct', 'correct answer', 'gender', 'genus',
            'präposition', 'praeposition', 'preposition', 'button', 'buttons', 'lösung', 'solution'],
        'prompt' => ['prompt', 'word', 'wort', 'nomen', 'noun', 'verb', 'term', 'begriff', 'question', 'frage',
            'verb / ausdruck', 'verb (reflexiv)', 'vocabulary', 'item', 'infinitive', 'infinitiv'],
        'translation' => ['translation', 'englisch', 'english', 'übersetzung', 'uebersetzung', 'meaning', 'bedeutung',
            'native', 'native language'],
        'plural' => ['plural', 'pl', 'pl.'],
        'altanswers' => ['alternatives', 'alternative answers', 'also accept', 'alt'],
        'hint' => ['hint', 'tipp', 'hinweis'],
        'explanation' => ['explanation', 'erklärung', 'notes', 'note', 'notiz'],
        'iscore' => ['core', 'kern', 'required', 'pflicht'],
        'level' => ['level', 'niveau', 'stufe', 'kursstufe'],
        'topic' => ['topic', 'lesson', 'lektion', 'thema', 'unit', 'kapitel'],
        'set' => ['set', 'category', 'kategorie', 'type', 'section'],
        'source' => ['source', 'quelle'],
        'gated' => ['gated', 'counts for mastery', 'mastery check', 'wertung'],
        'answermode' => ['answer mode', 'answermode', 'buttons mode', 'antwortmodus'],
        'instruction' => ['instruction', 'anweisung', 'aufgabe', 'aufgabenstellung'],
    ];

    /**
     * Split pasted text or CSV content into rows.
     *
     * @param string $text
     * @return array[]
     */
    public static function parse_text(string $text): array {
        $text = str_replace(["\r\n", "\r"], "\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $text)));
        if ($text === '') {
            return [];
        }
        $lines = explode("\n", $text);
        $delimiter = self::detect_delimiter($lines);
        $rows = [];
        if ($delimiter === "\t" || $delimiter === '|') {
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $rows[] = array_map('trim', explode($delimiter, $line));
            }
        } else if ($delimiter === 'space') {
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $rows[] = array_values(array_filter(
                    array_map('trim', preg_split('/\s{2,}/', trim($line))),
                    fn($c) => $c !== ''
                ));
            }
        } else {
            $fh = fopen('php://temp', 'r+');
            fwrite($fh, $text);
            rewind($fh);
            while (($r = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
                if ($r === [null] || !array_filter($r, fn($c) => trim((string)$c) !== '')) {
                    continue;
                }
                $rows[] = array_map(fn($c) => trim((string)$c), $r);
            }
            fclose($fh);
        }
        return self::pad($rows);
    }

    /**
     * Read an uploaded CSV or Excel file.
     *
     * @param string $path
     * @param string $filename
     * @return array[]
     */
    public static function parse_file(string $path, string $filename): array {
        global $CFG;
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
            // Moodle 4.4/4.5 only load PhpSpreadsheet through excellib; 5.x also autoloads it.
            require_once($CFG->libdir . '/excellib.class.php');
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            // Refuse sheets larger than an import can use before loading them into memory.
            foreach ($reader->listWorksheetInfo($path) as $info) {
                if ($info['totalRows'] > self::MAX_ROWS + 1 || $info['totalColumns'] > self::MAX_COLUMNS) {
                    throw new \moodle_exception('importtoolarge', 'mod_vocabmastery');
                }
            }
            $book = $reader->load($path);
            $rows = [];
            // Values as stored: formulas are not evaluated.
            foreach ($book->getActiveSheet()->toArray(null, false, true, false) as $r) {
                $r = array_map(fn($c) => trim((string)$c), $r);
                if (array_filter($r, fn($c) => $c !== '')) {
                    $rows[] = $r;
                }
            }
            return self::pad(self::trim_empty_columns($rows));
        }
        $content = file_get_contents($path);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = \core_text::convert($content, 'windows-1252', 'utf-8');
        }
        return self::parse_text($content);
    }

    /**
     * Pick a delimiter.
     *
     * @param array $lines
     * @return string
     */
    protected static function detect_delimiter(array $lines): string {
        $sample = array_slice(array_filter($lines, fn($l) => trim($l) !== ''), 0, 20);
        $best = ',';
        $bestscore = 0;
        foreach (["\t", ';', ',', '|'] as $d) {
            $counts = array_map(fn($l) => substr_count($l, $d), $sample);
            $min = $counts ? min($counts) : 0;
            if ($min > 0 && $min >= $bestscore) {
                if ($min > $bestscore || $d === "\t") {
                    $best = $d;
                    $bestscore = $min;
                }
            }
        }
        if ($bestscore === 0) {
            // Columns separated by runs of spaces (copied from some editors).
            $multi = array_filter($sample, fn($l) => preg_match('/\S\s{2,}\S/', $l));
            if (count($multi) === count($sample) && $sample) {
                return 'space';
            }
            // Single column or single spaces: "der Vater father" is ambiguous, keep as one column.
            return "\t";
        }
        return $best;
    }

    /**
     * Pad rows to equal width.
     *
     * @param array $rows
     * @return array
     */
    protected static function pad(array $rows): array {
        $w = $rows ? max(array_map('count', $rows)) : 0;
        return array_map(fn($r) => array_pad(array_values($r), $w, ''), $rows);
    }

    /**
     * Drop columns that are empty everywhere.
     *
     * @param array $rows
     * @return array
     */
    protected static function trim_empty_columns(array $rows): array {
        if (!$rows) {
            return $rows;
        }
        $w = max(array_map('count', $rows));
        $keep = [];
        for ($c = 0; $c < $w; $c++) {
            foreach ($rows as $r) {
                if (isset($r[$c]) && $r[$c] !== '') {
                    $keep[] = $c;
                    break;
                }
            }
        }
        return array_map(fn($r) => array_map(fn($c) => $r[$c] ?? '', $keep), $rows);
    }

    /**
     * Map a header label to a field.
     *
     * @param string $label
     * @return string|null
     */
    public static function header_field(string $label): ?string {
        $l = \core_text::strtolower(trim(preg_replace('/[*_]/', '', $label)));
        if ($l === '') {
            return null;
        }
        foreach (self::HEADERS as $field => $names) {
            if (in_array($l, $names, true)) {
                return $field;
            }
        }
        foreach (self::HEADERS as $field => $names) {
            foreach ($names as $n) {
                if (strlen($n) > 3 && str_starts_with($l, $n)) {
                    return $field;
                }
            }
        }
        return null;
    }

    /**
     * Guess whether the first row is a header and how columns map.
     *
     * @param array $rows
     * @return array ['hasheader' => bool, 'mapping' => string[]]
     */
    public static function guess_mapping(array $rows): array {
        if (!$rows) {
            return ['hasheader' => false, 'mapping' => []];
        }
        $width = count($rows[0]);
        $headermap = array_map(fn($c) => self::header_field((string)$c), $rows[0]);
        $recognised = count(array_filter($headermap));
        if ($recognised >= min(2, $width)) {
            $mapping = array_map(fn($f) => $f ?? 'ignore', $headermap);
            return ['hasheader' => true, 'mapping' => self::dedupe($mapping)];
        }
        // Heuristic: the column with few distinct short values holds the answer buttons.
        $mapping = array_fill(0, $width, 'ignore');
        $best = null;
        $bestdistinct = PHP_INT_MAX;
        for ($c = 0; $c < $width; $c++) {
            $values = array_filter(array_map(fn($r) => (string)$r[$c], $rows), fn($v) => $v !== '');
            if (!$values) {
                continue;
            }
            $distinct = count(array_unique(array_map('mb_strtolower', $values)));
            $maxlen = max(array_map('mb_strlen', $values));
            if ($distinct <= 8 && $maxlen <= 15 && $distinct < count($values) && $distinct < $bestdistinct) {
                $best = $c;
                $bestdistinct = $distinct;
            }
        }
        $rest = [];
        for ($c = 0; $c < $width; $c++) {
            if ($c !== $best) {
                $rest[] = $c;
            }
        }
        if ($best === null) {
            // Prompt, answer, translation.
            $order = ['prompt', 'correctvalue', 'translation', 'plural'];
            foreach ($rest as $i => $c) {
                $mapping[$c] = $order[$i] ?? 'ignore';
            }
        } else {
            $mapping[$best] = 'correctvalue';
            $order = ['prompt', 'translation', 'plural'];
            foreach ($rest as $i => $c) {
                $mapping[$c] = $order[$i] ?? 'ignore';
            }
        }
        return ['hasheader' => false, 'mapping' => $mapping];
    }

    /**
     * Only one column per field (except ignore).
     *
     * @param array $mapping
     * @return array
     */
    protected static function dedupe(array $mapping): array {
        $seen = [];
        foreach ($mapping as $i => $f) {
            if ($f !== 'ignore' && isset($seen[$f])) {
                $mapping[$i] = 'ignore';
            }
            $seen[$f] = true;
        }
        return $mapping;
    }

    /**
     * Apply mapping to rows.
     *
     * @param array $rows
     * @param array $mapping column index => field
     * @param bool $skipheader
     * @return array list of assoc arrays
     */
    public static function to_records(array $rows, array $mapping, bool $skipheader): array {
        if ($skipheader) {
            array_shift($rows);
        }
        $out = [];
        foreach ($rows as $r) {
            $rec = [];
            foreach ($mapping as $c => $field) {
                if ($field === 'ignore' || !isset($r[$c])) {
                    continue;
                }
                $rec[$field] = trim((string)$r[$c]);
            }
            if (!array_filter($rec, fn($v) => $v !== '')) {
                continue;
            }
            if (isset($rec['iscore'])) {
                $v = \core_text::strtolower($rec['iscore']);
                $rec['iscore'] = in_array($v, ['0', 'no', 'nein', 'n', 'false', 'optional', 'opt'], true) ? 0 : 1;
            }
            if (isset($rec['gated']) && $rec['gated'] !== '') {
                $v = \core_text::strtolower($rec['gated']);
                $rec['gated'] = in_array($v, ['0', 'no', 'nein', 'n', 'false'], true) ? 0 : 1;
            }
            if (isset($rec['answermode'])) {
                $v = \core_text::strtolower($rec['answermode']);
                if (in_array($v, ['fixed', 'buttons', 'fest', 'feste'], true)) {
                    $rec['answermode'] = 'fixed';
                } else if (in_array($v, ['pool', 'random', 'mixed', 'zufall'], true)) {
                    $rec['answermode'] = 'pool';
                } else {
                    unset($rec['answermode']);
                }
            }
            if (isset($rec['plural']) && preg_match('/^[-–—]+$/u', $rec['plural'])) {
                $rec['plural'] = '';
            }
            $out[] = $rec;
        }
        return $out;
    }
}
