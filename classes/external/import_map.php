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
use mod_vimipad\local\service\import_service;

/**
 * External function: import a JSON export into a workspace.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_map extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'json' => new external_value(PARAM_RAW, 'The JSON or XML export document to import'),
            'mode' => new external_value(PARAM_ALPHA, 'Import mode: append or replace', VALUE_DEFAULT, 'append'),
        ]);
    }

    /**
     * Import the given JSON export into the workspace.
     *
     * @param int $cmid The course module id.
     * @param int $workspaceid The workspace id.
     * @param string $json The JSON or XML export document.
     * @param string $mode Import mode: append or replace.
     * @return array The imported element counts and the new revision.
     */
    public static function execute(int $cmid, int $workspaceid, string $json, string $mode = 'append'): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'workspaceid' => $workspaceid,
            'json' => $json,
            'mode' => $mode,
        ]);

        helper::validate_workspace_for_edit($params['cmid'], $params['workspaceid'], $instance, $workspace);

        $service = new import_service();
        $importmode = $params['mode'] === 'replace' ? 'replace' : 'append';
        $document = ltrim($params['json']);
        $counts = (isset($document[0]) && $document[0] === '<')
            ? $service->import_xml($params['json'], $workspace, (int) $USER->id, $importmode)
            : $service->import_json($params['json'], $workspace, (int) $USER->id, $importmode);

        $revision = (int) $DB->get_field(
            'vimipad_workspace',
            'currentrevision',
            ['id' => $params['workspaceid']],
            MUST_EXIST
        );

        return [
            'nodes' => $counts['nodes'],
            'relations' => $counts['relations'],
            'revision' => $revision,
        ];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'nodes' => new external_value(PARAM_INT, 'Number of nodes imported'),
            'relations' => new external_value(PARAM_INT, 'Number of relations imported'),
            'revision' => new external_value(PARAM_INT, 'The workspace revision after import'),
        ]);
    }
}
