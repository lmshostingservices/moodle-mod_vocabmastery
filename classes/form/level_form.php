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

namespace mod_vocabmastery\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add/edit a level.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class level_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'levelid');
        $m->setType('levelid', PARAM_INT);
        $m->addElement('hidden', 'action', 'editlevel');
        $m->setType('action', PARAM_ALPHA);
        $m->addElement('text', 'name', get_string('levelname', 'mod_vocabmastery'), ['size' => 40]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required', null, 'client');
        $m->addHelpButton('name', 'levelname', 'mod_vocabmastery');
        $m->addElement('textarea', 'description', get_string('description'), ['rows' => 2, 'cols' => 60]);
        $m->setType('description', PARAM_TEXT);
        $this->add_action_buttons();
    }
}
