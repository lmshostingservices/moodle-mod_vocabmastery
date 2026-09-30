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

namespace mod_vocabmastery\import;

use mod_vocabmastery\engine;
use mod_vocabmastery\instance;

/**
 * Tests for the importer: set columns, heuristics and replace mode.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\import\importer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\import\table_parser::class)]
final class importer_test extends \advanced_testcase {
    /**
     * Empty activity.
     *
     * @return instance
     */
    protected function make_instance(): instance {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $vm = $gen->create_module('vocabmastery', ['course' => $course->id]);
        return instance::from_cmid((int)$vm->cmid);
    }

    /**
     * Records from CSV text with a header row.
     *
     * @param string $csv
     * @return array
     */
    protected function records(string $csv): array {
        $rows = table_parser::parse_text($csv);
        $guess = table_parser::guess_mapping($rows);
        return table_parser::to_records($rows, $guess['mapping'], $guess['hasheader']);
    }

    public function test_set_columns_from_the_file_are_used(): void {
        global $DB;
        $instance = $this->make_instance();
        $csv = "level,topic,set,answer,prompt,core,gated,answer mode,instruction\n" .
            "A1,Lektion 1,Verben,to live,wohnen,1,0,pool,What does it mean?\n" .
            "A1,Lektion 1,Verben,to come,kommen,1,0,pool,What does it mean?\n" .
            "A1,Lektion 1,Nomen,der,Vater,1,1,fixed,Which article?\n" .
            "A1,Lektion 1,Nomen,die,Mutter,1,1,fixed,Which article?\n";
        $records = $this->records($csv);
        $this->assertSame(0, $records[0]['gated']);
        $this->assertSame('pool', $records[0]['answermode']);
        (new importer($instance))->import_records($records, 'A1', 'Lektion 1', 'Set');
        $verbs = $DB->get_record('vocabmastery_set', ['name' => 'Verben']);
        $this->assertSame('pool', $verbs->answermode);
        $this->assertEquals(0, $verbs->gated);
        $this->assertSame('What does it mean?', $verbs->instruction);
        $nouns = $DB->get_record('vocabmastery_set', ['name' => 'Nomen']);
        $this->assertSame('fixed', $nouns->answermode);
        $this->assertEquals(1, $nouns->gated);
        $this->assertSame('Which article?', $nouns->instruction);
        $this->assertSame(4, $DB->count_records('vocabmastery_item'));
    }

    public function test_few_unique_answers_are_not_turned_into_buttons(): void {
        // Five verbs with five different meanings: a pool, not five fixed buttons.
        $settings = docx_parser::settings_for('generic', ['to live', 'to come', 'to go', 'to say', 'to do'], 5);
        $this->assertSame('pool', $settings['answermode']);
        // Prepositions that repeat across the words: fixed buttons.
        $settings = docx_parser::settings_for('generic', ['auf', 'an', 'mit'], 8);
        $this->assertSame('fixed', $settings['answermode']);
    }

    public function test_replace_keeps_progress_on_unchanged_words(): void {
        global $DB;
        $instance = $this->make_instance();
        $csv = "topic,set,answer,prompt,translation\nL1,Nomen,der,Vater,father\nL1,Nomen,die,Mutter,mother\n";
        (new importer($instance))->import_records($this->records($csv), 'A1', 'L1', 'Nomen');
        $vater = $DB->get_record('vocabmastery_item', ['prompt' => 'Vater']);
        $user = $this->getDataGenerator()->create_user();
        (new engine($instance, (int)$user->id))->update_item_state((int)$vater->id, true);

        $csv2 = "topic,set,answer,prompt,translation\nL1,Nomen,der,Vater,dad\nL1,Nomen,das,Kind,child\n";
        $stats = (new importer($instance, 'replace'))->import_records($this->records($csv2), 'A1', 'L1', 'Nomen');
        $this->assertSame(1, $stats['updated']);
        $this->assertSame(1, $stats['created']);
        $this->assertSame(1, $stats['deleted']);
        $kept = $DB->get_record('vocabmastery_item', ['id' => $vater->id]);
        $this->assertSame('dad', $kept->translation);
        $this->assertTrue($DB->record_exists('vocabmastery_itemstate', ['itemid' => $vater->id]));
        $this->assertFalse($DB->record_exists('vocabmastery_item', ['prompt' => 'Mutter']));
    }

    public function test_oversized_spreadsheet_is_refused(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/excellib.class.php');
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'prompt');
        $sheet->setCellValue('AF1', 'too wide');
        $path = make_request_directory() . '/wide.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
        $this->expectException(\moodle_exception::class);
        table_parser::parse_file($path, 'wide.xlsx');
    }
}
