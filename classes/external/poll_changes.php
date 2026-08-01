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
use mod_vimipad\local\service\lock_service;
use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\service\layout_service;

/**
 * External function: poll for collaborative changes.
 *
 * Returns, in one round-trip: operations applied since the client's known
 * revision, the current layout (so positions can be tweened), and the active
 * element leases (presence). This is the read side of the polling loop.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class poll_changes extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'sincerevision' => new external_value(PARAM_INT, 'The revision the client already has'),
            'layoutsince' => new external_value(
                PARAM_INT,
                'The layout modification time the client already has (0 = none)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Validate access and gather the delta, layout and presence.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param int $sincerevision The revision the client already has.
     * @param int $layoutsince The layout modification time the client already has.
     * @return array The poll payload.
     */
    public static function execute(int $cmid, int $workspaceid, int $sincerevision, int $layoutsince = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'sincerevision' => $sincerevision,
            'layoutsince' => $layoutsince,
        ]);

        helper::validate_workspace_for_edit($params['cmid'], $params['workspaceid'], $instance, $workspace);

        $operationservice = new operation_service();
        $batch = 200;
        $operations = $operationservice->get_operations_since(
            (int) $workspace->id,
            $params['sincerevision'],
            $batch + 1
        );
        $hasmore = count($operations) > $batch;
        if ($hasmore) {
            $operations = array_slice($operations, 0, $batch);
        }

        $operationsout = [];
        foreach ($operations as $op) {
            $operationsout[] = [
                'revision' => (int) $op->revision,
                'operationtype' => $op->operationtype,
                'payloadjson' => (string) $op->payloadjson,
                'userid' => (int) $op->userid,
            ];
        }

        $layoutservice = new layout_service();
        $layout = $layoutservice->get_layout_since(
            (int) $workspace->id,
            $instance->defaultprofile,
            (int) $params['layoutsince']
        );

        $lockservice = new lock_service();
        // Expired-lease cleanup runs in the purge_expired_locks scheduled task,
        // not in this hot read path.
        $leases = [];
        foreach ($lockservice->get_active_leases((int) $workspace->id) as $lease) {
            $leases[] = [
                'targettype' => $lease->targettype,
                'targetstableid' => $lease->targetstableid,
                'userid' => (int) $lease->userid,
                'timeexpires' => (int) $lease->timeexpires,
            ];
        }

        return [
            'revision' => (int) $workspace->currentrevision,
            'locked' => (int) $workspace->locked,
            'profile' => $instance->defaultprofile,
            'operations' => $operationsout,
            'hasmore' => $hasmore,
            'layoutjson' => $layout['layoutjson'],
            'layouttime' => $layout['revision'],
            'leases' => $leases,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'revision' => new external_value(PARAM_INT, 'The workspace current revision'),
            'locked' => new external_value(PARAM_INT, '1 if the workspace is locked (submitted)'),
            'profile' => new external_value(PARAM_ALPHANUMEXT, 'Active diagram profile'),
            'operations' => new external_multiple_structure(new external_single_structure([
                'revision' => new external_value(PARAM_INT, 'Operation revision'),
                'operationtype' => new external_value(PARAM_TEXT, 'Operation type'),
                'payloadjson' => new external_value(PARAM_RAW, 'JSON payload'),
                'userid' => new external_value(PARAM_INT, 'Acting user id'),
            ])),
            'hasmore' => new external_value(PARAM_BOOL, 'True if more operations remain beyond this batch'),
            'layoutjson' => new external_value(PARAM_RAW, 'Current layout JSON (empty when unchanged since layoutsince)'),
            'layouttime' => new external_value(PARAM_INT, 'Layout modification time, to pass back as layoutsince'),
            'leases' => new external_multiple_structure(new external_single_structure([
                'targettype' => new external_value(PARAM_ALPHA, 'Element type'),
                'targetstableid' => new external_value(PARAM_ALPHANUMEXT, 'Element stable id'),
                'userid' => new external_value(PARAM_INT, 'Lease holder user id'),
                'timeexpires' => new external_value(PARAM_INT, 'Lease expiry timestamp'),
            ])),
        ]);
    }
}
