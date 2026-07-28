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

namespace mod_vimipad\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_vimipad\local\service\journal_service;

/**
 * External function: return the current user's own journal entries.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_journal_entries extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
        ]);
    }

    /**
     * Return the calling user's journal entries for a workspace.
     *
     * @param int $cmid The course module id.
     * @param int $workspaceid The workspace id.
     * @return array The entries.
     */
    public static function execute(int $cmid, int $workspaceid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
        ]);

        helper::validate_workspace_for_edit($params['cmid'], $params['workspaceid'], $instance, $workspace);

        $service = new journal_service();
        $entries = $service->get_entries_for_user($params['workspaceid'], (int) $USER->id);

        $out = [];
        foreach ($entries as $entry) {
            $out[] = [
                'id' => (int) $entry->id,
                'entrytext' => (string) $entry->entrytext,
                'visibility' => (int) $entry->visibility,
                'timecreated' => (int) $entry->timecreated,
            ];
        }

        return ['entries' => $out];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entries' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Entry id'),
                'entrytext' => new external_value(PARAM_RAW, 'Entry text'),
                'visibility' => new external_value(PARAM_INT, '0 private, 1 teacher-visible'),
                'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
            ])),
        ]);
    }
}
