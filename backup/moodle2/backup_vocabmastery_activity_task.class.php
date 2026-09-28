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
 * Backup task.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/vocabmastery/backup/moodle2/backup_vocabmastery_stepslib.php');

/**
 * Backup task for mod_vocabmastery.
 */
class backup_vocabmastery_activity_task extends backup_activity_task {
    /**
     * No specific settings.
     */
    protected function define_my_settings() {
    }

    /**
     * Steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_vocabmastery_activity_structure_step('vocabmastery_structure', 'vocabmastery.xml'));
    }

    /**
     * Encode links.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            '/(' . $base . '\/mod\/vocabmastery\/index.php\?id\=)([0-9]+)/',
            '$@VOCABMASTERYINDEX*$2@$',
            $content
        );
        $content = preg_replace(
            '/(' . $base . '\/mod\/vocabmastery\/view.php\?id\=)([0-9]+)/',
            '$@VOCABMASTERYVIEWBYID*$2@$',
            $content
        );
        return $content;
    }
}
