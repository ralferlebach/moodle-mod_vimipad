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
use mod_vimipad\local\service\workspace_service;

/**
 * External function: return one page of a workspace's elements (nodes,
 * relations or containers).
 *
 * The editor loads large maps by first calling get_workspace with
 * includeelements=false (metadata + counts), then paging each element kind
 * through this function. Every row is validated against the same structure
 * get_workspace uses, so no unvalidated payload is returned; pagination simply
 * bounds the size, memory and return-validation cost of any single request.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_workspace_elements extends external_api {
    /** @var int Maximum rows returned in a single page. */
    public const MAX_PAGE = 500;

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'kind' => new external_value(PARAM_ALPHA, 'Element kind: nodes, relations or containers'),
            'offset' => new external_value(PARAM_INT, 'Zero-based row offset', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum rows (capped at MAX_PAGE)', VALUE_DEFAULT, self::MAX_PAGE),
        ]);
    }

    /**
     * Return one page of the requested element kind for the workspace.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param string $kind Element kind: nodes, relations or containers.
     * @param int $offset Zero-based row offset.
     * @param int $limit Maximum rows to return.
     * @return array
     */
    public static function execute(
        int $cmid,
        int $workspaceid,
        string $kind,
        int $offset = 0,
        int $limit = self::MAX_PAGE
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'kind' => $kind,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        $kind = $params['kind'];
        if (!in_array($kind, ['nodes', 'relations', 'containers'], true)) {
            throw new \invalid_parameter_exception('unknown element kind');
        }

        $instance = null;
        $workspace = null;
        helper::validate_workspace_for_read(
            (int) $params['cmid'],
            (int) $params['workspaceid'],
            $instance,
            $workspace
        );

        $offset = max(0, (int) $params['offset']);
        $limit = (int) $params['limit'];
        if ($limit <= 0 || $limit > self::MAX_PAGE) {
            $limit = self::MAX_PAGE;
        }

        $service = new workspace_service();
        $counts = $service->count_elements((int) $workspace->id);
        $total = (int) $counts[$kind];
        $records = $service->get_elements_page((int) $workspace->id, $kind, $offset, $limit);

        $mappers = [
            'nodes' => [get_workspace::class, 'map_node'],
            'relations' => [get_workspace::class, 'map_relation'],
            'containers' => [get_workspace::class, 'map_container'],
        ];
        $elements = array_map($mappers[$kind], $records);

        $result = [
            'kind' => $kind,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'hasmore' => ($offset + count($elements)) < $total,
        ];
        $result[$kind] = $elements;
        return $result;
    }

    /**
     * Return value definition. Only the requested kind's array is populated; the
     * others are omitted (VALUE_OPTIONAL). Each row is validated against the same
     * structure get_workspace uses.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'kind' => new external_value(PARAM_ALPHA, 'Element kind returned'),
            'offset' => new external_value(PARAM_INT, 'Row offset of this page'),
            'limit' => new external_value(PARAM_INT, 'Page size applied'),
            'total' => new external_value(PARAM_INT, 'Total live elements of this kind'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more rows remain beyond this page'),
            'nodes' => new external_multiple_structure(
                get_workspace::node_structure(),
                'Node page (present when kind = nodes)',
                VALUE_OPTIONAL
            ),
            'relations' => new external_multiple_structure(
                get_workspace::relation_structure(),
                'Relation page (present when kind = relations)',
                VALUE_OPTIONAL
            ),
            'containers' => new external_multiple_structure(
                get_workspace::container_structure(),
                'Container page (present when kind = containers)',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
