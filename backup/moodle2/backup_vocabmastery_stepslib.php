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
 * Backup structure.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step.
 */
class backup_vocabmastery_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $vm = new backup_nested_element(
            'vocabmastery',
            ['id'],
            [
                'name', 'intro', 'introformat', 'targetlanguage', 'nativelanguage', 'masterythreshold', 'itemcorrectrequired',
                'itemstreakrequired', 'progressionmode', 'unlockby', 'openprevious', 'testreview', 'testlength',
                'enablelearn', 'requirelearn', 'enableblitz',
                'blitzduration', 'blitzcountsmastery', 'enablestreak', 'enablehints', 'injectdifficult', 'colourscheme', 'grade',
                'grademethod', 'completiontopics', 'completionalltopics', 'completionprogress', 'timecreated', 'timemodified',
            ]
        );
        $levels = new backup_nested_element('levels');
        $level = new backup_nested_element('level', ['id'], ['name', 'description', 'sortorder']);
        $topics = new backup_nested_element('topics');
        $topic = new backup_nested_element(
            'topic',
            ['id'],
            [
                'name', 'description', 'teachernotes', 'pathkey', 'sortorder',
                'timemodified',
            ]
        );
        $sets = new backup_nested_element('sets');
        $set = new backup_nested_element(
            'set',
            ['id'],
            [
                'name', 'instruction', 'itemtype', 'answermode', 'options',
                'numoptions', 'showtranslation', 'gated', 'sortorder', 'timemodified',
            ]
        );
        $items = new backup_nested_element('items');
        $item = new backup_nested_element(
            'item',
            ['id'],
            [
                'prompt', 'correctvalue', 'altanswers', 'translation', 'plural',
                'hint', 'explanation', 'iscore', 'sortorder', 'metadata', 'timecreated', 'timemodified',
            ]
        );

        $topicstates = new backup_nested_element('topicstates');
        $topicstate = new backup_nested_element(
            'topicstate',
            ['id'],
            [
                'userid', 'topicid', 'mastery', 'besttest', 'passed',
                'learned', 'timepassed', 'timemodified',
            ]
        );
        $itemstates = new backup_nested_element('itemstates');
        $itemstate = new backup_nested_element(
            'itemstate',
            ['id'],
            [
                'userid', 'itemid', 'attempts', 'correct', 'wrong',
                'streak', 'mastered', 'difficult', 'wasdifficult', 'lastseen',
            ]
        );
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element(
            'attempt',
            ['id'],
            [
                'userid', 'topicid', 'setid', 'mode', 'itemids', 'total',
                'correct', 'beststreak', 'score', 'passed', 'finished', 'timestart', 'timefinish',
            ]
        );
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element(
            'response',
            ['id'],
            [
                'userid', 'itemid', 'mode', 'response', 'correct',
                'hinted', 'responsetime', 'timecreated',
            ]
        );
        $flags = new backup_nested_element('flags');
        $flag = new backup_nested_element(
            'flag',
            ['id'],
            [
                'itemid', 'userid', 'reason', 'commenttext', 'resolved',
                'timecreated', 'timemodified',
            ]
        );

        $vm->add_child($levels);
        $levels->add_child($level);
        $level->add_child($topics);
        $topics->add_child($topic);
        $topic->add_child($sets);
        $sets->add_child($set);
        $set->add_child($items);
        $items->add_child($item);
        $vm->add_child($topicstates);
        $topicstates->add_child($topicstate);
        $vm->add_child($itemstates);
        $itemstates->add_child($itemstate);
        $vm->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($responses);
        $responses->add_child($response);
        $vm->add_child($flags);
        $flags->add_child($flag);

        $vm->set_source_table('vocabmastery', ['id' => backup::VAR_ACTIVITYID]);
        $level->set_source_table('vocabmastery_level', ['vocabmasteryid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $topic->set_source_table('vocabmastery_topic', ['levelid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $set->set_source_table('vocabmastery_set', ['topicid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $item->set_source_table('vocabmastery_item', ['setid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        if ($userinfo) {
            $topicstate->set_source_table('vocabmastery_topicstate', ['vocabmasteryid' => backup::VAR_PARENTID]);
            $itemstate->set_source_table('vocabmastery_itemstate', ['vocabmasteryid' => backup::VAR_PARENTID]);
            $attempt->set_source_table('vocabmastery_attempt', ['vocabmasteryid' => backup::VAR_PARENTID], 'id ASC');
            $response->set_source_table('vocabmastery_response', ['attemptid' => backup::VAR_PARENTID], 'id ASC');
            $flag->set_source_table('vocabmastery_flag', ['vocabmasteryid' => backup::VAR_PARENTID], 'id ASC');
        }
        $topicstate->annotate_ids('user', 'userid');
        $itemstate->annotate_ids('user', 'userid');
        $attempt->annotate_ids('user', 'userid');
        $response->annotate_ids('user', 'userid');
        $flag->annotate_ids('user', 'userid');
        $vm->annotate_files('mod_vocabmastery', 'intro', null);

        return $this->prepare_activity_structure($vm);
    }
}
