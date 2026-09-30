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

use mod_vocabmastery\engine;
use mod_vocabmastery\instance;

/**
 * Finish sessions a learner left open (tab closed) so their progress and grade are saved.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_stale_sessions extends \core\task\scheduled_task {
    /** Sessions untouched for this long are closed. */
    public const STALE_AFTER = DAYSECS;

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_closestale', 'mod_vocabmastery');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;
        $cutoff = time() - self::STALE_AFTER;
        $sql = "SELECT a.id, a.userid, a.vocabmasteryid, a.mode
                  FROM {vocabmastery_attempt} a
                 WHERE a.finished = 0 AND a.timestart < :cutoff
                   AND NOT EXISTS (SELECT 1 FROM {vocabmastery_response} r
                                    WHERE r.attemptid = a.id AND r.timecreated >= :cutoff2)
              ORDER BY a.vocabmasteryid, a.id";
        $attempts = $DB->get_records_sql($sql, ['cutoff' => $cutoff, 'cutoff2' => $cutoff], 0, 2000);
        $instances = [];
        $closed = 0;
        foreach ($attempts as $a) {
            $vmid = (int)$a->vocabmasteryid;
            if (!array_key_exists($vmid, $instances)) {
                $vm = $DB->get_record('vocabmastery', ['id' => $vmid]);
                $instances[$vmid] = $vm ? instance::from_record($vm) : null;
            }
            try {
                if (!$instances[$vmid] || $a->mode === 'learn') {
                    // Learn is only complete when the learner finishes it.
                    throw new \moodle_exception('invalidattempt', 'mod_vocabmastery');
                }
                (new engine($instances[$vmid], (int)$a->userid))->finish((int)$a->id);
                $closed++;
            } catch (\Throwable $e) {
                // Learn session, or content gone: close it without scoring.
                $DB->update_record('vocabmastery_attempt', (object)['id' => $a->id, 'finished' => 1,
                    'timefinish' => time()]);
                mtrace('mod_vocabmastery: closed attempt ' . $a->id . ' without scoring: ' . $e->getMessage());
            }
        }
        mtrace("mod_vocabmastery: finished $closed stale sessions.");
    }
}
