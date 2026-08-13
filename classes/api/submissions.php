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

namespace mod_vimipad\api;

/**
 * Public facade for questions about submitted snapshots.
 *
 * Plugins that copy a submitted map elsewhere - a gallery materialising an
 * album, for instance - need to know whose work the copy contains, so their own
 * privacy provider can find, export and anonymise it. That question can only be
 * answered from the operation log and the element authorship columns, which are
 * internal. This facade answers it without exposing the schema, so consumers do
 * not have to query \mod_vimipad\local tables directly.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submissions {
    /**
     * The users who contributed to the workspace a snapshot was taken from.
     *
     * For an individual workspace this is normally just its owner. For a group
     * workspace it is every group member who actually did something, which is
     * the case that matters: a frozen group map is joint work, so a copy of it
     * carries several people's contributions rather than one author's.
     *
     * Anonymised contributions (recorded as user 0 after a deletion request) are
     * not returned: there is no longer a person to attribute them to.
     *
     * @param int $snapshotid The snapshot id.
     * @return int[] The contributing user ids, ascending. Empty if unknown.
     */
    public static function contributors(int $snapshotid): array {
        global $DB;

        $workspaceid = $DB->get_field('vimipad_snapshot', 'workspaceid', ['id' => $snapshotid]);
        if (!$workspaceid) {
            return [];
        }
        return self::workspace_contributors((int) $workspaceid);
    }

    /**
     * The users who contributed to a workspace.
     *
     * @param int $workspaceid The workspace id.
     * @return int[] The contributing user ids, ascending. Empty if unknown.
     */
    public static function workspace_contributors(int $workspaceid): array {
        global $DB;

        // The operation log is the authoritative record of who did what: element
        // authorship columns can be rewritten by later edits, the log cannot.
        $sql = "SELECT DISTINCT userid
                  FROM {vimipad_operation}
                 WHERE workspaceid = :workspaceid AND userid > 0";
        $ids = $DB->get_fieldset_sql($sql, ['workspaceid' => $workspaceid]);

        // A workspace filled by import or restore may have no operations; fall
        // back to the element authorship so such a map is not left unattributed.
        if (empty($ids)) {
            $sql = "SELECT DISTINCT createdby
                      FROM {vimipad_node}
                     WHERE workspaceid = :workspaceid AND createdby > 0";
            $ids = $DB->get_fieldset_sql($sql, ['workspaceid' => $workspaceid]);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $ids;
    }
}
