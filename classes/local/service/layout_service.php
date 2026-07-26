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

namespace mod_vimipad\local\service;

/**
 * Persists per-profile layout state for a workspace.
 *
 * Layout is presentation state, deliberately kept out of the operation log and
 * the revision counter: repositioning a node must not create semantic
 * conflicts with concurrent editors. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_service {
    /**
     * Upsert the layout for a workspace and profile.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param string $layoutjson The layout JSON payload.
     * @param string $viewportjson The viewport JSON payload.
     * @param int $userid The acting user id.
     * @return void
     */
    public function save(
        int $workspaceid,
        string $profile,
        string $layoutjson,
        string $viewportjson,
        int $userid
    ): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record(
            'vimipad_layout',
            ['workspaceid' => $workspaceid, 'profile' => $profile]
        );

        if ($existing) {
            $DB->update_record('vimipad_layout', (object) [
                'id' => $existing->id,
                'viewportjson' => $viewportjson,
                'layoutjson' => $layoutjson,
                'modifiedby' => $userid,
                'timemodified' => $now,
            ]);
            return;
        }

        $DB->insert_record('vimipad_layout', (object) [
            'workspaceid' => $workspaceid,
            'profile' => $profile,
            'viewportjson' => $viewportjson,
            'layoutjson' => $layoutjson,
            'modifiedby' => $userid,
            'timemodified' => $now,
        ]);
    }

    /**
     * Return the stored layout JSON for a workspace and profile.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @return string The layout JSON, or an empty string if none stored.
     */
    public function get_layout_json(int $workspaceid, string $profile): string {
        global $DB;

        $record = $DB->get_record(
            'vimipad_layout',
            ['workspaceid' => $workspaceid, 'profile' => $profile]
        );

        return $record && $record->layoutjson !== null ? $record->layoutjson : '';
    }
}
