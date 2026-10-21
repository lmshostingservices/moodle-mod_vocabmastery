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
 * Data generator for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_vocabmastery_generator extends testing_module_generator {
    /**
     * Create an activity instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        if (!isset($record->grade)) {
            $record->grade = 100;
        }
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Create one level, topic and set with the given items.
     *
     * @param int $vmid activity instance id
     * @param array $items list of [prompt, correct answer] pairs
     * @param array $setdata extra set fields
     * @return array ['levelid' => int, 'topicid' => int, 'setid' => int, 'itemids' => int[]]
     */
    public function create_topic_with_items(int $vmid, array $items, array $setdata = []): array {
        $levelid = \mod_vocabmastery\content::add_level($vmid, 'A1');
        $topicid = \mod_vocabmastery\content::add_topic($vmid, $levelid, 'Lektion 1', 1);
        $setid = \mod_vocabmastery\content::add_set(
            $vmid,
            $topicid,
            $setdata + [
                'name' => 'Nomen + Artikel',
                'options' => "der\ndie\ndas",
            ]
        );
        $itemids = [];
        foreach ($items as [$prompt, $answer]) {
            $itemids[] = \mod_vocabmastery\content::add_item($vmid, $setid, ['prompt' => $prompt, 'correctvalue' => $answer]);
        }
        return ['levelid' => $levelid, 'topicid' => $topicid, 'setid' => $setid, 'itemids' => $itemids];
    }

    /**
     * Create one word, creating its level, topic and set by name when needed (used by Behat).
     *
     * @param array $data vocabmasteryid, level, topic, set, prompt, answer, options (set buttons, "/"-separated)
     * @return int item id
     */
    public function create_word(array $data): int {
        $vmid = (int)$data['vocabmasteryid'];
        $levelid = \mod_vocabmastery\content::level_by_name($vmid, $data['level'] ?? 'A1');
        $topicid = \mod_vocabmastery\content::topic_by_name($vmid, $levelid, $data['topic']);
        $options = str_replace('/', "\n", $data['options'] ?? 'der/die/das');
        $setid = \mod_vocabmastery\content::set_by_name(
            $vmid,
            $topicid,
            $data['set'] ?? 'Nomen + Artikel',
            [
                'itemtype' => 'choice', 'answermode' => 'fixed', 'options' => $options,
                'numoptions' => count(explode("\n", $options)), 'gated' => 1,
            ]
        );
        return \mod_vocabmastery\content::add_item(
            $vmid,
            $setid,
            [
                'prompt' => $data['prompt'],
                'correctvalue' => $data['answer'], 'translation' => $data['translation'] ?? '',
            ]
        );
    }
}
