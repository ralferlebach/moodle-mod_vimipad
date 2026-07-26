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

/**
 * Test data generator for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * ViMi Pad module data generator.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_vimipad_generator extends testing_module_generator {
    /**
     * Create a vimipad instance with sensible defaults.
     *
     * @param array|stdClass|null $record Instance data overrides.
     * @param array|null $options Course module options.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'defaultprofile' => 'conceptmap',
            'collaborationmode' => 0,
            'gradingmode' => 0,
            'aienabled' => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a workspace with an optional set of nodes and a submitted snapshot.
     *
     * Used by Behat and integration tests to seed a gradable submission without
     * driving the JavaScript editor.
     *
     * @param stdClass $instance The vimipad instance.
     * @param int $userid The owning user id (individual mode).
     * @param array $nodes List of ['stableid' => ..., 'label' => ...] node specs.
     * @param bool $submit Whether to create and lock a submitted snapshot.
     * @return stdClass The workspace record (with ->snapshotid if submitted).
     */
    public function create_workspace(stdClass $instance, int $userid, array $nodes = [], bool $submit = false): stdClass {
        global $DB;

        $now = time();
        $workspace = (object) [
            'vimipadid' => $instance->id,
            'userid' => $userid,
            'groupid' => null,
            'currentrevision' => count($nodes),
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $workspace->id = $DB->insert_record('vimipad_workspace', $workspace);

        foreach ($nodes as $node) {
            $DB->insert_record('vimipad_node', (object) [
                'workspaceid' => $workspace->id,
                'stableid' => $node['stableid'],
                'type' => $node['type'] ?? 'concept',
                'label' => $node['label'],
                'contentformat' => FORMAT_HTML,
                'createdby' => $userid,
                'modifiedby' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
                'deleted' => 0,
            ]);
        }

        if ($submit) {
            $service = new \mod_vimipad\local\service\snapshot_service();
            $snapshot = $service->create_submission($workspace, $instance->defaultprofile, $userid);
            $workspace->snapshotid = $snapshot->id;
        }

        return $workspace;
    }
}
