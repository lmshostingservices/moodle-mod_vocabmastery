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

/**
 * Restore structure.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step.
 */
class restore_vocabmastery_activity_structure_step extends restore_activity_structure_step {
    /**
     * Paths.
     *
     * @return array
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [
            new restore_path_element('vocabmastery', '/activity/vocabmastery'),
            new restore_path_element('vocabmastery_level', '/activity/vocabmastery/levels/level'),
            new restore_path_element('vocabmastery_topic', '/activity/vocabmastery/levels/level/topics/topic'),
            new restore_path_element('vocabmastery_set', '/activity/vocabmastery/levels/level/topics/topic/sets/set'),
            new restore_path_element(
                'vocabmastery_item',
                '/activity/vocabmastery/levels/level/topics/topic/sets/set/items/item'
            ),
        ];
        if ($userinfo) {
            $paths[] = new restore_path_element('vocabmastery_topicstate', '/activity/vocabmastery/topicstates/topicstate');
            $paths[] = new restore_path_element('vocabmastery_itemstate', '/activity/vocabmastery/itemstates/itemstate');
            $paths[] = new restore_path_element('vocabmastery_attempt', '/activity/vocabmastery/attempts/attempt');
            $paths[] = new restore_path_element(
                'vocabmastery_response',
                '/activity/vocabmastery/attempts/attempt/responses/response'
            );
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Instance.
     *
     * @param array $data
     */
    protected function process_vocabmastery($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        if ($data->grade < 0) {
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }
        $newid = $DB->insert_record('vocabmastery', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Level.
     *
     * @param array $data
     */
    protected function process_vocabmastery_level($data) {
        global $DB;
        $data = (object)$data;
        $old = $data->id;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $this->set_mapping('vocabmastery_level', $old, $DB->insert_record('vocabmastery_level', $data));
    }

    /**
     * Topic.
     *
     * @param array $data
     */
    protected function process_vocabmastery_topic($data) {
        global $DB;
        $data = (object)$data;
        $old = $data->id;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->levelid = $this->get_new_parentid('vocabmastery_level');
        $this->set_mapping('vocabmastery_topic', $old, $DB->insert_record('vocabmastery_topic', $data));
    }

    /**
     * Set.
     *
     * @param array $data
     */
    protected function process_vocabmastery_set($data) {
        global $DB;
        $data = (object)$data;
        $old = $data->id;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->topicid = $this->get_new_parentid('vocabmastery_topic');
        $this->set_mapping('vocabmastery_set', $old, $DB->insert_record('vocabmastery_set', $data));
    }

    /**
     * Item.
     *
     * @param array $data
     */
    protected function process_vocabmastery_item($data) {
        global $DB;
        $data = (object)$data;
        $old = $data->id;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->setid = $this->get_new_parentid('vocabmastery_set');
        $this->set_mapping('vocabmastery_item', $old, $DB->insert_record('vocabmastery_item', $data));
    }

    /**
     * Topic state.
     *
     * @param array $data
     */
    protected function process_vocabmastery_topicstate($data) {
        global $DB;
        $data = (object)$data;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->topicid = $this->get_mappingid('vocabmastery_topic', $data->topicid);
        if ($data->userid && $data->topicid) {
            $DB->insert_record('vocabmastery_topicstate', $data);
        }
    }

    /**
     * Item state.
     *
     * @param array $data
     */
    protected function process_vocabmastery_itemstate($data) {
        global $DB;
        $data = (object)$data;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->itemid = $this->get_mappingid('vocabmastery_item', $data->itemid);
        if ($data->userid && $data->itemid) {
            $DB->insert_record('vocabmastery_itemstate', $data);
        }
    }

    /**
     * Attempt.
     *
     * @param array $data
     */
    protected function process_vocabmastery_attempt($data) {
        global $DB;
        $data = (object)$data;
        $old = $data->id;
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->topicid = $data->topicid ? (int)$this->get_mappingid('vocabmastery_topic', $data->topicid) : 0;
        $data->setid = $data->setid ? (int)$this->get_mappingid('vocabmastery_set', $data->setid) : 0;
        $ids = [];
        foreach (array_filter(explode(',', (string)$data->itemids)) as $iid) {
            if ($new = $this->get_mappingid('vocabmastery_item', (int)$iid)) {
                $ids[] = $new;
            }
        }
        $data->itemids = implode(',', $ids);
        if (!$data->userid) {
            return;
        }
        $this->set_mapping('vocabmastery_attempt', $old, $DB->insert_record('vocabmastery_attempt', $data));
    }

    /**
     * Response.
     *
     * @param array $data
     */
    protected function process_vocabmastery_response($data) {
        global $DB;
        $data = (object)$data;
        $data->attemptid = $this->get_new_parentid('vocabmastery_attempt');
        $data->vocabmasteryid = $this->get_new_parentid('vocabmastery');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->itemid = $this->get_mappingid('vocabmastery_item', $data->itemid);
        if ($data->attemptid && $data->userid && $data->itemid) {
            $DB->insert_record('vocabmastery_response', $data);
        }
    }

    /**
     * Files.
     */
    protected function after_execute() {
        $this->add_related_files('mod_vocabmastery', 'intro', null);
    }
}
