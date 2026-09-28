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
 * List of Vocabulary Mastery activities in a course.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$event = \mod_vocabmastery\event\course_module_instance_list_viewed::create(['context' => context_course::instance($id)]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$PAGE->set_url('/mod/vocabmastery/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_vocabmastery'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('modulenameplural', 'mod_vocabmastery'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_vocabmastery'));

$instances = get_all_instances_in_course('vocabmastery', $course);
if (!$instances) {
    notice(
        get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_vocabmastery')),
        new moodle_url('/course/view.php', ['id' => $id])
    );
}
$table = new html_table();
$table->head = [get_string('name'), get_string('progress', 'mod_vocabmastery')];
foreach ($instances as $inst) {
    $cm = get_coursemodule_from_instance('vocabmastery', $inst->id, $id);
    if (!has_capability('mod/vocabmastery:view', context_module::instance($cm->id))) {
        continue;
    }
    $link = html_writer::link(
        new moodle_url('/mod/vocabmastery/view.php', ['id' => $inst->coursemodule]),
        format_string($inst->name),
        $inst->visible ? [] : ['class' => 'dimmed']
    );
    $progress = new \mod_vocabmastery\progress(new \mod_vocabmastery\instance(
        $DB->get_record('vocabmastery', ['id' => $inst->id]),
        $cm,
        $course
    ), (int)$USER->id);
    $table->data[] = [$link, round($progress->overall_percent()) . '%'];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
