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

namespace mod_vocabmastery;

use stdClass;

/**
 * Content CRUD: levels, topics, sets and items.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {
    /** Editable item fields. */
    const ITEM_FIELDS = ['prompt', 'correctvalue', 'altanswers', 'translation', 'plural', 'hint', 'explanation', 'iscore'];

    /**
     * Next sort order.
     *
     * @param string $table
     * @param array $where
     * @return int
     */
    protected static function next_sort(string $table, array $where): int {
        global $DB;
        return (int)$DB->get_field('vocabmastery_' . $table, 'COALESCE(MAX(sortorder), 0) + 1', $where);
    }

    /**
     * Add a level.
     *
     * @param int $vmid
     * @param string $name
     * @param string $description
     * @return int
     */
    public static function add_level(int $vmid, string $name, string $description = ''): int {
        global $DB;
        return $DB->insert_record('vocabmastery_level', (object)[
            'vocabmasteryid' => $vmid, 'name' => trim($name), 'description' => $description,
            'sortorder' => self::next_sort('level', ['vocabmasteryid' => $vmid]),
        ]);
    }

    /**
     * Add a topic.
     *
     * @param int $vmid
     * @param int $levelid
     * @param string $name
     * @param int $pathkey
     * @param string $description
     * @param string $notes
     * @return int
     */
    public static function add_topic(
        int $vmid,
        int $levelid,
        string $name,
        int $pathkey = 0,
        string $description = '',
        string $notes = ''
    ): int {
        global $DB;
        return $DB->insert_record('vocabmastery_topic', (object)[
            'vocabmasteryid' => $vmid, 'levelid' => $levelid, 'name' => trim($name), 'description' => $description,
            'teachernotes' => $notes, 'pathkey' => $pathkey,
            'sortorder' => self::next_sort('topic', ['levelid' => $levelid]), 'timemodified' => time(),
        ]);
    }

    /**
     * Default set settings for a preset.
     *
     * @param string $preset choice_fixed|choice_pool|typed
     * @param string $options
     * @return array
     */
    public static function set_defaults(string $preset, string $options = ''): array {
        switch ($preset) {
            case 'choice_pool':
                return ['itemtype' => 'choice', 'answermode' => 'pool', 'options' => '', 'numoptions' => 4];
            case 'typed':
                return ['itemtype' => 'typed', 'answermode' => 'pool', 'options' => '', 'numoptions' => 4];
            default:
                return ['itemtype' => 'choice', 'answermode' => 'fixed', 'options' => $options, 'numoptions' => 3];
        }
    }

    /**
     * Add a set.
     *
     * @param int $vmid
     * @param int $topicid
     * @param array $data
     * @return int
     */
    public static function add_set(int $vmid, int $topicid, array $data): int {
        global $DB;
        $rec = (object)array_merge([
            'name' => '', 'instruction' => '', 'itemtype' => 'choice', 'answermode' => 'fixed', 'options' => '',
            'numoptions' => 3, 'showtranslation' => 1, 'gated' => 1,
        ], $data);
        $rec->vocabmasteryid = $vmid;
        $rec->topicid = $topicid;
        $rec->sortorder = self::next_sort('set', ['topicid' => $topicid]);
        $rec->timemodified = time();
        return $DB->insert_record('vocabmastery_set', $rec);
    }

    /**
     * Add an item.
     *
     * @param int $vmid
     * @param int $setid
     * @param array $data
     * @return int
     */
    public static function add_item(int $vmid, int $setid, array $data): int {
        global $DB;
        $rec = new stdClass();
        foreach (self::ITEM_FIELDS as $f) {
            $rec->$f = $data[$f] ?? ($f === 'iscore' ? 1 : '');
        }
        $rec = self::clean_item($rec);
        $rec->metadata = !empty($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null;
        $rec->vocabmasteryid = $vmid;
        $rec->setid = $setid;
        $rec->sortorder = $data['sortorder'] ?? self::next_sort('item', ['setid' => $setid]);
        $rec->timecreated = $rec->timemodified = time();
        return $DB->insert_record('vocabmastery_item', $rec);
    }

    /**
     * Trim and limit item fields.
     *
     * @param stdClass $rec
     * @return stdClass
     */
    public static function clean_item(stdClass $rec): stdClass {
        foreach (self::ITEM_FIELDS as $f) {
            if (!property_exists($rec, $f)) {
                continue;
            }
            if ($f === 'iscore') {
                $rec->iscore = empty($rec->iscore) ? 0 : 1;
            } else if ($f === 'explanation') {
                $rec->$f = trim(clean_param((string)$rec->$f, PARAM_TEXT));
            } else {
                $rec->$f = \core_text::substr(trim(clean_param((string)$rec->$f, PARAM_TEXT)), 0, 255);
            }
        }
        return $rec;
    }

    /**
     * Delete items and their user data.
     *
     * @param int[] $itemids
     */
    public static function delete_items(array $itemids): void {
        global $DB;
        if (!$itemids) {
            return;
        }
        foreach (array_chunk($itemids, 500) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $DB->delete_records_select('vocabmastery_itemstate', "itemid $in", $params);
            $DB->delete_records_select('vocabmastery_response', "itemid $in", $params);
            $DB->delete_records_select('vocabmastery_item', "id $in", $params);
        }
    }

    /**
     * Delete a set with items.
     *
     * @param int $setid
     */
    public static function delete_set(int $setid): void {
        global $DB;
        self::delete_items($DB->get_fieldset_select('vocabmastery_item', 'id', 'setid = :setid', ['setid' => $setid]));
        $DB->delete_records('vocabmastery_set', ['id' => $setid]);
    }

    /**
     * Delete a topic with sets.
     *
     * @param int $topicid
     */
    public static function delete_topic(int $topicid): void {
        global $DB;
        foreach ($DB->get_fieldset_select('vocabmastery_set', 'id', 'topicid = :topicid', ['topicid' => $topicid]) as $sid) {
            self::delete_set((int)$sid);
        }
        $DB->delete_records('vocabmastery_topicstate', ['topicid' => $topicid]);
        $DB->delete_records('vocabmastery_topic', ['id' => $topicid]);
    }

    /**
     * Delete a level with topics.
     *
     * @param int $levelid
     */
    public static function delete_level(int $levelid): void {
        global $DB;
        foreach ($DB->get_fieldset_select('vocabmastery_topic', 'id', 'levelid = :levelid', ['levelid' => $levelid]) as $tid) {
            self::delete_topic((int)$tid);
        }
        $DB->delete_records('vocabmastery_level', ['id' => $levelid]);
    }

    /**
     * Move a record up or down within its parent.
     *
     * @param string $table level|topic|set
     * @param int $id
     * @param int $direction -1 up, 1 down
     */
    public static function move(string $table, int $id, int $direction): void {
        global $DB;
        $parent = ['level' => 'vocabmasteryid', 'topic' => 'levelid', 'set' => 'topicid'][$table];
        $rec = $DB->get_record('vocabmastery_' . $table, ['id' => $id], '*', MUST_EXIST);
        $siblings = array_values($DB->get_records('vocabmastery_' . $table, [$parent => $rec->$parent], 'sortorder, id'));
        $ids = array_map(fn($s) => (int)$s->id, $siblings);
        $pos = array_search($id, $ids, true);
        $swap = $pos + $direction;
        if ($pos === false || $swap < 0 || $swap >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        foreach ($ids as $i => $sid) {
            $DB->set_field('vocabmastery_' . $table, 'sortorder', $i + 1, ['id' => $sid]);
        }
    }

    /**
     * Find or create a level by name.
     *
     * @param int $vmid
     * @param string $name
     * @return int
     */
    public static function level_by_name(int $vmid, string $name): int {
        global $DB;
        $name = trim($name) ?: get_string('defaultlevel', 'mod_vocabmastery');
        $id = $DB->get_field_select('vocabmastery_level', 'id', 'vocabmasteryid = :vid AND ' .
            $DB->sql_equal('name', ':name', false), ['vid' => $vmid, 'name' => $name], IGNORE_MULTIPLE);
        return $id ? (int)$id : self::add_level($vmid, $name);
    }

    /**
     * Find or create a topic by name within a level.
     *
     * @param int $vmid
     * @param int $levelid
     * @param string $name
     * @param int $pathkey
     * @return int
     */
    public static function topic_by_name(int $vmid, int $levelid, string $name, int $pathkey = 0): int {
        global $DB;
        $name = trim($name) ?: get_string('defaulttopic', 'mod_vocabmastery');
        $id = $DB->get_field_select('vocabmastery_topic', 'id', 'levelid = :levelid AND ' .
            $DB->sql_equal('name', ':name', false), ['levelid' => $levelid, 'name' => $name], IGNORE_MULTIPLE);
        return $id ? (int)$id : self::add_topic($vmid, $levelid, $name, $pathkey);
    }

    /**
     * Find or create a set by name within a topic.
     *
     * @param int $vmid
     * @param int $topicid
     * @param string $name
     * @param array $defaults used when creating
     * @return int
     */
    public static function set_by_name(int $vmid, int $topicid, string $name, array $defaults = []): int {
        global $DB;
        $name = trim($name) ?: get_string('defaultset', 'mod_vocabmastery');
        $id = $DB->get_field_select('vocabmastery_set', 'id', 'topicid = :topicid AND ' .
            $DB->sql_equal('name', ':name', false), ['topicid' => $topicid, 'name' => $name], IGNORE_MULTIPLE);
        return $id ? (int)$id : self::add_set($vmid, $topicid, ['name' => $name] + $defaults);
    }

    /**
     * Existing item in a set with the same prompt and answer (duplicate key).
     *
     * @param int $setid
     * @param string $prompt
     * @param string $correct
     * @return stdClass|null
     */
    public static function find_duplicate(int $setid, string $prompt, string $correct): ?stdClass {
        global $DB;
        $recs = $DB->get_records('vocabmastery_item', ['setid' => $setid]);
        foreach ($recs as $r) {
            if (
                engine::normalise($r->prompt) === engine::normalise($prompt)
                    && engine::normalise($r->correctvalue) === engine::normalise($correct)
            ) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Content counts.
     *
     * @param int $vmid
     * @return array
     */
    public static function counts(int $vmid): array {
        global $DB;
        return [
            'levels' => $DB->count_records('vocabmastery_level', ['vocabmasteryid' => $vmid]),
            'topics' => $DB->count_records('vocabmastery_topic', ['vocabmasteryid' => $vmid]),
            'sets' => $DB->count_records('vocabmastery_set', ['vocabmasteryid' => $vmid]),
            'items' => $DB->count_records('vocabmastery_item', ['vocabmasteryid' => $vmid]),
        ];
    }
}
