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
 * Tests for backward progression and hiding answers after the mastery check (1.1.0).
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\engine::class)]
final class options_test extends \advanced_testcase {
    /**
     * Two levels (A2, B1) of three lessons each, one word per lesson, and an enrolled learner.
     *
     * @param array $settings activity settings
     * @return array [instance, topics (level => [n => topicid]), items (topicid => itemid), userid]
     */
    protected function setup_grid(array $settings): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id] + $settings);
        $vmid = (int)$vm->id;
        $topics = [];
        $items = [];
        foreach (['A2', 'B1'] as $levelname) {
            $levelid = content::add_level($vmid, $levelname);
            for ($n = 1; $n <= 3; $n++) {
                $tid = content::add_topic($vmid, $levelid, 'Lesson ' . $n, $n);
                $setid = content::add_set($vmid, $tid, ['name' => 'Nouns', 'options' => "der\ndie\ndas"]);
                $items[$tid] = content::add_item($vmid, $setid, ['prompt' => 'Word' . $levelname . $n, 'correctvalue' => 'der']);
                $topics[$levelname][$n] = $tid;
            }
        }
        $user = $gen->create_and_enrol($course, 'student');
        $this->setUser($user);
        return [instance::from_cmid((int)$vm->cmid), $topics, $items, (int)$user->id];
    }

    public function test_earlier_topics_stay_locked_by_default(): void {
        [$instance, $topics, $items, $userid] = $this->setup_grid(['progressionmode' => 'dual']);
        // The learner has started B1 lesson 3 (e.g. reached it from A2 lesson 3).
        (new engine($instance, $userid))->update_item_state($items[$topics['B1'][3]], true);
        $s = (new progress($instance, $userid))->get_summaries();
        $this->assertTrue($s[$topics['B1'][3]]->unlocked);
        $this->assertFalse($s[$topics['B1'][2]]->unlocked);
        $this->assertFalse($s[$topics['B1'][1]]->unlocked);
    }

    public function test_backward_progression_opens_earlier_topics_in_the_level(): void {
        [$instance, $topics, $items, $userid] = $this->setup_grid(['progressionmode' => 'dual', 'openprevious' => 1]);
        (new engine($instance, $userid))->update_item_state($items[$topics['B1'][3]], true);
        $progress = new progress($instance, $userid);
        $s = $progress->get_summaries();
        $this->assertTrue($s[$topics['B1'][2]]->unlocked);
        $this->assertTrue($s[$topics['B1'][1]]->unlocked);
        // Other levels are not opened backwards.
        $this->assertFalse($s[$topics['A2'][2]]->unlocked);
        $routes = $progress->routes_after($topics['B1'][3]);
        $this->assertContains(['type' => 'backward', 'topicid' => $topics['B1'][2]], $routes);
    }

    /**
     * Run a mastery check on the first lesson with one right and no other answers.
     *
     * @param string $review show|hide
     * @return array finish() result
     */
    protected function finish_check(string $review): array {
        [$instance, $topics, $items, $userid] = $this->setup_grid(['testreview' => $review]);
        $engine = new engine($instance, $userid);
        $tid = $topics['A2'][1];
        $attemptid = (int)$engine->start('test', $tid, 0)['attempt']->id;
        $engine->record($attemptid, $items[$tid], 'die', 500, false);
        return $engine->finish($attemptid);
    }

    public function test_answers_shown_after_check_by_default(): void {
        $r = $this->finish_check('show');
        $this->assertFalse($r['answershidden']);
        $this->assertCount(1, $r['answers']);
        $this->assertSame('der', $r['answers'][0]['correct']);
        $this->assertCount(1, $r['mistakes']);
    }

    public function test_answers_hidden_after_check_when_configured(): void {
        $r = $this->finish_check('hide');
        $this->assertTrue($r['answershidden']);
        $this->assertSame([], $r['answers']);
        $this->assertSame([], $r['mistakes']);
        $this->assertEquals(0, $r['score']);
        $this->assertFalse($r['passed']);
        // Nothing in the result reveals the correct answer.
        $this->assertStringNotContainsString('"der"', json_encode($r));
    }
}
