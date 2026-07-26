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
use mod_vimipad\local\service\snapshot_service;

/**
 * External function: submit the current workspace as an immutable snapshot.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_snapshot extends external_api {
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
     * Validate access and create a submitted snapshot.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @return array{snapshotid: int, status: int}
     */
    public static function execute(int $cmid, int $workspaceid): array {
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'workspaceid' => $workspaceid]
        );

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vimipad:submit', $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = $DB->get_record(
            'vimipad_workspace',
            ['id' => $params['workspaceid'], 'vimipadid' => $instance->id],
            '*',
            MUST_EXIST
        );

        access::require_edit($instance, $context, $workspace, (int) $USER->id);

        if ((int) $workspace->locked === 1) {
            throw new \moodle_exception('error:alreadysubmitted', 'mod_vimipad');
        }

        $service = new snapshot_service();
        $snapshot = $service->create_submission($workspace, $instance->defaultprofile, (int) $USER->id);

        // Update activity completion if completion-on-submit is enabled.
        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && (int) $instance->completionsubmit === 1) {
            $completion->update_state($cm, COMPLETION_COMPLETE, (int) $USER->id);
        }

        return ['snapshotid' => (int) $snapshot->id, 'status' => (int) $snapshot->status];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'snapshotid' => new external_value(PARAM_INT, 'The created snapshot id'),
            'status' => new external_value(PARAM_INT, 'Snapshot status'),
        ]);
    }
}
