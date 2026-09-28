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
 * Site settings for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $status = \mod_vocabmastery\local\credentials::status();
    $statuscontext = [
        'central' => $status['source'] === \mod_vocabmastery\local\credentials::SOURCE_CENTRAL,
        'local' => $status['source'] === \mod_vocabmastery\local\credentials::SOURCE_LOCAL,
        'none' => $status['source'] === \mod_vocabmastery\local\credentials::SOURCE_NONE,
        'siteid' => $status['siteid'],
        'centralinstalled' => $status['centralinstalled'],
        'centralurl' => $status['centralinstalled']
            ? (new moodle_url('/admin/settings.php', ['section' => 'local_aiconfig']))->out(false) : null,
    ];
    $settings->add(new admin_setting_heading(
        'mod_vocabmastery/lmslabsheading',
        get_string('lmslabs_heading', 'mod_vocabmastery'),
        $OUTPUT->render_from_template('mod_vocabmastery/credentials_status', $statuscontext)
    ));
    $settings->add(new admin_setting_configtext(
        'mod_vocabmastery/siteid',
        get_string('lmslabs_siteid', 'mod_vocabmastery'),
        get_string('lmslabs_local_desc', 'mod_vocabmastery'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_vocabmastery/apikey',
        get_string('lmslabs_apikey', 'mod_vocabmastery'),
        get_string('lmslabs_local_desc', 'mod_vocabmastery'),
        ''
    ));
}
