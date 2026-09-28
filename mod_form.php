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
 * Activity settings form.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for Vocabulary Mastery.
 */
class mod_vocabmastery_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $c = 'mod_vocabmastery';

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $this->standard_intro_elements();

        $mform->addElement('text', 'targetlanguage', get_string('targetlanguage', $c), ['size' => 30]);
        $mform->setType('targetlanguage', PARAM_TEXT);
        $mform->addHelpButton('targetlanguage', 'targetlanguage', $c);
        $mform->addElement('text', 'nativelanguage', get_string('nativelanguage', $c), ['size' => 30]);
        $mform->setType('nativelanguage', PARAM_TEXT);

        // Mastery.
        $mform->addElement('header', 'masteryhdr', get_string('masterysettings', $c));
        $mform->setExpanded('masteryhdr');
        $mform->addElement('text', 'masterythreshold', get_string('masterythreshold', $c), ['size' => 4]);
        $mform->setType('masterythreshold', PARAM_INT);
        $mform->setDefault('masterythreshold', 90);
        $mform->addHelpButton('masterythreshold', 'masterythreshold', $c);

        $mform->addElement('select', 'unlockby', get_string('unlockby', $c), [
            'test' => get_string('unlockby_test', $c),
            'practice' => get_string('unlockby_practice', $c),
            'either' => get_string('unlockby_either', $c),
        ]);
        $mform->setDefault('unlockby', 'test');
        $mform->addHelpButton('unlockby', 'unlockby', $c);

        $mform->addElement('text', 'testlength', get_string('testlength', $c), ['size' => 4]);
        $mform->setType('testlength', PARAM_INT);
        $mform->setDefault('testlength', 0);
        $mform->addHelpButton('testlength', 'testlength', $c);

        $opts = array_combine(range(1, 10), range(1, 10));
        $mform->addElement('select', 'itemcorrectrequired', get_string('itemcorrectrequired', $c), $opts);
        $mform->setDefault('itemcorrectrequired', 3);
        $mform->addHelpButton('itemcorrectrequired', 'itemcorrectrequired', $c);
        $mform->addElement('select', 'itemstreakrequired', get_string('itemstreakrequired', $c), $opts);
        $mform->setDefault('itemstreakrequired', 2);

        // Progression.
        $mform->addElement('header', 'progressionhdr', get_string('progressionsettings', $c));
        $mform->addElement('select', 'progressionmode', get_string('progressionmode', $c), [
            'dual' => get_string('progression_dual', $c),
            'linear' => get_string('progression_linear', $c),
            'level' => get_string('progression_level', $c),
            'open' => get_string('progression_open', $c),
        ]);
        $mform->setDefault('progressionmode', 'dual');
        $mform->addHelpButton('progressionmode', 'progressionmode', $c);

        // Training modes.
        $mform->addElement('header', 'modeshdr', get_string('trainingmodes', $c));
        $mform->addElement('advcheckbox', 'enablelearn', get_string('enablelearn', $c));
        $mform->setDefault('enablelearn', 1);
        $mform->addElement('advcheckbox', 'requirelearn', get_string('requirelearn', $c));
        $mform->disabledIf('requirelearn', 'enablelearn');
        $mform->addElement('advcheckbox', 'enableblitz', get_string('enableblitz', $c));
        $mform->setDefault('enableblitz', 1);
        $mform->addElement('text', 'blitzduration', get_string('blitzduration', $c), ['size' => 4]);
        $mform->setType('blitzduration', PARAM_INT);
        $mform->setDefault('blitzduration', 60);
        $mform->disabledIf('blitzduration', 'enableblitz');
        $mform->addElement('advcheckbox', 'blitzcountsmastery', get_string('blitzcountsmastery', $c));
        $mform->addHelpButton('blitzcountsmastery', 'blitzcountsmastery', $c);
        $mform->disabledIf('blitzcountsmastery', 'enableblitz');
        $mform->addElement('advcheckbox', 'enablestreak', get_string('enablestreak', $c));
        $mform->setDefault('enablestreak', 1);
        $mform->addElement('advcheckbox', 'enablehints', get_string('enablehints', $c));
        $mform->setDefault('enablehints', 1);
        $mform->addElement('advcheckbox', 'injectdifficult', get_string('injectdifficult', $c));
        $mform->setDefault('injectdifficult', 1);
        $mform->addHelpButton('injectdifficult', 'injectdifficult', $c);
        $mform->addElement('select', 'colourscheme', get_string('colourscheme', $c), [
            'light' => get_string('colourscheme_light', $c),
            'dark' => get_string('colourscheme_dark', $c),
        ]);
        $mform->setDefault('colourscheme', 'light');

        // Grade.
        $this->standard_grading_coursemodule_elements();
        $mform->addElement('select', 'grademethod', get_string('grademethod', $c), [
            1 => get_string('grademethod_topics', $c),
            2 => get_string('grademethod_test', $c),
        ]);
        $mform->setDefault('grademethod', 1);
        $mform->addHelpButton('grademethod', 'grademethod', $c);
        $mform->setDefault('grade', 100);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['masterythreshold'] < 1 || $data['masterythreshold'] > 100) {
            $errors['masterythreshold'] = get_string('err_percent', 'mod_vocabmastery');
        }
        if ($data['testlength'] < 0) {
            $errors['testlength'] = get_string('err_positive', 'mod_vocabmastery');
        }
        if ($data['itemstreakrequired'] > $data['itemcorrectrequired']) {
            $errors['itemstreakrequired'] = get_string('err_streakgtcorrect', 'mod_vocabmastery');
        }
        if (!empty($data['enableblitz']) && ($data['blitzduration'] < 10 || $data['blitzduration'] > 600)) {
            $errors['blitzduration'] = get_string('err_blitzduration', 'mod_vocabmastery');
        }
        $suffix = $this->get_suffix();
        if (!empty($data['completionprogressenabled' . $suffix])) {
            $v = (int)($data['completionprogress' . $suffix] ?? 0);
            if ($v < 1 || $v > 100) {
                $errors['completionprogressgroup' . $suffix] = get_string('err_percent', 'mod_vocabmastery');
            }
        }
        if (!empty($data['completiontopicsenabled' . $suffix]) && (int)($data['completiontopics' . $suffix] ?? 0) < 1) {
            $errors['completiontopicsgroup' . $suffix] = get_string('err_positive', 'mod_vocabmastery');
        }
        return $errors;
    }

    /**
     * Custom completion rules.
     *
     * @return array
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $c = 'mod_vocabmastery';
        $suffix = $this->get_suffix();

        $group = [
            $mform->createElement('checkbox', 'completiontopicsenabled' . $suffix, '', get_string('completiontopics', $c)),
            $mform->createElement('text', 'completiontopics' . $suffix, '', ['size' => 3]),
        ];
        $mform->setType('completiontopics' . $suffix, PARAM_INT);
        $mform->addGroup($group, 'completiontopicsgroup' . $suffix, '', ' ', false);
        $mform->disabledIf('completiontopics' . $suffix, 'completiontopicsenabled' . $suffix, 'notchecked');

        $mform->addElement('checkbox', 'completionalltopics' . $suffix, '', get_string('completionalltopics', $c));

        $group = [
            $mform->createElement('checkbox', 'completionprogressenabled' . $suffix, '', get_string('completionprogress', $c)),
            $mform->createElement('text', 'completionprogress' . $suffix, '', ['size' => 3]),
        ];
        $mform->setType('completionprogress' . $suffix, PARAM_INT);
        $mform->addGroup($group, 'completionprogressgroup' . $suffix, '', ' ', false);
        $mform->disabledIf('completionprogress' . $suffix, 'completionprogressenabled' . $suffix, 'notchecked');

        return ['completiontopicsgroup' . $suffix, 'completionalltopics' . $suffix, 'completionprogressgroup' . $suffix];
    }

    /**
     * Whether any custom rule is enabled.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return (!empty($data['completiontopicsenabled' . $suffix]) && !empty($data['completiontopics' . $suffix]))
            || !empty($data['completionalltopics' . $suffix])
            || (!empty($data['completionprogressenabled' . $suffix]) && !empty($data['completionprogress' . $suffix]));
    }

    /**
     * Prepare defaults.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $suffix = $this->get_suffix();
        $defaultvalues['completiontopicsenabled' . $suffix] = !empty($defaultvalues['completiontopics']) ? 1 : 0;
        $defaultvalues['completionprogressenabled' . $suffix] = !empty($defaultvalues['completionprogress']) ? 1 : 0;
        if (isset($defaultvalues['completiontopics'])) {
            $defaultvalues['completiontopics' . $suffix] = $defaultvalues['completiontopics'] ?: '';
        }
        if (isset($defaultvalues['completionprogress'])) {
            $defaultvalues['completionprogress' . $suffix] = $defaultvalues['completionprogress'] ?: '';
        }
        if (isset($defaultvalues['completionalltopics'])) {
            $defaultvalues['completionalltopics' . $suffix] = $defaultvalues['completionalltopics'];
        }
    }

    /**
     * Post-process submitted data (map suffixed completion fields back).
     *
     * @param stdClass $data
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();
        if (empty($data->completionunlocked)) {
            // Completion settings are locked: keep existing values untouched.
            unset($data->completiontopics, $data->completionprogress, $data->completionalltopics);
            return;
        }
        $auto = isset($data->{'completion' . $suffix}) && $data->{'completion' . $suffix} == COMPLETION_TRACKING_AUTOMATIC;
        $data->completiontopics = ($auto && !empty($data->{'completiontopicsenabled' . $suffix}))
            ? (int)($data->{'completiontopics' . $suffix} ?? 0) : 0;
        $data->completionprogress = ($auto && !empty($data->{'completionprogressenabled' . $suffix}))
            ? (int)($data->{'completionprogress' . $suffix} ?? 0) : 0;
        $data->completionalltopics = ($auto && !empty($data->{'completionalltopics' . $suffix})) ? 1 : 0;
    }
}
