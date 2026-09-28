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
 * Import wizard: Upload → Parse → Preview → Validate → Confirm → Import.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

use mod_vocabmastery\import\docx_parser;
use mod_vocabmastery\import\importer;
use mod_vocabmastery\import\staging;
use mod_vocabmastery\import\table_parser;
use mod_vocabmastery\instance;

$id = required_param('id', PARAM_INT);
$source = optional_param('source', 'word', PARAM_ALPHA);
$step = optional_param('step', '', PARAM_ALPHA);
$token = optional_param('token', '', PARAM_ALPHANUM);

$instance = instance::from_cmid($id);
require_login($instance->course, false, $instance->cm);
require_capability('mod/vocabmastery:managecontent', $instance->context);
$c = 'mod_vocabmastery';

$url = $instance->url('import.php', ['source' => $source]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->vm->name) . ': ' . get_string('importvocab', $c));
$PAGE->set_heading(format_string($instance->course->fullname));
$PAGE->activityheader->disable();

// Option list for the duplicate policy select.
$dupoptions = function (string $selected) use ($c): array {
    $out = [];
    foreach (['skip', 'update', 'add', 'replace'] as $value) {
        $out[] = ['value' => $value, 'label' => get_string('dup_' . $value, $c), 'selected' => $value === $selected];
    }
    return $out;
};

$finished = function (array $stats) use ($instance, $c) {
    redirect(
        $instance->url('manage.php'),
        get_string('importdone', $c, (object)$stats),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
};

// Word: import confirmed selection.
if ($step === 'importdocx') {
    require_sesskey();
    $docs = staging::load($token, $id);
    $sel = [];
    $levelnames = [];
    foreach ($docs as $key => $doc) {
        foreach ($doc['sections'] as $s) {
            if (optional_param('sec_' . $key . '_' . $s['key'], 0, PARAM_BOOL)) {
                $sel[$key][$s['key']] = 1;
            }
        }
        $levelnames[$key] = optional_param('level_' . $key, $doc['level'], PARAM_TEXT);
    }
    $importer = new importer($instance, optional_param('duplicates', 'skip', PARAM_ALPHA));
    // Optional sections are ticked individually in the preview, so everything selected is imported.
    $stats = $importer->import_docs($docs, $sel, true, optional_param('importnotes', 0, PARAM_BOOL), $levelnames);
    staging::forget($token);
    $finished($stats);
}

// Word: preview.
if ($step === 'previewdocx') {
    $docs = staging::load($token, $id);
    $totals = ['files' => count($docs), 'core' => 0, 'optional' => 0, 'sets' => 0, 'skipped' => 0, 'warnings' => 0];
    $bylevel = [];
    foreach ($docs as $key => $doc) {
        $totals['warnings'] += count($doc['warnings']);
        $sections = [];
        foreach ($doc['sections'] as $s) {
            $totals['sets']++;
            $totals['skipped'] += count($s['skipped']);
            $core = count(array_filter($s['items'], fn($i) => $i['iscore']));
            $totals['core'] += $core;
            $totals['optional'] += count($s['items']) - $core;
            $settings = $s['settings'];
            $sections[] = [
                'inputid' => 'sec_' . $key . '_' . $s['key'],
                'label' => $s['label'],
                'count' => $s['core'] ? get_string('ncore', $c, $core) : get_string('noptional', $c, count($s['items']) - $core),
                'checked' => (bool)$s['core'],
                'skippedcount' => $s['skipped'] ? get_string('nskipped', $c, count($s['skipped'])) : '',
                'skippedlist' => implode(', ', array_map(
                    fn($k) => $k['prompt'] . ' (' . get_string('skip_' . $k['reason'], $c) . ')',
                    $s['skipped']
                )),
                'answers' => $settings['answermode'] === 'fixed' ? str_replace("\n", ' / ', $settings['options'])
                    : get_string('answermode_pool', $c),
                'gated' => (bool)$settings['gated'],
            ];
        }
        $bylevel[$doc['level'] ?: '?'][] = [
            'key' => $key,
            'pathkey' => (int)$doc['pathkey'],
            'topic' => $doc['topic'],
            'filename' => $doc['filename'],
            'level' => $doc['level'],
            'sections' => $sections,
            'warnings' => array_map(fn($w) => ['text' => get_string('warn_' . $w['type'], $c, (object)$w)], $doc['warnings']),
            'notes' => trim($doc['notes']),
        ];
    }
    ksort($bylevel);
    $levels = [];
    foreach ($bylevel as $name => $group) {
        usort($group, fn($a, $b) => $a['pathkey'] <=> $b['pathkey']);
        $levels[] = ['name' => $name, 'docs' => $group];
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('importpreview', $c));
    echo $OUTPUT->render_from_template('mod_vocabmastery/import_docx_preview', [
        'actionurl' => $instance->url('import.php')->out(false),
        'sesskey' => sesskey(),
        'token' => $token,
        'summary' => get_string('importsummary', $c, (object)$totals),
        'duplicates' => $dupoptions('skip'),
        'levels' => $levels,
        'cancelurl' => $instance->url('import.php')->out(false),
    ]);
    echo $OUTPUT->footer();
    die;
}

// Table: mapping, validation, preview and import.
if ($step === 'maptable') {
    $data = staging::load($token, $id);
    $rows = $data['rows'];
    $width = $rows ? count($rows[0]) : 0;
    $submitted = optional_param('mapped', 0, PARAM_BOOL);
    if ($submitted) {
        require_sesskey();
        $mapping = [];
        for ($ci = 0; $ci < $width; $ci++) {
            $f = optional_param('col' . $ci, 'ignore', PARAM_ALPHA);
            $mapping[$ci] = in_array($f, table_parser::FIELDS, true) ? $f : 'ignore';
        }
        $hasheader = optional_param('hasheader', 0, PARAM_BOOL);
        $level = optional_param('targetlevel', '', PARAM_TEXT);
        $topic = optional_param('targettopic', '', PARAM_TEXT);
        $set = optional_param('targetset', '', PARAM_TEXT);
    } else {
        $guess = table_parser::guess_mapping($rows);
        $mapping = $guess['mapping'];
        $hasheader = $guess['hasheader'];
        $levels = $instance->get_levels();
        $firstlevel = reset($levels);
        $level = $firstlevel ? $firstlevel->name : get_string('defaultlevel', $c);
        $topics = $instance->get_topics();
        $firsttopic = reset($topics);
        $topic = $firsttopic ? $firsttopic->name : get_string('defaulttopic', $c);
        $set = get_string('defaultset', $c);
    }
    $records = table_parser::to_records($rows, $mapping, (bool)$hasheader);
    $warnings = importer::validate_records($records);
    $mapped = array_flip(array_filter($mapping, fn($f) => $f !== 'ignore'));
    $ready = isset($mapped['prompt'], $mapped['correctvalue']);
    $duplicates = optional_param('duplicates', 'skip', PARAM_ALPHA);

    if ($submitted && optional_param('doimport', 0, PARAM_BOOL) && $ready) {
        $importer = new importer($instance, $duplicates);
        $stats = $importer->import_records($records, $level, $topic, $set);
        staging::forget($token);
        $finished($stats);
    }

    $fieldlabels = [];
    foreach (table_parser::FIELDS as $f) {
        $fieldlabels[$f] = get_string('field_' . $f, $c);
    }
    $detected = [];
    $columns = [];
    for ($ci = 0; $ci < $width; $ci++) {
        $current = $mapping[$ci] ?? 'ignore';
        if ($current !== 'ignore') {
            $detected[] = get_string('columnn', $c, $ci + 1) . ' → ' . $fieldlabels[$current];
        }
        $options = [];
        foreach ($fieldlabels as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label, 'selected' => $value === $current];
        }
        $columns[] = ['name' => 'col' . $ci, 'label' => get_string('columnn', $c, $ci + 1), 'options' => $options];
    }
    $samplerows = [];
    foreach (array_slice($rows, 0, 8) as $ri => $r) {
        $samplerows[] = ['header' => $ri === 0 && $hasheader, 'cells' => array_map(fn($v) => ['value' => $v], $r)];
    }
    $names = fn(array $list) => array_values(array_map(fn($v) => ['value' => $v], array_unique($list)));
    $targets = [
        ['name' => 'targetlevel', 'label' => get_string('defaultlevelname', $c), 'value' => $level,
            'list' => $names(array_map(fn($l) => $l->name, $instance->get_levels()))],
        ['name' => 'targettopic', 'label' => get_string('defaulttopicname', $c), 'value' => $topic,
            'list' => $names(array_map(fn($t) => $t->name, $instance->get_topics()))],
        ['name' => 'targetset', 'label' => get_string('defaultsetname', $c), 'value' => $set,
            'list' => $names(array_map(fn($s) => $s->name, $instance->get_all_sets()))],
    ];
    $cols = array_values(array_unique(array_filter($mapping, fn($f) => $f !== 'ignore')));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('importmapping', $c));
    echo $OUTPUT->render_from_template('mod_vocabmastery/import_table_mapping', [
        'actionurl' => $instance->url('import.php')->out(false),
        'sesskey' => sesskey(),
        'token' => $token,
        'detected' => implode(' · ', $detected),
        'columns' => $columns,
        'samplerows' => $samplerows,
        'rowcount' => get_string('nrows', $c, count($rows)),
        'hasheader' => (bool)$hasheader,
        'targets' => $targets,
        'duplicates' => $dupoptions($duplicates),
        'ready' => $ready,
        'haswarnings' => (bool)$warnings,
        'warningtitle' => get_string('nwarnings', $c, count($warnings)),
        'warnings' => array_map(
            fn($w) => ['text' => get_string('rown', $c, $w[0]) . ': ' . $w[1]],
            array_slice($warnings, 0, 25)
        ),
        'haspreview' => $ready && $records,
        'previewtitle' => get_string('previewn', $c, count($records)),
        'previewhead' => array_map(fn($f) => ['label' => $fieldlabels[$f]], $cols),
        'previewrows' => array_map(
            fn($rec) => ['cells' => array_map(fn($f) => ['value' => (string)($rec[$f] ?? '')], $cols)],
            array_slice($records, 0, 30)
        ),
        'importlabel' => get_string('importnrows', $c, count($records)),
        'cancelurl' => $instance->url('import.php', ['source' => 'table'])->out(false),
    ]);
    echo $OUTPUT->footer();
    die;
}

// Step 1: upload / paste.
$wordform = new \mod_vocabmastery\form\import_word_form($instance->url('import.php', ['source' => 'word']));
$tableform = new \mod_vocabmastery\form\import_table_form($instance->url('import.php', ['source' => 'table']));

if ($source === 'word') {
    if ($wordform->is_cancelled()) {
        redirect($instance->url('manage.php'));
    } else if ($data = $wordform->get_data()) {
        $fs = get_file_storage();
        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->docs, 'filename', false);
        $docs = [];
        $errors = [];
        foreach ($files as $file) {
            try {
                $tmp = $file->copy_content_to_temp('mod_vocabmastery');
                $doc = docx_parser::parse($tmp, $file->get_filename());
                unlink($tmp);
                if ($doc['sections']) {
                    $docs['f' . count($docs)] = $doc;
                } else {
                    $errors[] = get_string('nosectionsfound', $c, s($file->get_filename()));
                }
            } catch (Throwable $e) {
                $errors[] = s($file->get_filename()) . ': ' . s($e->getMessage());
            }
        }
        if ($docs) {
            $token = staging::store($docs, $id);
            $next = $instance->url('import.php', ['step' => 'previewdocx', 'token' => $token, 'source' => 'word']);
            if ($errors) {
                redirect($next, implode('<br>', $errors), null, \core\output\notification::NOTIFY_WARNING);
            }
            redirect($next);
        }
        \core\notification::error(implode('<br>', $errors) ?: get_string('nosectionsfound', $c, ''));
    }
} else if ($source === 'table') {
    if ($tableform->is_cancelled()) {
        redirect($instance->url('manage.php'));
    } else if ($data = $tableform->get_data()) {
        $rows = [];
        $filename = $tableform->get_new_filename('tablefile');
        if ($filename) {
            $tmp = $tableform->save_temp_file('tablefile');
            try {
                $rows = table_parser::parse_file($tmp, $filename);
            } catch (Throwable $e) {
                \core\notification::error(s($e->getMessage()));
            }
            if (file_exists($tmp)) {
                unlink($tmp);
            }
        } else if (trim((string)$data->pasted) !== '') {
            $rows = table_parser::parse_text((string)$data->pasted);
        }
        if ($rows) {
            $token = staging::store(['rows' => array_slice($rows, 0, 10000)], $id);
            redirect($instance->url('import.php', ['step' => 'maptable', 'token' => $token, 'source' => 'table']));
        }
        \core\notification::error(get_string('norows', $c));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importvocab', $c));
$tabs = [
    new tabobject('word', $instance->url('import.php', ['source' => 'word']), get_string('source_word', $c)),
    new tabobject('table', $instance->url('import.php', ['source' => 'table']), get_string('source_table', $c)),
];
echo $OUTPUT->tabtree($tabs, $source === 'table' ? 'table' : 'word');
$form = $source === 'table' ? $tableform : $wordform;
if ($source === 'table') {
    $columns = [];
    foreach (\mod_vocabmastery\import\sample::COLUMNS as $col) {
        $columns[] = ['name' => $col, 'required' => in_array($col, ['answer', 'prompt'], true),
            'desc' => get_string('col_' . $col . '_desc', $c)];
    }
    echo $OUTPUT->render_from_template('mod_vocabmastery/import_help', [
        'xlsxurl' => $instance->url('sample.php', ['type' => 'xlsx'])->out(false),
        'csvurl' => $instance->url('sample.php', ['type' => 'csv'])->out(false),
        'prompturl' => $instance->url('sample.php', ['type' => 'prompt'])->out(false),
        'prompt' => \mod_vocabmastery\import\sample::chatgpt_prompt(),
        'uniqid' => html_writer::random_id('vmhelp'),
        'columns' => $columns,
    ]);
}
$form->set_data(['id' => $id, 'source' => $source]);
$form->display();
echo $OUTPUT->render_from_template('mod_vocabmastery/import_hint', ['manageurl' => $instance->url('manage.php')->out(false)]);
echo $OUTPUT->footer();
