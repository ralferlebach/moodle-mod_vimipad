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
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'workspaceid' => $workspaceid, 'revision' => $revision]
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

        // A user may reconstruct their own map; inspecting another's needs grading.
        $isown = ((int) $workspace->userid === (int) $USER->id)
            || (!empty($workspace->groupid) && groups_is_member((int) $workspace->groupid, (int) $USER->id));
        if (!$isown) {
            require_capability('mod/vimipad:grade', $context);
        }

        $state = (new reconstruction_service())->reconstruct((int) $workspace->id, (int) $params['revision']);

        return [
            'workspaceid' => (int) $workspace->id,
            'revision' => (int) $params['revision'],
            'locked' => 1,
            'profile' => $instance->defaultprofile,
            'formconfig' => registry::for_profile($instance->defaultprofile)->to_array(),
            'layoutjson' => '',
            'nodes' => array_map([self::class, 'map_node'], $state['nodes']),
            'relations' => array_map([self::class, 'map_relation'], $state['relations']),
            'collab' => helper::collab_config(),
        ];
    }

    /**
     * Map a reconstructed node to the external structure.
     *
     * @param \stdClass $node The node.
     * @return array
     */
    private static function map_node(\stdClass $node): array {
        return [
            'stableid' => $node->stableid,
            'type' => $node->type,
            'label' => (string) $node->label,
            'content' => (string) ($node->content ?? ''),
            'contentformat' => (int) ($node->contentformat ?? 0),
            'metadatajson' => (string) ($node->metadatajson ?? ''),
        ];
    }

    /**
     * Map a reconstructed relation to the external structure.
     *
     * @param \stdClass $relation The relation.
     * @return array
     */
    private static function map_relation(\stdClass $relation): array {
        return [
            'stableid' => $relation->stableid,
            'sourceid' => $relation->sourceid,
            'targetid' => $relation->targetid,
            'type' => $relation->type,
            'label' => (string) $relation->label,
            'direction' => (int) $relation->direction,
            'metadatajson' => (string) ($relation->metadatajson ?? ''),
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
