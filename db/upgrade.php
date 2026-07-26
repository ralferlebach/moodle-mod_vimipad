<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade steps for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_vimipad upgrade from the given old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_vimipad_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026072600) {
        // Introduce the full domain model (M1): workspaces, nodes, relations,
        // containers, memberships, layout, operation log, snapshots,
        // annotations, AI feedback and journal entries.
        $tables = [
            'vimipad_workspace', 'vimipad_node', 'vimipad_relation',
            'vimipad_container', 'vimipad_membership', 'vimipad_layout',
            'vimipad_operation', 'vimipad_snapshot', 'vimipad_annotation',
            'vimipad_aifeedback', 'vimipad_journalentry',
        ];
        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file(
                    __DIR__ . '/install.xml',
                    $tablename
                );
            }
        }

        upgrade_mod_savepoint(true, 2026072600, 'vimipad');
    }

    if ($oldversion < 2026072605) {
        // M4: gradebook + completion-on-submit support.
        $table = new xmldb_table('vimipad');

        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100', 'aienabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('completionsubmit', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grade');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $gradetable = new xmldb_table('vimipad_grade');
        if (!$dbman->table_exists($gradetable)) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'vimipad_grade');
        }

        upgrade_mod_savepoint(true, 2026072605, 'vimipad');
    }

    return true;
}
