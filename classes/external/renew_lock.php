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
 * External function: renew (heartbeat) an editing lease the caller holds.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renew_lock extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return helper::lock_parameters();
    }

    /**
     * Validate access and renew the lease.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param string $targettype Element type.
     * @param string $targetstableid Element stable id.
     * @return array{acquired: bool, userid: int, timeexpires: int}
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
        $result = $service->renew(
            (int) $workspace->id,
            $params['targettype'],
            $params['targetstableid'],
            (int) $USER->id,
            helper::lease_ttl()
        );

        return (array) $result;
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'acquired' => new external_value(PARAM_BOOL, 'True if the caller still holds the lease'),
            'userid' => new external_value(PARAM_INT, 'Current holder user id'),
            'timeexpires' => new external_value(PARAM_INT, 'Lease expiry timestamp'),
        ]);
    }
}
