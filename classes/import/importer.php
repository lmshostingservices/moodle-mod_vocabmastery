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

use mod_vocabmastery\content;
use mod_vocabmastery\engine;
use mod_vocabmastery\instance;

/**
 * Writes previewed imports into the activity.
 *
 * Duplicate key is set + prompt + answer, so "der Fisch" may live in both
 * "Food" and "Animals" topics.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /** @var instance */
    protected instance $instance;
    /** @var string skip|update|add|replace */
    protected string $duplicates;
    /** @var array stats */
    public array $stats = ['levels' => 0, 'topics' => 0, 'sets' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0,
        'deleted' => 0];
    /** @var array replace mode: setid => ids of the words the set had before the import */
    protected array $replaced = [];
    /** @var array replace mode: setid => [itemid => true] words matched by the import */
    protected array $kept = [];

    /**
     * Constructor.
     *
     * @param instance $instance
     * @param string $duplicates
     */
    public function __construct(instance $instance, string $duplicates = 'skip') {
        $this->instance = $instance;
        $this->duplicates = in_array($duplicates, ['skip', 'update', 'add', 'replace'], true) ? $duplicates : 'skip';
    }

    /**
     * Level id by name, counting creations.
     *
     * @param string $name
     * @return int
     */
    protected function level(string $name): int {
        global $DB;
        $before = $DB->count_records('vocabmastery_level', ['vocabmasteryid' => $this->instance->vm->id]);
        $id = content::level_by_name($this->instance->vm->id, $name);
        $this->stats['levels'] += $DB->count_records('vocabmastery_level', ['vocabmasteryid' => $this->instance->vm->id])
            - $before;
        return $id;
    }

    /**
     * Topic id by name, counting creations.
     *
     * @param int $levelid
     * @param string $name
     * @param int $pathkey
     * @return int
     */
    protected function topic(int $levelid, string $name, int $pathkey = 0): int {
        global $DB;
        $before = $DB->count_records('vocabmastery_topic', ['levelid' => $levelid]);
        $id = content::topic_by_name($this->instance->vm->id, $levelid, $name, $pathkey);
        $this->stats['topics'] += $DB->count_records('vocabmastery_topic', ['levelid' => $levelid]) - $before;
        return $id;
    }

    /**
     * Set id by name, counting creations.
     *
     * @param int $topicid
     * @param string $name
     * @param array $settings
     * @return int
     */
    protected function set(int $topicid, string $name, array $settings): int {
        global $DB;
        $before = $DB->count_records('vocabmastery_set', ['topicid' => $topicid]);
        $id = content::set_by_name($this->instance->vm->id, $topicid, $name, $settings);
        $this->stats['sets'] += $DB->count_records('vocabmastery_set', ['topicid' => $topicid]) - $before;
        if ($this->duplicates === 'replace' && !isset($this->replaced[$id])) {
            // Remember what the set had. Matching words are updated in place, so learner progress is kept.
            // Only words missing from the import are deleted at the end.
            $this->replaced[$id] = array_map('intval', $DB->get_fieldset_select(
                'vocabmastery_item',
                'id',
                'setid = :setid',
                ['setid' => $id]
            ));
            $this->kept[$id] = [];
        }
        return $id;
    }

    /**
     * Store one item according to the duplicate policy.
     *
     * @param int $setid
     * @param array $item
     */
    protected function item(int $setid, array $item): void {
        global $DB;
        if (($item['prompt'] ?? '') === '' || ($item['correctvalue'] ?? '') === '') {
            $this->stats['skipped']++;
            return;
        }
        if ($this->duplicates === 'replace') {
            $dup = content::find_duplicate($setid, $item['prompt'], $item['correctvalue']);
            if ($dup && in_array((int)$dup->id, $this->replaced[$setid] ?? [], true) && empty($this->kept[$setid][$dup->id])) {
                $rec = (object)['id' => $dup->id, 'timemodified' => time()];
                foreach (['translation', 'plural', 'altanswers', 'hint', 'explanation'] as $f) {
                    $rec->$f = (string)($item[$f] ?? '');
                }
                $rec->iscore = isset($item['iscore']) ? (int)$item['iscore'] : 1;
                $rec->metadata = !empty($item['metadata']) ? json_encode($item['metadata'], JSON_UNESCAPED_UNICODE) : null;
                $DB->update_record('vocabmastery_item', content::clean_item($rec));
                $this->kept[$setid][$dup->id] = true;
                $this->stats['updated']++;
                return;
            }
        } else if ($this->duplicates !== 'add') {
            $dup = content::find_duplicate($setid, $item['prompt'], $item['correctvalue']);
            if ($dup) {
                if ($this->duplicates === 'update') {
                    $rec = (object)['id' => $dup->id, 'timemodified' => time()];
                    foreach (['translation', 'plural', 'altanswers', 'hint', 'explanation', 'iscore'] as $f) {
                        if (isset($item[$f]) && $item[$f] !== '') {
                            $rec->$f = $item[$f];
                        }
                    }
                    if (!empty($item['metadata'])) {
                        $rec->metadata = json_encode($item['metadata'], JSON_UNESCAPED_UNICODE);
                    }
                    $DB->update_record('vocabmastery_item', content::clean_item($rec));
                    $this->stats['updated']++;
                } else {
                    $this->stats['skipped']++;
                }
                return;
            }
        }
        content::add_item($this->instance->vm->id, $setid, $item);
        $this->stats['created']++;
    }

    /**
     * Import parsed Word documents.
     *
     * @param array $docs parsed documents keyed by file key
     * @param array $selected fileKey => [sectionKey => 1]
     * @param bool $optional include optional (not scored) items
     * @param bool $notes store "Hinweise" as teacher notes
     * @param array $levelnames fileKey => level name override
     * @return array stats
     */
    public function import_docs(array $docs, array $selected, bool $optional, bool $notes, array $levelnames = []): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        // Import in level / lesson order so topics are created in a sensible order.
        uasort($docs, fn($a, $b) => [$a['level'], $a['pathkey'], $a['topic']] <=> [$b['level'], $b['pathkey'], $b['topic']]);
        foreach ($docs as $key => $doc) {
            $sections = array_filter($doc['sections'], fn($s) => !empty($selected[$key][$s['key']]));
            if (!$sections) {
                continue;
            }
            $levelname = trim($levelnames[$key] ?? '') ?: ($doc['level'] ?: get_string('defaultlevel', 'mod_vocabmastery'));
            $levelid = $this->level($levelname);
            $topicid = $this->topic($levelid, $doc['topic'], (int)$doc['pathkey']);
            if ($notes && $doc['notes'] !== '') {
                $current = (string)$DB->get_field('vocabmastery_topic', 'teachernotes', ['id' => $topicid]);
                if (strpos($current, $doc['notes']) === false) {
                    $DB->set_field(
                        'vocabmastery_topic',
                        'teachernotes',
                        trim($current . "\n" . $doc['notes']),
                        ['id' => $topicid]
                    );
                }
            }
            foreach ($sections as $s) {
                $items = $s['items'];
                if (!$optional) {
                    $items = array_filter($items, fn($i) => !empty($i['iscore']));
                }
                if (!$items) {
                    continue;
                }
                $setid = $this->set($topicid, $s['setname'], $s['settings']);
                foreach ($items as $item) {
                    $this->item($setid, $item);
                }
            }
        }
        $this->finish_replace();
        $transaction->allow_commit();
        content::queue_recompute((int)$this->instance->vm->id);
        $this->instance->reset_cache();
        $this->log();
        return $this->stats;
    }

    /**
     * Import mapped table records (paste / CSV / Excel).
     *
     * @param array $records assoc arrays from table_parser::to_records
     * @param string $level default level name
     * @param string $topic default topic name
     * @param string $set default set name
     * @return array stats
     */
    public function import_records(array $records, string $level, string $topic, string $set): array {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        // Settings for new sets are derived from all answer values going into that set.
        // Set columns in the file (gated, answer mode, instruction) are applied on top.
        $values = [];
        $explicit = [];
        foreach ($records as $r) {
            $key = ($r['level'] ?? $level) . '|' . ($r['topic'] ?? $topic) . '|' . ($r['set'] ?? $set);
            if (($r['correctvalue'] ?? '') !== '') {
                $values[$key][] = $r['correctvalue'];
            }
            foreach (table_parser::SET_FIELDS as $f) {
                if (isset($r[$f]) && $r[$f] !== '' && !isset($explicit[$key][$f])) {
                    $explicit[$key][$f] = $r[$f];
                }
            }
        }
        $applied = [];
        $pathkeys = [];
        foreach ($records as $r) {
            $lname = ($r['level'] ?? '') !== '' ? $r['level'] : $level;
            $tname = ($r['topic'] ?? '') !== '' ? $r['topic'] : $topic;
            $sname = ($r['set'] ?? '') !== '' ? $r['set'] : $set;
            $levelid = $this->level($lname);
            $pk = 0;
            if (preg_match('/(\d+)/', $tname, $m)) {
                $pk = (int)$m[1];
            }
            $pathkeys[$tname] = $pk;
            $topicid = $this->topic($levelid, $tname, $pk);
            $key = ($r['level'] ?? $level) . '|' . ($r['topic'] ?? $topic) . '|' . ($r['set'] ?? $set);
            $vals = array_values(array_unique($values[$key] ?? []));
            $settings = self::apply_set_columns(
                docx_parser::settings_for('generic', $vals, count($values[$key] ?? [])),
                $explicit[$key] ?? [],
                $vals
            );
            $setid = $this->set($topicid, $sname, $settings);
            if (!empty($explicit[$key]) && empty($applied[$setid])) {
                // The file says how this set works: also update a set that already existed.
                $update = array_intersect_key($settings, array_flip(['gated', 'answermode', 'options', 'numoptions',
                    'instruction']));
                $DB->update_record('vocabmastery_set', (object)(['id' => $setid, 'timemodified' => time()] + $update));
                $applied[$setid] = true;
            }
            $item = $r;
            if (!empty($r['source'])) {
                $item['metadata'] = ['source' => $r['source']];
            }
            unset($item['level'], $item['topic'], $item['set'], $item['source']);
            foreach (table_parser::SET_FIELDS as $f) {
                unset($item[$f]);
            }
            $this->item($setid, $item);
        }
        $this->finish_replace();
        $transaction->allow_commit();
        content::queue_recompute((int)$this->instance->vm->id);
        $this->instance->reset_cache();
        $this->log();
        return $this->stats;
    }

    /**
     * Replace mode: delete the words of replaced sets that the import did not contain.
     */
    protected function finish_replace(): void {
        foreach ($this->replaced as $setid => $before) {
            $gone = array_values(array_diff($before, array_keys($this->kept[$setid] ?? [])));
            content::delete_items($gone);
            $this->stats['deleted'] += count($gone);
        }
        $this->replaced = $this->kept = [];
    }

    /**
     * Apply set columns from an imported file on top of the derived set settings.
     *
     * @param array $settings derived settings
     * @param array $explicit gated / answermode / instruction from the file
     * @param array $values distinct answers in the set
     * @return array
     */
    public static function apply_set_columns(array $settings, array $explicit, array $values): array {
        if (isset($explicit['gated'])) {
            $settings['gated'] = (int)(bool)$explicit['gated'];
        }
        if (isset($explicit['instruction'])) {
            $settings['instruction'] = \core_text::substr(trim($explicit['instruction']), 0, 255);
        }
        $mode = $explicit['answermode'] ?? null;
        if ($mode === 'pool') {
            $settings['answermode'] = 'pool';
            $settings['options'] = '';
            $settings['numoptions'] = 4;
        } else if ($mode === 'fixed' && $settings['answermode'] !== 'fixed') {
            $settings['answermode'] = 'fixed';
            $settings['options'] = implode("\n", $values);
            $settings['numoptions'] = count($values);
        }
        return $settings;
    }

    /**
     * Validation warnings for mapped records.
     *
     * @param array $records
     * @return array list of [row, message]
     */
    public static function validate_records(array $records): array {
        $warnings = [];
        $seen = [];
        foreach ($records as $i => $r) {
            $row = $i + 1;
            if (($r['prompt'] ?? '') === '') {
                $warnings[] = [$row, get_string('warn_noprompt', 'mod_vocabmastery')];
            }
            if (($r['correctvalue'] ?? '') === '') {
                $warnings[] = [$row, get_string('warn_noanswer', 'mod_vocabmastery')];
            }
            $key = ($r['level'] ?? '') . '|' . ($r['topic'] ?? '') . '|' . ($r['set'] ?? '') . '|' .
                engine::normalise($r['prompt'] ?? '') . '|' . engine::normalise($r['correctvalue'] ?? '');
            if (isset($seen[$key])) {
                $warnings[] = [$row, get_string('warn_duplicate', 'mod_vocabmastery', $seen[$key])];
            } else {
                $seen[$key] = $row;
            }
        }
        return $warnings;
    }

    /**
     * Log event.
     */
    protected function log(): void {
        $event = \mod_vocabmastery\event\content_imported::create([
            'objectid' => $this->instance->vm->id,
            'context' => $this->instance->context,
            'other' => ['items' => $this->stats['created'] + $this->stats['updated']],
        ]);
        $event->trigger();
    }
}
