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
use mod_vocabmastery\engine;
use mod_vocabmastery\instance;

/**
 * Record one answer.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_response extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
                'itemid' => new external_value(PARAM_INT, 'Item id'),
                'response' => new external_value(PARAM_TEXT, 'Answer given'),
                'responsetime' => new external_value(PARAM_INT, 'Milliseconds taken', VALUE_DEFAULT, 0),
                'hinted' => new external_value(PARAM_BOOL, 'Hint was used', VALUE_DEFAULT, false),
            ]
        );
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $attemptid
     * @param int $itemid
     * @param string $response
     * @param int $responsetime
     * @param bool $hinted
     * @return array
     */
    public static function execute(
        int $cmid,
        int $attemptid,
        int $itemid,
        string $response,
        int $responsetime = 0,
        bool $hinted = false
    ): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('cmid', 'attemptid', 'itemid', 'response', 'responsetime', 'hinted')
        );
        $instance = instance::from_cmid($params['cmid']);
        self::validate_context($instance->context);
        require_capability('mod/vocabmastery:view', $instance->context);
        $instance->require_attempt();
        $engine = new engine($instance, (int)$USER->id);
        return $engine->record(
            $params['attemptid'],
            $params['itemid'],
            $params['response'],
            $params['responsetime'],
            $params['hinted']
        );
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'correct' => new external_value(PARAM_BOOL, 'Was correct (always false in mastery checks)'),
                'recorded' => new external_value(PARAM_BOOL, 'Stored'),
                'state' => new external_value(PARAM_ALPHA, 'New learning state'),
                'mastery' => new external_value(PARAM_FLOAT, 'Unused, -1'),
            ]
        );
    }
}
