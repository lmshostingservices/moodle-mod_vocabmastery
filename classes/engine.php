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
 * The learning engine: builds sessions, checks answers, updates item mastery.
 *
 * Language agnostic: an item is a prompt with a correct value; answer buttons
 * come either from a fixed list on the set or from other items' values.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /** Modes that exist. */
    const MODES = ['learn', 'practice', 'blitz', 'streak', 'difficult', 'test'];

    /** @var instance */
    protected instance $instance;
    /** @var int */
    protected int $userid;
    /** @var progress */
    protected progress $progress;

    /**
     * Constructor.
     *
     * @param instance $instance
     * @param int $userid
     */
    public function __construct(instance $instance, int $userid) {
        $this->instance = $instance;
        $this->userid = $userid;
        $this->progress = new progress($instance, $userid);
    }

    /**
     * Progress helper.
     *
     * @return progress
     */
    public function get_progress(): progress {
        return $this->progress;
    }

    /**
     * Normalise a response for comparison.
     *
     * @param string $value
     * @return string
     */
    public static function normalise(string $value): string {
        $value = \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
        return str_replace(['‹', '›', '’'], ['', '', "'"], $value);
    }

    /**
     * Is the response correct for the item.
     *
     * @param stdClass $item
     * @param string $response
     * @return bool
     */
    public static function is_correct(stdClass $item, string $response): bool {
        $given = self::normalise($response);
        if ($given === '') {
            return false;
        }
        $accepted = [self::normalise($item->correctvalue)];
        if (!empty($item->altanswers)) {
            foreach (explode('|', $item->altanswers) as $alt) {
                if (trim($alt) !== '') {
                    $accepted[] = self::normalise($alt);
                }
            }
        }
        return in_array($given, $accepted, true);
    }

    /**
     * Fixed answer options of a set.
     *
     * @param stdClass $set
     * @return string[]
     */
    public static function fixed_options(stdClass $set): array {
        $opts = preg_split('/\r\n|\r|\n/', (string)$set->options);
        $opts = array_values(array_unique(array_filter(array_map('trim', $opts), fn($o) => $o !== '')));
        return $opts;
    }

    /**
     * Distractor pool for pool-mode sets: distinct values in the set, extended
     * with values from same-named sets across the activity when the set is small.
     *
     * @param stdClass $set
     * @return string[]
     */
    protected function value_pool(stdClass $set): array {
        global $DB;
        static $cache = [];
        if (isset($cache[$set->id])) {
            return $cache[$set->id];
        }
        $values = $DB->get_fieldset_select('vocabmastery_item', 'DISTINCT correctvalue', 'setid = :setid', ['setid' => $set->id]);
        if (count($values) < max(2, (int)$set->numoptions) * 2) {
            $sql = "SELECT DISTINCT i.correctvalue
                      FROM {vocabmastery_item} i
                      JOIN {vocabmastery_set} s ON s.id = i.setid
                     WHERE s.vocabmasteryid = :vid AND s.name = :name";
            $more = $DB->get_fieldset_sql($sql, ['vid' => $set->vocabmasteryid, 'name' => $set->name]);
            $values = array_merge($values, $more);
        }
        $values = array_values(array_unique(array_filter(array_map('trim', $values), fn($v) => $v !== '')));
        $cache[$set->id] = $values;
        return $values;
    }

    /**
     * Answer buttons for an item.
     *
     * @param stdClass $item
     * @param stdClass $set
     * @return string[]
     */
    public function options_for(stdClass $item, stdClass $set): array {
        if ($set->itemtype === 'typed') {
            return [];
        }
        if ($set->answermode === 'fixed') {
            $opts = self::fixed_options($set);
            $found = false;
            foreach ($opts as $o) {
                if (self::normalise($o) === self::normalise($item->correctvalue)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $opts[] = $item->correctvalue;
            }
            return $opts;
        }
        $n = max(2, min(6, (int)$set->numoptions));
        $pool = array_filter(
            $this->value_pool($set),
            fn($v) => self::normalise($v) !== self::normalise($item->correctvalue)
        );
        shuffle($pool);
        $opts = array_slice($pool, 0, $n - 1);
        $opts[] = $item->correctvalue;
        shuffle($opts);
        return array_values($opts);
    }

    /**
     * Learning-state label for an item row.
     *
     * @param stdClass $row
     * @return string new|learning|familiar|mastered|difficult
     */
    public static function state_label(stdClass $row): string {
        if (!$row->attempts) {
            return 'new';
        }
        if ($row->difficult) {
            return 'difficult';
        }
        if ($row->mastered) {
            return 'mastered';
        }
        return ($row->streak >= 1 && $row->correct >= 2) ? 'familiar' : 'learning';
    }

    /**
     * Selection weight: spend time where it matters.
     *
     * @param stdClass $row
     * @return float
     */
    public static function weight(stdClass $row): float {
        if (!$row->attempts) {
            return 2.0;
        }
        if ($row->difficult || $row->wrong >= 2) {
            return 5.0;
        }
        if ($row->wasdifficult) {
            return 4.0;
        }
        if ($row->wrong == 1 && !$row->mastered) {
            return 3.0;
        }
        if ($row->mastered) {
            return 0.5;
        }
        if ($row->correct == 1) {
            return 1.5;
        }
        return 1.0;
    }

    /**
     * Weighted random order (Efraimidis-Spirakis).
     *
     * @param array $rows
     * @return array
     */
    public static function weighted_order(array $rows): array {
        $keyed = [];
        foreach ($rows as $r) {
            $w = max(0.01, self::weight($r));
            $u = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
            $keyed[] = [pow($u, 1 / $w), $r];
        }
        usort($keyed, fn($a, $b) => $b[0] <=> $a[0]);
        return array_map(fn($k) => $k[1], $keyed);
    }

    /**
     * Check a topic can be used by this user.
     *
     * @param int $topicid
     */
    public function require_topic_access(int $topicid): void {
        $summary = $this->progress->get_summary($topicid);
        if (!$summary->unlocked && !$this->instance->bypass_locks()) {
            throw new \moodle_exception('topiclocked', 'mod_vocabmastery');
        }
    }

    /**
     * Start a session and return the attempt plus its questions.
     *
     * @param string $mode
     * @param int $topicid 0 for activity-wide (difficult words)
     * @param int $setid 0 for whole topic
     * @param int[] $itemids explicit item list (e.g. practise my mistakes)
     * @return array ['attempt' => stdClass, 'questions' => array, 'inject' => array]
     */
    public function start(string $mode, int $topicid, int $setid, array $itemids = []): array {
        global $DB;
        $vm = $this->instance->vm;
        if (!in_array($mode, self::MODES, true)) {
            throw new \invalid_parameter_exception('mode');
        }
        if (
            ($mode === 'blitz' && !$vm->enableblitz) || ($mode === 'streak' && !$vm->enablestreak)
                || ($mode === 'learn' && !$vm->enablelearn)
        ) {
            throw new \moodle_exception('modedisabled', 'mod_vocabmastery');
        }
        if ($setid) {
            $set = $this->instance->get_set($setid);
            $topicid = (int)$set->topicid;
        }
        if ($topicid) {
            $this->instance->get_topic($topicid);
            $this->require_topic_access($topicid);
            if (
                $vm->enablelearn && $vm->requirelearn && in_array($mode, ['practice', 'test', 'blitz', 'streak'])
                    && !$this->progress->get_summary($topicid)->learned && !$this->instance->bypass_locks()
            ) {
                throw new \moodle_exception('learnfirst', 'mod_vocabmastery');
            }
        } else if ($mode !== 'difficult') {
            throw new \invalid_parameter_exception('topicid');
        }

        $rows = $this->progress->get_item_rows();
        // Choose the item pool.
        if ($mode === 'test') {
            $pool = $this->test_items($topicid);
        } else if ($itemids) {
            $pool = array_filter($rows, fn($r) => in_array((int)$r->id, $itemids, true));
        } else if ($mode === 'difficult') {
            $pool = array_filter($rows, fn($r) => $r->difficult
                && (!$setid || (int)$r->setid === $setid) && (!$topicid || (int)$r->topicid === $topicid));
        } else if ($setid) {
            $pool = array_filter($rows, fn($r) => (int)$r->setid === $setid);
        } else {
            $pool = array_filter($rows, fn($r) => (int)$r->topicid === $topicid);
        }
        if ($mode !== 'difficult' && $topicid && !$itemids) {
            // Never leak items of locked topics into a topic session.
            $pool = array_filter($pool, fn($r) => (int)$r->topicid === $topicid);
        }
        if ($mode === 'difficult' && !$this->instance->bypass_locks()) {
            $summaries = $this->progress->get_summaries();
            $pool = array_filter($pool, fn($r) => !empty($summaries[(int)$r->topicid]->unlocked));
        }
        if ($itemids && !$this->instance->bypass_locks()) {
            $summaries = $this->progress->get_summaries();
            $pool = array_filter($pool, fn($r) => !empty($summaries[(int)$r->topicid]->unlocked));
        }
        if (!$pool) {
            throw new \moodle_exception('noitems', 'mod_vocabmastery');
        }

        if ($mode === 'learn') {
            $items = $DB->get_records_list('vocabmastery_item', 'id', array_keys($pool), 'sortorder, id');
            $ordered = array_values(array_filter(array_map(fn($i) => $pool[$i->id] ?? null, $items)));
        } else if ($mode === 'test') {
            $ordered = $pool; // Already shuffled.
        } else {
            $ordered = self::weighted_order($pool);
        }

        $attempt = (object)[
            'vocabmasteryid' => $vm->id,
            'userid' => $this->userid,
            'topicid' => $topicid,
            'setid' => $setid,
            'mode' => $mode,
            'itemids' => implode(',', array_map(fn($r) => (int)$r->id, $ordered)),
            'total' => 0,
            'correct' => 0,
            'beststreak' => 0,
            'score' => 0,
            'passed' => 0,
            'finished' => 0,
            'timestart' => time(),
            'timefinish' => 0,
        ];
        $attempt->id = $DB->insert_record('vocabmastery_attempt', $attempt);

        $questions = $this->export_questions($ordered, $mode === 'test');
        $inject = [];
        if ($mode === 'practice' && $vm->injectdifficult) {
            $inject = $this->injection_candidates($ordered);
        }
        return ['attempt' => $attempt, 'questions' => $questions, 'inject' => $inject];
    }

    /**
     * Items for a mastery check: all gated core items (or a sample), always
     * including anything missed in the previous failed check.
     *
     * @param int $topicid
     * @return array
     */
    protected function test_items(int $topicid): array {
        global $DB;
        $gated = $this->progress->gated_items($topicid);
        $length = (int)$this->instance->vm->testlength;
        if ($length <= 0 || $length >= count($gated)) {
            $list = array_values($gated);
            shuffle($list);
            return $list;
        }
        $missed = [];
        $last = $DB->get_records('vocabmastery_attempt', ['userid' => $this->userid, 'topicid' => $topicid,
            'mode' => 'test', 'finished' => 1], 'timefinish DESC', 'id, passed', 0, 1);
        if ($last && !reset($last)->passed) {
            $lastid = reset($last)->id;
            $missed = $DB->get_fieldset_select(
                'vocabmastery_response',
                'itemid',
                'attemptid = :attemptid AND correct = 0',
                ['attemptid' => $lastid]
            );
        }
        $missed = array_map('intval', $missed);
        $first = array_filter($gated, fn($r) => in_array((int)$r->id, $missed, true));
        $rest = array_filter($gated, fn($r) => !in_array((int)$r->id, $missed, true));
        $rest = array_values($rest);
        shuffle($rest);
        $list = array_merge(array_values($first), $rest);
        $list = array_slice($list, 0, $length);
        shuffle($list);
        return $list;
    }

    /**
     * Difficult items from elsewhere that share this session's answer buttons.
     *
     * @param array $ordered
     * @return array questions
     */
    protected function injection_candidates(array $ordered): array {
        $inthis = array_map(fn($r) => (int)$r->id, $ordered);
        $sets = $this->instance->get_all_sets();
        $signature = function ($set) {
            return $set->itemtype . '|' . $set->answermode . '|' .
                ($set->answermode === 'fixed' ? implode('/', self::fixed_options($set)) : $set->name);
        };
        $wanted = [];
        foreach ($ordered as $r) {
            $wanted[$signature($sets[$r->setid])] = true;
        }
        $summaries = $this->progress->get_summaries();
        $cands = array_filter($this->progress->get_item_rows(), function ($r) use (
            $inthis,
            $sets,
            $signature,
            $wanted,
            $summaries
) {
            return ($r->difficult || ($r->wasdifficult && !$r->mastered))
                && !in_array((int)$r->id, $inthis, true)
                && !empty($summaries[(int)$r->topicid]->unlocked)
                && isset($wanted[$signature($sets[$r->setid])]);
        });
        $cands = self::weighted_order($cands);
        return $this->export_questions(array_slice($cands, 0, 15), false);
    }

    /**
     * Turn item rows into question payloads.
     *
     * @param array $rows
     * @param bool $secure omit answers (mastery check)
     * @return array
     */
    public function export_questions(array $rows, bool $secure): array {
        global $DB;
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r->id, $rows);
        $items = $DB->get_records_list('vocabmastery_item', 'id', $ids);
        $sets = $this->instance->get_all_sets();
        $topics = $this->instance->get_topics();
        $hints = !$secure && $this->instance->vm->enablehints;
        $out = [];
        foreach ($rows as $r) {
            $item = $items[$r->id];
            $set = $sets[$item->setid];
            $meta = $item->metadata ? (json_decode($item->metadata, true) ?: []) : [];
            $out[] = [
                'itemid' => (int)$item->id,
                'setid' => (int)$set->id,
                'topicid' => (int)$set->topicid,
                'topicname' => format_string($topics[$set->topicid]->name ?? ''),
                'setname' => format_string($set->name),
                'instruction' => format_string((string)$set->instruction),
                'itemtype' => $set->itemtype,
                'fixedorder' => $set->answermode === 'fixed' ? 1 : 0,
                'prompt' => $item->prompt,
                'translation' => $set->showtranslation ? (string)$item->translation : '',
                'plural' => (string)$item->plural,
                'hint' => $hints ? (string)$item->hint : '',
                'explanation' => $secure ? '' : (string)$item->explanation,
                'note' => $secure ? '' : (string)($meta['note'] ?? ''),
                'iscore' => (int)$item->iscore,
                'options' => $this->options_for($item, $set),
                'correct' => $secure ? '' : $item->correctvalue,
                'alternatives' => $secure ? '' : (string)$item->altanswers,
                'state' => self::state_label($r),
            ];
        }
        return $out;
    }

    /**
     * Load and check an attempt belongs to the user and is open.
     *
     * @param int $attemptid
     * @return stdClass
     */
    public function get_open_attempt(int $attemptid): stdClass {
        global $DB;
        $attempt = $DB->get_record('vocabmastery_attempt', ['id' => $attemptid], '*', MUST_EXIST);
        if ((int)$attempt->userid !== $this->userid || (int)$attempt->vocabmasteryid !== (int)$this->instance->vm->id) {
            throw new \moodle_exception('invalidattempt', 'mod_vocabmastery');
        }
        if ($attempt->finished) {
            throw new \moodle_exception('attemptfinished', 'mod_vocabmastery');
        }
        return $attempt;
    }

    /**
     * Record one answer.
     *
     * @param int $attemptid
     * @param int $itemid
     * @param string $response
     * @param int $responsetime ms
     * @param bool $hinted
     * @return array
     */
    public function record(int $attemptid, int $itemid, string $response, int $responsetime, bool $hinted): array {
        global $DB;
        $attempt = $this->get_open_attempt($attemptid);
        $item = $DB->get_record(
            'vocabmastery_item',
            ['id' => $itemid, 'vocabmasteryid' => $this->instance->vm->id],
            '*',
            MUST_EXIST
        );
        $mode = $attempt->mode;
        $allowed = array_map('intval', explode(',', (string)$attempt->itemids));
        if ($mode === 'test') {
            if (!in_array($itemid, $allowed, true)) {
                throw new \moodle_exception('invaliditem', 'mod_vocabmastery');
            }
            if ($DB->record_exists('vocabmastery_response', ['attemptid' => $attemptid, 'itemid' => $itemid])) {
                return ['correct' => false, 'recorded' => false, 'state' => '', 'mastery' => -1];
            }
            $hinted = false;
        } else if (!in_array($itemid, $allowed, true)) {
            // Injected difficult items: must be from an unlocked topic of this activity.
            $set = $this->instance->get_set((int)$item->setid);
            $summary = $this->progress->get_summary((int)$set->topicid);
            if (!$summary->unlocked && !$this->instance->bypass_locks()) {
                throw new \moodle_exception('invaliditem', 'mod_vocabmastery');
            }
        }
        if ($mode === 'learn') {
            // Learn is self assessment: "I know this" / "need practice". Logged, never scored.
            $correct = $response === '1';
        } else {
            $correct = self::is_correct($item, $response);
        }
        $now = time();
        $DB->insert_record('vocabmastery_response', (object)[
            'attemptid' => $attemptid,
            'vocabmasteryid' => $this->instance->vm->id,
            'userid' => $this->userid,
            'itemid' => $itemid,
            'mode' => $mode,
            'response' => \core_text::substr($response, 0, 255),
            'correct' => $correct ? 1 : 0,
            'hinted' => $hinted ? 1 : 0,
            'responsetime' => max(0, min($responsetime, 3600000)),
            'timecreated' => $now,
        ]);
        $attempt->total++;
        if ($correct) {
            $attempt->correct++;
        }
        $DB->update_record('vocabmastery_attempt', (object)['id' => $attempt->id, 'total' => $attempt->total,
            'correct' => $attempt->correct]);

        $counts = in_array($mode, ['practice', 'difficult', 'test'], true)
            || (in_array($mode, ['blitz', 'streak'], true) && $this->instance->vm->blitzcountsmastery);
        $state = null;
        if ($counts) {
            $state = $this->update_item_state($itemid, $correct, $hinted);
        } else {
            $this->touch_item($itemid);
        }
        return [
            'correct' => $mode === 'test' ? false : $correct,
            'recorded' => true,
            'state' => $state ? self::state_label($state) : '',
            'mastery' => -1,
        ];
    }

    /**
     * Mark an item as seen without changing mastery.
     *
     * @param int $itemid
     */
    protected function touch_item(int $itemid): void {
        global $DB;
        $DB->set_field('vocabmastery_itemstate', 'lastseen', time(), ['userid' => $this->userid, 'itemid' => $itemid]);
    }

    /**
     * Apply one answer to the user's item state.
     *
     * Mastered = at least N correct AND the last M answers correct.
     * Difficult = 2+ wrong, or accuracy under 60% after 3 attempts, until mastered again.
     *
     * @param int $itemid
     * @param bool $correct
     * @param bool $hinted
     * @return stdClass
     */
    public function update_item_state(int $itemid, bool $correct, bool $hinted = false): stdClass {
        global $DB;
        $vm = $this->instance->vm;
        $st = $DB->get_record('vocabmastery_itemstate', ['userid' => $this->userid, 'itemid' => $itemid]);
        if (!$st) {
            $st = (object)['vocabmasteryid' => $vm->id, 'userid' => $this->userid, 'itemid' => $itemid,
                'attempts' => 0, 'correct' => 0, 'wrong' => 0, 'streak' => 0, 'mastered' => 0,
                'difficult' => 0, 'wasdifficult' => 0, 'lastseen' => 0];
            $st->id = $DB->insert_record('vocabmastery_itemstate', $st);
        }
        $st->attempts++;
        if ($correct && !$hinted) {
            $st->correct++;
            $st->streak++;
        } else if (!$correct) {
            $st->wrong++;
            $st->streak = 0;
        }
        $st->mastered = ($st->correct >= (int)$vm->itemcorrectrequired && $st->streak >= (int)$vm->itemstreakrequired) ? 1 : 0;
        if ($st->mastered) {
            $st->difficult = 0;
        } else if ($st->wrong >= 2 || ($st->attempts >= 3 && $st->correct / $st->attempts < 0.6)) {
            $st->difficult = 1;
            $st->wasdifficult = 1;
        }
        $st->lastseen = time();
        $DB->update_record('vocabmastery_itemstate', $st);
        return $st;
    }

    /**
     * Finish a session.
     *
     * @param int $attemptid
     * @param int $beststreak reported by the client (blitz/streak/practice)
     * @return array summary
     */
    public function finish(int $attemptid, int $beststreak = 0): array {
        global $DB;
        $attempt = $this->get_open_attempt($attemptid);
        $vm = $this->instance->vm;
        $mode = $attempt->mode;
        $topicid = (int)$attempt->topicid;

        $responses = $DB->get_records('vocabmastery_response', ['attemptid' => $attemptid], 'id');
        $total = count($responses);
        $correct = count(array_filter($responses, fn($r) => $r->correct));
        $questioncount = $total;
        if ($mode === 'test') {
            $questioncount = count(array_filter(explode(',', (string)$attempt->itemids)));
        }
        $score = $questioncount ? round($correct / $questioncount * 100, 2) : 0;
        $beststreak = max(0, min($beststreak, $total));
        if ($mode !== 'test') {
            // Compute the real longest run server side for blitz/streak.
            $run = 0;
            $best = 0;
            foreach ($responses as $r) {
                $run = $r->correct ? $run + 1 : 0;
                $best = max($best, $run);
            }
            $beststreak = $best;
        }

        $previousbest = 0;
        if (in_array($mode, ['blitz', 'streak'], true)) {
            $field = $mode === 'blitz' ? 'correct' : 'beststreak';
            $previousbest = (int)$DB->get_field_sql(
                "SELECT MAX($field) FROM {vocabmastery_attempt}
                WHERE userid = :userid AND vocabmasteryid = :vid AND mode = :mode AND setid = :setid
                   AND topicid = :topicid AND finished = 1",
                ['userid' => $this->userid, 'vid' => $vm->id, 'mode' => $mode, 'setid' => $attempt->setid,
                    'topicid' => $topicid]
            );
        }

        $passed = 0;
        $result = ['mastery' => -1, 'passed' => false, 'newlypassed' => false];
        if ($mode === 'test') {
            $passed = $score >= (float)$vm->masterythreshold ? 1 : 0;
            $result = $this->progress->recompute_topic($topicid, $score);
        } else if ($mode === 'learn') {
            $this->progress->mark_learned($topicid);
            $result = $this->progress->recompute_topic($topicid);
        } else if ($topicid) {
            $result = $this->progress->recompute_topic($topicid);
        } else {
            // Activity-wide difficult session: recompute touched topics.
            $touched = $DB->get_fieldset_sql("SELECT DISTINCT s.topicid
                  FROM {vocabmastery_response} r
                  JOIN {vocabmastery_item} i ON i.id = r.itemid
                  JOIN {vocabmastery_set} s ON s.id = i.setid
                 WHERE r.attemptid = :attemptid", ['attemptid' => $attemptid]);
            foreach ($touched as $tid) {
                $r = $this->progress->recompute_topic((int)$tid);
                $result['newlypassed'] = $result['newlypassed'] || $r['newlypassed'];
            }
        }

        $DB->update_record('vocabmastery_attempt', (object)[
            'id' => $attemptid, 'total' => $total, 'correct' => $correct, 'beststreak' => $beststreak,
            'score' => $score, 'passed' => $passed, 'finished' => 1, 'timefinish' => time(),
        ]);

        $event = event\session_completed::create([
            'objectid' => $attemptid,
            'context' => $this->instance->context,
            'other' => ['mode' => $mode, 'score' => $score, 'topicid' => $topicid],
        ]);
        $event->trigger();

        // Mistakes list.
        $wrongids = array_unique(array_map(
            fn($r) => (int)$r->itemid,
            array_filter($responses, fn($r) => !$r->correct)
        ));
        if ($mode === 'test') {
            $answered = array_map(fn($r) => (int)$r->itemid, $responses);
            foreach (array_filter(array_map('intval', explode(',', (string)$attempt->itemids))) as $id) {
                if (!in_array($id, $answered, true)) {
                    $wrongids[] = $id;
                }
            }
        }
        $mistakes = [];
        if ($wrongids && $mode !== 'learn') {
            $items = $DB->get_records_list('vocabmastery_item', 'id', $wrongids);
            foreach ($items as $it) {
                $mistakes[] = ['itemid' => (int)$it->id, 'prompt' => $it->prompt, 'correct' => $it->correctvalue,
                    'translation' => (string)$it->translation];
            }
        }

        $routes = [];
        $topics = $this->instance->get_topics();
        if ($topicid && ($result['passed'] ?? false)) {
            $this->progress->reset();
            foreach ($this->progress->routes_after($topicid) as $route) {
                $t = $topics[$route['topicid']];
                $level = $this->instance->get_level((int)$t->levelid);
                $routes[] = [
                    'type' => $route['type'],
                    'topicid' => $route['topicid'],
                    'name' => format_string($t->name),
                    'level' => format_string($level->name),
                    'url' => $this->instance->url('view.php', ['topic' => $route['topicid']])->out(false),
                ];
            }
        }

        $isbest = in_array($mode, ['blitz', 'streak'], true)
            && ($mode === 'blitz' ? $correct : $beststreak) > $previousbest && $total > 0;

        return [
            'mode' => $mode,
            'total' => $total,
            'questioncount' => $questioncount,
            'correct' => $correct,
            'score' => $score,
            'beststreak' => $beststreak,
            'personalbest' => $isbest,
            'previousbest' => $previousbest,
            'passed' => (bool)$passed,
            'topicpassed' => (bool)($result['passed'] ?? false),
            'newlypassed' => (bool)($result['newlypassed'] ?? false),
            'mastery' => (float)($result['mastery'] ?? -1),
            'threshold' => (int)$vm->masterythreshold,
            'mistakes' => $mistakes,
            'routes' => $routes,
        ];
    }
}
