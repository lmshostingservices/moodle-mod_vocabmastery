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
 * A training session or mastery check was completed.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_completed extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['objecttable'] = 'vocabmastery_attempt';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsessioncompleted', 'mod_vocabmastery');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        $mode = s($this->other['mode'] ?? '');
        return "The user with id '$this->userid' completed a '$mode' session (attempt id '$this->objectid') " .
            "in the vocabulary activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/vocabmastery/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Object mapping for restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'vocabmastery_attempt', 'restore' => 'vocabmastery_attempt'];
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
