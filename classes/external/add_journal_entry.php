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
use core_external\external_single_structure;
use core_external\external_value;
use mod_vimipad\local\service\journal_service;

/**
 * External function: add a journal entry to the caller's own journal.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_journal_entry extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'entrytext' => new external_value(PARAM_RAW, 'Entry text'),
            'visibility' => new external_value(PARAM_INT, '0 private, 1 teacher-visible', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Store a new journal entry authored by the calling user.
     *
     * @param int $cmid The course module id.
     * @param int $workspaceid The workspace id.
     * @param string $entrytext The entry text.
     * @param int $visibility 0 private, 1 teacher-visible.
     * @return array The new entry id.
     */
    public static function execute(int $cmid, int $workspaceid, string $entrytext, int $visibility = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'entrytext' => $entrytext,
            'visibility' => $visibility,
        ]);

        helper::validate_workspace_for_edit($params['cmid'], $params['workspaceid'], $instance, $workspace);

        if (trim($params['entrytext']) === '') {
            throw new \moodle_exception('error:emptyjournal', 'mod_vimipad');
        }

        $service = new journal_service();
        $id = $service->add_entry(
            $params['workspaceid'],
            (int) $USER->id,
            $params['entrytext'],
            FORMAT_PLAIN,
            $params['visibility']
        );

        return ['id' => $id];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new journal entry id'),
        ]);
    }
}
