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
 * Library of functions for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares supported features.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function vocabmastery_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Adds a new instance.
 *
 * @param stdClass $data
 * @param mod_vocabmastery_mod_form|null $mform
 * @return int new instance id
 */
function vocabmastery_add_instance($data, $mform = null) {
    global $DB;
    \mod_vocabmastery\instance::normalise_form_data($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('vocabmastery', $data);
    vocabmastery_grade_item_update($data);
    return $data->id;
}

/**
 * Updates an instance.
 *
 * @param stdClass $data
 * @param mod_vocabmastery_mod_form|null $mform
 * @return bool
 */
function vocabmastery_update_instance($data, $mform = null) {
    global $DB;
    \mod_vocabmastery\instance::normalise_form_data($data);
    $data->timemodified = time();
    $data->id = $data->instance;
    $DB->update_record('vocabmastery', $data);
    $vm = $DB->get_record('vocabmastery', ['id' => $data->id]);
    vocabmastery_grade_item_update($vm);
    vocabmastery_update_grades($vm);
    return true;
}

/**
 * Deletes an instance and all its content and user data.
 *
 * @param int $id
 * @return bool
 */
function vocabmastery_delete_instance($id) {
    global $DB;
    if (!$vm = $DB->get_record('vocabmastery', ['id' => $id])) {
        return false;
    }
    foreach (['response', 'attempt', 'itemstate', 'topicstate', 'flag', 'item', 'set', 'topic', 'level'] as $t) {
        $DB->delete_records('vocabmastery_' . $t, ['vocabmasteryid' => $id]);
    }
    vocabmastery_grade_item_delete($vm);
    $DB->delete_records('vocabmastery', ['id' => $id]);
    return true;
}

/**
 * Course module info, including custom completion rules.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function vocabmastery_get_coursemodule_info($coursemodule) {
    global $DB;
    $fields = 'id, name, intro, introformat, completiontopics, completionalltopics, completionprogress';
    if (!$vm = $DB->get_record('vocabmastery', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $vm->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('vocabmastery', $vm, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completiontopics'] = $vm->completiontopics;
        $result->customdata['customcompletionrules']['completionalltopics'] = $vm->completionalltopics;
        $result->customdata['customcompletionrules']['completionprogress'] = $vm->completionprogress;
    }
    return $result;
}

/**
 * Creates or updates the grade item.
 *
 * @param stdClass $vm instance record (with cmidnumber optionally)
 * @param mixed $grades optional grades or 'reset'
 * @return int
 */
function vocabmastery_grade_item_update($vm, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $vm->name];
    if (isset($vm->cmidnumber)) {
        $params['idnumber'] = $vm->cmidnumber;
    }
    if ($vm->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $vm->grade;
        $params['grademin'] = 0;
    } else if ($vm->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$vm->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/vocabmastery', $vm->course, 'mod', 'vocabmastery', $vm->id, 0, $grades, $params);
}

/**
 * Deletes the grade item.
 *
 * @param stdClass $vm
 * @return int
 */
function vocabmastery_grade_item_delete($vm) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/vocabmastery', $vm->course, 'mod', 'vocabmastery', $vm->id, 0, null, ['deleted' => 1]);
}

/**
 * Returns user grades.
 *
 * @param stdClass $vm
 * @param int $userid 0 for all
 * @return array userid => grade object
 */
function vocabmastery_get_user_grades($vm, $userid = 0) {
    global $DB;
    $grades = [];
    $params = ['vid' => $vm->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $userids = $DB->get_fieldset_sql("SELECT DISTINCT userid FROM {vocabmastery_topicstate}
                                       WHERE vocabmasteryid = :vid $usersql", $params);
    if ($userid && !in_array($userid, $userids)) {
        $userids[] = $userid;
    }
    if (!$userids) {
        return [];
    }
    $instance = \mod_vocabmastery\instance::from_record($vm);
    foreach ($userids as $uid) {
        $progress = new \mod_vocabmastery\progress($instance, (int)$uid);
        $percent = $progress->grade_percent();
        $g = new stdClass();
        $g->userid = $uid;
        $g->rawgrade = $vm->grade > 0 ? round($percent * $vm->grade / 100, 5) : null;
        if ($vm->grade < 0) {
            // Map percentage onto the scale.
            $scale = $DB->get_record('scale', ['id' => -$vm->grade]);
            if ($scale) {
                $count = count(explode(',', $scale->scale));
                $g->rawgrade = max(1, (int)ceil($percent / 100 * $count));
            }
        }
        $grades[$uid] = $g;
    }
    return $grades;
}

/**
 * Updates grades in the gradebook.
 *
 * @param stdClass $vm
 * @param int $userid
 * @param bool $nullifnone
 */
function vocabmastery_update_grades($vm, $userid = 0, $nullifnone = true) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    if ($vm->grade == 0) {
        vocabmastery_grade_item_update($vm);
        return;
    }
    if ($grades = vocabmastery_get_user_grades($vm, $userid)) {
        vocabmastery_grade_item_update($vm, $grades);
    } else if ($userid && $nullifnone) {
        $g = new stdClass();
        $g->userid = $userid;
        $g->rawgrade = null;
        vocabmastery_grade_item_update($vm, $g);
    } else {
        vocabmastery_grade_item_update($vm);
    }
}

/**
 * Adds teacher links to the activity secondary navigation.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $node
 */
function vocabmastery_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = context_module::instance($cm->id);
    if (has_capability('mod/vocabmastery:managecontent', $context)) {
        $node->add(
            get_string('managecontent', 'mod_vocabmastery'),
            new moodle_url('/mod/vocabmastery/manage.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'vocabmastery_manage'
        );
        $node->add(
            get_string('importvocab', 'mod_vocabmastery'),
            new moodle_url('/mod/vocabmastery/import.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'vocabmastery_import'
        );
    }
    if (has_capability('mod/vocabmastery:viewallprogress', $context)) {
        $node->add(
            get_string('reports', 'mod_vocabmastery'),
            new moodle_url('/mod/vocabmastery/report.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'vocabmastery_report'
        );
    }
}

/**
 * Course reset form elements.
 *
 * @param MoodleQuickForm $mform
 */
function vocabmastery_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'vocabmasteryheader', get_string('modulenameplural', 'mod_vocabmastery'));
    $mform->addElement('advcheckbox', 'reset_vocabmastery_progress', get_string('resetallprogress', 'mod_vocabmastery'));
}

/**
 * Course reset defaults.
 *
 * @param stdClass $course
 * @return array
 */
function vocabmastery_reset_course_form_defaults($course) {
    return ['reset_vocabmastery_progress' => 1];
}

/**
 * Course reset.
 *
 * @param stdClass $data
 * @return array status
 */
function vocabmastery_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_vocabmastery_progress)) {
        $ids = $DB->get_fieldset_select('vocabmastery', 'id', 'course = :course', ['course' => $data->courseid]);
        foreach ($ids as $id) {
            foreach (['response', 'attempt', 'itemstate', 'topicstate', 'flag'] as $t) {
                $DB->delete_records('vocabmastery_' . $t, ['vocabmasteryid' => $id]);
            }
            $vm = $DB->get_record('vocabmastery', ['id' => $id]);
            vocabmastery_grade_item_update($vm, 'reset');
        }
        $status[] = ['component' => get_string('modulenameplural', 'mod_vocabmastery'),
            'item' => get_string('resetallprogress', 'mod_vocabmastery'), 'error' => false];
    }
    return $status;
}
