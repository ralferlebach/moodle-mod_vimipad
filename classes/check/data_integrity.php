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

namespace mod_vimipad\check;

use core\check\check;
use core\check\result;

/**
 * Status check: no orphaned child rows in the workspace data model.
 *
 * Flags workspace children (nodes, relations, containers, memberships, layout,
 * layout history, operations, snapshots) whose parent no longer exists, and
 * relations whose endpoints reference missing nodes. Such rows should never
 * occur — a warning here points to a deletion or restore bug.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_integrity extends check {
    /**
     * Run the integrity scan.
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;

        $problems = [];

        // Workspace children whose workspace no longer exists.
        $childtables = [
            'vimipad_node', 'vimipad_relation', 'vimipad_container', 'vimipad_layout',
            'vimipad_layouthist', 'vimipad_operation', 'vimipad_snapshot',
        ];
        foreach ($childtables as $table) {
            $count = $DB->count_records_sql(
                "SELECT COUNT(c.id) FROM {" . $table . "} c
                  LEFT JOIN {vimipad_workspace} ws ON ws.id = c.workspaceid
                  WHERE ws.id IS NULL"
            );
            if ($count > 0) {
                $problems[] = get_string('check:orphanrows', 'mod_vimipad', (object) [
                    'count' => $count, 'table' => $table,
                ]);
            }
        }

        // Memberships whose container no longer exists.
        $orphanmemberships = $DB->count_records_sql(
            "SELECT COUNT(m.id) FROM {vimipad_membership} m
              LEFT JOIN {vimipad_container} c ON c.id = m.containerid
              WHERE c.id IS NULL"
        );
        if ($orphanmemberships > 0) {
            $problems[] = get_string('check:orphanrows', 'mod_vimipad', (object) [
                'count' => $orphanmemberships, 'table' => 'vimipad_membership',
            ]);
        }

        if (empty($problems)) {
            return new result(result::OK, get_string('check:integrityok', 'mod_vimipad'));
        }
        return new result(
            result::WARNING,
            get_string('check:integrityproblems', 'mod_vimipad', count($problems)),
            \html_writer::alist($problems)
        );
    }
}
