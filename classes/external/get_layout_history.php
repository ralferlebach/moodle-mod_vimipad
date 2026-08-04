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
use mod_vimipad\local\service\layout_service;

/**
 * External function: return a workspace's append-only node-layout history.
 *
 * The revision player uses this to show each past frame with the topology it
 * actually had: for a frame at revision N it picks the newest history entry
 * whose revision is <= N. Read-only.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_layout_history extends external_api {
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
     * Validate access and return the layout history.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @return array The history entries.
     */
    public static function execute(int $cmid, int $workspaceid): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'workspaceid' => $workspaceid]
        );

        $instance = null;
        $workspace = null;
        helper::validate_workspace_for_read(
            (int) $params['cmid'],
            (int) $params['workspaceid'],
            $instance,
            $workspace
        );

        $history = (new layout_service())->layout_history(
            (int) $workspace->id,
            $instance->defaultprofile
        );

        return ['history' => $history];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'history' => new external_multiple_structure(
                new external_single_structure([
                    'revision' => new external_value(PARAM_INT, 'Semantic revision at which this layout applied'),
                    'layoutjson' => new external_value(PARAM_RAW, 'JSON-encoded node layout'),
                ]),
                'Layout history in ascending revision order'
            ),
        ]);
    }
}
