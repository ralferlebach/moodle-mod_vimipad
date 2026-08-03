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

use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\service\reconstruction_service;

/**
 * Reproduce: does the revision replay reconstruct nodes and relations, not just
 * containers?
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\reconstruction_service
 */
final class replay_reconstruction_test extends \advanced_testcase {
    /**
     * A workspace built from node/relation/container operations reconstructs
     * all three at the final revision.
     *
     * @return void
     */
    public function test_replay_reconstructs_all_kinds(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id,
            'userid' => 0,
            'groupid' => 0,
            'currentrevision' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        $svc = new operation_service();
        $rev = 0;
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'node_create',
            ['stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept', 'label' => 'A'],
            1
        )['revision'];
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'node_create',
            ['stableid' => 'node_bbbbbbbbbbbb', 'type' => 'concept', 'label' => 'B'],
            1
        )['revision'];
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'relation_create',
            ['stableid' => 'rel_aaaaaaaaaaaa', 'sourceid' => 'node_aaaaaaaaaaaa',
            'targetid' => 'node_bbbbbbbbbbbb',
            'type' => 'link',
            'label' => 'r',
            'direction' => 1],
            1
        )['revision'];
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'container_create',
            ['stableid' => 'cont_aaaaaaaaaaaa', 'type' => 'group', 'label' => 'C',
            'geometryjson' => '{"x":0,"y":0,"w":100,"h":100}'],
            1
        )['revision'];

        $state = (new reconstruction_service())->reconstruct($ws->id, $rev);

        fwrite(STDERR, "\n=== Replay bei rev $rev ===\n");
        fwrite(STDERR, 'nodes: ' . count($state['nodes']) . "\n");
        fwrite(STDERR, 'relations: ' . count($state['relations']) . "\n");
        fwrite(STDERR, 'containers: ' . count($state['containers']) . "\n");

        $this->assertCount(2, $state['nodes'], 'expected 2 nodes');
        $this->assertCount(1, $state['relations'], 'expected 1 relation');
        $this->assertCount(1, $state['containers'], 'expected 1 container');
    }

    /**
     * Proves the reported symptom's cause: when nodes/relations exist only in
     * the tables (no create operations in the op-log) but a container was added
     * via an operation, the replay reconstructs only the container. This is the
     * "old data" case — the fix is to re-materialise such maps, not a code bug.
     *
     * @return void
     */
    public function test_replay_ignores_rows_without_operations(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id,
            'userid' => 0,
            'groupid' => 0,
            'currentrevision' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        // Nodes written straight into the table (as legacy/pre-op-log data would
        // be) — no node_create operation is logged.
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $ws->id,
            'stableid' => 'node_ffffffffffff',
            'type' => 'concept',
            'label' => 'Legacy',
            'contentformat' => FORMAT_HTML,
            'deleted' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // A container added the normal way (through an operation).
        $svc = new operation_service();
        $rev = $svc->apply(
            $ws->id,
            0,
            'container_create',
            ['stableid' => 'cont_ffffffffffff', 'type' => 'group', 'label' => 'C',
            'geometryjson' => '{"x":0,"y":0,"w":80,"h":80}'],
            1
        )['revision'];

        $state = (new reconstruction_service())->reconstruct($ws->id, $rev);

        // The replay sees only the container, because only its operation exists.
        $this->assertCount(0, $state['nodes'], 'legacy table rows are not replayed');
        $this->assertCount(1, $state['containers'], 'operation-created container is replayed');
    }
}
