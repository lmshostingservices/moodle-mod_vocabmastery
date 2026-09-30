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
 * Behat data generator for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_vocabmastery_generator extends behat_generator_base {
    /**
     * Entities Behat can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'words' => [
                'singular' => 'word',
                'datagenerator' => 'word',
                'required' => ['activity', 'topic', 'prompt', 'answer'],
            ],
        ];
    }

    /**
     * Resolve the activity idnumber to its instance id.
     *
     * @param array $data
     * @return array
     */
    protected function preprocess_word(array $data): array {
        global $DB;
        $cm = $DB->get_record('course_modules', ['idnumber' => $data['activity']], '*', MUST_EXIST);
        $data['vocabmasteryid'] = (int)$cm->instance;
        unset($data['activity']);
        return $data;
    }
}
