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
use mod_vimipad\local\service\import_service;

/**
 * Tests for the import service (export -> import round-trip).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\import_service
 */
final class import_service_test extends \advanced_testcase {
    /**
     * Insert a workspace and return its record.
     *
     * @param int $vimipadid The instance id.
     * @param int|null $userid The owner user id.
     * @return \stdClass The workspace record.
     */
    private function make_workspace(int $vimipadid, ?int $userid): \stdClass {
        global $DB;
        $now = time();
        $id = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $vimipadid, 'userid' => $userid, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->get_record('vimipad_workspace', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Build a source workspace with two concept nodes and one relation.
     *
     * @return array Instance record, user record and workspace record.
     */
    private function make_source_map(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();

        $source = $this->make_workspace((int) $instance->id, (int) $user->id);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $source->id, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept',
            'label' => 'Energy', 'contentformat' => FORMAT_HTML, 'createdby' => $user->id,
            'modifiedby' => $user->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $source->id, 'stableid' => 'node_bbbbbbbbbbbb', 'type' => 'concept',
            'label' => 'Motion', 'contentformat' => FORMAT_HTML, 'createdby' => $user->id,
            'modifiedby' => $user->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $source->id, 'stableid' => 'rel_cccccccccccc',
            'sourceid' => 'node_aaaaaaaaaaaa', 'targetid' => 'node_bbbbbbbbbbbb',
            'type' => 'isform', 'label' => 'is a form of', 'direction' => 1,
            'createdby' => $user->id, 'modifiedby' => $user->id,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);

        return [$instance, $user, $source];
    }

    /**
     * A JSON export imported into a fresh workspace recreates the nodes and the
     * relation, with the relation remapped to the new node stable ids.
     *
     * @return void
     */
    public function test_export_import_roundtrip(): void {
        global $DB;
        $this->resetAfterTest();

        [$instance, $user, $source] = $this->make_source_map();

        $json = (new export_service())->export_json($instance, $source, 'conceptmap');

        // Import into a fresh, empty workspace.
        $target = $this->make_workspace((int) $instance->id, (int) $user->id);
        $counts = (new import_service())->import_json($json, $target, (int) $user->id);

        $this->assertSame(2, $counts['nodes']);
        $this->assertSame(1, $counts['relations']);

        $nodes = $DB->get_records('vimipad_node', ['workspaceid' => $target->id, 'deleted' => 0]);
        $this->assertCount(2, $nodes);
        $labels = array_map(static fn($n) => $n->label, $nodes);
        sort($labels);
        $this->assertSame(['Energy', 'Motion'], $labels);

        // The relation was remapped onto the new node stable ids.
        $relations = $DB->get_records('vimipad_relation', ['workspaceid' => $target->id, 'deleted' => 0]);
        $this->assertCount(1, $relations);
        $relation = reset($relations);
        $bylabel = [];
        foreach ($nodes as $node) {
            $bylabel[$node->label] = $node->stableid;
        }
        $this->assertSame($bylabel['Energy'], $relation->sourceid);
        $this->assertSame($bylabel['Motion'], $relation->targetid);
        $this->assertNotSame('node_aaaaaaaaaaaa', $relation->sourceid);
    }

    /**
     * A document that is not a ViMi Pad export is rejected.
     *
     * @return void
     */
    public function test_invalid_document_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $workspace = $this->make_workspace((int) $instance->id, null);

        $this->expectException(\moodle_exception::class);
        (new import_service())->import_json('{"generator":"something else"}', $workspace, 1);
    }

    /**
     * An XML export imported into a fresh workspace recreates the nodes and the
     * remapped relation.
     *
     * @return void
     */
    public function test_xml_roundtrip(): void {
        global $DB;
        $this->resetAfterTest();

        [$instance, $user, $source] = $this->make_source_map();

        $xml = (new export_service())->export_xml($instance, $source, 'conceptmap');

        $target = $this->make_workspace((int) $instance->id, (int) $user->id);
        $counts = (new import_service())->import_xml($xml, $target, (int) $user->id);

        $this->assertSame(2, $counts['nodes']);
        $this->assertSame(1, $counts['relations']);
        $this->assertSame(2, $DB->count_records('vimipad_node', ['workspaceid' => $target->id, 'deleted' => 0]));
        $this->assertSame(1, $DB->count_records('vimipad_relation', ['workspaceid' => $target->id, 'deleted' => 0]));
    }

    /**
     * Replace mode removes the existing map before importing.
     *
     * @return void
     */
    public function test_replace_mode_clears_existing(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();

        $workspace = $this->make_workspace((int) $instance->id, (int) $user->id);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspace->id, 'stableid' => 'node_oldoldoldol', 'type' => 'concept',
            'label' => 'Old', 'contentformat' => FORMAT_HTML, 'createdby' => $user->id,
            'modifiedby' => $user->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);

        $json = json_encode([
            'generator' => 'mod_vimipad',
            'data' => ['nodes' => [
                ['stableid' => 'node_newnewnewne', 'type' => 'concept', 'label' => 'New A'],
                ['stableid' => 'node_newbnewbnew', 'type' => 'concept', 'label' => 'New B'],
            ], 'relations' => []],
        ]);

        $counts = (new import_service())->import_json($json, $workspace, (int) $user->id, 'replace');

        $this->assertSame(2, $counts['nodes']);
        $live = $DB->get_records('vimipad_node', ['workspaceid' => $workspace->id, 'deleted' => 0]);
        $this->assertCount(2, $live);
        $labels = array_map(static fn($n) => $n->label, $live);
        sort($labels);
        $this->assertSame(['New A', 'New B'], $labels);
    }

    /**
     * The imported layout positions are remapped onto the new node stable ids.
     *
     * @return void
     */
    public function test_layout_is_imported_and_remapped(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'defaultprofile' => 'conceptmap']
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();

        $source = $this->make_workspace((int) $instance->id, (int) $user->id);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $source->id, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept',
            'label' => 'Energy', 'contentformat' => FORMAT_HTML, 'createdby' => $user->id,
            'modifiedby' => $user->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $DB->insert_record('vimipad_layout', (object) [
            'workspaceid' => $source->id, 'profile' => 'conceptmap',
            'layoutjson' => json_encode(
                ['v' => 1, 'pos' => ['node_aaaaaaaaaaaa' => ['x' => 111, 'y' => 222]], 'size' => []]
            ),
            'viewportjson' => '', 'modifiedby' => $user->id, 'timemodified' => $now,
        ]);

        $json = (new export_service())->export_json($instance, $source, 'conceptmap');

        $target = $this->make_workspace((int) $instance->id, (int) $user->id);
        (new import_service())->import_json($json, $target, (int) $user->id);

        $newnode = $DB->get_record('vimipad_node', ['workspaceid' => $target->id, 'deleted' => 0], '*', MUST_EXIST);
        $stored = json_decode(
            (new \mod_vimipad\local\service\layout_service())->get_layout_json((int) $target->id, 'conceptmap'),
            true
        );
        $this->assertArrayHasKey($newnode->stableid, $stored['pos']);
        $this->assertArrayNotHasKey('node_aaaaaaaaaaaa', $stored['pos']);
        $this->assertSame(111, $stored['pos'][$newnode->stableid]['x']);
        $this->assertSame(222, $stored['pos'][$newnode->stableid]['y']);
    }
}
