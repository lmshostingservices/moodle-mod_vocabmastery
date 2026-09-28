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

namespace mod_vocabmastery\event;

/**
 * Vocabulary was imported.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_imported extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['objecttable'] = 'vocabmastery';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcontentimported', 'mod_vocabmastery');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $count = (int)($this->other['items'] ?? 0);
        return "The user with id '$this->userid' imported $count items into the vocabulary activity " .
            "with course module id '$this->contextinstanceid'.";
    }

    /**
     * URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/vocabmastery/manage.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Object mapping for restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'vocabmastery', 'restore' => 'vocabmastery'];
    }

    /**
     * Other mapping.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
