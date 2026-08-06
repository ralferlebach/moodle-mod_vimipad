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
use mod_vimipad\local\service\workspace_service;
use mod_vimipad\local\service\layout_service;
use mod_vimipad\local\form\registry;

/**
 * External function: resolve and return a user's workspace with its state.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_workspace extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'groupid' => new external_value(PARAM_INT, 'Group id (group mode), 0 to auto-select', VALUE_DEFAULT, 0),
            'targetuserid' => new external_value(
                PARAM_INT,
                'Owner user to view read-only (0 = self); requires grade capability',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Resolve the workspace and return its full state.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Requested group id (group mode).
     * @param int $targetuserid Owner user to view read-only (0 = self).
     * @return array
     */
    public static function execute(int $cmid, int $groupid = 0, int $targetuserid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'groupid' => $groupid, 'targetuserid' => $targetuserid]
        );

        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/vimipad:view', $context);
        $canmanage = has_capability('mod/vimipad:manageprofiles', $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $lockmodeforlearners = !empty($instance->lockmodeforlearners);
        $service = new workspace_service();

        $target = (int) $params['targetuserid'];
        if ($target > 0 && $target !== (int) $USER->id) {
            // Foreign, read-only view: only graders may inspect another user's map.
            require_capability('mod/vimipad:grade', $context);
            $workspace = $service->find_for_user($instance, $target);
            if ($workspace === null) {
                return self::empty_state($instance, $canmanage, $lockmodeforlearners);
            }
        } else {
            $workspace = $service->get_or_create_for_user(
                $instance,
                $context,
                (int) $USER->id,
                $params['groupid'] ?: null
            );
        }

        $state = $service->get_state((int) $workspace->id);

        $layoutservice = new layout_service();
        $layoutjson = $layoutservice->get_layout_json((int) $workspace->id, $instance->defaultprofile);

        return [
            'workspaceid' => (int) $workspace->id,
            'revision' => (int) $workspace->currentrevision,
            'locked' => (int) $workspace->locked,
            'profile' => $instance->defaultprofile,
            'formconfig' => registry::for_profile($instance->defaultprofile)->to_array(),
            'layoutjson' => $layoutjson,
            'nodes' => array_map([self::class, 'map_node'], $state['nodes']),
            'relations' => array_map([self::class, 'map_relation'], $state['relations']),
            'containers' => array_map([self::class, 'map_container'], $state['containers']),
            'canmanage' => $canmanage,
            'lockmodeforlearners' => $lockmodeforlearners,
            'journalallowprivate' => !empty($instance->journalallowprivate),
            'collab' => helper::collab_config((int) $workspace->id),
        ];
    }

    /**
     * Empty state for a foreign user who has no workspace yet.
     *
     * @param \stdClass $instance The activity instance.
     * @param bool $canmanage Whether the viewer may author/manage templates.
     * @param bool $lockmodeforlearners Whether learners may also use lock mode.
     * @return array
     */
    private static function empty_state(
        \stdClass $instance,
        bool $canmanage = false,
        bool $lockmodeforlearners = false
    ): array {
        return [
            'workspaceid' => 0,
            'revision' => 0,
            'locked' => 0,
            'profile' => $instance->defaultprofile,
            'formconfig' => registry::for_profile($instance->defaultprofile)->to_array(),
            'layoutjson' => '',
            'nodes' => [],
            'relations' => [],
            'containers' => [],
            'canmanage' => $canmanage,
            'lockmodeforlearners' => $lockmodeforlearners,
            'journalallowprivate' => !empty($instance->journalallowprivate),
            'collab' => helper::collab_config((int) $workspace->id),
        ];
    }

    /**
     * Map a node record to the external structure.
     *
     * @param \stdClass $node The node record.
     * @return array
     */
    public static function map_node(\stdClass $node): array {
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
     * Map a relation record to the external structure.
     *
     * @param \stdClass $relation The relation record.
     * @return array
     */
    public static function map_relation(\stdClass $relation): array {
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
     * Map a container record to the external structure.
     *
     * @param \stdClass $container The container record.
     * @return array
     */
    public static function map_container(\stdClass $container): array {
        return [
            'stableid' => $container->stableid,
            'type' => $container->type,
            'label' => (string) ($container->label ?? ''),
            'geometryjson' => (string) ($container->geometryjson ?? ''),
            'metadatajson' => (string) ($container->metadatajson ?? ''),
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
            'revision' => new external_value(PARAM_INT, 'Current server revision'),
            'locked' => new external_value(PARAM_INT, '1 if the workspace is locked'),
            'profile' => new external_value(PARAM_ALPHANUMEXT, 'Active diagram profile'),
            'formconfig' => new external_single_structure([
                'profile' => new external_value(PARAM_ALPHANUMEXT, 'Profile key'),
                'name' => new external_value(PARAM_TEXT, 'Display name of the form'),
                'allowedshapes' => new external_multiple_structure(
                    new external_value(PARAM_ALPHA, 'Allowed node shape key')
                ),
                'defaultshape' => new external_value(PARAM_ALPHA, 'Default node shape key'),
                'line' => new external_value(PARAM_ALPHA, 'Connector line style'),
                'bifurcation' => new external_value(PARAM_ALPHA, 'Bifurcation behaviour'),
                'layout' => new external_single_structure([
                    'directed' => new external_value(PARAM_BOOL, 'Treat relations as directed for layout'),
                    'cyclicorder' => new external_value(PARAM_BOOL, 'Preserve cyclic order of a hub\'s neighbours'),
                    'direction' => new external_single_structure([
                        'x' => new external_value(PARAM_FLOAT, 'Preferred direction x component'),
                        'y' => new external_value(PARAM_FLOAT, 'Preferred direction y component'),
                    ], 'Preferred flow direction for directed edges', VALUE_OPTIONAL),
                    'orderaxis' => new external_single_structure([
                        'x' => new external_value(PARAM_FLOAT, 'Order axis x component'),
                        'y' => new external_value(PARAM_FLOAT, 'Order axis y component'),
                    ], 'Cross-axis along which sibling order is preserved', VALUE_OPTIONAL),
                    'lineaxis' => new external_single_structure([
                        'x' => new external_value(PARAM_FLOAT, 'Line axis x component'),
                        'y' => new external_value(PARAM_FLOAT, 'Line axis y component'),
                    ], 'Axis onto whose parallel line nodes are confined', VALUE_OPTIONAL),
                ], 'Layout-potential parameters for the arrange refiner'),
            ]),
            'layoutjson' => new external_value(PARAM_RAW, 'Stored layout JSON, empty if none'),
            'nodes' => new external_multiple_structure(new external_single_structure([
                'stableid' => new external_value(PARAM_ALPHANUMEXT, 'Stable node id'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Node type'),
                'label' => new external_value(PARAM_TEXT, 'Node label'),
                'content' => new external_value(PARAM_RAW, 'Rich node content (HTML), empty if none'),
                'contentformat' => new external_value(PARAM_INT, 'Content format (1 = HTML)'),
                'metadatajson' => new external_value(PARAM_RAW, 'Style/profile metadata JSON, empty if none'),
            ])),
            'relations' => new external_multiple_structure(new external_single_structure([
                'stableid' => new external_value(PARAM_ALPHANUMEXT, 'Stable relation id'),
                'sourceid' => new external_value(PARAM_ALPHANUMEXT, 'Source node stable id'),
                'targetid' => new external_value(PARAM_ALPHANUMEXT, 'Target node stable id'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Relation type'),
                'label' => new external_value(PARAM_TEXT, 'Relation label'),
                'direction' => new external_value(PARAM_INT, 'Direction 0/1/2'),
                'metadatajson' => new external_value(PARAM_RAW, 'Style/profile metadata JSON, empty if none'),
            ])),
            'containers' => new external_multiple_structure(new external_single_structure([
                'stableid' => new external_value(PARAM_ALPHANUMEXT, 'Stable container id'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Container type'),
                'label' => new external_value(PARAM_TEXT, 'Container label'),
                'geometryjson' => new external_value(PARAM_RAW, 'Geometry JSON (x,y,w,h), empty if none'),
                'metadatajson' => new external_value(PARAM_RAW, 'Style/lock metadata JSON, empty if none'),
            ]), 'Containers drawn on the canvas', VALUE_OPTIONAL),
            'canmanage' => new external_value(
                PARAM_BOOL,
                'Whether the viewer may author/manage templates (set element locks)',
                VALUE_OPTIONAL
            ),
            'lockmodeforlearners' => new external_value(
                PARAM_BOOL,
                'Whether learners may also toggle lock mode in this activity',
                VALUE_OPTIONAL
            ),
            'journalallowprivate' => new external_value(
                PARAM_BOOL,
                'Whether learners may mark journal entries as private (hidden from teachers)',
                VALUE_OPTIONAL
            ),
            'collab' => new external_single_structure([
                'pollinterval' => new external_value(PARAM_INT, 'Default poll interval (ms)'),
                'polladaptive' => new external_value(PARAM_INT, '1 if adaptive polling is enabled'),
                'pollmin' => new external_value(PARAM_INT, 'Minimum poll interval (ms)'),
                'pollmax' => new external_value(PARAM_INT, 'Maximum poll interval (ms)'),
                'leasetimeout' => new external_value(PARAM_INT, 'Lease timeout (s)'),
                'pushenabled' => new external_value(PARAM_INT, '1 if push is enabled'),
                'pushendpoint' => new external_value(PARAM_RAW, 'Push endpoint URL'),
                'pushtopic' => new external_value(PARAM_RAW, 'Per-workspace push topic (empty if push off)', VALUE_OPTIONAL),
                'pushtoken' => new external_value(PARAM_RAW, 'Scoped subscriber token (empty if push off)', VALUE_OPTIONAL),
            ]),
        ]);
    }
}
