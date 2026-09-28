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
 * External functions for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_vocabmastery_start_session' => [
        'classname' => 'mod_vocabmastery\external\start_session',
        'description' => 'Start a training session or mastery check and return its questions.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocabmastery:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_vocabmastery_record_response' => [
        'classname' => 'mod_vocabmastery\external\record_response',
        'description' => 'Record one answer within a session.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocabmastery:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_vocabmastery_finish_session' => [
        'classname' => 'mod_vocabmastery\external\finish_session',
        'description' => 'Finish a session and return the summary, mastery and next routes.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vocabmastery:view',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
