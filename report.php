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

/**
 * Teacher reports: overview, heatmap, difficult words, student detail.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_vocabmastery\instance;
use mod_vocabmastery\progress;
use mod_vocabmastery\report;

$id = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
$userid = optional_param('user', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$instance = instance::from_cmid($id);
require_login($instance->course, false, $instance->cm);
require_capability('mod/vocabmastery:viewallprogress', $instance->context);
$c = 'mod_vocabmastery';
$context = $instance->context;

$url = $instance->url('report.php', ['tab' => $tab] + ($userid ? ['user' => $userid] : []));
$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->vm->name) . ': ' . get_string('reports', $c));
$PAGE->set_heading(format_string($instance->course->fullname));
$PAGE->activityheader->disable();

$report = new report($instance);
$groupmode = groups_get_activity_groupmode($instance->cm);
$groupid = $groupmode ? (int)groups_get_activity_group($instance->cm, true) : 0;
$students = $report->students($groupid);
$topics = $instance->get_topics();
$levels = $instance->get_levels();

$fullname = fn($u) => fullname($u, has_capability('moodle/site:viewfullnames', $context));
$date = fn($t) => $t ? userdate($t, get_string('strftimedatetimeshort', 'langconfig')) : '–';

// Downloads.
if ($download) {
    require_capability('mod/vocabmastery:exportresults', $context);
    require_sesskey();
    $rows = $report->overview($students);
    $states = $report->topic_states(array_keys($students));
    $columns = [get_string('student', $c), get_string('email'), get_string('progress', $c),
        get_string('topicspassed', $c), get_string('topics', $c), get_string('accuracy', $c), get_string('answers', $c),
        get_string('difficultitems', $c), get_string('lastpractice', $c)];
    foreach ($topics as $t) {
        $columns[] = format_string($levels[$t->levelid]->name) . ' · ' . format_string($t->name);
    }
    $data = [];
    foreach ($rows as $uid => $r) {
        $line = [$fullname($r->user), $r->user->email, $r->progress, $r->passed, $r->topics,
            $r->accuracy ?? '', $r->answers, $r->difficult, $r->lastseen ? userdate($r->lastseen, '%Y-%m-%d %H:%M') : ''];
        foreach ($topics as $t) {
            $st = $states[$uid][(int)$t->id] ?? null;
            $line[] = $st ? ($st->passed ? get_string('status_passed', $c) : round((float)$st->mastery) . '%') : '';
        }
        $data[] = $line;
    }
    \core\dataformat::download_data(
        clean_filename($instance->vm->name . '-results'),
        $download,
        $columns,
        new ArrayIterator($data)
    );
    die;
}

// Reset one student.
if ($userid && optional_param('reset', 0, PARAM_BOOL)) {
    require_capability('mod/vocabmastery:resetprogress', $context);
    $u = core_user::get_user($userid, '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $report->reset_user($userid);
        redirect(
            $instance->url('report.php', ['user' => $userid]),
            get_string('progressreset', $c, $fullname($u)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmreset', $c, $fullname($u)),
        new moodle_url($url, ['reset' => 1, 'confirm' => 1, 'sesskey' => sesskey()]),
        $instance->url(
            'report.php',
            ['user' => $userid]
        )
    );
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->vm->name) . ': ' . get_string('reports', $c));

$tabs = [];
foreach (['overview', 'heatmap', 'words'] as $t) {
    $tabs[] = new tabobject($t, $instance->url('report.php', ['tab' => $t]), get_string('report_' . $t, $c));
}
echo $OUTPUT->tabtree($tabs, $userid ? '' : $tab);
if ($groupmode) {
    groups_print_activity_menu($instance->cm, $instance->url('report.php', ['tab' => $tab]));
}

// Student detail.
if ($userid) {
    if (
        !isset($students[$userid]) && !$DB->record_exists('vocabmastery_topicstate', ['userid' => $userid,
            'vocabmasteryid' => $instance->vm->id])
    ) {
        throw new moodle_exception('invaliduser');
    }
    $u = core_user::get_user($userid, '*', MUST_EXIST);
    $progress = new progress($instance, $userid);
    $summaries = $progress->get_summaries();
    $leveldata = [];
    foreach ($levels as $level) {
        $ts = array_filter($summaries, fn($s) => $s->levelid === (int)$level->id);
        $p = count(array_filter($ts, fn($s) => $s->passed));
        $avg = $ts ? round(array_sum(array_map(fn($s) => $s->passed ? 100 : min(99, $s->mastery), $ts)) / count($ts)) : 0;
        $leveldata[] = ['name' => format_string($level->name),
            'status' => ($ts && $p === count($ts)) ? get_string('complete', $c) : $avg . '%',
            'passed' => $p . ' / ' . count($ts)];
    }
    $accuracy = $report->topic_accuracy([$userid])[$userid] ?? [];
    $topicdata = [];
    foreach ($topics as $t) {
        $s = $summaries[(int)$t->id];
        $topicdata[] = [
            'level' => format_string($levels[$t->levelid]->name),
            'name' => format_string($t->name),
            'mastery' => round($s->mastery) . '% (' . $s->mastered . '/' . $s->corecount . ')',
            'besttest' => $s->besttest >= 0 ? round($s->besttest) . '%' : '–',
            'accuracy' => isset($accuracy[(int)$t->id]) ? round($accuracy[(int)$t->id]) . '%' : '–',
            'status' => get_string('status_' . progress::status($s), $c),
        ];
    }
    $difficult = array_map(fn($r) => ['prompt' => $r->prompt, 'correct' => $r->correctvalue,
        'ratio' => $r->wrong . ' / ' . $r->attempts], $progress->difficult_items(null, 50));
    $sessions = [];
    foreach ($report->sessions($userid) as $a) {
        $sessions[] = [
            'date' => $date($a->timestart),
            'mode' => $a->mode === 'test' ? get_string('masterycheck', $c) : get_string('mode_' . $a->mode, $c),
            'topic' => $a->topicid && isset($topics[$a->topicid]) ? format_string($topics[$a->topicid]->name) : '–',
            'answers' => $a->correct . ' / ' . $a->total,
            'score' => $a->finished ? round((float)$a->score) . '%' . ($a->mode === 'test' ? ($a->passed ? ' ✓' : ' ✕') : '')
                : get_string('unfinished', $c),
        ];
    }
    echo $OUTPUT->render_from_template('mod_vocabmastery/report_student', [
        'picture' => $OUTPUT->user_picture($u, ['size' => 35]),
        'name' => $fullname($u),
        'overall' => round($progress->overall_percent()),
        'passedsummary' => get_string('topicspassedof', $c, (object)['passed' => $progress->topics_passed(),
            'total' => count($summaries)]),
        'levels' => $leveldata,
        'topics' => $topicdata,
        'hasdifficult' => (bool)$difficult,
        'difficult' => $difficult,
        'hassessions' => (bool)$sessions,
        'sessions' => $sessions,
        'reseturl' => has_capability('mod/vocabmastery:resetprogress', $context)
            ? (new moodle_url($url, ['reset' => 1]))->out(false) : null,
    ]);
    echo $OUTPUT->footer();
    die;
}

if (!$students) {
    echo $OUTPUT->notification(get_string('nostudents', $c), 'info');
    echo $OUTPUT->footer();
    die;
}

// Heatmap.
if ($tab === 'heatmap') {
    $userids = array_keys($students);
    $states = $report->topic_states($userids);
    $acc = $report->topic_accuracy($userids);
    $rows = [];
    foreach ($students as $uid => $u) {
        $cells = [];
        foreach ($topics as $t) {
            [$cls, $label] = report::heat($states[$uid][(int)$t->id] ?? null, $acc[$uid][(int)$t->id] ?? null);
            $cells[] = ['cls' => $cls, 'label' => $label,
                'title' => format_string($t->name) . ': ' . get_string('heat_' . $cls, $c)];
        }
        $rows[] = ['url' => $instance->url('report.php', ['user' => $uid])->out(false), 'name' => $fullname($u),
            'cells' => $cells];
    }
    echo $OUTPUT->render_from_template('mod_vocabmastery/report_heatmap', [
        'topics' => array_values(array_map(fn($t) => ['label' => format_string($levels[$t->levelid]->name) . ' · ' .
            format_string($t->name)], $topics)),
        'rows' => $rows,
    ]);
    echo $OUTPUT->footer();
    die;
}

// Class difficult words.
if ($tab === 'words') {
    $words = [];
    foreach ($report->difficult_words(array_keys($students)) as $w) {
        $words[] = [
            'display' => ($w->set && $w->set->answermode === 'fixed') ? $w->item->correctvalue . ' ' . $w->item->prompt
                : $w->item->prompt,
            'translation' => (string)$w->item->translation,
            'correct' => $w->item->correctvalue,
            'topic' => $w->topic ? format_string($levels[$w->topic->levelid]->name) . ' · ' . format_string($w->topic->name) : '',
            'wrongrate' => $w->wrongrate,
            'wrong' => $w->wrong . ' / ' . $w->total,
            'struggling' => $w->struggling . ' / ' . $w->users,
        ];
    }
    echo $OUTPUT->render_from_template('mod_vocabmastery/report_words', ['haswords' => (bool)$words, 'words' => $words]);
    echo $OUTPUT->footer();
    die;
}

// Overview.
if (has_capability('mod/vocabmastery:exportresults', $context)) {
    echo $OUTPUT->download_dataformat_selector(
        get_string('downloadresults', $c),
        $instance->url('report.php')->out_omit_querystring(),
        'download',
        ['id' => $id, 'sesskey' => sesskey()]
    );
}
$rows = [];
foreach ($report->overview($students) as $uid => $r) {
    $rows[] = [
        'url' => $instance->url('report.php', ['user' => $uid])->out(false),
        'name' => $fullname($r->user),
        'progress' => (int)$r->progress,
        'passed' => $r->passed . ' / ' . $r->topics,
        'accuracy' => $r->accuracy !== null ? $r->accuracy . '%' : '–',
        'answers' => $r->answers,
        'difficult' => $r->difficult,
        'lastseen' => $date($r->lastseen),
    ];
}
echo $OUTPUT->render_from_template('mod_vocabmastery/report_overview', ['rows' => $rows]);
echo $OUTPUT->footer();
