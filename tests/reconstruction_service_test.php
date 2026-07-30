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
use mod_vimipad\local\service\workspace_service;

/**
 * Tests for reconstructing a workspace state at a past revision.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\reconstruction_service
 */
final class reconstruction_service_test extends \advanced_testcase {
    /**
     * Replaying the op log rebuilds the topology at each revision.
     *
     * @return void
     */
    public function test_reconstruct_across_revisions(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0]
        );
        $context = \context_module::instance($instance->cmid);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $workspace = (new workspace_service())->get_or_create_for_user(
            $instance,
            $context,
            (int) $user->id
        );
        $wsid = (int) $workspace->id;
        $ops = new operation_service();
        $uid = (int) $user->id;

        // Build: +A, +B, A->B, update A label, delete B.
        $ops->apply($wsid, 0, 'node_create', ['stableid' => 'node_00000000000a', 'type' => 'concept', 'label' => 'A'], $uid);
        $ops->apply($wsid, 1, 'node_create', ['stableid' => 'node_00000000000b', 'type' => 'concept', 'label' => 'B'], $uid);
        $ops->apply(
            $wsid,
            2,
            'relation_create',
            [
                'stableid' => 'rel_0000000000c1',
                'sourceid' => 'node_00000000000a',
                'targetid' => 'node_00000000000b',
                'type' => 'link',
            ],
            $uid
        );
        $ops->apply($wsid, 3, 'node_update', ['stableid' => 'node_00000000000a', 'label' => 'A2'], $uid);
        $ops->apply($wsid, 4, 'node_delete', ['stableid' => 'node_00000000000b'], $uid);

        $service = new reconstruction_service();

        // At revision 2: both nodes, no relation yet.
        $state = $service->reconstruct($wsid, 2);
        $this->assertEqualsCanonicalizing(['node_00000000000a', 'node_00000000000b'], $this->ids($state['nodes']));
        $this->assertCount(0, $state['relations']);

        // At revision 3: the relation exists.
        $state = $service->reconstruct($wsid, 3);
        $this->assertCount(1, $state['relations']);
        $this->assertSame('rel_0000000000c1', $state['relations'][0]->stableid);

        // At revision 4: A carries the updated label.
        $state = $service->reconstruct($wsid, 4);
        $bykey = $this->keyed($state['nodes']);
        $this->assertSame('A2', $bykey['node_00000000000a']->label);

        // At revision 5: B is gone and its relation is dropped.
        $state = $service->reconstruct($wsid, 5);
        $this->assertSame(['node_00000000000a'], $this->ids($state['nodes']));
        $this->assertCount(0, $state['relations']);
    }

    /**
     * Container operations are replayed so historical revisions show containers.
     *
     * @return void
     */
    public function test_reconstruct_containers(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0]
        );
        $context = \context_module::instance($instance->cmid);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $workspace = (new workspace_service())->get_or_create_for_user($instance, $context, (int) $user->id);
        $wsid = (int) $workspace->id;
        $ops = new operation_service();
        $uid = (int) $user->id;

        // Build: +C1, update C1 (label + geometry), +C2, delete C2.
        $ops->apply($wsid, 0, 'container_create', [
            'stableid' => 'cont_00000000000a', 'type' => 'group', 'label' => 'Sec',
            'geometryjson' => '{"x":0,"y":0,"w":100,"h":80}',
        ], $uid);
        $ops->apply($wsid, 1, 'container_update', [
            'stableid' => 'cont_00000000000a', 'label' => 'Section',
            'geometryjson' => '{"x":10,"y":10,"w":200,"h":150}',
        ], $uid);
        $ops->apply($wsid, 2, 'container_create', [
            'stableid' => 'cont_00000000000b', 'type' => 'group', 'label' => 'Temp',
        ], $uid);
        $ops->apply($wsid, 3, 'container_delete', ['stableid' => 'cont_00000000000b'], $uid);

        $service = new reconstruction_service();

        // At revision 1: C1 with its original label.
        $state = $service->reconstruct($wsid, 1);
        $this->assertCount(1, $state['containers']);
        $this->assertSame('Sec', $state['containers'][0]->label);

        // At revision 2: C1 carries the updated label and geometry.
        $state = $service->reconstruct($wsid, 2);
        $bykey = $this->keyed($state['containers']);
        $this->assertSame('Section', $bykey['cont_00000000000a']->label);
        $this->assertSame('{"x":10,"y":10,"w":200,"h":150}', $bykey['cont_00000000000a']->geometryjson);

        // At revision 3: both containers exist.
        $state = $service->reconstruct($wsid, 3);
        $this->assertEqualsCanonicalizing(
            ['cont_00000000000a', 'cont_00000000000b'],
            $this->ids($state['containers'])
        );

        // At revision 4: the deleted container is gone.
        $state = $service->reconstruct($wsid, 4);
        $this->assertSame(['cont_00000000000a'], $this->ids($state['containers']));
    }

    /**
     * Stable ids of a list of reconstructed records.
     *
     * @param array $records The records.
     * @return string[]
     */
    private function ids(array $records): array {
        return array_map(static fn($record) => $record->stableid, $records);
    }

    /**
     * Index reconstructed records by stable id.
     *
     * @param array $records The records.
     * @return array
     */
    private function keyed(array $records): array {
        $keyed = [];
        foreach ($records as $record) {
            $keyed[$record->stableid] = $record;
        }
        return $keyed;
    }
}
