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

use externallib_advanced_testcase;
use mod_vimipad\external\acquire_lock;
use mod_vimipad\external\renew_lock;
use mod_vimipad\external\release_lock;
use mod_vimipad\external\poll_changes;
use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\operation\operation_type;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the collaboration external functions.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\acquire_lock
 * @covers     \mod_vimipad\external\renew_lock
 * @covers     \mod_vimipad\external\release_lock
 * @covers     \mod_vimipad\external\poll_changes
 * @covers     \mod_vimipad\external\helper
 */
final class collaboration_external_test extends externallib_advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private \stdClass $instance;

    /** @var \stdClass The course module. */
    private \stdClass $cm;

    /** @var int The workspace id. */
    private int $workspaceid;

    /** @var \stdClass User A. */
    private \stdClass $usera;

    /** @var \stdClass User B. */
    private \stdClass $userb;

    /**
     * Set up a group workspace shared by two students.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        // Collaboration mode 2 = course-wide shared workspace, so both users edit the same one.
        $this->instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 2]
        );
        $this->cm = get_coursemodule_from_instance('vimipad', $this->instance->id);

        $this->usera = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userb = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * User A acquires a lock; user B is refused and learns A holds it.
     *
     * @return void
     */
    public function test_acquire_refuses_second_user_and_reports_holder(): void {
        $this->setUser($this->usera);
        $a = acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $a = \core_external\external_api::clean_returnvalue(acquire_lock::execute_returns(), $a);
        $this->assertTrue($a['acquired']);
        $this->assertSame((int) $this->usera->id, $a['userid']);

        $this->setUser($this->userb);
        $b = acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $b = \core_external\external_api::clean_returnvalue(acquire_lock::execute_returns(), $b);
        $this->assertFalse($b['acquired']);
        $this->assertSame((int) $this->usera->id, $b['userid'], 'B must see that A holds the lock.');
    }

    /**
     * The holder can renew; a non-holder cannot.
     *
     * @return void
     */
    public function test_renew_only_by_holder(): void {
        $this->setUser($this->usera);
        acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $r = renew_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $r = \core_external\external_api::clean_returnvalue(renew_lock::execute_returns(), $r);
        $this->assertTrue($r['acquired']);

        $this->setUser($this->userb);
        $r2 = renew_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $r2 = \core_external\external_api::clean_returnvalue(renew_lock::execute_returns(), $r2);
        $this->assertFalse($r2['acquired']);
    }

    /**
     * Releasing frees the element for another user.
     *
     * @return void
     */
    public function test_release_frees_element(): void {
        $this->setUser($this->usera);
        acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $rel = release_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $rel = \core_external\external_api::clean_returnvalue(release_lock::execute_returns(), $rel);
        $this->assertTrue($rel['status']);

        $this->setUser($this->userb);
        $b = acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $b = \core_external\external_api::clean_returnvalue(acquire_lock::execute_returns(), $b);
        $this->assertTrue($b['acquired']);
    }

    /**
     * poll_changes returns operations since a revision, layout and presence.
     *
     * @return void
     */
    public function test_poll_returns_delta_layout_and_presence(): void {
        // Seed two operations as user A.
        $this->setUser($this->usera);
        $opservice = new operation_service();
        $r1 = $opservice->apply(
            $this->workspaceid,
            0,
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'A'],
            (int) $this->usera->id
        );
        $opservice->apply(
            $this->workspaceid,
            $r1['revision'],
            operation_type::NODE_CREATE,
            ['type' => 'concept', 'label' => 'B'],
            (int) $this->usera->id
        );

        // A holds a lock (presence).
        acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');

        // User B polls from revision 1: should see the second op only, plus A's lease.
        $this->setUser($this->userb);
        $poll = poll_changes::execute($this->cm->id, $this->workspaceid, 1);
        $poll = \core_external\external_api::clean_returnvalue(poll_changes::execute_returns(), $poll);

        $this->assertSame(2, $poll['revision']);
        $this->assertCount(1, $poll['operations']);
        $this->assertSame(2, $poll['operations'][0]['revision']);
        $this->assertCount(1, $poll['leases']);
        $this->assertSame((int) $this->usera->id, $poll['leases'][0]['userid']);
        $this->assertSame('node_aaaaaaaaaaaa', $poll['leases'][0]['targetstableid']);
    }

    /**
     * poll_changes drops expired leases from presence.
     *
     * @return void
     */
    public function test_poll_omits_expired_leases(): void {
        global $DB;
        $this->setUser($this->usera);
        acquire_lock::execute($this->cm->id, $this->workspaceid, 'node', 'node_aaaaaaaaaaaa');
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() - 1,
            ['workspaceid' => $this->workspaceid]
        );

        $poll = poll_changes::execute($this->cm->id, $this->workspaceid, 0);
        $poll = \core_external\external_api::clean_returnvalue(poll_changes::execute_returns(), $poll);
        $this->assertCount(0, $poll['leases']);
    }

    /**
     * A user without edit access cannot poll.
     *
     * @return void
     */
    public function test_poll_requires_edit_access(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->expectException(\require_login_exception::class);
        poll_changes::execute($this->cm->id, $this->workspaceid, 0);
    }
}
