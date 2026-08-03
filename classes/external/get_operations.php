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

/**
 * External function: return a workspace's operation log up to a revision.
 *
 * This lets the revision player fetch the whole op-log once and reconstruct
 * every frame incrementally on the client, instead of requesting a full
 * server-side reconstruction per revision (which made an initial 1..N replay
 * cost O(N^2) work across N web-service calls).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_operations extends external_api {
    /** Server-side hard cap on how many operations one request may return. */
    public const MAX_BATCH = 500;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'torevision' => new external_value(PARAM_INT, 'Highest revision to include (inclusive)'),
            'fromrevision' => new external_value(
                PARAM_INT,
                'Lowest revision to include (inclusive)',
                VALUE_DEFAULT,
                1
            ),
            'limit' => new external_value(
                PARAM_INT,
                'Maximum number of operations to return (capped server-side)',
                VALUE_DEFAULT,
                self::MAX_BATCH
            ),
        ]);
    }

    /**
     * Return the operations for the workspace in [fromrevision, torevision],
     * bounded by a server-side maximum batch size.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param int $torevision Highest revision to include.
     * @param int $fromrevision Lowest revision to include (inclusive).
     * @param int $limit Client-requested maximum (capped at MAX_BATCH).
     * @return array
     */
    public static function execute(
        int $cmid,
        int $workspaceid,
        int $torevision,
        int $fromrevision = 1,
        int $limit = self::MAX_BATCH
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'torevision' => $torevision,
            'fromrevision' => $fromrevision,
            'limit' => $limit,
        ]);

        $instance = null;
        $workspace = null;
        helper::validate_workspace_for_read(
            (int) $params['cmid'],
            (int) $params['workspaceid'],
            $instance,
            $workspace
        );

        // Clamp the range into the valid window and bound the batch size, so no
        // request can pull an unbounded number of rows.
        $current = (int) $workspace->currentrevision;
        $torev = max(0, min((int) $params['torevision'], $current));
        $fromrev = max(1, (int) $params['fromrevision']);
        $limit = (int) $params['limit'];
        if ($limit <= 0 || $limit > self::MAX_BATCH) {
            $limit = self::MAX_BATCH;
        }

        // Fetch one extra row to detect whether more remain beyond this batch.
        $records = $DB->get_records_select(
            'vimipad_operation',
            'workspaceid = :wid AND revision >= :fromrev AND revision <= :torev',
            ['wid' => (int) $workspace->id, 'fromrev' => $fromrev, 'torev' => $torev],
            'revision ASC, id ASC',
            'id, revision, operationtype, payloadjson',
            0,
            $limit + 1
        );

        $hasmore = count($records) > $limit;
        if ($hasmore) {
            array_splice($records, $limit);
        }

        $operations = [];
        $lastrev = $fromrev - 1;
        foreach ($records as $op) {
            $operations[] = [
                'revision' => (int) $op->revision,
                'operationtype' => (string) $op->operationtype,
                'payloadjson' => (string) $op->payloadjson,
            ];
            $lastrev = (int) $op->revision;
        }

        return [
            'workspaceid' => (int) $workspace->id,
            'fromrevision' => $fromrev,
            'torevision' => $torev,
            'operations' => $operations,
            'hasmore' => $hasmore,
            // The next revision to request; equals lastrev + 1 when more remain.
            'nextrevision' => $hasmore ? $lastrev + 1 : 0,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'fromrevision' => new external_value(PARAM_INT, 'Lowest revision included'),
            'torevision' => new external_value(PARAM_INT, 'Highest revision requested'),
            'operations' => new external_multiple_structure(
                new external_single_structure([
                    'revision' => new external_value(PARAM_INT, 'Revision this operation produced'),
                    'operationtype' => new external_value(PARAM_ALPHANUMEXT, 'Operation type'),
                    'payloadjson' => new external_value(PARAM_RAW, 'JSON-encoded operation payload'),
                ])
            ),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more operations remain beyond this batch'),
            'nextrevision' => new external_value(PARAM_INT, 'Revision to request next, or 0 if none remain'),
        ]);
    }
}
