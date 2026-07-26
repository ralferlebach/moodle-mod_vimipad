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
use mod_vimipad\local\access;
use mod_vimipad\local\service\layout_service;

/**
 * External function: persist the (non-revisioned) layout of a workspace.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_layout extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'layoutjson' => new external_value(PARAM_RAW, 'JSON-encoded layout state'),
            'viewportjson' => new external_value(PARAM_RAW, 'JSON-encoded viewport state', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Validate access and store the layout.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param string $layoutjson JSON layout.
     * @param string $viewportjson JSON viewport.
     * @return array{status: bool}
     */
    public static function execute(
        int $cmid,
        int $workspaceid,
        string $layoutjson,
        string $viewportjson = ''
    ): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'layoutjson' => $layoutjson,
            'viewportjson' => $viewportjson,
        ]);

        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = $DB->get_record(
            'vimipad_workspace',
            ['id' => $params['workspaceid'], 'vimipadid' => $instance->id],
            '*',
            MUST_EXIST
        );

        access::require_edit($instance, $context, $workspace, (int) $USER->id);

        // Validate that the payloads are well-formed JSON before storing.
        if (json_decode($params['layoutjson']) === null && trim($params['layoutjson']) !== 'null') {
            throw new \invalid_parameter_exception('layoutjson must be valid JSON');
        }
        $viewport = $params['viewportjson'];
        if ($viewport !== '' && json_decode($viewport) === null && trim($viewport) !== 'null') {
            throw new \invalid_parameter_exception('viewportjson must be valid JSON');
        }

        $service = new layout_service();
        $service->save(
            (int) $workspace->id,
            $instance->defaultprofile,
            $params['layoutjson'],
            $viewport,
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
