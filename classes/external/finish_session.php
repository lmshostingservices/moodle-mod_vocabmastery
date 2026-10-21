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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_vocabmastery\engine;
use mod_vocabmastery\instance;

/**
 * Finish a session.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finish_session extends external_api {
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
                'beststreak' => new external_value(PARAM_INT, 'Best streak seen by client', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $attemptid
     * @param int $beststreak
     * @return array
     */
    public static function execute(int $cmid, int $attemptid, int $beststreak = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'attemptid', 'beststreak'));
        $instance = instance::from_cmid($params['cmid']);
        self::validate_context($instance->context);
        require_capability('mod/vocabmastery:view', $instance->context);
        $instance->require_attempt();
        $engine = new engine($instance, (int)$USER->id);
        return $engine->finish($params['attemptid'], $params['beststreak']);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'mode' => new external_value(PARAM_ALPHA, 'Mode'),
                'total' => new external_value(PARAM_INT, 'Answers given'),
                'questioncount' => new external_value(PARAM_INT, 'Questions in session'),
                'correct' => new external_value(PARAM_INT, 'Correct answers'),
                'score' => new external_value(PARAM_FLOAT, 'Percent'),
                'beststreak' => new external_value(PARAM_INT, 'Best streak'),
                'personalbest' => new external_value(PARAM_BOOL, 'New personal best'),
                'previousbest' => new external_value(PARAM_INT, 'Previous best'),
                'passed' => new external_value(PARAM_BOOL, 'Mastery check passed'),
                'topicpassed' => new external_value(PARAM_BOOL, 'Topic is passed'),
                'newlypassed' => new external_value(PARAM_BOOL, 'Topic passed just now'),
                'mastery' => new external_value(PARAM_FLOAT, 'Topic mastery percent'),
                'threshold' => new external_value(PARAM_INT, 'Threshold'),
                'mistakes' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'itemid' => new external_value(PARAM_INT, 'Item'),
                            'prompt' => new external_value(PARAM_TEXT, 'Prompt'),
                            'correct' => new external_value(PARAM_TEXT, 'Correct'),
                            'translation' => new external_value(PARAM_TEXT, 'Translation'),
                        ]
                    )
                ),
                'routes' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'type' => new external_value(PARAM_ALPHA, 'horizontal, vertical or next'),
                            'topicid' => new external_value(PARAM_INT, 'Topic'),
                            'name' => new external_value(PARAM_TEXT, 'Topic name'),
                            'level' => new external_value(PARAM_TEXT, 'Level name'),
                            'url' => new external_value(PARAM_URL, 'Link'),
                        ]
                    )
                ),
                'seconds' => new external_value(PARAM_INT, 'Session length in seconds'),
                'previousscore' => new external_value(PARAM_FLOAT, 'Score of the previous session of this kind, -1 if none'),
                'answers' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'itemid' => new external_value(PARAM_INT, 'Item'),
                            'prompt' => new external_value(PARAM_TEXT, 'Prompt'),
                            'correct' => new external_value(PARAM_TEXT, 'Correct answer'),
                            'given' => new external_value(PARAM_TEXT, 'First answer given, empty if unanswered'),
                            'iscorrect' => new external_value(PARAM_BOOL, 'First answer was right'),
                            'translation' => new external_value(PARAM_TEXT, 'Translation'),
                            'setname' => new external_value(PARAM_TEXT, 'Set'),
                            'prefix' => new external_value(PARAM_BOOL, 'Show answer before the word (article style)'),
                        ]
                    )
                ),
                'sets' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'name' => new external_value(PARAM_TEXT, 'Set'),
                            'correct' => new external_value(PARAM_INT, 'Right first time'),
                            'total' => new external_value(PARAM_INT, 'Words'),
                        ]
                    )
                ),
                'answershidden' => new external_value(PARAM_BOOL, 'Answers are hidden after this mastery check'),
            ]
        );
    }
}
