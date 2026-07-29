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
use mod_vimipad\local\service\consensus_service;
use mod_vimipad\local\service\workspace_service;

/**
 * External function: confirm the group-consensus submission for a member.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_consensus extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return helper::consensus_parameters();
    }

    /**
     * Confirm for the current member and return the resulting status.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id (0 to auto-select).
     * @return array
     */
    public static function execute(int $cmid, int $groupid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(
            helper::consensus_parameters(),
            ['cmid' => $cmid, 'groupid' => $groupid]
        );

        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vimipad:submit', $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = (new workspace_service())->get_or_create_for_user(
            $instance,
            $context,
            (int) $USER->id,
            $params['groupid'] ?: null
        );

        $service = new consensus_service();
        $result = $service->confirm($instance, $workspace, $context, (int) $USER->id);

        $fresh = $DB->get_record('vimipad_workspace', ['id' => (int) $workspace->id], '*', MUST_EXIST);
        $status = $service->get_status($instance, $fresh, $context);

        return helper::consensus_payload($status, (int) ($result['snapshotid'] ?? 0));
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::consensus_returns();
    }
}
