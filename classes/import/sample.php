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
 * Sample import files and the ChatGPT preparation prompt.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sample {
    /** Column keys in import order (recognised automatically by the importer). */
    public const COLUMNS = ['level', 'topic', 'set', 'answer', 'prompt', 'translation', 'plural', 'alternatives', 'hint', 'core'];

    /** Example vocabulary rows (content, taken from the A1 and B1 lesson overviews). */
    public const ROWS = [
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'die', 'Frau', 'woman / Ms', 'Frauen', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'der', 'Herr', 'Mr', '', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'der', 'Name', 'name', 'Namen', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'der', 'Vorname', 'first name', 'Vornamen', '',
            'Compound nouns take the article of the last word: der Name.', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'das', 'Land', 'country', 'Länder', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'die', 'Sprache', 'language', 'Sprachen', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'das', 'Jahr', 'year', 'Jahre', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Nomen + Artikel', 'der', 'Familienname', 'family name', 'Familiennamen', '', '', '0'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Verben', 'to be called', 'heißen', '', '', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Verben', 'to live', 'wohnen', '', '', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Verben', 'to learn', 'lernen', '', '', '', '', '1'],
        ['A1', 'Lektion 1 – Hallo Welt!', 'Verben', 'to speak', 'sprechen', '', '', '', '', '1'],
        ['B1', 'Lektion 1 – Ankommen', 'Verb + Präposition', 'in + Akk.', 'sich einleben', 'to settle into', '', '', '', '1'],
        ['B1', 'Lektion 1 – Ankommen', 'Verb + Präposition', 'für + Akk.', 'sich engagieren', 'to get involved in', '',
            'für + Akkusativ', '', '1'],
        ['B1', 'Lektion 1 – Ankommen', 'Verb + Präposition', 'mit + Dat.', 'befreundet sein', 'to be friends with', '',
            'mit + Dativ', '', '1'],
    ];

    /**
     * The ChatGPT prompt.
     *
     * @return string
     */
    public static function chatgpt_prompt(): string {
        return get_string('chatgptprompt', 'mod_vocabmastery');
    }

    /**
     * Send the sample as an Excel download (uses Moodle's Excel library on every supported version).
     */
    public static function send_xlsx(): void {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');
        $workbook = new \MoodleExcelWorkbook('vocabulary_import_example');
        $sheet = $workbook->add_worksheet(get_string('samplesheetname', 'mod_vocabmastery'));
        $head = $workbook->add_format(['bold' => 1, 'color' => 'white', 'bg_color' => '#4F46E5']);
        $cell = $workbook->add_format(['color' => '#0000FF']);
        foreach (self::COLUMNS as $c => $name) {
            $sheet->write_string(0, $c, $name, $head);
        }
        foreach (self::ROWS as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->write_string($r + 1, $c, $value, $cell);
            }
        }
        $widths = [8, 26, 20, 14, 18, 20, 15, 17, 40, 7];
        foreach ($widths as $c => $w) {
            $sheet->set_column($c, $c, $w);
        }
        $help = $workbook->add_worksheet(get_string('samplehelpsheetname', 'mod_vocabmastery'));
        $bold = $workbook->add_format(['bold' => 1]);
        $help->set_column(0, 0, 18);
        $help->set_column(1, 1, 100);
        $help->write_string(0, 0, get_string('samplehelptitle', 'mod_vocabmastery'), $bold);
        $line = 2;
        foreach (self::COLUMNS as $name) {
            $help->write_string($line, 0, $name, $bold);
            $help->write_string($line, 1, get_string('col_' . $name . '_desc', 'mod_vocabmastery'));
            $line++;
        }
        $line++;
        $help->write_string($line, 0, get_string('samplehelpnote', 'mod_vocabmastery'));
        $workbook->close();
    }

    /**
     * Send the sample as CSV.
     */
    public static function send_csv(): void {
        $rows = array_map(fn($r) => $r, self::ROWS);
        \core\dataformat::download_data('vocabulary_import_example', 'csv', self::COLUMNS, new \ArrayIterator($rows));
    }

    /**
     * Send the ChatGPT prompt as a text file.
     */
    public static function send_prompt(): void {
        send_file(self::chatgpt_prompt(), 'chatgpt_vocabulary_prompt.txt', 0, 0, true, true, 'text/plain');
    }
}
