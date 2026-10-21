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
 * Add/edit a set.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $m = $this->_form;
        $c = 'mod_vocabmastery';
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'setid');
        $m->setType('setid', PARAM_INT);
        $m->addElement('hidden', 'action', 'editset');
        $m->setType('action', PARAM_ALPHA);
        $m->addElement('select', 'topicid', get_string('topic', $c), $this->_customdata['topics']);
        $m->addElement('text', 'name', get_string('setname', $c), ['size' => 50]);
        $m->addHelpButton('name', 'setname', $c);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required', null, 'client');
        $m->addElement('text', 'instruction', get_string('instruction', $c), ['size' => 50]);
        $m->setType('instruction', PARAM_TEXT);
        $m->addHelpButton('instruction', 'instruction', $c);
        $m->addElement(
            'select',
            'itemtype',
            get_string('itemtype', $c),
            [
                'choice' => get_string('itemtype_choice', $c),
                'typed' => get_string('itemtype_typed', $c),
            ]
        );
        $m->addHelpButton('itemtype', 'itemtype', $c);
        $m->addElement(
            'select',
            'answermode',
            get_string('answermode', $c),
            [
                'fixed' => get_string('answermode_fixed', $c),
                'pool' => get_string('answermode_pool', $c),
            ]
        );
        $m->addHelpButton('answermode', 'answermode', $c);
        $m->hideIf('answermode', 'itemtype', 'eq', 'typed');
        $m->addElement('textarea', 'options', get_string('fixedoptions', $c), ['rows' => 4, 'cols' => 30]);
        $m->setType('options', PARAM_TEXT);
        $m->addHelpButton('options', 'fixedoptions', $c);
        $m->hideIf('options', 'answermode', 'neq', 'fixed');
        $m->hideIf('options', 'itemtype', 'eq', 'typed');
        $m->addElement('select', 'numoptions', get_string('numoptions', $c), array_combine(range(2, 6), range(2, 6)));
        $m->setDefault('numoptions', 4);
        $m->addHelpButton('numoptions', 'numoptions', $c);
        $m->hideIf('numoptions', 'answermode', 'neq', 'pool');
        $m->hideIf('numoptions', 'itemtype', 'eq', 'typed');
        $m->addElement('advcheckbox', 'showtranslation', get_string('showtranslation', $c));
        $m->addHelpButton('showtranslation', 'showtranslation', $c);
        $m->setDefault('showtranslation', 1);
        $m->addElement('advcheckbox', 'gated', get_string('gated', $c));
        $m->setDefault('gated', 1);
        $m->addHelpButton('gated', 'gated', $c);
        $this->add_action_buttons();
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['itemtype'] === 'choice' && $data['answermode'] === 'fixed') {
            $opts = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$data['options'])));
            if (count($opts) < 2) {
                $errors['options'] = get_string('err_twooptions', 'mod_vocabmastery');
            }
        }
        return $errors;
    }
}
