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
use mod_vimipad\local\service\operation_service;

/**
 * External function: apply a single validated operation to a workspace.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_operation extends external_api {
    /**
     * Parameter definition.
     *
     * The payload is accepted as a JSON string (PARAM_RAW) and strictly
     * schema-validated server-side; it is never trusted or echoed unescaped.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'baserevision' => new external_value(PARAM_INT, 'Revision the client based this operation on'),
            'operationtype' => new external_value(PARAM_ALPHANUMEXT, 'Operation type'),
            'payloadjson' => new external_value(PARAM_RAW, 'JSON-encoded operation payload'),
            'enforcelocks' => new external_value(
                PARAM_BOOL,
                'Whether the caller wants template element locks enforced against itself '
                    . '(the lock-mode preview toggle). Only ever tightens enforcement.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Validate context, group and workspace ownership, then apply the operation.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param int $baserevision Client base revision.
     * @param string $operationtype Operation type.
     * @param string $payloadjson JSON-encoded payload.
     * @param bool $enforcelocks Whether the caller opts into lock enforcement against itself.
     * @return array{revision: int, stableid: string}
     */
    public static function execute(
        int $cmid,
        int $workspaceid,
        int $baserevision,
        string $operationtype,
        string $payloadjson,
        bool $enforcelocks = false
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'baserevision' => $baserevision,
            'operationtype' => $operationtype,
            'payloadjson' => $payloadjson,
            'enforcelocks' => $enforcelocks,
        ]);

        $instance = null;
        $workspace = null;
        $context = helper::validate_workspace_for_edit(
            (int) $params['cmid'],
            (int) $params['workspaceid'],
            $instance,
            $workspace
        );

        $payload = json_decode($params['payloadjson'], true);
        if (!is_array($payload)) {
            throw new \invalid_parameter_exception('payloadjson must decode to a JSON object');
        }

        // Template element locks protect teacher-authored elements and are only
        // bypassed by users who may author the template (manageprofiles). The
        // cooperative collaboration lock mode (lockmodeforlearners) is a
        // separate concept — an editing lease between peers — and must never
        // disable template protection.
        //
        // The lock-mode preview toggle ($enforcelocks) lets a manager opt back
        // into enforcement against their own edits, so they can verify a locked
        // template behaves for learners. It only ever tightens: a non-manager
        // never bypasses regardless of the flag.
        $canmanage = has_capability('mod/vimipad:manageprofiles', $context);
        $bypasslocks = $canmanage && !$params['enforcelocks'];
        $service = new operation_service($bypasslocks);
        $result = $service->apply(
            (int) $workspace->id,
            $params['baserevision'],
            $params['operationtype'],
            $payload,
            (int) $USER->id
        );

        // Log editing activity for course reports (one event per operation).
        \mod_vimipad\event\map_updated::create([
            'context' => $context,
            'objectid' => (int) $workspace->id,
            'other' => [
                'operationtype' => (string) $params['operationtype'],
                'revision' => (int) $result['revision'],
            ],
        ])->trigger();

        return [
            'revision' => $result['revision'],
            'stableid' => (string) ($result['stableid'] ?? ''),
        ];
    }


    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'revision' => new external_value(PARAM_INT, 'New workspace revision after applying'),
            'stableid' => new external_value(PARAM_ALPHANUMEXT, 'Server-assigned stable id, empty if none'),
        ]);
    }
}
