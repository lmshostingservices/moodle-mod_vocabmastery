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
 * Upgrade steps for mod_vocabmastery.
 *
 * @package    mod_vocabmastery
 * @copyright  2026 LMS Hosting Services
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_vocabmastery_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026092800) {
        // Light is now the default colour scheme.
        $table = new xmldb_table('vocabmastery');
        $field = new xmldb_field('colourscheme', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'light', 'injectdifficult');
        $dbman->change_field_default($table, $field);
        upgrade_mod_savepoint(true, 2026092800, 'vocabmastery');
    }

    if ($oldversion < 2026092900) {
        // Learners can report a problem with a word.
        $table = new xmldb_table('vocabmastery_flag');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('vocabmasteryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reason', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'other');
        $table->add_field('commenttext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('resolved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('vocabmasteryid', XMLDB_KEY_FOREIGN, ['vocabmasteryid'], 'vocabmastery', ['id']);
        $table->add_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'vocabmastery_item', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('vmresolved', XMLDB_INDEX_NOTUNIQUE, ['vocabmasteryid', 'resolved']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026092900, 'vocabmastery');
    }

    if ($oldversion < 2026102100) {
        // Backward progression within a level, and hiding answers after the mastery check.
        $table = new xmldb_table('vocabmastery');
        $field = new xmldb_field('openprevious', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'unlockby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('testreview', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'show', 'openprevious');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102100, 'vocabmastery');
    }
    return true;
}
