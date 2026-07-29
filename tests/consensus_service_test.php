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

use mod_vimipad\local\service\consensus_service;

/**
 * Tests for the group-consensus submission state machine.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\consensus_service
 */
final class consensus_service_test extends \advanced_testcase {
    /** @var \stdClass The activity instance. */
    private $instance;

    /** @var \context_module The module context. */
    private $context;

    /** @var \stdClass The group workspace. */
    private $workspace;

    /** @var int First member id. */
    private $u1;

    /** @var int Second member id. */
    private $u2;

    /**
     * Build a group activity with consensus, two members and a group workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => 1, 'requireallteamsubmit' => 1,
        ]);
        $this->context = \context_module::instance($this->instance->cmid);

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->u1 = (int) $user1->id;
        $this->u2 = (int) $user2->id;

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $user1);
        groups_add_member($group, $user2);

        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => null, 'groupid' => $group->id,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);
    }

    /**
     * open -> voting -> submitted across start and the final confirmation.
     *
     * @return void
     */
    public function test_full_flow(): void {
        global $DB;
        $service = new consensus_service();

        $this->assertSame(consensus_service::STATE_OPEN, $service->state($this->workspace));

        $service->start($this->instance, $this->workspace, $this->context, $this->u1);
        $this->assertSame(consensus_service::STATE_VOTING, $service->state($this->workspace));

        $status = $service->get_status($this->instance, $this->workspace, $this->context);
        $confirmed = [];
        foreach ($status['members'] as $member) {
            $confirmed[$member['userid']] = $member['confirmed'];
        }
        $this->assertTrue($confirmed[$this->u1]);
        $this->assertFalse($confirmed[$this->u2]);
        $this->assertSame($this->u1, $status['startedby']);

        $result = $service->confirm($this->instance, $this->workspace, $this->context, $this->u2);
        $this->assertSame(consensus_service::STATE_SUBMITTED, $result['state']);
        $this->assertGreaterThan(0, $result['snapshotid']);
        $this->assertSame(1, (int) $DB->get_field('vimipad_workspace', 'locked', ['id' => $this->workspace->id]));
        $this->assertSame(0, $DB->count_records('vimipad_submissionintent', ['workspaceid' => $this->workspace->id]));
    }

    /**
     * Cancelling clears confirmations and returns to open.
     *
     * @return void
     */
    public function test_cancel_returns_to_open(): void {
        global $DB;
        $service = new consensus_service();

        $service->start($this->instance, $this->workspace, $this->context, $this->u1);
        $service->cancel($this->instance, $this->workspace, $this->context, $this->u2);

        $this->assertSame(consensus_service::STATE_OPEN, $service->state($this->workspace));
        $this->assertSame(0, $DB->count_records('vimipad_submissionintent', ['workspaceid' => $this->workspace->id]));
    }

    /**
     * Starting twice is rejected.
     *
     * @return void
     */
    public function test_start_twice_rejected(): void {
        $service = new consensus_service();
        $service->start($this->instance, $this->workspace, $this->context, $this->u1);

        $this->expectException(\moodle_exception::class);
        $service->start($this->instance, $this->workspace, $this->context, $this->u2);
    }

    /**
     * Confirming without a process under way is rejected.
     *
     * @return void
     */
    public function test_confirm_without_start_rejected(): void {
        $service = new consensus_service();

        $this->expectException(\moodle_exception::class);
        $service->confirm($this->instance, $this->workspace, $this->context, $this->u1);
    }

    /**
     * A user who is not a submitting group member cannot take part.
     *
     * @return void
     */
    public function test_non_member_rejected(): void {
        $service = new consensus_service();
        $stranger = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        $service->start($this->instance, $this->workspace, $this->context, (int) $stranger->id);
    }
}
