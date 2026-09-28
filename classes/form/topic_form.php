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
 * Add/edit a topic.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class topic_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $m = $this->_form;
        $levels = $this->_customdata['levels'];
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'topicid');
        $m->setType('topicid', PARAM_INT);
        $m->addElement('hidden', 'action', 'edittopic');
        $m->setType('action', PARAM_ALPHA);
        $m->addElement('select', 'levelid', get_string('level', 'mod_vocabmastery'), $levels);
        $m->addElement('text', 'name', get_string('topicname', 'mod_vocabmastery'), ['size' => 50]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required', null, 'client');
        $m->addElement('text', 'pathkey', get_string('pathkey', 'mod_vocabmastery'), ['size' => 4]);
        $m->setType('pathkey', PARAM_INT);
        $m->addHelpButton('pathkey', 'pathkey', 'mod_vocabmastery');
        $m->addElement(
            'textarea',
            'description',
            get_string('topicdescription', 'mod_vocabmastery'),
            ['rows' => 2, 'cols' => 60]
        );
        $m->setType('description', PARAM_TEXT);
        $m->addElement(
            'textarea',
            'teachernotes',
            get_string('teachernotes', 'mod_vocabmastery'),
            ['rows' => 4, 'cols' => 60]
        );
        $m->setType('teachernotes', PARAM_TEXT);
        $this->add_action_buttons();
    }
}
