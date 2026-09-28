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

namespace mod_vocabmastery\external;

use mod_vocabmastery\instance;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The training web services must enforce mod/vocabmastery:attempt, not only :view.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(start_session::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(record_response::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(finish_session::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(instance::class)]
final class attempt_capability_test extends \externallib_advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass */
    protected $vm;
    /** @var array */
    protected $data;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->vm = $gen->create_module('vocabmastery', ['course' => $this->course->id]);
        $this->data = $gen->get_plugin_generator('mod_vocabmastery')->create_topic_with_items(
            (int)$this->vm->id,
            [['Vater', 'der'], ['Mutter', 'die'], ['Kind', 'das']]
        );
    }

    public function test_student_can_train(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($user);
        $result = start_session::execute((int)$this->vm->cmid, 'practice', $this->data['topicid']);
        $attemptid = (int)$result['attemptid'];
        $this->assertGreaterThan(0, $attemptid);
        $q = $result['questions'][0];
        $recorded = record_response::execute((int)$this->vm->cmid, $attemptid, (int)$q['itemid'], (string)$q['correct']);
        $this->assertTrue((bool)$recorded['correct']);
        finish_session::execute((int)$this->vm->cmid, $attemptid);
    }

    public function test_view_without_attempt_cannot_start(): void {
        [$user] = $this->student_with_open_attempt();
        $this->prohibit_attempt();
        $this->setUser($user);
        $this->assertTrue(has_capability('mod/vocabmastery:view', \context_module::instance((int)$this->vm->cmid)));
        $this->expectException(\required_capability_exception::class);
        start_session::execute((int)$this->vm->cmid, 'practice', $this->data['topicid']);
    }

    public function test_view_without_attempt_cannot_record(): void {
        [$user, $attemptid] = $this->student_with_open_attempt();
        $this->prohibit_attempt();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        record_response::execute((int)$this->vm->cmid, $attemptid, $this->data['itemids'][0], 'der');
    }

    public function test_view_without_attempt_cannot_finish(): void {
        [$user, $attemptid] = $this->student_with_open_attempt();
        $this->prohibit_attempt();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        finish_session::execute((int)$this->vm->cmid, $attemptid);
    }

    public function test_non_editing_teacher_cannot_train(): void {
        // Non-editing teachers have :view but neither :attempt nor :managecontent.
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        start_session::execute((int)$this->vm->cmid, 'practice', $this->data['topicid']);
    }

    public function test_content_manager_can_preview(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($user);
        $result = start_session::execute((int)$this->vm->cmid, 'practice', $this->data['topicid']);
        $this->assertGreaterThan(0, (int)$result['attemptid']);
    }

    /**
     * A student who has already started a session.
     *
     * @return array [user, attemptid]
     */
    protected function student_with_open_attempt(): array {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($user);
        $result = start_session::execute((int)$this->vm->cmid, 'practice', $this->data['topicid']);
        return [$user, (int)$result['attemptid']];
    }

    /**
     * Prohibit :attempt for students in this activity (they keep :view).
     */
    protected function prohibit_attempt(): void {
        global $DB;
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/vocabmastery:attempt', CAP_PROHIBIT, $roleid, \context_module::instance((int)$this->vm->cmid));
        accesslib_clear_all_caches_for_unit_testing();
    }
}
