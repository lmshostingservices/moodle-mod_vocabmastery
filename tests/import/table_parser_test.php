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

/**
 * Tests for table (paste / CSV / Excel) import.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\import\table_parser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_vocabmastery\import\importer::class)]
final class table_parser_test extends \advanced_testcase {
    public function test_parse_pasted_text_and_guess_mapping(): void {
        $rows = table_parser::parse_text("Artikel\tWort\tEnglisch\nder\tVater\tfather\ndie\tMutter\tmother\ndas\tKind\tchild");
        $this->assertCount(4, $rows);
        $guess = table_parser::guess_mapping($rows);
        $this->assertTrue((bool)$guess['hasheader']);
        $this->assertSame(['correctvalue', 'prompt', 'translation'], array_values($guess['mapping']));
        $records = table_parser::to_records($rows, $guess['mapping'], true);
        $this->assertCount(3, $records);
        $this->assertSame('Vater', $records[0]['prompt']);
    }

    public function test_core_column_values(): void {
        $rows = [['answer', 'prompt', 'core'], ['der', 'Name', '1'], ['der', 'Familienname', '0'], ['das', 'Land', 'nein']];
        $records = table_parser::to_records($rows, ['correctvalue', 'prompt', 'iscore'], true);
        $this->assertSame([1, 0, 0], array_column($records, 'iscore'));
    }

    /**
     * The sample file offered on the import page must parse (regression: PhpSpreadsheet was not
     * autoloaded on Moodle 4.4/4.5).
     */
    public function test_parse_sample_xlsx(): void {
        global $CFG;
        $path = $CFG->dirroot . '/mod/vocabmastery/tests/fixtures/vocabulary_import_example.xlsx';
        $rows = table_parser::parse_file($path, 'vocabulary_import_example.xlsx');
        $this->assertCount(count(sample::ROWS) + 1, $rows);
        $this->assertSame(sample::COLUMNS, array_map('strval', $rows[0]));
        $guess = table_parser::guess_mapping($rows);
        $this->assertSame(
            [
                'level', 'topic', 'set', 'correctvalue', 'prompt', 'translation', 'plural', 'altanswers', 'hint',
                'iscore',
            ],
            array_values($guess['mapping'])
        );
    }

    public function test_import_records_builds_structure_and_skips_duplicates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $vm = $this->getDataGenerator()->create_module('vocabmastery', ['course' => $course->id]);
        $instance = \mod_vocabmastery\instance::from_cmid((int)$vm->cmid);
        $rows = array_merge([sample::COLUMNS], sample::ROWS);
        $mapping = table_parser::guess_mapping($rows)['mapping'];
        $records = table_parser::to_records($rows, $mapping, true);
        $this->assertSame([], importer::validate_records($records));

        $stats = (new importer($instance, 'skip'))->import_records($records, 'X', 'Y', 'Z');
        $this->assertSame(2, $stats['levels']);
        $this->assertSame(2, $stats['topics']);
        $this->assertSame(3, $stats['sets']);
        $this->assertSame(count(sample::ROWS), $stats['created']);

        $again = (new importer(\mod_vocabmastery\instance::from_cmid((int)$vm->cmid), 'skip'))
            ->import_records($records, 'X', 'Y', 'Z');
        $this->assertSame(0, $again['created']);
        $this->assertSame(count(sample::ROWS), $again['skipped']);
    }
}
