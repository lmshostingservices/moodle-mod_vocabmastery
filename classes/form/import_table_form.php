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
 * Paste a table or upload CSV / Excel.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_table_form extends \moodleform {
    /**
     * Definition.
     */
    protected function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'id');
        $m->setType('id', PARAM_INT);
        $m->addElement('hidden', 'source', 'table');
        $m->setType('source', PARAM_ALPHA);
        $m->addElement('static', 'tableintro', '', get_string('importtable_help', 'mod_vocabmastery'));
        $m->addElement(
            'textarea',
            'pasted',
            get_string('pastetable', 'mod_vocabmastery'),
            ['rows' => 10, 'cols' => 80, 'class' => 'text-monospace', 'placeholder' => "der\tVater\tfather\ndie\tMutter\tmother"]
        );
        $m->setType('pasted', PARAM_TEXT);
        $m->addElement(
            'filepicker',
            'tablefile',
            get_string('orupload', 'mod_vocabmastery'),
            null,
            ['accepted_types' => ['.csv', '.txt', '.tsv', '.xlsx', '.xls', '.ods']]
        );
        $this->add_action_buttons(true, get_string('analyse', 'mod_vocabmastery'));
    }
}
