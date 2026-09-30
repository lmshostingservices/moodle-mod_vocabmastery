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

declare(strict_types=1);

namespace mod_vocabmastery\completion;

use core_completion\activity_custom_completion;
use mod_vocabmastery\instance;
use mod_vocabmastery\progress;

/**
 * Custom completion rules.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * State of one rule.
     *
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $vm = $DB->get_record('vocabmastery', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $progress = new progress(new instance($vm, $this->cm), $this->userid);
        $summaries = $progress->countable_summaries();
        switch ($rule) {
            case 'completiontopics':
                $ok = $progress->topics_passed() >= (int)$vm->completiontopics;
                break;
            case 'completionalltopics':
                $ok = $summaries && $progress->topics_passed() >= count($summaries);
                break;
            case 'completionprogress':
                $ok = $progress->overall_percent() >= (float)$vm->completionprogress;
                break;
            default:
                $ok = false;
        }
        return $ok ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completiontopics', 'completionalltopics', 'completionprogress'];
    }

    /**
     * Descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $rules = $this->cm->customdata['customcompletionrules'] ?? [];
        return [
            'completiontopics' => get_string('completiondetail:topics', 'mod_vocabmastery', $rules['completiontopics'] ?? 0),
            'completionalltopics' => get_string('completiondetail:alltopics', 'mod_vocabmastery'),
            'completionprogress' => get_string('completiondetail:progress', 'mod_vocabmastery', $rules['completionprogress'] ?? 0),
        ];
    }

    /**
     * Order.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completiontopics', 'completionalltopics', 'completionprogress', 'completionusegrade',
            'completionpassgrade'];
    }
}
