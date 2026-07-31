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

namespace mod_vimipad\local;

/**
 * Shared cascade deletion for workspaces and their dependent rows.
 *
 * Used by both instance deletion (lib.php) and the privacy provider so the
 * cascade lives in exactly one place. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup {
    /**
     * Delete the given workspaces and every row that depends on them.
     *
     * @param int[] $workspaceids The workspace ids to purge.
     * @return void
     */
    public static function delete_workspaces(array $workspaceids): void {
        global $DB;

        if (empty($workspaceids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($workspaceids, SQL_PARAMS_NAMED);

        $snapshotids = $DB->get_fieldset_select('vimipad_snapshot', 'id', "workspaceid $insql", $params);
        if (!empty($snapshotids)) {
            [$sinsql, $sparams] = $DB->get_in_or_equal($snapshotids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('vimipad_annotation', "snapshotid $sinsql", $sparams);
            $DB->delete_records_select('vimipad_aifeedback', "snapshotid $sinsql", $sparams);
            $DB->delete_records_select('vimipad_gradeinstance', "snapshotid $sinsql", $sparams);
            $DB->delete_records_select('vimipad_peerreview', "snapshotid $sinsql", $sparams);
            // Clear any reference pointer at a deleted snapshot. The frozen
            // reference copy (referencemapjson) on the activity is deliberately
            // kept: the model solution is course configuration and its JSON
            // carries no user identifiers.
            [$rinsql, $rparams] = $DB->get_in_or_equal($snapshotids, SQL_PARAMS_NAMED);
            $DB->set_field_select('vimipad', 'referencesnapshotid', null, "referencesnapshotid $rinsql", $rparams);
        }

        $containerids = $DB->get_fieldset_select('vimipad_container', 'id', "workspaceid $insql", $params);
        if (!empty($containerids)) {
            [$cinsql, $cparams] = $DB->get_in_or_equal($containerids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('vimipad_membership', "containerid $cinsql", $cparams);
        }

        $childtables = [
            'vimipad_node', 'vimipad_relation', 'vimipad_container', 'vimipad_layout',
            'vimipad_operation', 'vimipad_snapshot', 'vimipad_journalentry', 'vimipad_lock',
            'vimipad_submissionintent',
        ];
        foreach ($childtables as $table) {
            $DB->delete_records_select($table, "workspaceid $insql", $params);
        }

        $DB->delete_records_list('vimipad_workspace', 'id', $workspaceids);
    }
}
