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
 * Teacher reporting queries.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report {
    /** @var instance */
    protected instance $instance;

    /**
     * Constructor.
     *
     * @param instance $instance
     */
    public function __construct(instance $instance) {
        $this->instance = $instance;
    }

    /**
     * Students (users who can attempt), optionally in a group.
     *
     * @param int $groupid
     * @return stdClass[]
     */
    public function students(int $groupid = 0): array {
        $fields = 'u.id, ' . implode(', ', \core_user\fields::get_name_fields(true)) . ', u.email, u.picture, u.imagealt';
        $users = get_enrolled_users(
            $this->instance->context,
            'mod/vocabmastery:attempt',
            $groupid,
            $fields,
            'u.lastname, u.firstname',
            0,
            0,
            true
        );
        return $users;
    }

    /**
     * Topic states for users.
     *
     * @param array $userids
     * @return array userid => topicid => record
     */
    public function topic_states(array $userids): array {
        global $DB;
        $out = [];
        if (!$userids) {
            return $out;
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['vid'] = $this->instance->vm->id;
        $rs = $DB->get_recordset_select('vocabmastery_topicstate', "vocabmasteryid = :vid AND userid $in", $params);
        foreach ($rs as $r) {
            $out[(int)$r->userid][(int)$r->topicid] = $r;
        }
        $rs->close();
        return $out;
    }

    /**
     * Answer statistics per user (excluding self-rated Learn cards).
     *
     * @param array $userids
     * @return array userid => {total, correct, lastseen, difficult}
     */
    public function answer_stats(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['vid'] = $this->instance->vm->id;
        $params['learn'] = 'learn';
        $sql = "SELECT userid, COUNT(1) AS total, SUM(correct) AS correct, MAX(timecreated) AS lastseen
                  FROM {vocabmastery_response}
                 WHERE vocabmasteryid = :vid AND userid $in AND mode <> :learn
              GROUP BY userid";
        $stats = $DB->get_records_sql($sql, $params);
        $params2 = $params;
        unset($params2['learn']);
        $difficult = $DB->get_records_sql_menu("SELECT userid, COUNT(1) FROM {vocabmastery_itemstate}
            WHERE vocabmasteryid = :vid AND userid $in AND difficult = 1 GROUP BY userid", $params2);
        $last = $DB->get_records_sql_menu("SELECT userid, MAX(timestart) FROM {vocabmastery_attempt}
            WHERE vocabmasteryid = :vid AND userid $in GROUP BY userid", $params2);
        $out = [];
        foreach ($userids as $uid) {
            $s = $stats[$uid] ?? null;
            $out[$uid] = (object)[
                'total' => $s ? (int)$s->total : 0,
                'correct' => $s ? (int)$s->correct : 0,
                'lastseen' => max($s ? (int)$s->lastseen : 0, (int)($last[$uid] ?? 0)),
                'difficult' => (int)($difficult[$uid] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Overview rows.
     *
     * @param array $students
     * @return array
     */
    public function overview(array $students): array {
        $userids = array_keys($students);
        $states = $this->topic_states($userids);
        $stats = $this->answer_stats($userids);
        $topics = $this->instance->get_topics();
        $n = count($topics);
        $rows = [];
        foreach ($students as $uid => $u) {
            $sum = 0;
            $passed = 0;
            foreach ($topics as $t) {
                $st = $states[$uid][(int)$t->id] ?? null;
                if ($st && $st->passed) {
                    $passed++;
                    $sum += 100;
                } else if ($st) {
                    $sum += min(99, (float)$st->mastery);
                }
            }
            $s = $stats[$uid];
            $rows[$uid] = (object)[
                'user' => $u,
                'progress' => $n ? round($sum / $n) : 0,
                'passed' => $passed,
                'topics' => $n,
                'accuracy' => $s->total ? round($s->correct / $s->total * 100) : null,
                'answers' => $s->total,
                'difficult' => $s->difficult,
                'lastseen' => $s->lastseen,
            ];
        }
        return $rows;
    }

    /**
     * Heatmap cell classification.
     *
     * @param stdClass|null $state topicstate
     * @param float|null $accuracy topic accuracy for that user
     * @return array [class, label]
     */
    public static function heat(?stdClass $state, ?float $accuracy): array {
        if (!$state) {
            return ['none', '–'];
        }
        if ($state->passed) {
            return ['passed', '✓'];
        }
        $m = (int)round((float)$state->mastery);
        if ($accuracy !== null && $accuracy < 60) {
            return ['struggling', (string)$m];
        }
        return ['active', (string)$m];
    }

    /**
     * Per user per topic accuracy.
     *
     * @param array $userids
     * @return array userid => topicid => percent
     */
    public function topic_accuracy(array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['vid'] = $this->instance->vm->id;
        $params['learn'] = 'learn';
        $sql = "SELECT " . $DB->sql_concat('r.userid', "'_'", 's.topicid') . " AS k, r.userid, s.topicid,
                       COUNT(1) AS total, SUM(r.correct) AS correct
                  FROM {vocabmastery_response} r
                  JOIN {vocabmastery_item} i ON i.id = r.itemid
                  JOIN {vocabmastery_set} s ON s.id = i.setid
                 WHERE r.vocabmasteryid = :vid AND r.userid $in AND r.mode <> :learn
              GROUP BY r.userid, s.topicid";
        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $out[(int)$r->userid][(int)$r->topicid] = $r->total ? $r->correct / $r->total * 100 : null;
        }
        return $out;
    }

    /**
     * Most difficult items across the given users.
     *
     * @param array $userids
     * @param int $limit
     * @return array
     */
    public function difficult_words(array $userids, int $limit = 50): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['vid'] = $this->instance->vm->id;
        $params['learn'] = 'learn';
        $sql = "SELECT r.itemid, COUNT(1) AS total, SUM(1 - r.correct) AS wrong, COUNT(DISTINCT r.userid) AS users
                  FROM {vocabmastery_response} r
                 WHERE r.vocabmasteryid = :vid AND r.userid $in AND r.mode <> :learn
              GROUP BY r.itemid
                HAVING COUNT(1) >= 3 AND SUM(1 - r.correct) > 0";
        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $r) {
            $r->wrongrate = $r->total ? $r->wrong / $r->total * 100 : 0;
        }
        uasort($rows, fn($a, $b) => [$b->wrongrate, $b->total] <=> [$a->wrongrate, $a->total]);
        $rows = array_slice($rows, 0, $limit, true);
        if (!$rows) {
            return [];
        }
        $items = $DB->get_records_list('vocabmastery_item', 'id', array_keys($rows));
        $struggling = [];
        [$in2, $p2] = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED, 'it');
        [$in3, $p3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'us');
        $struggling = $DB->get_records_sql_menu("SELECT itemid, COUNT(1) FROM {vocabmastery_itemstate}
            WHERE itemid $in2 AND userid $in3 AND difficult = 1 GROUP BY itemid", $p2 + $p3);
        $sets = $this->instance->get_all_sets();
        $topics = $this->instance->get_topics();
        $out = [];
        foreach ($rows as $itemid => $r) {
            if (!isset($items[$itemid])) {
                continue;
            }
            $it = $items[$itemid];
            $set = $sets[$it->setid] ?? null;
            $topic = $set ? ($topics[$set->topicid] ?? null) : null;
            $out[] = (object)[
                'item' => $it,
                'set' => $set,
                'topic' => $topic,
                'total' => (int)$r->total,
                'wrong' => (int)$r->wrong,
                'users' => (int)$r->users,
                'wrongrate' => round($r->wrongrate),
                'struggling' => (int)($struggling[$itemid] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Recent sessions of one user.
     *
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public function sessions(int $userid, int $limit = 25): array {
        global $DB;
        return $DB->get_records_select(
            'vocabmastery_attempt',
            'vocabmasteryid = :vid AND userid = :userid AND (total > 0 OR finished = 1)',
            ['vid' => $this->instance->vm->id, 'userid' => $userid],
            'timestart DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Delete one user's progress.
     *
     * @param int $userid
     */
    public function reset_user(int $userid): void {
        global $DB;
        $vid = $this->instance->vm->id;
        foreach (['response', 'attempt', 'itemstate', 'topicstate'] as $t) {
            $DB->delete_records('vocabmastery_' . $t, ['vocabmasteryid' => $vid, 'userid' => $userid]);
        }
        (new progress($this->instance, $userid))->after_change();
    }
}
