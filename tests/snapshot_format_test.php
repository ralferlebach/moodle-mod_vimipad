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

use mod_vimipad\local\service\snapshot_service;

/**
 * The submitted snapshot keeps what the canvas needs to redraw the same map.
 *
 * The gallery shows submitted snapshots through the editor's value transport.
 * A snapshot whose relation direction was the database string "1" lost every
 * arrowhead there, and one without positions was redrawn with an invented
 * layout - the same map, topologically different. These tests pin the shape.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\snapshot_service
 */
final class snapshot_format_test extends \advanced_testcase {
    /**
     * Build a workspace with two nodes, a directed relation and stored positions.
     *
     * @return array The instance and the workspace.
     */
    private function make_map(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $user->id, 'groupid' => null,
            'currentrevision' => 3, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        foreach (['node_aaaaaaaaaaaa' => 'Free trade', 'node_bbbbbbbbbbbb' => 'Tariffs'] as $id => $label) {
            $DB->insert_record('vimipad_node', (object) [
                'workspaceid' => $wsid, 'stableid' => $id, 'type' => 'concept', 'label' => $label,
                'content' => '', 'contentformat' => FORMAT_HTML, 'metadatajson' => '{}',
                'createdby' => $user->id, 'modifiedby' => $user->id,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $wsid, 'stableid' => 'rel_aaaaaaaaaaaaa',
            'sourceid' => 'node_aaaaaaaaaaaa', 'targetid' => 'node_bbbbbbbbbbbb',
            'type' => '', 'label' => 'reduces', 'direction' => 1, 'metadatajson' => '{}',
            'createdby' => $user->id, 'modifiedby' => $user->id,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_layout', (object) [
            'workspaceid' => $wsid,
            'profile' => 'conceptmap',
            'modifiedby' => $user->id,
            'layoutjson' => json_encode([
                'node_aaaaaaaaaaaa' => ['x' => 860, 'y' => 230],
                'node_bbbbbbbbbbbb' => ['x' => 560, 'y' => 325],
            ]),
            'timemodified' => $now,
        ]);
        return [$instance, $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST)];
    }

    /**
     * A relation's direction is stored as an integer, as the canvas expects.
     *
     * @return void
     */
    public function test_direction_is_an_integer(): void {
        $this->resetAfterTest();
        [, $workspace] = $this->make_map();

        $snapshot = (new snapshot_service())->build_normalized($workspace, 'conceptmap');
        $encoded = json_decode(json_encode($snapshot), true);

        $this->assertSame(
            1,
            $encoded['relations'][0]['direction'],
            'The snapshot must carry direction as the integer the canvas compares against.'
        );
    }

    /**
     * The stored positions travel with the snapshot.
     *
     * @return void
     */
    public function test_layout_is_kept(): void {
        $this->resetAfterTest();
        [, $workspace] = $this->make_map();

        $snapshot = (new snapshot_service())->build_normalized($workspace, 'conceptmap');

        $this->assertIsArray($snapshot['layout']);
        $this->assertEquals(['x' => 860, 'y' => 230], $snapshot['layout']['node_aaaaaaaaaaaa']);
        $this->assertEquals(['x' => 560, 'y' => 325], $snapshot['layout']['node_bbbbbbbbbbbb']);
    }
}
