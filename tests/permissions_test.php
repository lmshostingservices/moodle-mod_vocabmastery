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

/**
 * Tests for the default permissions (1.2.0: managing vocabulary is manager-only).
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\instance::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\local\roles::class)]
final class permissions_test extends \advanced_testcase {
    /**
     * Make the test site look like one without the Premium SaaS theme (the theme may be in the code base).
     */
    protected function without_premium_saas_theme(): void {
        global $DB;
        if ($role = $DB->get_record('role', ['shortname' => \mod_vocabmastery\local\roles::SITEOWNER])) {
            delete_role($role->id);
        }
        unset_config(\mod_vocabmastery\local\roles::CONFIG, 'mod_vocabmastery');
        \mod_vocabmastery\local\roles::apply_defaults();
        accesslib_clear_all_caches_for_unit_testing();
    }

    public function test_only_managers_manage_vocabulary_but_teachers_can_preview(): void {
        $this->resetAfterTest();
        $this->without_premium_saas_theme();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        $users = [];
        foreach (['manager', 'editingteacher', 'teacher', 'student'] as $role) {
            $users[$role] = $gen->create_and_enrol($course, $role);
        }
        $expect = [
            // Role => [manage, attempt or preview, view all progress].
            'manager' => [true, true, true], // No Premium SaaS theme in the test site.
            'editingteacher' => [false, true, true],
            'teacher' => [false, false, true],
            'student' => [false, true, false],
        ];
        foreach ($expect as $role => [$manage, $attempt, $viewall]) {
            $this->setUser($users[$role]);
            $instance = instance::from_cmid((int)$vm->cmid);
            $this->assertSame($manage, $instance->can_manage(), "$role manage");
            $this->assertSame($attempt, $instance->can_attempt(), "$role attempt");
            $this->assertSame($viewall, $instance->can_view_all(), "$role view all");
        }
    }

    public function test_upgrade_step_removes_teacher_default_but_keeps_overrides(): void {
        global $DB;
        $this->resetAfterTest();
        $cap = 'mod/vocabmastery:managecontent';
        $syscontext = \context_system::instance();
        $teacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        // State of a site that installed an earlier version, plus one course override.
        assign_capability($cap, CAP_ALLOW, $teacher, $syscontext->id, true);
        assign_capability($cap, CAP_ALLOW, $teacher, $coursecontext->id, true);
        \mod_vocabmastery\local\roles::remove_teacher_default();
        $where = ['roleid' => $teacher, 'capability' => $cap];
        $this->assertFalse($DB->record_exists('role_capabilities', $where + ['contextid' => $syscontext->id]));
        $this->assertTrue($DB->record_exists('role_capabilities', $where + ['contextid' => $coursecontext->id]));
    }

    /**
     * Whether a role holds the capability at site level.
     *
     * @param string $shortname
     * @return bool
     */
    protected function role_allows(string $shortname): bool {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => $shortname]);
        $where = [
            'roleid' => $roleid,
            'capability' => \mod_vocabmastery\local\roles::CAPABILITY,
            'contextid' => \context_system::instance()->id,
            'permission' => CAP_ALLOW,
        ];
        return $DB->record_exists('role_capabilities', $where);
    }

    public function test_manager_manages_vocabulary_without_premium_saas_theme(): void {
        $this->resetAfterTest();
        $this->without_premium_saas_theme();
        $this->assertSame('manager', get_config('mod_vocabmastery', \mod_vocabmastery\local\roles::CONFIG));
        $this->assertTrue($this->role_allows('manager'));
        $this->assertFalse($this->role_allows('editingteacher'));
        $this->assertSame('', \mod_vocabmastery\local\roles::apply_defaults(), 'applied once only');
    }

    public function test_site_owner_manages_vocabulary_with_premium_saas_theme(): void {
        global $DB;
        $this->resetAfterTest();
        $roles = \mod_vocabmastery\local\roles::class;
        // The site started without the theme.
        $this->without_premium_saas_theme();
        // The Premium SaaS theme is installed later: it creates a Site Owner role based on Manager.
        $ownerid = create_role('Site Owner', 'siteowner', '', 'manager');
        set_role_contextlevels($ownerid, [CONTEXT_SYSTEM]);
        $this->expectOutputRegex('/given to the siteowner role/');
        (new \mod_vocabmastery\task\apply_role_defaults())->execute();
        $this->assertSame('siteowner', get_config('mod_vocabmastery', $roles::CONFIG));
        $this->assertTrue($this->role_allows('siteowner'));
        $this->assertFalse($this->role_allows('manager'));
        $this->assertFalse($this->role_allows('editingteacher'));
        // A Site Owner can manage vocabulary in any activity; a Manager cannot.
        $course = $this->getDataGenerator()->create_course();
        $vm = $this->getDataGenerator()->create_module('vocabmastery', ['course' => $course->id]);
        $owner = $this->getDataGenerator()->create_user();
        role_assign($ownerid, $owner->id, \context_system::instance());
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($owner);
        $this->assertTrue(instance::from_cmid((int)$vm->cmid)->can_manage());
        $this->setUser($manager);
        $this->assertFalse(instance::from_cmid((int)$vm->cmid)->can_manage());
        // An administrator gives it back to Managers: the task does not undo that.
        assign_capability(
            $roles::CAPABILITY,
            CAP_ALLOW,
            $DB->get_field('role', 'id', ['shortname' => 'manager']),
            \context_system::instance()->id,
            true
        );
        $this->assertSame('', $roles::apply_defaults());
        $this->assertTrue($this->role_allows('manager'));
    }
}
