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
        $snapshot = $service->create_submission($workspace, 'conceptmap', $this->studentid);

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
     * A snapshot is immutable: later edits do not change it.
     *
     * @return void
     */
    public function test_snapshot_is_immutable(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid]);
        $service = new snapshot_service();
        $snapshot = $service->create_submission($workspace, 'conceptmap', $this->studentid);

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
        $snapshot = $snapservice->create_submission($workspace, 'conceptmap', $this->studentid);

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
        $snapshot = $snapservice->create_submission($workspace, 'conceptmap', $this->studentid);

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
}
