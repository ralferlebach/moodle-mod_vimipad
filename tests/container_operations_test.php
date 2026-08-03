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

use mod_vimipad\local\operation\operation_type;
use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\service\import_service;

/**
 * Tests for container/membership operations and their import round-trip.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\operation\operation_type
 * @covers     \mod_vimipad\local\service\operation_service
 */
final class container_operations_test extends \advanced_testcase {
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
     * The new container/membership types are registered.
     *
     * @return void
     */
    public function test_new_operation_types_are_known(): void {
        foreach (
            [
            operation_type::CONTAINER_CREATE, operation_type::CONTAINER_UPDATE,
            operation_type::CONTAINER_DELETE, operation_type::MEMBERSHIP_ADD,
            operation_type::MEMBERSHIP_REMOVE,
            ] as $type
        ) {
            $this->assertTrue(operation_type::is_known($type), $type);
        }
    }

    /**
     * A bad membership itemtype is rejected by payload validation.
     *
     * @return void
     */
    public function test_membership_itemtype_is_validated(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload(operation_type::MEMBERSHIP_ADD, [
            'containerstableid' => 'container_aaaaaaaaaaaa',
            'itemtype' => 'bogus',
            'itemstableid' => 'node_aaaaaaaaaaaa',
        ]);
    }

    /**
     * Full container lifecycle through the operation service: create, update,
     * add and remove a membership, then delete (which drops memberships).
     *
     * @return void
     */
    public function test_container_lifecycle(): void {
        global $DB;
        $service = new operation_service();
        $rev = 0;

        // A node to place into the container.
        $r = $service->apply($this->workspaceid, $rev, 'node_create', ['type' => 'concept', 'label' => 'A'], 1);
        $rev = $r['revision'];
        $nodeid = $r['stableid'];

        // Create a container.
        $r = $service->apply($this->workspaceid, $rev, 'container_create', ['type' => 'group', 'label' => 'Cluster'], 1);
        $rev = $r['revision'];
        $containerid = $r['stableid'];
        $this->assertNotEmpty($containerid);
        $this->assertTrue($DB->record_exists('vimipad_container', [
            'workspaceid' => $this->workspaceid, 'stableid' => $containerid, 'deleted' => 0,
        ]));

        // Update its label.
        $r = $service->apply($this->workspaceid, $rev, 'container_update', [
            'stableid' => $containerid, 'label' => 'Renamed',
        ], 1);
        $rev = $r['revision'];
        $this->assertSame('Renamed', $DB->get_field('vimipad_container', 'label', ['stableid' => $containerid]));

        // Add the node as a member.
        $r = $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $containerid, 'itemtype' => 'node', 'itemstableid' => $nodeid, 'sortorder' => 3,
        ], 1);
        $rev = $r['revision'];
        $cid = (int) $DB->get_field('vimipad_container', 'id', ['stableid' => $containerid]);
        $this->assertTrue($DB->record_exists('vimipad_membership', [
            'containerid' => $cid, 'itemtype' => 'node', 'itemstableid' => $nodeid,
        ]));

        // Adding again is an upsert (still one row), updating sortorder.
        $r = $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $containerid, 'itemtype' => 'node', 'itemstableid' => $nodeid, 'sortorder' => 9,
        ], 1);
        $rev = $r['revision'];
        $this->assertCount(1, $DB->get_records('vimipad_membership', ['containerid' => $cid]));
        $this->assertEquals(9, $DB->get_field('vimipad_membership', 'sortorder', ['containerid' => $cid]));

        // Remove the membership.
        $r = $service->apply($this->workspaceid, $rev, 'membership_remove', [
            'containerstableid' => $containerid, 'itemtype' => 'node', 'itemstableid' => $nodeid,
        ], 1);
        $rev = $r['revision'];
        $this->assertCount(0, $DB->get_records('vimipad_membership', ['containerid' => $cid]));

        // Delete the container (soft) and confirm memberships are gone.
        $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $containerid, 'itemtype' => 'node', 'itemstableid' => $nodeid,
        ], 1);
        $rev = (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $this->workspaceid]);
        $service->apply($this->workspaceid, $rev, 'container_delete', ['stableid' => $containerid], 1);
        $this->assertEquals(1, $DB->get_field('vimipad_container', 'deleted', ['id' => $cid]));
        $this->assertCount(0, $DB->get_records('vimipad_membership', ['containerid' => $cid]));
    }

    /**
     * Importing an export that carries containers and memberships round-trips
     * them, remapping stable ids onto the freshly created elements.
     *
     * @return void
     */
    public function test_import_round_trips_containers(): void {
        global $DB;

        $envelope = [
            'generator' => 'mod_vimipad',
            'formatversion' => 1,
            'data' => [
                'profile' => 'conceptmap',
                'nodes' => [
                    ['stableid' => 'node_oldaaaaaaaa', 'type' => 'concept', 'label' => 'Photosynthesis'],
                ],
                'relations' => [],
                'containers' => [
                    ['stableid' => 'container_oldaaa', 'type' => 'group', 'label' => 'Biology'],
                ],
                'memberships' => [
                    [
                        'containerstableid' => 'container_oldaaa',
                        'itemtype' => 'node',
                        'itemstableid' => 'node_oldaaaaaaaa',
                        'role' => null,
                        'sortorder' => 0,
                    ],
                ],
                'layout' => null,
            ],
        ];

        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);
        $counts = (new import_service())->import_json(json_encode($envelope), $workspace, 1, 'append');

        $this->assertSame(1, $counts['nodes']);
        $this->assertSame(1, $counts['containers']);
        $this->assertSame(1, $counts['memberships']);

        // The container exists (with a fresh stable id) and has exactly one
        // membership pointing at the imported node.
        $containers = $DB->get_records('vimipad_container', ['workspaceid' => $this->workspaceid, 'deleted' => 0]);
        $this->assertCount(1, $containers);
        $container = reset($containers);
        $this->assertSame('Biology', $container->label);
        $this->assertNotSame('container_oldaaa', $container->stableid, 'stable id should be remapped');

        $memberships = $DB->get_records('vimipad_membership', ['containerid' => (int) $container->id]);
        $this->assertCount(1, $memberships);
        $membership = reset($memberships);
        $this->assertSame('node', $membership->itemtype);
        $node = $DB->get_record('vimipad_node', ['workspaceid' => $this->workspaceid], '*', MUST_EXIST);
        $this->assertSame($node->stableid, $membership->itemstableid, 'membership should point at the imported node');
    }

    /**
     * A locked container is protected against update and delete for ordinary
     * editors, while an author (bypass) can still change it — the same lock
     * contract nodes and relations already have.
     *
     * @return void
     */
    public function test_locked_container_is_protected(): void {
        $service = new operation_service();
        $rev = 0;

        $r = $service->apply($this->workspaceid, $rev, 'container_create', ['type' => 'group', 'label' => 'Box'], 1);
        $rev = $r['revision'];
        $cid = $r['stableid'];

        // Lock it (metadatajson carries the lock flag).
        $locked = json_encode(['locked' => true, 'editable' => []]);
        $r = $service->apply($this->workspaceid, $rev, 'container_update', [
            'stableid' => $cid, 'metadatajson' => $locked,
        ], 1);
        $rev = $r['revision'];

        // An ordinary editor (no bypass) cannot move/resize or delete it.
        try {
            $service->apply($this->workspaceid, $rev, 'container_update', [
                'stableid' => $cid, 'geometryjson' => json_encode(['x' => 5, 'y' => 5, 'w' => 200, 'h' => 150]),
            ], 1);
            $this->fail('Expected error:elementlocked on geometry change.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:elementlocked', $e->errorcode);
        }
        try {
            $service->apply($this->workspaceid, $rev, 'container_delete', ['stableid' => $cid], 1);
            $this->fail('Expected error:elementlocked on delete.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:elementlocked', $e->errorcode);
        }

        // An author (bypasslocks) can still change and delete it.
        $author = new operation_service(true);
        $r = $author->apply($this->workspaceid, $rev, 'container_update', [
            'stableid' => $cid, 'geometryjson' => json_encode(['x' => 5, 'y' => 5, 'w' => 200, 'h' => 150]),
        ], 1);
        $rev = $r['revision'];
        $author->apply($this->workspaceid, $rev, 'container_delete', ['stableid' => $cid], 1);
    }
}
