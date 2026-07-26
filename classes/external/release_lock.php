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
use mod_vimipad\local\service\lock_service;

/**
 * External function: release an editing lease the caller holds.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class release_lock extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'targettype' => new external_value(PARAM_ALPHA, 'Element type: node or relation'),
            'targetstableid' => new external_value(PARAM_ALPHANUMEXT, 'Element stable id'),
        ]);
    }

    /**
     * Validate access and release the lease.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param string $targettype Element type.
     * @param string $targetstableid Element stable id.
     * @return array{status: bool}
     */
    public static function execute(int $cmid, int $workspaceid, string $targettype, string $targetstableid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'targettype' => $targettype,
            'targetstableid' => $targetstableid,
        ]);

        helper::validate_workspace_for_edit($params['cmid'], $params['workspaceid'], $instance, $workspace);

        $service = new lock_service();
        $service->release(
            (int) $workspace->id,
            $params['targettype'],
            $params['targetstableid'],
            (int) $USER->id
        );

        return ['status' => true];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True on success'),
        ]);
    }
}
