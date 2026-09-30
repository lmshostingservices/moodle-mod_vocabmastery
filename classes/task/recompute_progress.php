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

namespace mod_vocabmastery\task;

use mod_vocabmastery\instance;
use mod_vocabmastery\progress;

/**
 * Recalculate stored progress, grades and completion after a teacher changed the words.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recompute_progress extends \core\task\adhoc_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_recompute', 'mod_vocabmastery');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $vm = $DB->get_record('vocabmastery', ['id' => (int)($data->vocabmasteryid ?? 0)]);
        if (!$vm) {
            return; // Activity deleted meanwhile.
        }
        $instance = instance::from_record($vm);
        $userids = $DB->get_fieldset_sql(
            "SELECT userid FROM {vocabmastery_topicstate} WHERE vocabmasteryid = :v1
              UNION
             SELECT userid FROM {vocabmastery_itemstate} WHERE vocabmasteryid = :v2",
            ['v1' => $vm->id, 'v2' => $vm->id]
        );
        foreach ($userids as $userid) {
            (new progress($instance, (int)$userid))->recompute_all();
        }
    }
}
