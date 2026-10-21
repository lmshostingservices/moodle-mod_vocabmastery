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
 * Start a training session.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_session extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'Course module id'),
                'mode' => new external_value(PARAM_ALPHA, 'learn, practice, blitz, streak, difficult or test'),
                'topicid' => new external_value(PARAM_INT, 'Topic id, 0 for activity wide', VALUE_DEFAULT, 0),
                'setid' => new external_value(PARAM_INT, 'Set id, 0 for whole topic', VALUE_DEFAULT, 0),
                'itemids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Item id'),
                    'Explicit items',
                    VALUE_DEFAULT,
                    []
                ),
            ]
        );
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param string $mode
     * @param int $topicid
     * @param int $setid
     * @param array $itemids
     * @return array
     */
    public static function execute(int $cmid, string $mode, int $topicid = 0, int $setid = 0, array $itemids = []): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cmid', 'mode', 'topicid', 'setid', 'itemids'));
        $instance = instance::from_cmid($params['cmid']);
        self::validate_context($instance->context);
        require_capability('mod/vocabmastery:view', $instance->context);
        $instance->require_attempt();
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception('Guests cannot train');
        }
        $engine = new engine($instance, (int)$USER->id);
        $result = $engine->start(
            $params['mode'],
            $params['topicid'],
            $params['setid'],
            array_map('intval', $params['itemids'])
        );
        return [
            'attemptid' => (int)$result['attempt']->id,
            'mode' => $params['mode'],
            'questions' => $result['questions'],
            'inject' => $result['inject'],
        ];
    }

    /**
     * Question structure.
     *
     * @return external_single_structure
     */
    public static function question_structure(): external_single_structure {
        return new external_single_structure(
            [
                'itemid' => new external_value(PARAM_INT, 'Item id'),
                'setid' => new external_value(PARAM_INT, 'Set id'),
                'topicid' => new external_value(PARAM_INT, 'Topic id'),
                'topicname' => new external_value(PARAM_TEXT, 'Topic name'),
                'setname' => new external_value(PARAM_TEXT, 'Set name'),
                'instruction' => new external_value(PARAM_TEXT, 'Instruction'),
                'itemtype' => new external_value(PARAM_ALPHA, 'choice or typed'),
                'fixedorder' => new external_value(PARAM_INT, 'Options are a fixed list'),
                'prompt' => new external_value(PARAM_TEXT, 'Prompt'),
                'translation' => new external_value(PARAM_TEXT, 'Translation'),
                'plural' => new external_value(PARAM_TEXT, 'Plural'),
                'hint' => new external_value(PARAM_TEXT, 'Hint'),
                'explanation' => new external_value(PARAM_TEXT, 'Explanation'),
                'note' => new external_value(PARAM_TEXT, 'Note'),
                'iscore' => new external_value(PARAM_INT, 'Core item'),
                'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option')),
                'correct' => new external_value(PARAM_TEXT, 'Correct value (empty in mastery checks)'),
                'alternatives' => new external_value(PARAM_TEXT, 'Alternatives'),
                'state' => new external_value(PARAM_ALPHA, 'Learning state'),
            ]
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
                'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
                'mode' => new external_value(PARAM_ALPHA, 'Mode'),
                'questions' => new external_multiple_structure(self::question_structure()),
                'inject' => new external_multiple_structure(self::question_structure()),
            ]
        );
    }
}
