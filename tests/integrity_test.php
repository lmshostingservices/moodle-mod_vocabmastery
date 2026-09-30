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
 * Tests for grade and progress integrity (fixes from the 0.9.5 audit).
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\engine::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\content::class)]
final class integrity_test extends \advanced_testcase {
    /**
     * Activity with one topic of three nouns and an enrolled student.
     *
     * @param array $settings activity settings
     * @return array [instance, data, userid, course]
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
        return [instance::from_cmid((int)$vm->cmid), $data, (int)$user->id, $course];
    }

    public function test_mastery_check_scores_each_word_once(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'test', 'masterythreshold' => 90]);
        $engine = new engine($instance, $userid);
        $attemptid = (int)$engine->start('test', $data['topicid'], 0)['attempt']->id;
        $first = $engine->record($attemptid, $data['itemids'][0], 'der', 500, false);
        $this->assertSame('', $first['state'], 'no correctness hint during a mastery check');
        $again = $engine->record($attemptid, $data['itemids'][0], 'der', 500, false);
        $this->assertFalse($again['recorded']);
        // Simulate a duplicate that slipped in (e.g. from an older version) - still scored once.
        $dup = $DB->get_record('vocabmastery_response', ['attemptid' => $attemptid]);
        unset($dup->id);
        $DB->insert_record('vocabmastery_response', $dup);
        $DB->insert_record('vocabmastery_response', $dup);
        $result = $engine->finish($attemptid);
        $this->assertEquals(33.33, $result['score']);
        $this->assertFalse($result['passed']);
    }

    public function test_repeating_a_right_answer_in_one_session_does_not_master(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'practice']);
        $engine = new engine($instance, $userid);
        $attemptid = (int)$engine->start('practice', $data['topicid'], 0)['attempt']->id;
        for ($i = 0; $i < 15; $i++) {
            $engine->record($attemptid, $data['itemids'][0], 'der', 500, false);
        }
        $st = $DB->get_record('vocabmastery_itemstate', ['userid' => $userid, 'itemid' => $data['itemids'][0]]);
        $this->assertSame(1, (int)$st->correct);
        $this->assertSame(0, (int)$st->mastered);
        $this->assertSame(
            engine::MAX_RESPONSES_PER_ITEM,
            $DB->count_records('vocabmastery_response', ['attemptid' => $attemptid])
        );
    }

    public function test_only_one_open_mastery_check_per_topic(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $a = (int)$engine->start('test', $data['topicid'], 0)['attempt']->id;
        $b = (int)$engine->start('test', $data['topicid'], 0)['attempt']->id;
        $this->assertEquals(1, $DB->get_field('vocabmastery_attempt', 'finished', ['id' => $a]));
        $this->assertEquals(0, $DB->get_field('vocabmastery_attempt', 'finished', ['id' => $b]));
    }

    public function test_practice_pass_is_saved_without_finishing(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'practice', 'masterythreshold' => 90]);
        $engine = new engine($instance, $userid);
        $answers = ['der', 'die', 'das'];
        for ($round = 0; $round < 3; $round++) {
            $attemptid = (int)$engine->start('practice', $data['topicid'], 0)['attempt']->id;
            foreach ($data['itemids'] as $i => $itemid) {
                $engine->record($attemptid, $itemid, $answers[$i], 500, false);
            }
            // Sessions are never finished (tab closed).
        }
        $this->assertEquals(1, $DB->get_field('vocabmastery_topicstate', 'passed', ['userid' => $userid,
            'topicid' => $data['topicid']]));
    }

    public function test_topic_without_core_words_does_not_block(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['progressionmode' => 'linear']);
        $vmid = (int)$instance->vm->id;
        // An empty topic between two real ones.
        $empty = content::add_topic($vmid, $data['levelid'], 'Lektion 2', 2);
        $third = content::add_topic($vmid, $data['levelid'], 'Lektion 3', 3);
        $setid = content::add_set($vmid, $third, ['name' => 'Nomen', 'options' => "der\ndie\ndas"]);
        content::add_item($vmid, $setid, ['prompt' => 'Hund', 'correctvalue' => 'der']);
        $instance->reset_cache();
        $progress = new progress($instance, $userid);
        $progress->recompute_topic($data['topicid'], 100.0);
        $progress->reset();
        $summaries = $progress->get_summaries();
        $this->assertTrue($summaries[$empty]->empty);
        $this->assertTrue($summaries[$third]->unlocked, 'the empty topic does not lock the next one');
        $this->assertEquals(50.0, $progress->grade_percent(), 'empty topic left out of the grade');
        $DB->set_field('vocabmastery', 'completionalltopics', 1, ['id' => $vmid]);
        $progress->recompute_topic($third, 100.0);
        $this->assertEquals(100.0, (new progress($instance, $userid))->grade_percent());
    }

    public function test_deleting_a_topic_removes_its_sessions_and_finish_survives(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity();
        $engine = new engine($instance, $userid);
        $attemptid = (int)$engine->start('practice', $data['topicid'], 0)['attempt']->id;
        $engine->record($attemptid, $data['itemids'][0], 'der', 500, false);
        content::delete_topic($data['topicid']);
        $this->assertFalse($DB->record_exists('vocabmastery_attempt', ['id' => $attemptid]));
        $this->assertFalse($DB->record_exists('vocabmastery_response', ['attemptid' => $attemptid]));
    }

    public function test_recompute_task_refreshes_stored_mastery(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['unlockby' => 'test']);
        $engine = new engine($instance, $userid);
        foreach ([0, 1] as $i) {
            for ($n = 0; $n < 3; $n++) {
                $engine->update_item_state($data['itemids'][$i], true);
            }
        }
        (new progress($instance, $userid))->recompute_topic($data['topicid']);
        $this->assertEquals(66.67, (float)$DB->get_field('vocabmastery_topicstate', 'mastery', ['userid' => $userid]));
        // The teacher deletes the word the learner has not mastered.
        content::delete_items([$data['itemids'][2]]);
        content::queue_recompute((int)$instance->vm->id);
        content::queue_recompute((int)$instance->vm->id);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(task\recompute_progress::class));
        $this->runAdhocTasks(task\recompute_progress::class);
        $this->assertEquals(100.0, (float)$DB->get_field('vocabmastery_topicstate', 'mastery', ['userid' => $userid]));
    }

    public function test_stale_sessions_are_finished(): void {
        global $DB;
        [$instance, $data, $userid] = $this->setup_activity(['enablelearn' => 1, 'requirelearn' => 0]);
        $engine = new engine($instance, $userid);
        $attemptid = (int)$engine->start('practice', $data['topicid'], 0)['attempt']->id;
        $engine->record($attemptid, $data['itemids'][0], 'der', 500, false);
        $learnid = (int)$engine->start('learn', $data['topicid'], 0)['attempt']->id;
        $old = time() - 2 * DAYSECS;
        $DB->set_field('vocabmastery_attempt', 'timestart', $old, []);
        $DB->set_field('vocabmastery_response', 'timecreated', $old, []);
        $fresh = (int)$engine->start('practice', $data['topicid'], 0)['attempt']->id;
        $this->expectOutputRegex('/finished 1 stale sessions/');
        (new task\close_stale_sessions())->execute();
        $scored = $DB->get_record('vocabmastery_attempt', ['id' => $attemptid]);
        $this->assertEquals(1, $scored->finished);
        $this->assertEquals(100, (float)$scored->score);
        $learn = $DB->get_record('vocabmastery_attempt', ['id' => $learnid]);
        $this->assertEquals(1, $learn->finished);
        $this->assertEquals(0, (int)$DB->get_field('vocabmastery_topicstate', 'learned', ['userid' => $userid]) ?: 0);
        $this->assertEquals(0, $DB->get_field('vocabmastery_attempt', 'finished', ['id' => $fresh]));
    }

    public function test_teacher_preview_writes_no_grade(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        [$instance, $data, , $course] = $this->setup_activity(['unlockby' => 'test']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        (new progress($instance, (int)$teacher->id))->recompute_topic($data['topicid'], 100.0);
        $grades = grade_get_grades($course->id, 'mod', 'vocabmastery', $instance->vm->id, $teacher->id);
        $this->assertNull(reset($grades->items[0]->grades)->grade ?? null);
    }
}
