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

namespace mod_vocabmastery;

use context_module;
use stdClass;

/**
 * Wraps one activity instance and gives cached access to its content structure.
 *
 * Nothing in here knows about any particular language: levels, topics, sets and
 * answer options are all content.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance {
    /** @var stdClass activity record */
    public stdClass $vm;
    /** @var \cm_info|stdClass|null course module */
    public $cm = null;
    /** @var stdClass|null course */
    public ?stdClass $course = null;
    /** @var context_module|null */
    public ?context_module $context = null;

    /** @var array|null cached levels */
    protected ?array $levels = null;
    /** @var array|null cached topics */
    protected ?array $topics = null;
    /** @var array|null cached sets */
    protected ?array $sets = null;

    /**
     * Constructor.
     *
     * @param stdClass $vm
     * @param mixed $cm
     * @param stdClass|null $course
     */
    public function __construct(stdClass $vm, $cm = null, ?stdClass $course = null) {
        $this->vm = $vm;
        if ($cm === null) {
            $cm = get_coursemodule_from_instance('vocabmastery', $vm->id, $vm->course, false, MUST_EXIST);
        }
        $this->cm = $cm;
        $this->course = $course;
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Load from a course module id.
     *
     * @param int $cmid
     * @return self
     */
    public static function from_cmid(int $cmid): self {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'vocabmastery');
        $vm = $DB->get_record('vocabmastery', ['id' => $cm->instance], '*', MUST_EXIST);
        return new self($vm, $cm, $course);
    }

    /**
     * Load from an instance record.
     *
     * @param stdClass $vm
     * @return self
     */
    public static function from_record(stdClass $vm): self {
        global $DB;
        if (!isset($vm->masterythreshold)) {
            $vm = $DB->get_record('vocabmastery', ['id' => $vm->id], '*', MUST_EXIST);
        }
        return new self($vm);
    }

    /**
     * Load from an instance id.
     *
     * @param int $id
     * @return self
     */
    public static function from_id(int $id): self {
        global $DB;
        return new self($DB->get_record('vocabmastery', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Levels ordered.
     *
     * @return stdClass[]
     */
    public function get_levels(): array {
        global $DB;
        if ($this->levels === null) {
            $this->levels = $DB->get_records('vocabmastery_level', ['vocabmasteryid' => $this->vm->id], 'sortorder, id');
        }
        return $this->levels;
    }

    /**
     * All topics ordered by level then topic order.
     *
     * @return stdClass[]
     */
    public function get_topics(): array {
        global $DB;
        if ($this->topics === null) {
            $sql = "SELECT t.*
                      FROM {vocabmastery_topic} t
                      JOIN {vocabmastery_level} l ON l.id = t.levelid
                     WHERE t.vocabmasteryid = :vid
                  ORDER BY l.sortorder, l.id, t.sortorder, t.id";
            $this->topics = $DB->get_records_sql($sql, ['vid' => $this->vm->id]);
        }
        return $this->topics;
    }

    /**
     * Topics of one level.
     *
     * @param int $levelid
     * @return stdClass[]
     */
    public function get_level_topics(int $levelid): array {
        return array_filter($this->get_topics(), fn($t) => (int)$t->levelid === $levelid);
    }

    /**
     * One topic (must belong to this instance).
     *
     * @param int $topicid
     * @return stdClass
     */
    public function get_topic(int $topicid): stdClass {
        $topics = $this->get_topics();
        if (!isset($topics[$topicid])) {
            throw new \moodle_exception('invalidtopic', 'mod_vocabmastery');
        }
        return $topics[$topicid];
    }

    /**
     * All sets.
     *
     * @return stdClass[]
     */
    public function get_all_sets(): array {
        global $DB;
        if ($this->sets === null) {
            $this->sets = $DB->get_records('vocabmastery_set', ['vocabmasteryid' => $this->vm->id], 'topicid, sortorder, id');
        }
        return $this->sets;
    }

    /**
     * Sets of a topic.
     *
     * @param int $topicid
     * @return stdClass[]
     */
    public function get_topic_sets(int $topicid): array {
        return array_filter($this->get_all_sets(), fn($s) => (int)$s->topicid === $topicid);
    }

    /**
     * One set (must belong to this instance).
     *
     * @param int $setid
     * @return stdClass
     */
    public function get_set(int $setid): stdClass {
        $sets = $this->get_all_sets();
        if (!isset($sets[$setid])) {
            throw new \moodle_exception('invalidset', 'mod_vocabmastery');
        }
        return $sets[$setid];
    }

    /**
     * Items of a set.
     *
     * @param int $setid
     * @return stdClass[]
     */
    public function get_set_items(int $setid): array {
        global $DB;
        return $DB->get_records('vocabmastery_item', ['setid' => $setid], 'sortorder, id');
    }

    /**
     * Level record by id.
     *
     * @param int $levelid
     * @return stdClass
     */
    public function get_level(int $levelid): stdClass {
        $levels = $this->get_levels();
        if (!isset($levels[$levelid])) {
            throw new \moodle_exception('invalidlevel', 'mod_vocabmastery');
        }
        return $levels[$levelid];
    }

    /**
     * Clear caches after content changes.
     */
    public function reset_cache(): void {
        $this->levels = $this->topics = $this->sets = null;
    }

    /**
     * Can the current user manage content.
     *
     * @return bool
     */
    public function can_manage(): bool {
        return has_capability('mod/vocabmastery:managecontent', $this->context);
    }

    /**
     * Can the current user see everyone's progress.
     *
     * @return bool
     */
    public function can_view_all(): bool {
        return has_capability('mod/vocabmastery:viewallprogress', $this->context);
    }

    /**
     * Can the current user practise and take mastery checks.
     *
     * Students need mod/vocabmastery:attempt. Content managers may also run the trainer to preview it.
     *
     * @return bool
     */
    public function can_attempt(): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        return has_capability('mod/vocabmastery:attempt', $this->context) || $this->can_manage();
    }

    /**
     * Throw unless the current user may practise (see can_attempt()).
     *
     * @throws \required_capability_exception
     */
    public function require_attempt(): void {
        if (!$this->can_attempt()) {
            throw new \required_capability_exception($this->context, 'mod/vocabmastery:attempt', 'nopermissions', '');
        }
    }

    /**
     * Teachers can open any topic (preview) regardless of unlocking.
     *
     * @return bool
     */
    public function bypass_locks(): bool {
        return $this->can_manage() || $this->can_view_all();
    }

    /**
     * Base URL for a page in this activity.
     *
     * @param string $page
     * @param array $params
     * @return \moodle_url
     */
    public function url(string $page = 'view.php', array $params = []): \moodle_url {
        return new \moodle_url('/mod/vocabmastery/' . $page, ['id' => $this->cm->id] + $params);
    }

    /**
     * Normalise activity form data before saving.
     *
     * @param stdClass $data
     */
    public static function normalise_form_data(stdClass $data): void {
        foreach (
            ['enablelearn', 'requirelearn', 'enableblitz', 'blitzcountsmastery', 'enablestreak',
                'enablehints', 'injectdifficult'] as $flag
        ) {
            $data->$flag = empty($data->$flag) ? 0 : 1;
        }
        foreach (['completiontopics', 'completionprogress', 'completionalltopics'] as $field) {
            if (property_exists($data, $field)) {
                $data->$field = (int)$data->$field;
            }
        }
    }

    /**
     * Trigger the viewed event and mark completion.
     */
    public function log_view(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $event = event\course_module_viewed::create(
            [
                'objectid' => $this->vm->id,
                'context' => $this->context,
            ]
        );
        $course = $this->course ?? get_course($this->vm->course);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('vocabmastery', $this->vm);
        $event->trigger();
        $completion = new \completion_info($course);
        $completion->set_module_viewed($this->cm);
    }
}
