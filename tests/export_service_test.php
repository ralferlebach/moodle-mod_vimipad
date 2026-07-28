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

namespace mod_vimipad;

use mod_vimipad\local\service\export_service;

/**
 * Tests for the workspace export service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\export_service
 */
final class export_service_test extends \advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private $instance;

    /** @var \stdClass The workspace record. */
    private $workspace;

    /**
     * Create an activity with a workspace holding two nodes and a relation.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0, 'defaultprofile' => 'conceptmap']
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id,
            'userid' => (int) $user->id,
            'groupid' => null,
            'currentrevision' => 3,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        foreach (['node_aaaaaaaaaaaa' => 'Energy', 'node_bbbbbbbbbbbb' => 'Work'] as $stableid => $label) {
            $DB->insert_record('vimipad_node', (object) [
                'workspaceid' => $workspaceid, 'stableid' => $stableid, 'type' => 'concept',
                'label' => $label, 'contentformat' => FORMAT_HTML,
                'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
            ]);
        }
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $workspaceid, 'stableid' => 'rel_aaaaaaaaaaaaa',
            'sourceid' => 'node_aaaaaaaaaaaa', 'targetid' => 'node_bbbbbbbbbbbb',
            'type' => 'related', 'label' => 'produces', 'direction' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $this->workspace = $DB->get_record('vimipad_workspace', ['id' => $workspaceid], '*', MUST_EXIST);
    }

    /**
     * The JSON export is a valid, versioned envelope carrying the map contents.
     *
     * @return void
     */
    public function test_export_json_envelope(): void {
        $exporter = new export_service();
        $json = $exporter->export_json($this->instance, $this->workspace, 'conceptmap');
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame(export_service::FORMAT_VERSION, $decoded['formatversion']);
        $this->assertSame('mod_vimipad', $decoded['generator']);
        $this->assertSame($this->instance->name, $decoded['activity']);
        $this->assertArrayHasKey('data', $decoded);

        $data = $decoded['data'];
        $this->assertSame('conceptmap', $data['profile']);
        $this->assertCount(2, $data['nodes']);
        $this->assertCount(1, $data['relations']);

        $labels = array_column($data['nodes'], 'label');
        $this->assertContains('Energy', $labels);
        $this->assertContains('Work', $labels);
        $this->assertSame('produces', $data['relations'][0]['label']);
    }

    /**
     * The download filename is cleaned and carries the requested extension.
     *
     * @return void
     */
    public function test_filename(): void {
        $exporter = new export_service();
        $name = $exporter->filename($this->instance, 'conceptmap', 'json');
        $this->assertStringEndsWith('.json', $name);
        $this->assertStringNotContainsString('/', $name);
    }
}
