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
 * Tests for answer checking, item mastery and topic passing.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\progress::class)]
final class engine_test extends \advanced_testcase {
    /**
     * Activity with one topic of three nouns and an enrolled student.
     *
     * @param array $settings activity settings
     * @return array [instance, data, userid]
     */
    protected function setup_activity(array $settings = []): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id] + $settings);
        $data = $gen->get_plugin_generator('mod_vocabmastery')->create_topic_with_items(
            (int)$vm->id,
            [['Vater', 'der'], ['Mutter', 'die'], ['Kind', 'das']]
        );
        $user = $gen->create_and_enrol($course, 'student');
        $this->setUser($user);
        return [instance::from_cmid((int)$vm->cmid), $data, (int)$user->id];
    }

    public function test_is_correct_normalises_and_accepts_alternatives(): void {
        $item = (object)['correctvalue' => 'über + Akk.', 'altanswers' => 'über + Akkusativ|über+Akk'];
        $this->assertTrue(engine::is_correct($item, '  Über  +  AKK. '));
        $this->assertTrue(engine::is_correct($item, 'über + akkusativ'));
        $this->assertFalse(engine::is_correct($item, 'auf + Akk.'));
        $this->assertFalse(engine::is_correct($item, ''));
    }

    public function test_item_mastery_needs_total_and_streak(): void {
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $itemid = $data['itemids'][0];
        $engine->update_item_state($itemid, true);
        $engine->update_item_state($itemid, true);
        $this->assertSame(0, $engine->update_item_state($itemid, false)->mastered);
        $this->assertSame(0, $engine->update_item_state($itemid, true)->mastered, '3 correct but streak 1');
        $st = $engine->update_item_state($itemid, true);
        $this->assertSame(1, $st->mastered, '4 correct, streak 2');
        $this->assertSame(0, $engine->update_item_state($itemid, false)->mastered, 'a mistake removes mastery');
    }

    public function test_hinted_answer_does_not_count(): void {
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $st = $engine->update_item_state($data['itemids'][0], true, true);
        $this->assertSame(0, (int)$st->correct);
    }

    public function test_difficult_after_two_wrong(): void {
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $engine->update_item_state($data['itemids'][1], false);
        $this->assertSame(1, $engine->update_item_state($data['itemids'][1], false)->difficult);
    }

    public function test_topic_passes_by_practice_when_configured(): void {
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'practice', 'masterythreshold' => 90]);
        $engine = new engine($instance, $userid);
        foreach ($data['itemids'] as $itemid) {
            for ($i = 0; $i < 3; $i++) {
                $engine->update_item_state($itemid, true);
            }
        }
        $result = (new progress($instance, $userid))->recompute_topic($data['topicid']);
        $this->assertEquals(100, $result['mastery']);
        $this->assertTrue($result['passed']);
        $this->assertTrue($result['newlypassed']);
    }

    public function test_practice_alone_does_not_pass_when_test_required(): void {
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'test']);
        $engine = new engine($instance, $userid);
        foreach ($data['itemids'] as $itemid) {
            for ($i = 0; $i < 3; $i++) {
                $engine->update_item_state($itemid, true);
            }
        }
        $this->assertFalse((new progress($instance, $userid))->recompute_topic($data['topicid'])['passed']);
        $this->assertTrue((new progress($instance, $userid))->recompute_topic($data['topicid'], 95.0)['passed']);
    }

    public function test_mastery_check_sends_no_answers(): void {
        [$instance, $data, $userid] = $this->setup_activity();
        $session = (new engine($instance, $userid))->start('test', $data['topicid'], 0);
        $this->assertNotEmpty($session['questions']);
        foreach ($session['questions'] as $q) {
            $this->assertSame('', $q['correct']);
            $this->assertSame('', $q['alternatives']);
            $this->assertSame('', $q['hint']);
        }
    }

    public function test_finish_returns_review_and_first_try_score(): void {
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $session = $engine->start('practice', $data['topicid'], 0);
        $attemptid = (int)$session['attempt']->id;
        $ids = $data['itemids'];
        // First word wrong then right, the other two right first time.
        $engine->record($attemptid, $ids[0], 'die', 1000, false);
        $engine->record($attemptid, $ids[1], 'die', 1000, false);
        $engine->record($attemptid, $ids[2], 'das', 1000, false);
        $engine->record($attemptid, $ids[0], 'der', 1000, false);
        $result = $engine->finish($attemptid, 0);
        $this->assertCount(3, $result['answers']);
        $this->assertFalse($result['answers'][0]['iscorrect']);
        $this->assertSame('die', $result['answers'][0]['given']);
        $this->assertTrue($result['answers'][0]['prefix'], 'article answers are written before the word');
        $this->assertEquals(66.67, $result['score']);
        $this->assertSame(-1.0, $result['previousscore']);
        $this->assertSame([['name' => 'Nomen + Artikel', 'correct' => 2, 'total' => 3]], $result['sets']);
        $this->assertGreaterThanOrEqual(0, $result['seconds']);
        // A second session compares with the first.
        $second = $engine->start('practice', $data['topicid'], 0);
        $engine->record((int)$second['attempt']->id, $ids[0], 'der', 1000, false);
        $this->assertEquals(66.67, $engine->finish((int)$second['attempt']->id, 0)['previousscore']);
    }

    public function test_answer_prefix_rule(): void {
        $fixed = (object)['answermode' => 'fixed'];
        $pool = (object)['answermode' => 'pool'];
        $this->assertTrue(engine::answer_is_prefix($fixed, 'die'));
        $this->assertFalse(engine::answer_is_prefix($fixed, 'to live'));
        $this->assertFalse(engine::answer_is_prefix($fixed, 'über + Akk.'));
        $this->assertFalse(engine::answer_is_prefix($pool, 'die'));
    }
}
