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
 * Student view: dashboard or a topic.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/vocabmastery/lib.php');

use mod_vocabmastery\instance;
use mod_vocabmastery\progress;

$id = required_param('id', PARAM_INT);
$topicid = optional_param('topic', 0, PARAM_INT);

$instance = instance::from_cmid($id);
require_login($instance->course, true, $instance->cm);
require_capability('mod/vocabmastery:view', $instance->context);
$c = 'mod_vocabmastery';

$params = $topicid ? ['topic' => $topicid] : [];
$PAGE->set_url($instance->url('view.php', $params));
$PAGE->set_title(format_string($instance->vm->name));
$PAGE->set_heading(format_string($instance->course->fullname));
$PAGE->add_body_class('vm-page');

if (!$topicid) {
    $instance->log_view();
}

$progress = new progress($instance, (int)$USER->id);
if ($topicid) {
    $topic = $instance->get_topic($topicid);
    $summary = $progress->get_summary($topicid);
    if (!$summary->unlocked && !$instance->bypass_locks()) {
        redirect($instance->url('view.php'), get_string('topiclocked', $c), null, \core\output\notification::NOTIFY_WARNING);
    }
    $PAGE->navbar->add(format_string($topic->name));
    $PAGE->activityheader->set_description('');
    $renderable = new \mod_vocabmastery\output\topic_page($instance, $progress, $topicid);
    $template = 'mod_vocabmastery/topic';
} else {
    $renderable = new \mod_vocabmastery\output\dashboard($instance, $progress);
    $template = 'mod_vocabmastery/dashboard';
}

$vm = $instance->vm;
$config = [
    'cmid' => (int)$instance->cm->id,
    'topicid' => $topicid,
    'blitzduration' => (int)$vm->blitzduration,
    'threshold' => (int)$vm->masterythreshold,
    'topicurl' => $topicid ? $instance->url('view.php', ['topic' => $topicid])->out(false) : '',
    'homeurl' => $instance->url('view.php')->out(false),
];
$canattempt = $instance->can_attempt();
if ($canattempt) {
    $PAGE->requires->js_call_amd('mod_vocabmastery/trainer', 'init', [$config]);
} else {
    $PAGE->add_body_class('vm-noattempt');
}

echo $OUTPUT->header();
if (!$canattempt) {
    echo $OUTPUT->notification(get_string('noattemptcapability', $c), \core\output\notification::NOTIFY_INFO);
}
echo $OUTPUT->render_from_template($template, $renderable->export_for_template($OUTPUT));
echo $OUTPUT->footer();
