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

use mod_vimipad\local\service\snapshot_service;
use mod_vimipad\local\service\grading_service;

/**
 * Tests for snapshot submission and grading.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\snapshot_service
 * @covers     \mod_vimipad\local\service\grading_service
 */
final class snapshot_grading_test extends \advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private $instance;

    /** @var \stdClass The course. */
    private $course;

    /** @var int The workspace id. */
    private $workspaceid;

    /** @var int The student user id. */
    private $studentid;

    /** @var \context_module The module context. */
    private $context;

    /**
     * Create course, activity, student, workspace and one node.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $this->course->id, 'collaborationmode' => 0, 'grade' => 100]
        );
        $this->context = \context_module::instance($this->instance->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->studentid = (int) $student->id;

        global $DB;
        $now = time();
        $this->workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => $this->studentid, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $this->workspaceid, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept',
            'label' => 'Energy', 'contentformat' => FORMAT_HTML, 'createdby' => $this->studentid,
            'modifiedby' => $this->studentid, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
    }

    /**
     * Submitting creates an immutable snapshot and locks the workspace.
     *
     * @return void
     */
    public function test_create_submission_locks_workspace(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);

        $service = new snapshot_service();
        $snapshot = $service->create_submission($this->instance, $workspace, $this->context, $this->studentid)['snapshot'];

        $this->assertSame(snapshot_service::STATUS_SUBMITTED, (int) $snapshot->status);

        $ws = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $this->assertSame(1, (int) $ws->locked);
        $this->assertEquals($snapshot->id, $ws->submittedsnapshotid);

        $decoded = json_decode($snapshot->snapshotjson, true);
        $this->assertSame('conceptmap', $decoded['profile']);
        $this->assertCount(1, $decoded['nodes']);
        $this->assertSame('Energy', $decoded['nodes'][0]['label']);
    }

    /**
     * A second submission of an already-locked workspace is rejected, so a
     * double click cannot create two snapshots.
     *
     * @return void
     */
    public function test_double_submission_is_rejected(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $service = new snapshot_service();

        $service->create_submission($this->instance, $workspace, $this->context, $this->studentid);

        // The caller still holds the pre-submission workspace record.
        $this->expectException(\moodle_exception::class);
        $service->create_submission($this->instance, $workspace, $this->context, $this->studentid);
    }

    /**
     * Grading a course-wide shared workspace applies the grade to every
     * participant who may submit.
     *
     * @return void
     */
    public function test_course_grade_applies_to_all_participants(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 2, 'grade' => 100]
        );
        $s1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $s2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $snapshotid = $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $wsid, 'revision' => 1, 'snapshotjson' => '{}',
            'submittedby' => $s1->id, 'status' => snapshot_service::STATUS_SUBMITTED, 'timecreated' => $now,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid]);

        (new grading_service())->save_grade(
            $instance,
            $workspace,
            (int) $snapshotid,
            75.0,
            'Good',
            FORMAT_PLAIN,
            (int) $teacher->id
        );

        $this->assertEquals(
            75.0,
            (float) $DB->get_field('vimipad_grade', 'grade', ['vimipadid' => $instance->id, 'userid' => $s1->id])
        );
        $this->assertEquals(
            75.0,
            (float) $DB->get_field('vimipad_grade', 'grade', ['vimipadid' => $instance->id, 'userid' => $s2->id])
        );
    }

    /**
     * A snapshot is immutable: later edits do not change it.
     *
     * @return void
     */
    public function test_snapshot_is_immutable(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $service = new snapshot_service();
        $snapshot = $service->create_submission($this->instance, $workspace, $this->context, $this->studentid)['snapshot'];

        // Change the underlying node afterwards.
        $DB->set_field(
            'vimipad_node',
            'label',
            'Changed',
            ['workspaceid' => $this->workspaceid, 'stableid' => 'node_aaaaaaaaaaaa']
        );

        $reloaded = $service->get((int) $snapshot->id);
        $decoded = json_decode($reloaded->snapshotjson, true);
        $this->assertSame('Energy', $decoded['nodes'][0]['label']);
    }

    /**
     * Grading stores the grade, sets snapshot status and pushes to the gradebook.
     *
     * @return void
     */
    public function test_grading_pushes_to_gradebook(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $snapservice = new snapshot_service();
        $snapshot = $snapservice->create_submission($this->instance, $workspace, $this->context, $this->studentid)['snapshot'];

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $grading = new grading_service();
        $grading->save_grade(
            $this->instance,
            $workspace,
            (int) $snapshot->id,
            85.0,
            'Well structured.',
            FORMAT_PLAIN,
            (int) $teacher->id
        );

        // Stored grade.
        $stored = $DB->get_record(
            'vimipad_grade',
            ['vimipadid' => $this->instance->id, 'userid' => $this->studentid]
        );
        $this->assertNotEmpty($stored);
        $this->assertEquals(85.0, (float) $stored->grade);

        // Snapshot status advanced.
        $reloaded = $snapservice->get((int) $snapshot->id);
        $this->assertSame(snapshot_service::STATUS_GRADED, (int) $reloaded->status);

        // Gradebook received it.
        $grades = grade_get_grades(
            $this->course->id,
            'mod',
            'vimipad',
            $this->instance->id,
            $this->studentid
        );
        $item = reset($grades->items);
        $this->assertEquals(85.0, (float) $item->grades[$this->studentid]->grade);
    }

    /**
     * Submitting a snapshot via the external function fires the submitted event.
     *
     * @return void
     * @covers \mod_vimipad\event\snapshot_submitted
     */
    public function test_submitting_fires_event(): void {
        $this->setUser($this->studentid);
        $cm = get_coursemodule_from_instance('vimipad', $this->instance->id);

        $sink = $this->redirectEvents();
        \mod_vimipad\external\create_snapshot::execute((int) $cm->id, $this->workspaceid);
        $events = $sink->get_events();
        $sink->close();

        $found = false;
        foreach ($events as $event) {
            if ($event instanceof \mod_vimipad\event\snapshot_submitted) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'snapshot_submitted event was not triggered');
    }

    /**
     * Saving a grade fires the graded event.
     *
     * @return void
     * @covers \mod_vimipad\event\snapshot_graded
     */
    public function test_grading_fires_event(): void {
        global $DB;
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $snapservice = new snapshot_service();
        $snapshot = $snapservice->create_submission($this->instance, $workspace, $this->context, $this->studentid)['snapshot'];

        $sink = $this->redirectEvents();
        $grading = new grading_service();
        $grading->save_grade($this->instance, $workspace, (int) $snapshot->id, 70.0, 'ok', FORMAT_PLAIN, (int) $teacher->id);
        $events = $sink->get_events();
        $sink->close();

        $found = false;
        foreach ($events as $event) {
            if ($event instanceof \mod_vimipad\event\snapshot_graded) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'snapshot_graded event was not triggered');
    }

    /**
     * Submission is blocked once the cut-off date has passed.
     *
     * @return void
     */
    public function test_submission_blocked_after_cutoff(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);
        $this->instance->cutoffdate = time() - 100;

        $this->expectException(\moodle_exception::class);
        (new snapshot_service())->create_submission($this->instance, $workspace, $this->context, $this->studentid);
    }

    /**
     * With group consensus, the map is submitted only once every member has.
     *
     * @return void
     */
    public function test_group_consensus_pending_until_all_submit(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => 1, 'requireallteamsubmit' => 1,
        ]);
        $context = \context_module::instance($instance->cmid);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $u2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $u1);
        groups_add_member($group, $u2);

        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => $group->id,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);
        $service = new snapshot_service();

        // First member submits: pending, no snapshot, still unlocked.
        $first = $service->create_submission($instance, $workspace, $context, (int) $u1->id);
        $this->assertNull($first['snapshot']);
        $this->assertSame(1, $first['pending']);
        $this->assertSame(0, (int) $DB->get_field('vimipad_workspace', 'locked', ['id' => $wsid]));

        // Second member submits: the map is submitted, locked and intents cleared.
        $second = $service->create_submission($instance, $workspace, $context, (int) $u2->id);
        $this->assertNotNull($second['snapshot']);
        $this->assertSame(0, $second['pending']);
        $this->assertSame(1, (int) $DB->get_field('vimipad_workspace', 'locked', ['id' => $wsid]));
        $this->assertSame(0, $DB->count_records('vimipad_submissionintent', ['workspaceid' => $wsid]));
    }
}
