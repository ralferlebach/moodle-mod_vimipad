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

use mod_vimipad\local\service\peer_review_service;

/**
 * Tests for peer review allocation, reviews and aggregation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\peer_review_service
 */
final class peer_review_service_test extends \advanced_testcase {
    /**
     * Create an activity with submitted maps for a number of students.
     *
     * @param int $students How many students submit.
     * @param int $perreview Reviews wanted per submission.
     * @return array{0: \stdClass, 1: array<int,int>} Instance and snapshotid => userid.
     */
    private function setup_submissions(int $students, int $perreview = 2): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'peerreviewmode' => 1, 'peerreviewcount' => $perreview,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $owners = [];
        for ($i = 0; $i < $students; $i++) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $workspace = $generator->create_workspace($module, (int) $student->id, [
                ['stableid' => 'node_' . sprintf('%012x', $i + 1), 'label' => 'Concept ' . $i],
            ], true);
            $owners[(int) $workspace->snapshotid] = (int) $student->id;
        }

        $instance = $DB->get_record('vimipad', ['id' => $module->id], '*', MUST_EXIST);
        return [$instance, $owners];
    }

    /**
     * Every submission gets the requested number of reviewers, and nobody reviews their own.
     *
     * @return void
     */
    public function test_allocation_spreads_and_excludes_self(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance, $owners] = $this->setup_submissions(4, 2);

        $created = (new peer_review_service())->allocate($instance);

        $this->assertSame(8, $created);
        foreach ($owners as $snapshotid => $ownerid) {
            $reviews = $DB->get_records('vimipad_peerreview', ['snapshotid' => $snapshotid]);
            $this->assertCount(2, $reviews);
            foreach ($reviews as $review) {
                $this->assertNotEquals($ownerid, (int) $review->reviewerid);
            }
        }
    }

    /**
     * Allocating twice does not duplicate or reshuffle existing allocations.
     *
     * @return void
     */
    public function test_allocation_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance] = $this->setup_submissions(3, 2);

        $service = new peer_review_service();
        $service->allocate($instance);
        $before = $DB->count_records('vimipad_peerreview');
        $created = $service->allocate($instance);

        $this->assertSame(0, $created);
        $this->assertSame($before, $DB->count_records('vimipad_peerreview'));
    }

    /**
     * A single submission cannot be peer reviewed.
     *
     * @return void
     */
    public function test_single_submission_gets_no_allocation(): void {
        $this->resetAfterTest();
        [$instance] = $this->setup_submissions(1, 2);

        $this->assertSame(0, (new peer_review_service())->allocate($instance));
    }

    /**
     * A reviewer sees their allocations and can record a verdict.
     *
     * @return void
     */
    public function test_save_review(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance] = $this->setup_submissions(3, 1);

        $service = new peer_review_service();
        $service->allocate($instance);

        $allocation = $DB->get_record_sql('SELECT * FROM {vimipad_peerreview} ORDER BY id ASC', [], IGNORE_MULTIPLE);
        $this->assertNotEmpty($allocation);

        $service->save_review((int) $allocation->snapshotid, (int) $allocation->reviewerid, 0.75, 'Good coverage.');

        $stored = $DB->get_record('vimipad_peerreview', ['id' => $allocation->id], '*', MUST_EXIST);
        $this->assertEquals(peer_review_service::STATUS_SUBMITTED, (int) $stored->status);
        $this->assertEqualsWithDelta(0.75, (float) $stored->score, 0.0001);
        $this->assertSame('Good coverage.', $stored->reviewcomment);

        // The reviewer can see their own allocation listed.
        $mine = $service->for_reviewer($instance, (int) $allocation->reviewerid);
        $this->assertNotEmpty($mine);
        $this->assertArrayHasKey((int) $allocation->id, $mine);
    }

    /**
     * Reviewing without an allocation is refused.
     *
     * @return void
     */
    public function test_review_without_allocation_is_refused(): void {
        $this->resetAfterTest();
        [$instance, $owners] = $this->setup_submissions(2, 1);
        $snapshotid = (int) array_key_first($owners);

        $this->expectException(\moodle_exception::class);
        (new peer_review_service())->save_review($snapshotid, 999999, 0.5, 'No allocation.');
    }

    /**
     * Aggregation reports count, mean, median and how many reviews are outstanding.
     *
     * @return void
     */
    public function test_aggregate(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance, $owners] = $this->setup_submissions(4, 3);
        $service = new peer_review_service();
        $service->allocate($instance);

        $snapshotid = (int) array_key_first($owners);
        $allocations = array_values($DB->get_records('vimipad_peerreview', ['snapshotid' => $snapshotid], 'id ASC'));
        $this->assertCount(3, $allocations);

        $service->save_review($snapshotid, (int) $allocations[0]->reviewerid, 0.4, 'a');
        $service->save_review($snapshotid, (int) $allocations[1]->reviewerid, 0.8, 'b');

        $aggregate = $service->aggregate($snapshotid);

        $this->assertSame(2, $aggregate['count']);
        $this->assertEqualsWithDelta(0.6, $aggregate['mean'], 0.0001);
        $this->assertEqualsWithDelta(0.6, $aggregate['median'], 0.0001);
        $this->assertSame(1, $aggregate['pending']);
    }

    /**
     * Guidance is empty without a reference and returns scorer results with one.
     *
     * @return void
     */
    public function test_guidance_follows_reference(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance, $owners] = $this->setup_submissions(2, 1);
        $snapshotids = array_keys($owners);

        $service = new peer_review_service();
        $this->assertSame([], $service->guidance($instance, (int) $snapshotids[0]));

        $instance->referencesnapshotid = (int) $snapshotids[1];
        $DB->update_record('vimipad', $instance);

        $guidance = $service->guidance($instance, (int) $snapshotids[0]);
        $this->assertNotEmpty($guidance);
        $this->assertArrayHasKey('reference', $guidance);
    }

    /**
     * Allocation does not issue a per-candidate existence query: the query
     * count for a larger cohort must not grow with submissions x reviewers.
     * We assert a modest, roughly submission-proportional bound rather than an
     * exact number, which stays robust to incidental query changes.
     *
     * @return void
     */
    public function test_allocation_query_count_is_bounded(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance] = $this->setup_submissions(20, 3);

        $before = $DB->perf_get_reads() + $DB->perf_get_writes();
        (new peer_review_service())->allocate($instance);
        $after = $DB->perf_get_reads() + $DB->perf_get_writes();
        $queries = $after - $before;

        // 20 submissions x 3 reviews = 60 inserts, plus a handful of setup
        // reads. The old code added ~20x20 = 400 record_exists reads on top.
        // A ceiling of 120 proves the N+1 existence probing is gone while
        // leaving ample head-room for the necessary inserts and lookups.
        $this->assertLessThan(120, $queries, "allocation issued $queries queries; expected the N+1 probes to be gone");
    }
}
