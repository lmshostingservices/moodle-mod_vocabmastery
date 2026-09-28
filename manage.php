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
 * Manage levels, topics and sets.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_vocabmastery\content;
use mod_vocabmastery\instance;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$instance = instance::from_cmid($id);
require_login($instance->course, false, $instance->cm);
require_capability('mod/vocabmastery:managecontent', $instance->context);
$vmid = (int)$instance->vm->id;
$c = 'mod_vocabmastery';

$baseurl = $instance->url('manage.php');
$PAGE->set_url($baseurl, $action ? ['action' => $action] : []);
$PAGE->set_title(format_string($instance->vm->name) . ': ' . get_string('managecontent', $c));
$PAGE->set_heading(format_string($instance->course->fullname));
$PAGE->activityheader->disable();

// CSV export of all content (round-trips through the importer).
if ($action === 'export') {
    require_sesskey();
    $rows = [];
    foreach ($instance->get_topics() as $t) {
        $level = $instance->get_level((int)$t->levelid);
        foreach ($instance->get_topic_sets((int)$t->id) as $s) {
            foreach ($instance->get_set_items((int)$s->id) as $i) {
                $meta = $i->metadata ? (json_decode($i->metadata, true) ?: []) : [];
                $rows[] = [$level->name, $t->name, $s->name, $i->correctvalue, $i->prompt, $i->translation, $i->plural,
                    $i->altanswers, $i->hint, $i->iscore ? 1 : 0, $meta['source'] ?? ''];
            }
        }
    }
    $columns = ['level', 'topic', 'set', 'answer', 'prompt', 'translation', 'plural', 'alternatives', 'hint', 'core',
        'source'];
    \core\dataformat::download_data(
        clean_filename($instance->vm->name . '-vocabulary'),
        'csv',
        $columns,
        new ArrayIterator($rows)
    );
    die;
}

// Move.
if ($action === 'move') {
    require_sesskey();
    $type = required_param('type', PARAM_ALPHA);
    $itemid = required_param('itemid', PARAM_INT);
    if (
        in_array($type, ['level', 'topic', 'set'], true)
            && $DB->record_exists('vocabmastery_' . $type, ['id' => $itemid, 'vocabmasteryid' => $vmid])
    ) {
        content::move($type, $itemid, required_param('dir', PARAM_INT) < 0 ? -1 : 1);
    }
    redirect($baseurl);
}

// Delete with confirmation.
if ($action === 'delete') {
    $type = required_param('type', PARAM_ALPHA);
    $itemid = required_param('itemid', PARAM_INT);
    if (!in_array($type, ['level', 'topic', 'set'], true)) {
        throw new moodle_exception('invalidparameter');
    }
    $rec = $DB->get_record('vocabmastery_' . $type, ['id' => $itemid, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $fn = 'delete_' . $type;
        content::$fn($itemid);
        redirect($baseurl, get_string('deleted', $c, format_string($rec->name)), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    $confirm = new moodle_url($baseurl, ['action' => 'delete', 'type' => $type, 'itemid' => $itemid, 'confirm' => 1,
        'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(get_string('confirmdelete_' . $type, $c, format_string($rec->name)), $confirm, $baseurl);
    echo $OUTPUT->footer();
    die;
}

// Level form.
if ($action === 'editlevel') {
    $levelid = optional_param('levelid', 0, PARAM_INT);
    $form = new \mod_vocabmastery\form\level_form(new moodle_url($baseurl));
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        if ($data->levelid) {
            $DB->get_record('vocabmastery_level', ['id' => $data->levelid, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
            $DB->update_record('vocabmastery_level', (object)['id' => $data->levelid, 'name' => $data->name,
                'description' => $data->description]);
        } else {
            content::add_level($vmid, $data->name, $data->description);
        }
        redirect($baseurl);
    }
    $defaults = ['id' => $id, 'levelid' => $levelid];
    if ($levelid) {
        $level = $DB->get_record('vocabmastery_level', ['id' => $levelid, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
        $defaults += ['name' => $level->name, 'description' => $level->description];
    }
    $form->set_data($defaults);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($levelid ? 'editlevel' : 'addlevel', $c));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

// Topic form.
if ($action === 'edittopic') {
    $topicid = optional_param('topicid', 0, PARAM_INT);
    $levels = array_map(fn($l) => format_string($l->name), $instance->get_levels());
    if (!$levels) {
        redirect(new moodle_url($baseurl, ['action' => 'editlevel']), get_string('addlevelfirst', $c));
    }
    $form = new \mod_vocabmastery\form\topic_form(new moodle_url($baseurl), ['levels' => $levels]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $instance->get_level((int)$data->levelid);
        if ($data->topicid) {
            $old = $DB->get_record('vocabmastery_topic', ['id' => $data->topicid, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
            $rec = (object)['id' => $data->topicid, 'name' => $data->name, 'levelid' => $data->levelid,
                'pathkey' => (int)$data->pathkey, 'description' => $data->description,
                'teachernotes' => $data->teachernotes, 'timemodified' => time()];
            if ((int)$old->levelid !== (int)$data->levelid) {
                $rec->sortorder = (int)$DB->get_field(
                    'vocabmastery_topic',
                    'COALESCE(MAX(sortorder), 0) + 1',
                    ['levelid' => $data->levelid]
                );
            }
            $DB->update_record('vocabmastery_topic', $rec);
        } else {
            content::add_topic(
                $vmid,
                (int)$data->levelid,
                $data->name,
                (int)$data->pathkey,
                $data->description,
                $data->teachernotes
            );
        }
        redirect($baseurl);
    }
    $defaults = ['id' => $id, 'topicid' => $topicid, 'levelid' => optional_param('levelid', 0, PARAM_INT)];
    if ($topicid) {
        $t = $instance->get_topic($topicid);
        $defaults = array_merge($defaults, (array)$t, ['id' => $id, 'topicid' => $topicid]);
    }
    $form->set_data($defaults);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($topicid ? 'edittopic' : 'addtopic', $c));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

// Set form.
if ($action === 'editset') {
    $setid = optional_param('setid', 0, PARAM_INT);
    $topics = [];
    foreach ($instance->get_topics() as $t) {
        $topics[$t->id] = format_string($instance->get_level((int)$t->levelid)->name) . ' · ' . format_string($t->name);
    }
    if (!$topics) {
        redirect(new moodle_url($baseurl, ['action' => 'edittopic']), get_string('addtopicfirst', $c));
    }
    $form = new \mod_vocabmastery\form\set_form(new moodle_url($baseurl), ['topics' => $topics]);
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $instance->get_topic((int)$data->topicid);
        $rec = (object)['name' => $data->name, 'instruction' => $data->instruction, 'itemtype' => $data->itemtype,
            'answermode' => $data->answermode, 'options' => trim($data->options), 'numoptions' => $data->numoptions,
            'showtranslation' => $data->showtranslation, 'gated' => $data->gated, 'topicid' => $data->topicid];
        if ($data->setid) {
            $DB->get_record('vocabmastery_set', ['id' => $data->setid, 'vocabmasteryid' => $vmid], '*', MUST_EXIST);
            $rec->id = $data->setid;
            $rec->timemodified = time();
            $DB->update_record('vocabmastery_set', $rec);
            $newid = $data->setid;
        } else {
            $newid = content::add_set($vmid, (int)$data->topicid, (array)$rec);
        }
        redirect(new moodle_url('/mod/vocabmastery/items.php', ['id' => $id, 'setid' => $newid]));
    }
    $defaults = ['id' => $id, 'setid' => $setid, 'topicid' => optional_param('topicid', 0, PARAM_INT),
        'options' => "der\ndie\ndas", 'instruction' => ''];
    if ($setid) {
        $s = $instance->get_set($setid);
        $defaults = array_merge($defaults, (array)$s, ['id' => $id, 'setid' => $setid]);
    }
    $form->set_data($defaults);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($setid ? 'editset' : 'addset', $c));
    $form->display();
    echo $OUTPUT->footer();
    die;
}

// Overview tree.
$counts = $DB->get_records_sql_menu(
    "SELECT setid, COUNT(1) FROM {vocabmastery_item} WHERE vocabmasteryid = :vid GROUP BY setid",
    ['vid' => $vmid]
);
$corecounts = $DB->get_records_sql_menu("SELECT setid, COUNT(1) FROM {vocabmastery_item}
    WHERE vocabmasteryid = :vid AND iscore = 1 GROUP BY setid", ['vid' => $vmid]);

$actions = function (string $type, int $itemid, bool $first, bool $last, array $editparams) use ($baseurl) {
    $out = [['url' => (new moodle_url($baseurl, $editparams))->out(false), 'pix' => 't/edit', 'label' => get_string('edit')]];
    if (!$first) {
        $out[] = ['url' => (new moodle_url($baseurl, ['action' => 'move', 'type' => $type, 'itemid' => $itemid, 'dir' => -1,
            'sesskey' => sesskey()]))->out(false), 'pix' => 't/up', 'label' => get_string('moveup')];
    }
    if (!$last) {
        $out[] = ['url' => (new moodle_url($baseurl, ['action' => 'move', 'type' => $type, 'itemid' => $itemid, 'dir' => 1,
            'sesskey' => sesskey()]))->out(false), 'pix' => 't/down', 'label' => get_string('movedown')];
    }
    $out[] = ['url' => (new moodle_url($baseurl, ['action' => 'delete', 'type' => $type, 'itemid' => $itemid]))->out(false),
        'pix' => 't/delete', 'label' => get_string('delete'), 'danger' => true];
    return $out;
};

$totals = content::counts($vmid);
$levels = array_values($instance->get_levels());
$levelsdata = [];
foreach ($levels as $li => $level) {
    $topics = array_values($instance->get_level_topics((int)$level->id));
    $topicsdata = [];
    foreach ($topics as $ti => $topic) {
        $sets = array_values($instance->get_topic_sets((int)$topic->id));
        $setsdata = [];
        foreach ($sets as $si => $set) {
            $setsdata[] = [
                'name' => format_string($set->name),
                'url' => (new moodle_url('/mod/vocabmastery/items.php', ['id' => $id, 'setid' => $set->id]))->out(false),
                'items' => get_string('nitems', $c, (object)['total' => (int)($counts[$set->id] ?? 0),
                    'core' => (int)($corecounts[$set->id] ?? 0)]),
                'gated' => (bool)$set->gated,
                'answers' => $set->answermode === 'fixed' && $set->itemtype === 'choice'
                    ? str_replace("\n", ' / ', $set->options)
                    : get_string($set->itemtype === 'typed' ? 'itemtype_typed' : 'answermode_pool', $c),
                'actions' => $actions(
                    'set',
                    (int)$set->id,
                    $si === 0,
                    $si === count($sets) - 1,
                    ['action' => 'editset', 'setid' => $set->id]
                ),
            ];
        }
        $topicsdata[] = [
            'name' => format_string($topic->name),
            'pathkey' => get_string('pathkeyshort', $c, (int)$topic->pathkey),
            'previewurl' => $instance->url('view.php', ['topic' => $topic->id])->out(false),
            'addseturl' => (new moodle_url($baseurl, ['action' => 'editset', 'topicid' => $topic->id]))->out(false),
            'actions' => $actions(
                'topic',
                (int)$topic->id,
                $ti === 0,
                $ti === count($topics) - 1,
                ['action' => 'edittopic', 'topicid' => $topic->id]
            ),
            'hassets' => (bool)$setsdata,
            'sets' => $setsdata,
            'notes' => trim((string)$topic->teachernotes),
        ];
    }
    $levelsdata[] = [
        'name' => format_string($level->name),
        'ntopics' => get_string('ntopics', $c, count($topics)),
        'addtopicurl' => (new moodle_url($baseurl, ['action' => 'edittopic', 'levelid' => $level->id]))->out(false),
        'actions' => $actions(
            'level',
            (int)$level->id,
            $li === 0,
            $li === count($levels) - 1,
            ['action' => 'editlevel', 'levelid' => $level->id]
        ),
        'topics' => $topicsdata,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->vm->name) . ': ' . get_string('managecontent', $c));
echo $OUTPUT->render_from_template('mod_vocabmastery/manage', [
    'addlevelurl' => (new moodle_url($baseurl, ['action' => 'editlevel']))->out(false),
    'addtopicurl' => (new moodle_url($baseurl, ['action' => 'edittopic']))->out(false),
    'addseturl' => (new moodle_url($baseurl, ['action' => 'editset']))->out(false),
    'importurl' => $instance->url('import.php')->out(false),
    'exporturl' => $totals['items'] ? (new moodle_url($baseurl, ['action' => 'export', 'sesskey' => sesskey()]))->out(false)
        : null,
    'counts' => get_string('contentcounts', $c, (object)$totals),
    'haslevels' => (bool)$levelsdata,
    'levels' => $levelsdata,
]);
echo $OUTPUT->footer();
