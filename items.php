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
 * Bulk item editor for one set.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_vocabmastery\content;
use mod_vocabmastery\instance;

$id = required_param('id', PARAM_INT);
$setid = required_param('setid', PARAM_INT);

$instance = instance::from_cmid($id);
require_login($instance->course, false, $instance->cm);
require_capability('mod/vocabmastery:managecontent', $instance->context);
$set = $instance->get_set($setid);
$topic = $instance->get_topic((int)$set->topicid);
$level = $instance->get_level((int)$topic->levelid);
$c = 'mod_vocabmastery';
$vmid = (int)$instance->vm->id;

$url = $instance->url('items.php', ['setid' => $setid]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($set->name));
$PAGE->set_heading(format_string($instance->course->fullname));
$PAGE->activityheader->disable();

$fields = ['correctvalue', 'prompt', 'translation', 'plural', 'altanswers', 'hint'];

$resolveflag = optional_param('resolveflag', 0, PARAM_INT);
if ($resolveflag && data_submitted() && confirm_sesskey()) {
    $flag = $DB->get_record('vocabmastery_flag', ['id' => $resolveflag, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
    $DB->update_record('vocabmastery_flag', (object)['id' => $flag->id, 'resolved' => 1, 'timemodified' => time()]);
    redirect(
        new moodle_url($url, [], 'vm-reports'),
        get_string('reports_resolved', $c),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if (($submitted = data_submitted()) && confirm_sesskey()) {
    $existing = $instance->get_set_items($setid);
    $posted = json_decode(json_encode($submitted->items ?? []), true) ?: [];
    $selected = array_map('intval', array_keys(optional_param_array('sel', [], PARAM_BOOL)));
    $selected = array_values(array_filter($selected, fn($i) => isset($existing[$i])));
    $bulk = optional_param('bulk', '', PARAM_ALPHA);
    $message = get_string('changessaved');

    $transaction = $DB->start_delegated_transaction();
    // Save edits to existing rows.
    $sort = 0;
    foreach ($existing as $item) {
        $sort++;
        if (!isset($posted[$item->id]) || !is_array($posted[$item->id])) {
            continue;
        }
        $row = $posted[$item->id];
        $rec = (object)['id' => $item->id];
        foreach ($fields as $f) {
            $rec->$f = clean_param($row[$f] ?? '', PARAM_TEXT);
        }
        $rec->iscore = !empty($row['iscore']) ? 1 : 0;
        $rec = content::clean_item($rec);
        if ($rec->prompt === '' || $rec->correctvalue === '') {
            continue; // Required fields cleared: keep the old values.
        }
        $changed = false;
        foreach (array_merge($fields, ['iscore']) as $f) {
            if ((string)$rec->$f !== (string)$item->$f) {
                $changed = true;
            }
        }
        if ($changed) {
            $rec->timemodified = time();
            $DB->update_record('vocabmastery_item', $rec);
        }
    }
    // New rows.
    $new = json_decode(json_encode($submitted->new ?? []), true) ?: [];
    $added = 0;
    if (is_array($new)) {
        foreach ($new as $row) {
            if (!is_array($row)) {
                continue;
            }
            $data = [];
            foreach ($fields as $f) {
                $data[$f] = clean_param($row[$f] ?? '', PARAM_TEXT);
            }
            $data['iscore'] = !empty($row['iscore']) ? 1 : 0;
            if (trim($data['prompt']) === '' || trim($data['correctvalue']) === '') {
                continue;
            }
            content::add_item($vmid, $setid, $data);
            $added++;
        }
    }
    // Bulk actions.
    if ($selected && $bulk === 'delete') {
        content::delete_items($selected);
        $message = get_string('itemsdeleted', $c, count($selected));
    } else if ($selected && ($bulk === 'core' || $bulk === 'optional')) {
        [$in, $params] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
        $DB->set_field_select('vocabmastery_item', 'iscore', $bulk === 'core' ? 1 : 0, "id $in", $params);
    } else if ($selected && $bulk === 'move') {
        $target = required_param('targetset', PARAM_INT);
        $instance->get_set($target);
        [$in, $params] = $DB->get_in_or_equal($selected, SQL_PARAMS_NAMED);
        $DB->set_field_select('vocabmastery_item', 'setid', $target, "id $in", $params);
        $message = get_string('itemsmoved', $c, count($selected));
    } else if ($selected && $bulk === 'duplicate') {
        foreach ($selected as $iid) {
            $orig = (array)$existing[$iid];
            $orig['metadata'] = $orig['metadata'] ? json_decode($orig['metadata'], true) : null;
            unset($orig['sortorder']);
            content::add_item($vmid, $setid, $orig);
        }
    }
    $transaction->allow_commit();
    content::queue_recompute($vmid);
    if ($added) {
        $message .= ' ' . get_string('itemsadded', $c, $added);
    }
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$items = $instance->get_set_items($setid);
$PAGE->requires->js_call_amd('mod_vocabmastery/items', 'init');

// Open problem reports from learners for words in this set.
$reports = [];
$flagged = [];
if ($items) {
    [$in, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED);
    $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $flags = $DB->get_records_sql(
        "SELECT f.*, $userfields
           FROM {vocabmastery_flag} f
           JOIN {user} u ON u.id = f.userid
          WHERE f.resolved = 0 AND f.itemid $in
       ORDER BY f.timecreated DESC",
        $params
    );
    foreach ($flags as $f) {
        $flagged[(int)$f->itemid] = true;
        $item = $items[$f->itemid];
        $reports[] = [
            'id' => (int)$f->id,
            'word' => $item->prompt . ' → ' . $item->correctvalue,
            'reason' => get_string('reason_' . $f->reason, $c),
            'comment' => (string)$f->commenttext,
            'by' => get_string(
                'reports_by',
                $c,
                (object)['name' => fullname($f), 'date' => userdate(
                    $f->timecreated,
                    get_string('strftimedatetimeshort', 'langconfig')
                )]
            ),
        ];
    }
}

$labels = array_combine($fields, array_map(fn($f) => get_string('field_' . $f, $c), $fields));
$othersets = [];
foreach ($instance->get_topics() as $t) {
    foreach ($instance->get_topic_sets((int)$t->id) as $s) {
        if ((int)$s->id !== $setid) {
            $othersets[] = [
                'value' => (int)$s->id, 'label' => format_string($instance->get_level((int)$t->levelid)->name) .
                ' · ' . format_string($t->name) . ' · ' . format_string($s->name),
            ];
        }
    }
}
$bulk = [
    ['value' => '', 'label' => get_string('withselected', $c)],
    ['value' => 'core', 'label' => get_string('markcore', $c)],
    ['value' => 'optional', 'label' => get_string('markoptional', $c)],
    ['value' => 'duplicate', 'label' => get_string('duplicate')],
    ['value' => 'delete', 'label' => get_string('delete')],
];
if ($othersets) {
    $bulk[] = ['value' => 'move', 'label' => get_string('moveto', $c)];
}
$itemsdata = [];
foreach ($items as $item) {
    $itemsdata[] = [
        'id' => (int)$item->id,
        'search' => core_text::strtolower($item->prompt . ' ' . $item->correctvalue . ' ' . $item->translation),
        'prompt' => $item->prompt,
        'iscore' => (bool)$item->iscore,
        'flagged' => !empty($flagged[(int)$item->id]),
        'cells' => array_map(
            fn($f) => [
                'name' => "items[{$item->id}][$f]",
                'value' => (string)$item->$f,
                'field' => $f,
                'label' => $labels[$f],
            ],
            $fields
        ),
    ];
}
$newrows = [];
for ($n = 0; $n < 3; $n++) {
    $newrows[] = [
        'cells' => array_map(fn($f) => ['name' => "new[$n][$f]", 'field' => $f, 'label' => $labels[$f]], $fields),
        'corename' => "new[$n][iscore]",
    ];
}
$info = [];
$info[] = $set->itemtype === 'typed' ? get_string('itemtype_typed', $c)
    : ($set->answermode === 'fixed' ? get_string('answermode_fixed', $c) . ': ' . str_replace("\n", ' / ', $set->options)
    : get_string('answermode_pool', $c));
$info[] = $set->gated ? get_string('gated', $c) : get_string('notgated', $c);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($level->name) . ' · ' . format_string($topic->name) . ' · ' . format_string($set->name));
echo $OUTPUT->render_from_template(
    'mod_vocabmastery/items',
    [
        'manageurl' => $instance->url('manage.php')->out(false),
        'info' => implode(' · ', $info),
        'seturl' => $instance->url('manage.php', ['action' => 'editset', 'setid' => $setid])->out(false),
        'actionurl' => $url->out(false),
        'sesskey' => sesskey(),
        'count' => get_string(
            'nitems',
            $c,
            (object)[
                'total' => count($items),
                'core' => count(array_filter($items, fn($i) => $i->iscore)),
            ]
        ),
        'bulk' => $bulk,
        'hastargets' => (bool)$othersets,
        'targets' => $othersets,
        'fields' => array_map(fn($f) => ['field' => $f, 'label' => $labels[$f]], $fields),
        'items' => $itemsdata,
        'newrows' => $newrows,
        'hasreports' => (bool)$reports,
        'reportstitle' => get_string('reports_open', $c, count($reports)),
        'reports' => $reports,
    ]
);
echo $OUTPUT->footer();
