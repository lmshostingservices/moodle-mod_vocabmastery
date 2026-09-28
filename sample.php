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
 * Downloads for the import page: sample Excel/CSV and the ChatGPT prompt.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

$instance = \mod_vocabmastery\instance::from_cmid($id);
require_login($instance->course, false, $instance->cm);
require_capability('mod/vocabmastery:managecontent', $instance->context);

switch ($type) {
    case 'xlsx':
        \mod_vocabmastery\import\sample::send_xlsx();
        break;
    case 'csv':
        \mod_vocabmastery\import\sample::send_csv();
        break;
    case 'prompt':
        \mod_vocabmastery\import\sample::send_prompt();
        break;
    default:
        throw new moodle_exception('invalidparameter', 'debug');
}
die;
