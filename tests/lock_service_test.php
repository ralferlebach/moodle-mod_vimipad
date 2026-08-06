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

use mod_vimipad\local\service\lock_service;

/**
 * Tests for the element lease (locking + presence) service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\lock_service
 */
final class lock_service_test extends \advanced_testcase {
    /** @var int A workspace id to lock elements within. */
    private int $workspaceid;

    /**
     * Create a workspace to hold leases.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => 2, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * A free element can be acquired, and the lease records holder and expiry.
     *
     * @return void
     */
    public function test_acquire_free_element(): void {
        $service = new lock_service();
        $lease = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        $this->assertTrue($lease->acquired);
        $this->assertSame(101, $lease->userid);
        $this->assertGreaterThan(time(), $lease->timeexpires);
    }

    /**
     * The holder may re-acquire (idempotent) their own lease, extending it.
     *
     * @return void
     */
    public function test_reacquire_own_lease_is_idempotent(): void {
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $again = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        $this->assertTrue($again->acquired);
        $this->assertSame(101, $again->userid);
    }

    /**
     * Scenario from the design: A holds the node; B tries to grab it and is
     * refused, and is told who holds it (presence).
     *
     * @return void
     */
    public function test_second_user_is_refused_and_sees_holder(): void {
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        $bresult = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertFalse($bresult->acquired);
        $this->assertSame(101, $bresult->userid, 'B must learn that user 101 holds the lease.');
    }

    /**
     * Renewing pushes the expiry further into the future.
     *
     * @return void
     */
    public function test_renew_extends_expiry(): void {
        global $DB;
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        // Backdate the lease so renewal has something to extend.
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() + 1,
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_aaaaaaaaaaaa']
        );

        $renewed = $service->renew($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $this->assertTrue($renewed->acquired);
        $this->assertGreaterThan(time() + 10, $renewed->timeexpires);
    }

    /**
     * Only the holder may renew; a non-holder renewal fails.
     *
     * @return void
     */
    public function test_non_holder_cannot_renew(): void {
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        $renewed = $service->renew($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertFalse($renewed->acquired);
    }

    /**
     * After the lease has been taken over by another user, the original holder's
     * renewal fails and reports the real (new) holder rather than clobbering it.
     * This is the observable contract the compare-and-swap in renew() protects.
     *
     * @return void
     */
    public function test_renew_after_takeover_reports_new_holder(): void {
        global $DB;
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        // Lapse the lease, then let user 202 take it over.
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() - 1,
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_aaaaaaaaaaaa']
        );
        $takeover = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertTrue($takeover->acquired);

        // The original holder's renewal must fail and surface 202 as the holder,
        // and must not have extended 202's lease.
        $before = (int) $DB->get_field(
            'vimipad_lock',
            'timeexpires',
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_aaaaaaaaaaaa']
        );
        $renewed = $service->renew($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 999);
        $this->assertFalse($renewed->acquired);
        $this->assertSame(202, $renewed->userid);
        $after = (int) $DB->get_field(
            'vimipad_lock',
            'timeexpires',
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_aaaaaaaaaaaa']
        );
        $this->assertSame($before, $after, '202\'s lease must not be clobbered by 101\'s renew');
    }

    /**
     * An expired lease is treated as free: a different user may acquire it.
     *
     * @return void
     */
    public function test_expired_lease_can_be_taken_over(): void {
        global $DB;
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);

        // Force the lease into the past.
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() - 5,
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_aaaaaaaaaaaa']
        );

        $bresult = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertTrue($bresult->acquired, 'An expired lease must be takeable by another user.');
        $this->assertSame(202, $bresult->userid);
    }

    /**
     * Releasing frees the element for others.
     *
     * @return void
     */
    public function test_release_frees_element(): void {
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $service->release($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101);

        $bresult = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertTrue($bresult->acquired);
    }

    /**
     * A non-holder cannot release someone else's lease.
     *
     * @return void
     */
    public function test_non_holder_cannot_release(): void {
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $service->release($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202);

        // Still held by 101.
        $bresult = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 303, 15);
        $this->assertFalse($bresult->acquired);
        $this->assertSame(101, $bresult->userid);
    }

    /**
     * get_active_leases returns current holders (presence) and omits expired ones.
     *
     * @return void
     */
    public function test_active_leases_reflect_presence(): void {
        global $DB;
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $service->acquire($this->workspaceid, 'relation', 'rel_bbbbbbbbbbbb', 202, 15);

        // Expire the second one.
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() - 1,
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'rel_bbbbbbbbbbbb']
        );

        $active = $service->get_active_leases($this->workspaceid);
        $this->assertCount(1, $active);
        $lease = reset($active);
        $this->assertSame('node_aaaaaaaaaaaa', $lease->targetstableid);
        $this->assertSame(101, (int) $lease->userid);
    }

    /**
     * purge_expired removes only stale leases.
     *
     * @return void
     */
    public function test_purge_expired(): void {
        global $DB;
        $service = new lock_service();
        $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 101, 15);
        $service->acquire($this->workspaceid, 'node', 'node_cccccccccccc', 202, 15);
        $DB->set_field(
            'vimipad_lock',
            'timeexpires',
            time() - 1,
            ['workspaceid' => $this->workspaceid, 'targetstableid' => 'node_cccccccccccc']
        );

        $service->purge_expired($this->workspaceid);

        $this->assertSame(1, $DB->count_records('vimipad_lock', ['workspaceid' => $this->workspaceid]));
    }

    /**
     * Compare-and-swap: if another caller takes over an expired lease between
     * this caller's read and write, this caller must NOT also report success.
     * We simulate the interleaving by mutating the row to a fresh holder after
     * the expired state is established, matching the CAS precondition failing.
     *
     * @return void
     */
    public function test_expired_takeover_is_compare_and_swap(): void {
        global $DB;
        $service = new lock_service();

        // An expired lease held by 101.
        $id = (int) $DB->insert_record('vimipad_lock', (object) [
            'workspaceid' => $this->workspaceid, 'targettype' => 'node',
            'targetstableid' => 'node_aaaaaaaaaaaa', 'userid' => 101,
            'timeacquired' => time() - 100, 'timeexpires' => time() - 10,
        ]);

        // A concurrent winner (303) grabs the lease first: the row no longer
        // matches the (olduser=101, oldexpiry) that a racing caller read.
        $DB->update_record('vimipad_lock', (object) [
            'id' => $id, 'userid' => 303,
            'timeacquired' => time(), 'timeexpires' => time() + 15,
        ]);

        // A caller that read the pre-takeover (expired, 101) state now tries to
        // acquire. The lease is currently held validly by 303, so acquire must
        // refuse and report 303, not overwrite it.
        $result = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertFalse((bool) $result->acquired);
        $this->assertSame(303, (int) $result->userid);
    }

    /**
     * Owner-renewal edge case: if the caller's OWN lease has expired and a
     * different user has taken it over in the meantime, the original owner must
     * not overwrite the new holder with an unconditional update. Routing the
     * owner's expired lease through the CAS path prevents that.
     *
     * @return void
     */
    public function test_expired_own_lease_does_not_clobber_new_holder(): void {
        global $DB;
        $service = new lock_service();

        // User 202 held a lease that has since expired.
        $id = (int) $DB->insert_record('vimipad_lock', (object) [
            'workspaceid' => $this->workspaceid, 'targettype' => 'node',
            'targetstableid' => 'node_aaaaaaaaaaaa', 'userid' => 202,
            'timeacquired' => time() - 100, 'timeexpires' => time() - 10,
        ]);

        // A new user 303 validly took it over (CAS winner).
        $DB->update_record('vimipad_lock', (object) [
            'id' => $id, 'userid' => 303,
            'timeacquired' => time(), 'timeexpires' => time() + 15,
        ]);

        // The original owner 202, acting on its stale (expired) view, tries to
        // renew. It must not overwrite 303: acquire refuses and reports 303.
        $result = $service->acquire($this->workspaceid, 'node', 'node_aaaaaaaaaaaa', 202, 15);
        $this->assertFalse((bool) $result->acquired);
        $this->assertSame(303, (int) $result->userid);
    }
}
