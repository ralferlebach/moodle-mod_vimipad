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
 * Reconstruction round-trip: replaying the operation log must reproduce the
 * live state exactly, for every operation type.
 *
 * This is the invariant the journal/revision view depends on. It would have
 * caught the relation_retarget payload-field mismatch automatically.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\reconstruction_service
 */
final class reconstruction_roundtrip_test extends \advanced_testcase {
    /** @var int The workspace id. */
    private int $workspaceid;

    /**
     * Create a course, module and an empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Normalize the live DB state into comparable maps.
     *
     * @return array{nodes: array, relations: array, containers: array}
     */
    private function live_state(): array {
        global $DB;
        $nodes = [];
        foreach ($DB->get_records('vimipad_node', ['workspaceid' => $this->workspaceid, 'deleted' => 0]) as $node) {
            $nodes[$node->stableid] = [
                'type' => $node->type, 'label' => $node->label,
                'content' => $node->content, 'metadatajson' => $node->metadatajson,
            ];
        }
        $relations = [];
        foreach ($DB->get_records('vimipad_relation', ['workspaceid' => $this->workspaceid, 'deleted' => 0]) as $rel) {
            $relations[$rel->stableid] = [
                'sourceid' => $rel->sourceid, 'targetid' => $rel->targetid, 'type' => $rel->type,
                'label' => $rel->label, 'direction' => (int) $rel->direction, 'metadatajson' => $rel->metadatajson,
            ];
        }
        $containers = [];
        foreach ($DB->get_records('vimipad_container', ['workspaceid' => $this->workspaceid, 'deleted' => 0]) as $c) {
            $containers[$c->stableid] = [
                'type' => $c->type, 'label' => $c->label,
                'geometryjson' => $c->geometryjson, 'metadatajson' => $c->metadatajson,
            ];
        }
        ksort($nodes);
        ksort($relations);
        ksort($containers);
        return ['nodes' => $nodes, 'relations' => $relations, 'containers' => $containers];
    }

    /**
     * Normalize a reconstruction result into the same comparable maps.
     *
     * @param array $state The reconstruct() result.
     * @return array{nodes: array, relations: array, containers: array}
     */
    private function reconstructed_state(array $state): array {
        $nodes = [];
        foreach ($state['nodes'] as $node) {
            $nodes[$node->stableid] = [
                'type' => $node->type, 'label' => $node->label,
                'content' => $node->content, 'metadatajson' => $node->metadatajson,
            ];
        }
        $relations = [];
        foreach ($state['relations'] as $rel) {
            $relations[$rel->stableid] = [
                'sourceid' => $rel->sourceid, 'targetid' => $rel->targetid, 'type' => $rel->type,
                'label' => $rel->label, 'direction' => (int) $rel->direction, 'metadatajson' => $rel->metadatajson,
            ];
        }
        $containers = [];
        foreach ($state['containers'] as $c) {
            $containers[$c->stableid] = [
                'type' => $c->type, 'label' => $c->label,
                'geometryjson' => $c->geometryjson, 'metadatajson' => $c->metadatajson,
            ];
        }
        ksort($nodes);
        ksort($relations);
        ksort($containers);
        return ['nodes' => $nodes, 'relations' => $relations, 'containers' => $containers];
    }

    /**
     * Apply every operation type at least once; the reconstruction at the final
     * revision must equal the live state, and intermediate revisions must show
     * the pre-retarget endpoints.
     *
     * @return void
     */
    public function test_every_operation_type_round_trips(): void {
        $service = new operation_service();
        $rev = 0;
        $apply = function (string $type, array $payload) use ($service, &$rev): ?string {
            $r = $service->apply($this->workspaceid, $rev, $type, $payload, 1);
            $rev = (int) $r['revision'];
            return $r['stableid'] ?? null;
        };

        // Nodes: create three, update one, delete one.
        $a = $apply('node_create', ['type' => 'concept', 'label' => 'A', 'content' => 'Alpha']);
        $b = $apply('node_create', ['type' => 'concept', 'label' => 'B']);
        $c = $apply('node_create', ['type' => 'concept', 'label' => 'C']);
        $d = $apply('node_create', ['type' => 'concept', 'label' => 'D (doomed)']);
        $apply('node_update', ['stableid' => $a, 'label' => 'A2', 'content' => 'Alpha 2']);

        // Relations: create two, update one, retarget one, delete one.
        $rab = $apply('relation_create', ['sourceid' => $a, 'targetid' => $b, 'type' => 'link', 'label' => 'ab']);
        $rbc = $apply('relation_create', ['sourceid' => $b, 'targetid' => $c, 'type' => 'link', 'label' => 'bc']);
        $rad = $apply('relation_create', ['sourceid' => $a, 'targetid' => $d, 'type' => 'link']);
        $apply('relation_update', ['stableid' => $rab, 'label' => 'ab2', 'direction' => 0]);
        $retargetrev = $rev;
        $apply('relation_retarget', ['stableid' => $rbc, 'newsource' => $a, 'newtarget' => $c]);
        $apply('relation_delete', ['stableid' => $rad]);

        // Deleting node D exercises the delete cascade in both worlds.
        $apply('node_delete', ['stableid' => $d]);

        // Containers: create two, update one, delete one; memberships add/remove
        // (they do not affect reconstruction output, but must not break replay).
        $g1 = $apply('container_create', [
            'type' => 'group', 'label' => 'G1',
            'geometryjson' => json_encode(['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]),
        ]);
        $g2 = $apply('container_create', ['type' => 'group', 'label' => 'G2 (doomed)']);
        $apply('container_update', [
            'stableid' => $g1,
            'geometryjson' => json_encode(['x' => 10, 'y' => 10, 'w' => 200, 'h' => 100]),
        ]);
        $apply('membership_add', ['containerstableid' => $g1, 'itemtype' => 'node', 'itemstableid' => $a]);
        $apply('membership_remove', ['containerstableid' => $g1, 'itemtype' => 'node', 'itemstableid' => $a]);
        $apply('container_delete', ['stableid' => $g2]);

        $reconstruction = new reconstruction_service();

        // Final revision: reconstruction must equal the live state exactly.
        $this->assertSame(
            $this->live_state(),
            $this->reconstructed_state($reconstruction->reconstruct($this->workspaceid, $rev))
        );

        // Before the retarget, the relation still points b -> c.
        $before = $this->reconstructed_state($reconstruction->reconstruct($this->workspaceid, $retargetrev));
        $this->assertSame($b, $before['relations'][$rbc]['sourceid']);
        $this->assertSame($c, $before['relations'][$rbc]['targetid']);

        // After the retarget (final state), it points a -> c.
        $after = $this->reconstructed_state($reconstruction->reconstruct($this->workspaceid, $rev));
        $this->assertSame($a, $after['relations'][$rbc]['sourceid']);
        $this->assertSame($c, $after['relations'][$rbc]['targetid']);
    }
}
