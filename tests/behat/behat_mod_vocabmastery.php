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
// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_vocabmastery extends behat_base {
    /**
     * Pages: "Report", "Manage" and "Import" of an activity (by idnumber or name).
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $cm = $this->get_cm_by_activity_name('vocabmastery', $identifier);
        switch (strtolower($type)) {
            case 'report':
                return new moodle_url('/mod/vocabmastery/report.php', ['id' => $cm->id]);
            case 'manage':
                return new moodle_url('/mod/vocabmastery/manage.php', ['id' => $cm->id]);
            case 'import':
                return new moodle_url('/mod/vocabmastery/import.php', ['id' => $cm->id, 'source' => 'table']);
            default:
                throw new Exception('Unrecognised vocabmastery page type "' . $type . '"');
        }
    }

    /**
     * Keep answering with the given button until the results screen shows.
     *
     * @Given /^I answer "(?P<answer_string>(?:[^"]|\\")*)" to every vocabulary question$/
     * @param string $answer
     */
    public function i_answer_to_every_vocabulary_question(string $answer): void {
        for ($i = 0; $i < 60; $i++) {
            $page = $this->getSession()->getPage();
            if ($page->find('css', '.vm-res')) {
                return;
            }
            foreach ($page->findAll('css', 'button.vm-answer') as $button) {
                if (
                    $button->isVisible() && !$button->hasAttribute('disabled')
                        && $button->getAttribute('data-value') === $answer
                ) {
                    $button->click();
                    break;
                }
            }
            $this->getSession()->wait(700);
        }
        throw new ExpectationException('The results screen did not appear.', $this->getSession());
    }
}
