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

use mod_vimipad\local\service\layout_service;
use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\service\snapshot_service;

/**
 * Membership integrity and spatial derivation tests.
 *
 * Membership truth is spatial (derived from container geometry and layout at
 * snapshot/export time); the vimipad_membership table is a compatibility store
 * whose rows must never reference deleted elements.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\operation_service
 * @covers     \mod_vimipad\local\service\snapshot_service
 */
final class membership_integrity_test extends \advanced_testcase {
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
     * Apply an operation and return [revision, stableid].
     *
     * @param operation_service $service The service.
     * @param int $rev The current revision.
     * @param string $type The operation type.
     * @param array $payload The payload.
     * @return array{0: int, 1: ?string}
     */
    private function op(operation_service $service, int $rev, string $type, array $payload): array {
        $r = $service->apply($this->workspaceid, $rev, $type, $payload, 1);
        return [(int) $r['revision'], $r['stableid'] ?? null];
    }

    /**
     * membership_add rejects a member item that does not exist live.
     *
     * @return void
     */
    public function test_membership_add_requires_existing_item(): void {
        $service = new operation_service();
        [$rev, $cid] = $this->op($service, 0, 'container_create', ['type' => 'group']);

        $this->expectException(\moodle_exception::class);
        $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'node', 'itemstableid' => 'node_missing0001',
        ], 1);
    }

    /**
     * A container cannot be added as a member of itself.
     *
     * @return void
     */
    public function test_membership_add_rejects_self_membership(): void {
        $service = new operation_service();
        [$rev, $cid] = $this->op($service, 0, 'container_create', ['type' => 'group']);

        $this->expectException(\invalid_parameter_exception::class);
        $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'container', 'itemstableid' => $cid,
        ], 1);
    }

    /**
     * Deleting a node removes its membership rows and those of its relations.
     *
     * @return void
     */
    public function test_node_delete_purges_memberships(): void {
        global $DB;
        $service = new operation_service();
        [$rev, $nodea] = $this->op($service, 0, 'node_create', ['type' => 'concept', 'label' => 'A']);
        [$rev, $nodeb] = $this->op($service, $rev, 'node_create', ['type' => 'concept', 'label' => 'B']);
        [$rev, $relid] = $this->op($service, $rev, 'relation_create', [
            'sourceid' => $nodea, 'targetid' => $nodeb, 'type' => 'link',
        ]);
        [$rev, $cid] = $this->op($service, $rev, 'container_create', ['type' => 'group']);
        [$rev] = $this->op($service, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'node', 'itemstableid' => $nodea,
        ]);
        [$rev] = $this->op($service, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'relation', 'itemstableid' => $relid,
        ]);
        $containerid = (int) $DB->get_field('vimipad_container', 'id', ['stableid' => $cid]);
        $this->assertCount(2, $DB->get_records('vimipad_membership', ['containerid' => $containerid]));

        // Deleting node A soft-deletes the attached relation; both membership
        // rows must be gone afterwards.
        $this->op($service, $rev, 'node_delete', ['stableid' => $nodea]);
        $this->assertCount(0, $DB->get_records('vimipad_membership', ['containerid' => $containerid]));
    }

    /**
     * Deleting a relation removes its membership rows.
     *
     * @return void
     */
    public function test_relation_delete_purges_memberships(): void {
        global $DB;
        $service = new operation_service();
        [$rev, $nodea] = $this->op($service, 0, 'node_create', ['type' => 'concept', 'label' => 'A']);
        [$rev, $nodeb] = $this->op($service, $rev, 'node_create', ['type' => 'concept', 'label' => 'B']);
        [$rev, $relid] = $this->op($service, $rev, 'relation_create', [
            'sourceid' => $nodea, 'targetid' => $nodeb, 'type' => 'link',
        ]);
        [$rev, $cid] = $this->op($service, $rev, 'container_create', ['type' => 'group']);
        [$rev] = $this->op($service, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'relation', 'itemstableid' => $relid,
        ]);

        $this->op($service, $rev, 'relation_delete', ['stableid' => $relid]);
        $containerid = (int) $DB->get_field('vimipad_container', 'id', ['stableid' => $cid]);
        $this->assertCount(0, $DB->get_records('vimipad_membership', ['containerid' => $containerid]));
    }

    /**
     * Deleting a container also removes rows where it was itself a member.
     *
     * @return void
     */
    public function test_container_delete_purges_both_directions(): void {
        global $DB;
        $service = new operation_service();
        [$rev, $outer] = $this->op($service, 0, 'container_create', ['type' => 'group', 'label' => 'Outer']);
        [$rev, $inner] = $this->op($service, $rev, 'container_create', ['type' => 'group', 'label' => 'Inner']);
        [$rev] = $this->op($service, $rev, 'membership_add', [
            'containerstableid' => $outer, 'itemtype' => 'container', 'itemstableid' => $inner,
        ]);
        $outerid = (int) $DB->get_field('vimipad_container', 'id', ['stableid' => $outer]);
        $this->assertCount(1, $DB->get_records('vimipad_membership', ['containerid' => $outerid]));

        // Deleting the inner container must remove the row in which it is a
        // member of the outer one.
        $this->op($service, $rev, 'container_delete', ['stableid' => $inner]);
        $this->assertCount(0, $DB->get_records('vimipad_membership', ['containerid' => $outerid]));
    }

    /**
     * Snapshots derive membership spatially: what the canvas shows is what the
     * snapshot (and thus assessment/export) contains — stale table rows do not
     * leak in, and un-persisted spatial containment is captured.
     *
     * @return void
     */
    public function test_snapshot_membership_is_spatial(): void {
        global $DB;
        $service = new operation_service();
        [$rev, $inside] = $this->op($service, 0, 'node_create', ['type' => 'concept', 'label' => 'Inside']);
        [$rev, $outside] = $this->op($service, $rev, 'node_create', ['type' => 'concept', 'label' => 'Outside']);
        [$rev, $cid] = $this->op($service, $rev, 'container_create', [
            'type' => 'group', 'label' => 'Biology',
            'geometryjson' => json_encode(['x' => 100, 'y' => 100, 'w' => 200, 'h' => 100]),
        ]);

        // A stale persistent membership claims the OUTSIDE node as a member;
        // no persistent row exists for the INSIDE node.
        [$rev] = $this->op($service, $rev, 'membership_add', [
            'containerstableid' => $cid, 'itemtype' => 'node', 'itemstableid' => $outside,
        ]);

        // The layout places one node inside the box and one outside.
        (new layout_service())->save($this->workspaceid, 'conceptmap', json_encode([
            'v' => 1,
            'pos' => [
                $inside => ['x' => 150, 'y' => 150],
                $outside => ['x' => 500, 'y' => 500],
            ],
            'size' => [],
        ]), '', 1);

        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);
        $data = (new snapshot_service())->build_normalized($workspace, 'conceptmap');

        $this->assertCount(1, $data['memberships']);
        $this->assertSame($inside, $data['memberships'][0]['itemstableid']);
        $this->assertSame($cid, $data['memberships'][0]['containerstableid']);
    }
}
