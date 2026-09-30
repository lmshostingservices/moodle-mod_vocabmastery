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

use stdClass;

/**
 * Progress, mastery and unlocking for one user in one activity.
 *
 * Topic mastery = share of the topic's gated core items the user has mastered.
 * Optional items never block completion. Progression is two-dimensional:
 * horizontal (next topic in the same level) and vertical (the topic with the
 * same path key in the next level).
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /** @var instance */
    protected instance $instance;
    /** @var int */
    protected int $userid;
    /** @var array|null itemid => row */
    protected ?array $items = null;
    /** @var array|null topicid => topicstate */
    protected ?array $topicstates = null;
    /** @var array|null topicid => summary */
    protected ?array $summaries = null;

    /**
     * Constructor.
     *
     * @param instance $instance
     * @param int $userid
     */
    public function __construct(instance $instance, int $userid) {
        $this->instance = $instance;
        $this->userid = $userid;
    }

    /**
     * Clear caches.
     */
    public function reset(): void {
        $this->items = $this->topicstates = $this->summaries = null;
    }

    /**
     * Item rows joined with the user's item state.
     *
     * @return array
     */
    public function get_item_rows(): array {
        global $DB;
        if ($this->items === null) {
            $sql = "SELECT i.id, i.setid, i.iscore, i.prompt, i.correctvalue, i.translation, s.topicid, s.gated,
                           COALESCE(st.attempts, 0) AS attempts, COALESCE(st.correct, 0) AS correct,
                           COALESCE(st.wrong, 0) AS wrong, COALESCE(st.streak, 0) AS streak,
                           COALESCE(st.mastered, 0) AS mastered, COALESCE(st.difficult, 0) AS difficult,
                           COALESCE(st.wasdifficult, 0) AS wasdifficult, COALESCE(st.lastseen, 0) AS lastseen
                      FROM {vocabmastery_item} i
                      JOIN {vocabmastery_set} s ON s.id = i.setid
                 LEFT JOIN {vocabmastery_itemstate} st ON st.itemid = i.id AND st.userid = :uid
                     WHERE i.vocabmasteryid = :vid";
            $this->items = $DB->get_records_sql($sql, ['uid' => $this->userid, 'vid' => $this->instance->vm->id]);
        }
        return $this->items;
    }

    /**
     * Topic states for the user.
     *
     * @return array topicid => record
     */
    public function get_topic_states(): array {
        global $DB;
        if ($this->topicstates === null) {
            $this->topicstates = [];
            $recs = $DB->get_records(
                'vocabmastery_topicstate',
                ['vocabmasteryid' => $this->instance->vm->id, 'userid' => $this->userid]
            );
            foreach ($recs as $r) {
                $this->topicstates[(int)$r->topicid] = $r;
            }
        }
        return $this->topicstates;
    }

    /**
     * Items that count for mastery of a topic: gated core items, falling back to all core items.
     *
     * @param int $topicid
     * @return array
     */
    public function gated_items(int $topicid): array {
        $rows = array_filter($this->get_item_rows(), fn($r) => (int)$r->topicid === $topicid && $r->iscore);
        $gated = array_filter($rows, fn($r) => $r->gated);
        return $gated ?: $rows;
    }

    /**
     * Summaries for all topics, including unlock state.
     *
     * @return array topicid => stdClass
     */
    public function get_summaries(): array {
        if ($this->summaries !== null) {
            return $this->summaries;
        }
        $states = $this->get_topic_states();
        $rows = $this->get_item_rows();
        $bytopic = [];
        foreach ($rows as $r) {
            $bytopic[(int)$r->topicid][] = $r;
        }
        $summaries = [];
        foreach ($this->instance->get_topics() as $t) {
            $tid = (int)$t->id;
            $all = $bytopic[$tid] ?? [];
            $gated = $this->gated_items($tid);
            $s = new stdClass();
            $s->topicid = $tid;
            $s->levelid = (int)$t->levelid;
            $s->itemcount = count($all);
            $s->corecount = count($gated);
            $s->mastered = count(array_filter($gated, fn($r) => $r->mastered));
            $s->difficult = count(array_filter($all, fn($r) => $r->difficult));
            $s->newcount = count(array_filter($gated, fn($r) => !$r->attempts));
            $s->learning = max(0, $s->corecount - $s->mastered - $s->newcount);
            $s->mastery = $s->corecount ? round($s->mastered / $s->corecount * 100, 1) : 0;
            $state = $states[$tid] ?? null;
            $s->passed = $state ? (bool)$state->passed : false;
            $s->besttest = $state ? (float)$state->besttest : -1;
            $s->learned = $state ? (bool)$state->learned : false;
            $s->started = (bool)array_filter($all, fn($r) => $r->attempts > 0);
            $s->lastseen = $all ? max(array_map(fn($r) => (int)$r->lastseen, $all)) : 0;
            // A topic without core words has nothing to master. It never blocks progression, completion or grade.
            $s->empty = $s->corecount === 0;
            $summaries[$tid] = $s;
        }
        $this->apply_unlocks($summaries);
        $this->summaries = $summaries;
        return $summaries;
    }

    /**
     * Summary for one topic.
     *
     * @param int $topicid
     * @return stdClass
     */
    public function get_summary(int $topicid): stdClass {
        $all = $this->get_summaries();
        if (!isset($all[$topicid])) {
            throw new \moodle_exception('invalidtopic', 'mod_vocabmastery');
        }
        return $all[$topicid];
    }

    /**
     * Path key used to align topics across levels.
     *
     * @return array topicid => int
     */
    public function path_keys(): array {
        $keys = [];
        foreach ($this->instance->get_levels() as $level) {
            $pos = 0;
            foreach ($this->instance->get_level_topics((int)$level->id) as $t) {
                $pos++;
                $keys[(int)$t->id] = (int)$t->pathkey > 0 ? (int)$t->pathkey : $pos;
            }
        }
        return $keys;
    }

    /**
     * Horizontal and vertical neighbours of every topic.
     *
     * @return array topicid => ['prevh' => ?int, 'nexth' => ?int, 'prevv' => ?int, 'nextv' => ?int, 'levelindex' => int]
     */
    public function neighbours(): array {
        $keys = $this->path_keys();
        $levels = array_values($this->instance->get_levels());
        $bylevel = [];
        foreach ($levels as $li => $level) {
            $bylevel[$li] = array_values(array_map(
                fn($t) => (int)$t->id,
                $this->instance->get_level_topics((int)$level->id)
            ));
        }
        $n = [];
        foreach ($bylevel as $li => $tids) {
            foreach ($tids as $pi => $tid) {
                $n[$tid] = [
                    'levelindex' => $li,
                    'prevh' => $tids[$pi - 1] ?? null,
                    'nexth' => $tids[$pi + 1] ?? null,
                    'prevv' => null,
                    'nextv' => null,
                ];
            }
        }
        foreach ($bylevel as $li => $tids) {
            foreach ($tids as $tid) {
                foreach ([-1 => 'prevv', 1 => 'nextv'] as $delta => $field) {
                    $other = $bylevel[$li + $delta] ?? [];
                    foreach ($other as $oid) {
                        if ($keys[$oid] === $keys[$tid]) {
                            $n[$tid][$field] = $oid;
                            break;
                        }
                    }
                }
            }
        }
        return $n;
    }

    /**
     * Work out which topics are unlocked under the activity's progression mode.
     *
     * @param array $summaries
     */
    protected function apply_unlocks(array &$summaries): void {
        $mode = $this->instance->vm->progressionmode;
        $topics = array_values($this->instance->get_topics());
        $passed = array_map(fn($s) => $s->passed || $s->empty, $summaries);
        foreach ($summaries as $s) {
            $s->unlocked = false;
        }
        if (!$topics) {
            return;
        }
        if ($mode === 'open') {
            foreach ($summaries as $s) {
                $s->unlocked = true;
            }
            return;
        }
        $n = $this->neighbours();
        if ($mode === 'linear') {
            $prev = null;
            foreach ($topics as $t) {
                $tid = (int)$t->id;
                $summaries[$tid]->unlocked = $prev === null || $passed[$prev];
                $prev = $tid;
            }
        } else if ($mode === 'level') {
            // Column-major: topic 1 of every level, then topic 2 of every level, ...
            $keys = $this->path_keys();
            $order = $topics;
            usort($order, function ($a, $b) use ($keys, $n) {
                return [$keys[(int)$a->id], $n[(int)$a->id]['levelindex']]
                    <=> [$keys[(int)$b->id], $n[(int)$b->id]['levelindex']];
            });
            $prev = null;
            foreach ($order as $t) {
                $tid = (int)$t->id;
                $summaries[$tid]->unlocked = $prev === null || $passed[$prev];
                $prev = $tid;
            }
        } else {
            // Dual pathway.
            $first = (int)$topics[0]->id;
            $levels = array_values($this->instance->get_levels());
            foreach ($topics as $t) {
                $tid = (int)$t->id;
                $nb = $n[$tid];
                if ($tid === $first) {
                    $unlocked = true;
                } else if ($nb['prevh'] === null && $nb['prevv'] === null) {
                    // Orphan start of a level: open once anything in the previous level is passed.
                    $unlocked = false;
                    $prevlevel = $levels[$nb['levelindex'] - 1] ?? null;
                    if ($prevlevel) {
                        foreach ($this->instance->get_level_topics((int)$prevlevel->id) as $pt) {
                            if ($passed[(int)$pt->id]) {
                                $unlocked = true;
                                break;
                            }
                        }
                    }
                } else {
                    $unlocked = ($nb['prevh'] !== null && $passed[$nb['prevh']])
                        || ($nb['prevv'] !== null && $passed[$nb['prevv']]);
                }
                $summaries[$tid]->unlocked = $unlocked;
            }
        }
        foreach ($summaries as $s) {
            if ($s->passed || $s->started) {
                $s->unlocked = true;
            }
        }
    }

    /**
     * Status keyword for display.
     *
     * @param stdClass $s summary
     * @return string passed|active|available|locked
     */
    public static function status(stdClass $s): string {
        if ($s->passed) {
            return 'passed';
        }
        if (!$s->unlocked) {
            return 'locked';
        }
        return $s->started ? 'active' : 'available';
    }

    /**
     * Summaries of the topics that count towards progress, completion and grade
     * (topics without core words are left out).
     *
     * @return array topicid => stdClass
     */
    public function countable_summaries(): array {
        return array_filter($this->get_summaries(), fn($s) => !$s->empty);
    }

    /**
     * Overall progress percentage (passed topics count as 100).
     *
     * @return float
     */
    public function overall_percent(): float {
        $s = $this->countable_summaries();
        if (!$s) {
            return 0;
        }
        $sum = 0;
        foreach ($s as $t) {
            $sum += $t->passed ? 100 : min(99, $t->mastery);
        }
        return round($sum / count($s), 1);
    }

    /**
     * Topics passed.
     *
     * @return int
     */
    public function topics_passed(): int {
        return count(array_filter($this->countable_summaries(), fn($s) => $s->passed));
    }

    /**
     * Grade percentage according to grade method.
     *
     * @return float
     */
    public function grade_percent(): float {
        $s = $this->countable_summaries();
        if (!$s) {
            return 0;
        }
        if ((int)$this->instance->vm->grademethod === 2) {
            $sum = 0;
            foreach ($s as $t) {
                $sum += max(0, $t->besttest);
            }
            return round($sum / count($s), 2);
        }
        return round($this->topics_passed() / count($s) * 100, 2);
    }

    /**
     * Best topic to continue with.
     *
     * @return stdClass|null summary
     */
    public function next_topic(): ?stdClass {
        $candidates = array_filter($this->get_summaries(), fn($s) => $s->unlocked && !$s->passed && $s->corecount);
        if (!$candidates) {
            return null;
        }
        $inprogress = array_filter($candidates, fn($s) => $s->started);
        if ($inprogress) {
            usort($inprogress, fn($a, $b) => $b->lastseen <=> $a->lastseen);
            return reset($inprogress);
        }
        return reset($candidates);
    }

    /**
     * Where can the student go after a topic.
     *
     * @param int $topicid
     * @return array list of ['type' => horizontal|vertical|next, 'topicid' => int]
     */
    public function routes_after(int $topicid): array {
        $n = $this->neighbours()[$topicid] ?? null;
        $s = $this->get_summaries();
        $routes = [];
        if (!$n) {
            return [];
        }
        $mode = $this->instance->vm->progressionmode;
        $types = $mode === 'level' ? ['nextv' => 'vertical', 'nexth' => 'horizontal']
            : ['nexth' => 'horizontal', 'nextv' => 'vertical'];
        if ($mode === 'linear') {
            $types = ['nexth' => 'horizontal'];
        }
        foreach ($types as $field => $type) {
            $tid = $n[$field];
            if ($tid && isset($s[$tid]) && $s[$tid]->unlocked && !$s[$tid]->passed) {
                $routes[] = ['type' => $type, 'topicid' => $tid];
            }
        }
        if (!$routes && ($next = $this->next_topic()) && $next->topicid !== $topicid) {
            $routes[] = ['type' => 'next', 'topicid' => $next->topicid];
        }
        return $routes;
    }

    /**
     * Get or create a topic state record.
     *
     * @param int $topicid
     * @return stdClass
     */
    protected function get_or_create_state(int $topicid): stdClass {
        global $DB;
        $rec = $DB->get_record('vocabmastery_topicstate', ['userid' => $this->userid, 'topicid' => $topicid]);
        if (!$rec) {
            $rec = (object)[
                'vocabmasteryid' => $this->instance->vm->id,
                'userid' => $this->userid,
                'topicid' => $topicid,
                'mastery' => 0,
                'besttest' => -1,
                'passed' => 0,
                'learned' => 0,
                'timepassed' => 0,
                'timemodified' => time(),
            ];
            $rec->id = $DB->insert_record('vocabmastery_topicstate', $rec);
        }
        return $rec;
    }

    /**
     * Recalculate a topic's mastery and pass state.
     *
     * @param int $topicid
     * @param float|null $testscore a just-finished mastery check score
     * @param bool $notify update gradebook and completion afterwards
     * @return array ['mastery' => float, 'passed' => bool, 'newlypassed' => bool]
     */
    public function recompute_topic(int $topicid, ?float $testscore = null, bool $notify = true): array {
        global $DB;
        $this->reset();
        $gated = $this->gated_items($topicid);
        $mastered = count(array_filter($gated, fn($r) => $r->mastered));
        $mastery = $gated ? round($mastered / count($gated) * 100, 2) : 0;

        $state = $this->get_or_create_state($topicid);
        $state->mastery = $mastery;
        if ($testscore !== null && $testscore > (float)$state->besttest) {
            $state->besttest = $testscore;
        }
        $threshold = (float)$this->instance->vm->masterythreshold;
        $bypractice = $gated && $mastery >= $threshold;
        $bytest = (float)$state->besttest >= $threshold;
        switch ($this->instance->vm->unlockby) {
            case 'practice':
                $nowpassed = $bypractice;
                break;
            case 'either':
                $nowpassed = $bypractice || $bytest;
                break;
            default:
                $nowpassed = $bytest;
        }
        $newlypassed = false;
        if ($nowpassed && !$state->passed) {
            $state->passed = 1;
            $state->timepassed = time();
            $newlypassed = true;
        }
        $state->timemodified = time();
        $DB->update_record('vocabmastery_topicstate', $state);
        $this->reset();

        if ($newlypassed) {
            $event = event\topic_passed::create([
                'objectid' => $topicid,
                'context' => $this->instance->context,
                'relateduserid' => $this->userid,
                'other' => ['mastery' => $mastery, 'besttest' => (float)$state->besttest],
            ]);
            $event->trigger();
        }
        if ($notify) {
            $this->after_change();
        }
        return ['mastery' => $mastery, 'passed' => (bool)$state->passed, 'newlypassed' => $newlypassed];
    }

    /**
     * Recalculate every topic the user has progress in (after the teacher changed content),
     * then update the gradebook and completion once. A topic already passed stays passed.
     */
    public function recompute_all(): void {
        global $DB;
        $topics = $this->instance->get_topics();
        $DB->delete_records_select(
            'vocabmastery_topicstate',
            'vocabmasteryid = :vid AND userid = :uid AND topicid NOT IN (SELECT id FROM {vocabmastery_topic})',
            ['vid' => $this->instance->vm->id, 'uid' => $this->userid]
        );
        $withstate = array_keys($this->get_topic_states());
        $started = array_unique(array_map(fn($r) => (int)$r->topicid, array_filter(
            $this->get_item_rows(),
            fn($r) => $r->attempts > 0
        )));
        foreach (array_unique(array_merge($withstate, $started)) as $tid) {
            if (isset($topics[$tid])) {
                $this->recompute_topic((int)$tid, null, false);
            }
        }
        $this->reset();
        $this->after_change();
    }

    /**
     * Mark Learn mode as done for a topic.
     *
     * @param int $topicid
     */
    public function mark_learned(int $topicid): void {
        global $DB;
        $state = $this->get_or_create_state($topicid);
        if (!$state->learned) {
            $DB->set_field('vocabmastery_topicstate', 'learned', 1, ['id' => $state->id]);
        }
        $this->reset();
    }

    /**
     * Update gradebook and completion after progress changes.
     */
    public function after_change(): void {
        global $CFG;
        if (!has_capability('mod/vocabmastery:attempt', $this->instance->context, $this->userid)) {
            // Teachers previewing the trainer get no grades or completion.
            return;
        }
        require_once($CFG->dirroot . '/mod/vocabmastery/lib.php');
        require_once($CFG->libdir . '/completionlib.php');
        $vm = clone $this->instance->vm;
        $vm->cmidnumber = $this->instance->cm->idnumber ?? '';
        vocabmastery_update_grades($vm, $this->userid);
        $course = $this->instance->course ?? get_course($this->instance->vm->course);
        $completion = new \completion_info($course);
        if ($completion->is_enabled($this->instance->cm)) {
            $completion->update_state($this->instance->cm, COMPLETION_UNKNOWN, $this->userid);
        }
    }

    /**
     * Difficult items for a scope, weakest first.
     *
     * @param int|null $topicid
     * @param int $limit
     * @return array
     */
    public function difficult_items(?int $topicid = null, int $limit = 0): array {
        $rows = array_filter($this->get_item_rows(), fn($r) => $r->difficult
            && ($topicid === null || (int)$r->topicid === $topicid));
        usort($rows, function ($a, $b) {
            $aa = $a->attempts ? $a->correct / $a->attempts : 0;
            $bb = $b->attempts ? $b->correct / $b->attempts : 0;
            return $aa <=> $bb;
        });
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }
}
