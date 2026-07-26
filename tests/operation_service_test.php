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
use mod_vimipad\local\service\workspace_service;
use mod_vimipad\local\operation\operation_type;

/**
 * Tests for the operation service (mutations, revisions, conflicts).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\operation_service
 * @covers     \mod_vimipad\local\operation\operation_type
 */
final class operation_service_test extends \advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private $instance;

    /** @var int The workspace id under test. */
    private $workspaceid;

    /** @var int The acting user id. */
    private $userid;

    /**
     * Set up a course, activity, user and an empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userid = (int) $user->id;

        global $DB;
        $now = time();
        $this->workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id,
            'userid' => $this->userid,
            'groupid' => null,
            'currentrevision' => 0,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Applying a node_create operation inserts a node and bumps the revision.
     *
     * @return void
     */
    public function test_node_create_bumps_revision(): void {
        global $DB;
        $service = new operation_service();

        $result = $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'Energy'],
            $this->userid
        );

        $this->assertSame(1, $result['revision']);
        $this->assertNotEmpty($result['stableid']);
        $this->assertTrue($DB->record_exists(
            'vimipad_node',
            ['workspaceid' => $this->workspaceid, 'stableid' => $result['stableid']]
        ));

        $ws = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $this->assertSame(1, (int) $ws->currentrevision);
        $this->assertSame(1, $DB->count_records(
            'vimipad_operation',
            ['workspaceid' => $this->workspaceid]
        ));
    }

    /**
     * A stale base revision is rejected as a conflict.
     *
     * @return void
     */
    public function test_revision_conflict_is_rejected(): void {
        $service = new operation_service();
        $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'A'],
            $this->userid
        );

        $this->expectException(\moodle_exception::class);
        // Base revision 0 is now stale (current is 1).
        $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'B'],
            $this->userid
        );
    }

    /**
     * Creating a relation between two existing nodes works; a dangling
     * reference is rejected.
     *
     * @return void
     */
    public function test_relation_create_validates_endpoints(): void {
        $service = new operation_service();

        $a = $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'A'],
            $this->userid
        );
        $b = $service->apply(
            $this->workspaceid,
            1,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'B'],
            $this->userid
        );

        $rel = $service->apply($this->workspaceid, 2, operation_type::RELATION_CREATE, [
            'sourceid' => $a['stableid'],
            'targetid' => $b['stableid'],
            'type' => 'related',
        ], $this->userid);
        $this->assertSame(3, $rel['revision']);

        $this->expectException(\moodle_exception::class);
        $service->apply($this->workspaceid, 3, operation_type::RELATION_CREATE, [
            'sourceid' => $a['stableid'],
            'targetid' => 'node_deadbeef0000',
            'type' => 'related',
        ], $this->userid);
    }

    /**
     * Deleting a node soft-deletes it and its attached relations.
     *
     * @return void
     */
    public function test_node_delete_cascades_to_relations(): void {
        global $DB;
        $service = new operation_service();

        $a = $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'A'],
            $this->userid
        );
        $b = $service->apply(
            $this->workspaceid,
            1,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'B'],
            $this->userid
        );
        $rel = $service->apply($this->workspaceid, 2, operation_type::RELATION_CREATE, [
            'sourceid' => $a['stableid'], 'targetid' => $b['stableid'], 'type' => 'related',
        ], $this->userid);

        $service->apply(
            $this->workspaceid,
            3,
            operation_type::NODE_DELETE,
            ['stableid' => $a['stableid']],
            $this->userid
        );

        $node = $DB->get_record(
            'vimipad_node',
            ['workspaceid' => $this->workspaceid, 'stableid' => $a['stableid']]
        );
        $this->assertSame(1, (int) $node->deleted);

        $relation = $DB->get_record(
            'vimipad_relation',
            ['workspaceid' => $this->workspaceid, 'stableid' => $rel['stableid']]
        );
        $this->assertSame(1, (int) $relation->deleted);
    }

    /**
     * A locked workspace rejects further operations.
     *
     * @return void
     */
    public function test_locked_workspace_is_rejected(): void {
        global $DB;
        $DB->set_field('vimipad_workspace', 'locked', 1, ['id' => $this->workspaceid]);

        $service = new operation_service();
        $this->expectException(\moodle_exception::class);
        $service->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'A'],
            $this->userid
        );
    }

    /**
     * An unknown operation type is rejected before any mutation.
     *
     * @return void
     */
    public function test_unknown_operation_type_is_rejected(): void {
        $service = new operation_service();
        $this->expectException(\invalid_parameter_exception::class);
        $service->apply($this->workspaceid, 0, 'bogus_op', ['x' => 1], $this->userid);
    }
}
