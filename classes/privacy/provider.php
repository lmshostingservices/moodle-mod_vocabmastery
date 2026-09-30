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

namespace mod_vocabmastery\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** User data tables. */
    const TABLES = ['vocabmastery_response', 'vocabmastery_attempt', 'vocabmastery_itemstate', 'vocabmastery_topicstate',
        'vocabmastery_flag'];

    /**
     * Metadata.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('vocabmastery_attempt', [
            'userid' => 'privacy:metadata:userid', 'mode' => 'privacy:metadata:attempt:mode',
            'total' => 'privacy:metadata:attempt:total', 'correct' => 'privacy:metadata:attempt:correct',
            'score' => 'privacy:metadata:attempt:score', 'timestart' => 'privacy:metadata:timestart',
            'itemids' => 'privacy:metadata:attempt:itemids', 'beststreak' => 'privacy:metadata:attempt:beststreak',
            'passed' => 'privacy:metadata:attempt:passed', 'timefinish' => 'privacy:metadata:attempt:timefinish',
        ], 'privacy:metadata:attempt');
        $collection->add_database_table('vocabmastery_response', [
            'userid' => 'privacy:metadata:userid', 'response' => 'privacy:metadata:response:response',
            'correct' => 'privacy:metadata:response:correct', 'responsetime' => 'privacy:metadata:response:responsetime',
            'hinted' => 'privacy:metadata:response:hinted', 'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:response');
        $collection->add_database_table('vocabmastery_itemstate', [
            'userid' => 'privacy:metadata:userid', 'attempts' => 'privacy:metadata:itemstate:attempts',
            'mastered' => 'privacy:metadata:itemstate:mastered', 'difficult' => 'privacy:metadata:itemstate:difficult',
            'correct' => 'privacy:metadata:itemstate:correct', 'wrong' => 'privacy:metadata:itemstate:wrong',
            'streak' => 'privacy:metadata:itemstate:streak', 'lastseen' => 'privacy:metadata:itemstate:lastseen',
        ], 'privacy:metadata:itemstate');
        $collection->add_database_table('vocabmastery_topicstate', [
            'userid' => 'privacy:metadata:userid', 'mastery' => 'privacy:metadata:topicstate:mastery',
            'besttest' => 'privacy:metadata:topicstate:besttest', 'passed' => 'privacy:metadata:topicstate:passed',
            'learned' => 'privacy:metadata:topicstate:learned', 'timepassed' => 'privacy:metadata:topicstate:timepassed',
        ], 'privacy:metadata:topicstate');
        $collection->add_database_table('vocabmastery_flag', [
            'userid' => 'privacy:metadata:userid', 'reason' => 'privacy:metadata:flag:reason',
            'commenttext' => 'privacy:metadata:flag:commenttext', 'timecreated' => 'privacy:metadata:timecreated',
            'resolved' => 'privacy:metadata:flag:resolved',
        ], 'privacy:metadata:flag');
        return $collection;
    }

    /**
     * Contexts with user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {vocabmastery_topicstate} ts ON ts.vocabmasteryid = cm.instance
                 WHERE ts.userid = :userid
                 UNION
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel2
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname2
                  JOIN {vocabmastery_attempt} a ON a.vocabmasteryid = cm.instance
                 WHERE a.userid = :userid2
                 UNION
                SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel3
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname3
                  JOIN {vocabmastery_flag} f ON f.vocabmasteryid = cm.instance
                 WHERE f.userid = :userid3";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'modname' => 'vocabmastery', 'userid' => $userid,
            'ctxlevel2' => CONTEXT_MODULE, 'modname2' => 'vocabmastery', 'userid2' => $userid,
            'ctxlevel3' => CONTEXT_MODULE, 'modname3' => 'vocabmastery', 'userid3' => $userid]);
        return $contextlist;
    }

    /**
     * Users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'vocabmastery'];
        foreach (['vocabmastery_attempt', 'vocabmastery_topicstate', 'vocabmastery_flag'] as $table) {
            $sql = "SELECT t.userid
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {{$table}} t ON t.vocabmasteryid = cm.instance
                     WHERE cm.id = :cmid";
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('vocabmastery', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $vid = $cm->instance;
            $data = helper::get_context_data($context, $contextlist->get_user());
            helper::export_context_files($context, $contextlist->get_user());

            $topics = $DB->get_records_menu('vocabmastery_topic', ['vocabmasteryid' => $vid], '', 'id, name');
            $data->topics = [];
            foreach ($DB->get_records('vocabmastery_topicstate', ['vocabmasteryid' => $vid, 'userid' => $userid]) as $t) {
                $data->topics[] = [
                    'topic' => $topics[$t->topicid] ?? $t->topicid,
                    'mastery' => (float)$t->mastery,
                    'bestmasterycheck' => (float)$t->besttest,
                    'passed' => transform::yesno($t->passed),
                    'timepassed' => $t->timepassed ? transform::datetime($t->timepassed) : '',
                ];
            }
            $data->sessions = [];
            foreach ($DB->get_records('vocabmastery_attempt', ['vocabmasteryid' => $vid, 'userid' => $userid], 'timestart') as $a) {
                $data->sessions[] = [
                    'mode' => $a->mode, 'total' => (int)$a->total, 'correct' => (int)$a->correct,
                    'score' => (float)$a->score, 'passed' => transform::yesno($a->passed),
                    'timestart' => transform::datetime($a->timestart),
                ];
            }
            $sql = "SELECT st.*, i.prompt, i.correctvalue
                      FROM {vocabmastery_itemstate} st
                      JOIN {vocabmastery_item} i ON i.id = st.itemid
                     WHERE st.vocabmasteryid = :vid AND st.userid = :userid";
            $data->items = [];
            foreach ($DB->get_records_sql($sql, ['vid' => $vid, 'userid' => $userid]) as $s) {
                $data->items[] = [
                    'item' => $s->prompt . ' (' . $s->correctvalue . ')', 'attempts' => (int)$s->attempts,
                    'correct' => (int)$s->correct, 'wrong' => (int)$s->wrong,
                    'mastered' => transform::yesno($s->mastered), 'difficult' => transform::yesno($s->difficult),
                ];
            }
            $sql = "SELECT r.*, i.prompt
                      FROM {vocabmastery_response} r
                      JOIN {vocabmastery_item} i ON i.id = r.itemid
                     WHERE r.vocabmasteryid = :vid AND r.userid = :userid
                  ORDER BY r.id";
            $data->responses = [];
            $rs = $DB->get_recordset_sql($sql, ['vid' => $vid, 'userid' => $userid]);
            foreach ($rs as $r) {
                $data->responses[] = [
                    'item' => $r->prompt, 'mode' => $r->mode, 'response' => $r->response,
                    'correct' => transform::yesno($r->correct), 'responsetimems' => (int)$r->responsetime,
                    'time' => transform::datetime($r->timecreated),
                ];
            }
            $rs->close();
            $sql = "SELECT f.*, i.prompt
                      FROM {vocabmastery_flag} f
                      JOIN {vocabmastery_item} i ON i.id = f.itemid
                     WHERE f.vocabmasteryid = :vid AND f.userid = :userid
                  ORDER BY f.id";
            $data->reports = [];
            foreach ($DB->get_records_sql($sql, ['vid' => $vid, 'userid' => $userid]) as $f) {
                $data->reports[] = [
                    'item' => $f->prompt, 'reason' => $f->reason, 'comment' => (string)$f->commenttext,
                    'resolved' => transform::yesno($f->resolved), 'time' => transform::datetime($f->timecreated),
                ];
            }
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Delete for all users in context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('vocabmastery', $context->instanceid);
        if (!$cm) {
            return;
        }
        foreach (self::TABLES as $t) {
            $DB->delete_records($t, ['vocabmasteryid' => $cm->instance]);
        }
    }

    /**
     * Delete for one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('vocabmastery', $context->instanceid);
            if (!$cm) {
                continue;
            }
            foreach (self::TABLES as $t) {
                $DB->delete_records($t, ['vocabmasteryid' => $cm->instance, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete for several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('vocabmastery', $context->instanceid);
        if (!$cm || !$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['vid'] = $cm->instance;
        foreach (self::TABLES as $t) {
            $DB->delete_records_select($t, "vocabmasteryid = :vid AND userid $in", $params);
        }
    }
}
