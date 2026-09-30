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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Learners report a problem with a word.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report_item::class)]
final class report_item_test extends \externallib_advanced_testcase {
    public function test_report_is_saved_once_per_learner_and_word(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $data = $gen->get_plugin_generator('mod_vocabmastery')->create_topic_with_items((int)$vm->id, [['Vater', 'der']]);
        $this->setUser($gen->create_and_enrol($course, 'student'));
        $itemid = $data['itemids'][0];
        $this->assertTrue(report_item::execute((int)$vm->cmid, $itemid, 'typo', 'first')['saved']);
        report_item::execute((int)$vm->cmid, $itemid, 'translation', 'second');
        $flags = $DB->get_records('vocabmastery_flag', ['itemid' => $itemid]);
        $this->assertCount(1, $flags);
        $flag = reset($flags);
        $this->assertSame('translation', $flag->reason);
        $this->assertSame('second', $flag->commenttext);
    }

    public function test_invalid_reason_and_foreign_item_are_rejected(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $other = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $pg = $gen->get_plugin_generator('mod_vocabmastery');
        $data = $pg->create_topic_with_items((int)$vm->id, [['Vater', 'der']]);
        $foreign = $pg->create_topic_with_items((int)$other->id, [['Kind', 'das']]);
        $this->setUser($gen->create_and_enrol($course, 'student'));
        try {
            report_item::execute((int)$vm->cmid, $data['itemids'][0], 'nonsense');
            $this->fail('Invalid reason accepted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('reason', $e->getMessage() . $e->debuginfo);
        }
        $this->expectException(\dml_missing_record_exception::class);
        report_item::execute((int)$vm->cmid, $foreign['itemids'][0], 'typo');
    }

    public function test_non_editing_teacher_cannot_report(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $data = $gen->get_plugin_generator('mod_vocabmastery')->create_topic_with_items((int)$vm->id, [['Vater', 'der']]);
        $this->setUser($gen->create_and_enrol($course, 'teacher'));
        $this->expectException(\required_capability_exception::class);
        report_item::execute((int)$vm->cmid, $data['itemids'][0], 'typo');
    }

    public function test_privacy_export_and_delete_include_reports(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $data = $gen->get_plugin_generator('mod_vocabmastery')->create_topic_with_items((int)$vm->id, [['Vater', 'der']]);
        $user = $gen->create_and_enrol($course, 'student');
        $this->setUser($user);
        report_item::execute((int)$vm->cmid, $data['itemids'][0], 'typo', 'check');
        $context = \context_module::instance((int)$vm->cmid);
        $contexts = \mod_vocabmastery\privacy\provider::get_contexts_for_userid((int)$user->id);
        $this->assertContains((int)$context->id, array_map('intval', $contexts->get_contextids()));
        $approved = new \core_privacy\local\request\approved_contextlist($user, 'mod_vocabmastery', [$context->id]);
        \mod_vocabmastery\privacy\provider::export_user_data($approved);
        $exported = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $this->assertSame('check', $exported->reports[0]['comment']);
        \mod_vocabmastery\privacy\provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('vocabmastery_flag', ['userid' => $user->id]));
    }
}
