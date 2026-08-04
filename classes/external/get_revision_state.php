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
use mod_vimipad\local\form\registry;
use mod_vimipad\local\service\reconstruction_service;

/**
 * External function: rebuild a workspace's state at a past revision (read-only).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_revision_state extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'revision' => new external_value(PARAM_INT, 'Revision to reconstruct'),
        ]);
    }

    /**
     * Reconstruct and return the workspace state at the given revision.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param int $revision Revision to reconstruct.
     * @return array
     */
    public static function execute(int $cmid, int $workspaceid, int $revision): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'workspaceid' => $workspaceid, 'revision' => $revision]
        );

        $instance = null;
        $workspace = null;
        helper::validate_workspace_for_read(
            (int) $params['cmid'],
            (int) $params['workspaceid'],
            $instance,
            $workspace
        );

        // Enforce a valid revision range: 0 <= revision <= current revision.
        // Out-of-range values are a broken API contract and would trigger
        // pointless reconstruction work.
        $revision = (int) $params['revision'];
        if ($revision < 0 || $revision > (int) $workspace->currentrevision) {
            throw new \invalid_parameter_exception('revision out of range');
        }

        $state = (new reconstruction_service())->reconstruct((int) $workspace->id, $revision);

        // Populate the historical node layout so the reconstructed state renders
        // with the topology it actually had at this revision, not a recomputed
        // auto-layout. Empty when nothing was recorded that early.
        $layoutjson = (new \mod_vimipad\local\service\layout_service())
            ->layout_at_revision((int) $workspace->id, $instance->defaultprofile, $revision);

        return [
            'workspaceid' => (int) $workspace->id,
            'revision' => $revision,
            'locked' => 1,
            'profile' => $instance->defaultprofile,
            'formconfig' => registry::for_profile($instance->defaultprofile)->to_array(),
            'layoutjson' => $layoutjson,
            'nodes' => array_map([get_workspace::class, 'map_node'], $state['nodes']),
            'relations' => array_map([get_workspace::class, 'map_relation'], $state['relations']),
            'containers' => array_map([get_workspace::class, 'map_container'], $state['containers']),
            'collab' => helper::collab_config(),
        ];
    }

    /**
     * Return value definition (mirrors get_workspace).
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_workspace::execute_returns();
    }
}
