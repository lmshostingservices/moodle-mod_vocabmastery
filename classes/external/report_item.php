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

namespace mod_vocabmastery\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_vocabmastery\instance;

/**
 * A learner reports a problem with a word (wrong answer, typo, translation, other).
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_item extends external_api {
    /** Allowed reasons. */
    public const REASONS = ['wronganswer', 'typo', 'translation', 'other'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'reason' => new external_value(PARAM_ALPHA, 'wronganswer, typo, translation or other'),
            'comment' => new external_value(PARAM_TEXT, 'Optional comment', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $itemid
     * @param string $reason
     * @param string $comment
     * @return array
     */
    public static function execute(int $cmid, int $itemid, string $reason, string $comment = ''): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'itemid', 'reason', 'comment'));
        $instance = instance::from_cmid($params['cmid']);
        self::validate_context($instance->context);
        require_capability('mod/vocabmastery:view', $instance->context);
        $instance->require_attempt();
        if (!in_array($params['reason'], self::REASONS, true)) {
            throw new \invalid_parameter_exception('reason');
        }
        $item = $DB->get_record(
            'vocabmastery_item',
            ['id' => $params['itemid'], 'vocabmasteryid' => $instance->vm->id],
            '*',
            MUST_EXIST
        );
        $comment = \core_text::substr(trim($params['comment']), 0, 500);
        $now = time();
        // One open report per learner and word: a second report updates the first.
        $existing = $DB->get_record('vocabmastery_flag', ['itemid' => $item->id, 'userid' => $USER->id, 'resolved' => 0]);
        if ($existing) {
            $DB->update_record('vocabmastery_flag', (object)['id' => $existing->id, 'reason' => $params['reason'],
                'commenttext' => $comment, 'timemodified' => $now]);
        } else {
            $DB->insert_record('vocabmastery_flag', (object)['vocabmasteryid' => $instance->vm->id, 'itemid' => $item->id,
                'userid' => $USER->id, 'reason' => $params['reason'], 'commenttext' => $comment, 'resolved' => 0,
                'timecreated' => $now, 'timemodified' => $now]);
        }
        return ['saved' => true];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Report saved'),
        ]);
    }
}
