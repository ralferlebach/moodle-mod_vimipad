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
use core_external\external_multiple_structure;
use core_external\external_value;
use mod_vimipad\local\policy\constraint_config;
use mod_vimipad\local\policy\constraint_policy;
use mod_vimipad\local\service\snapshot_service;

/**
 * External function: report the current map's constraint status (non-blocking).
 *
 * The editor uses this to show soft, edit-time hints about missing required
 * concepts, forbidden concepts, disallowed relation types and minimum counts.
 * It never changes anything and never blocks; the hard enforcement happens at
 * submission (see {@see \mod_vimipad\local\service\snapshot_service::create_submission()}).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_constraint_status extends external_api {
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
     * Evaluate the workspace's current map against the activity constraints.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @return array
     */
    public static function execute(int $cmid, int $workspaceid): array {
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'workspaceid' => $workspaceid]
        );

        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vimipad:view', $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = $DB->get_record(
            'vimipad_workspace',
            ['id' => $params['workspaceid'], 'vimipadid' => $instance->id],
            '*',
            MUST_EXIST
        );

        // A user may inspect their own map; inspecting another's needs grading.
        $isown = ((int) $workspace->userid === (int) $USER->id)
            || (!empty($workspace->groupid) && groups_is_member((int) $workspace->groupid, (int) $USER->id));
        if (!$isown) {
            require_capability('mod/vimipad:grade', $context);
        }

        $config = constraint_config::from_instance($instance);
        $normalized = (new snapshot_service())->build_normalized($workspace, $instance->defaultprofile);
        $report = constraint_policy::evaluate($normalized, $config);

        return [
            'configured' => !$config->is_empty(),
            'satisfied' => $report->is_satisfied(),
            'messages' => array_values($report->messages()),
            'requiredmissing' => array_values($report->requiredmissing),
            'forbiddenpresent' => array_values($report->forbiddenpresent),
            'typeviolations' => array_values($report->typeviolations),
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'configured' => new external_value(PARAM_BOOL, 'Whether any constraint is configured'),
            'satisfied' => new external_value(PARAM_BOOL, 'Whether the map satisfies all constraints'),
            'messages' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A localized hint message'),
                'Localized hint messages for display'
            ),
            'requiredmissing' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A missing required concept'),
                'Required concepts still missing'
            ),
            'forbiddenpresent' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A forbidden concept present'),
                'Forbidden concepts currently present'
            ),
            'typeviolations' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A disallowed relation type in use'),
                'Relation types used but not allowed'
            ),
        ]);
    }
}
