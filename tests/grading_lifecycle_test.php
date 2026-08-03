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

use mod_vimipad\local\service\grading_service;
use mod_vimipad\local\service\snapshot_service;
use mod_vimipad\local\service\workspace_service;

/**
 * Grading lifecycle contracts.
 *
 * (a) The server validates the grade domain (0..activity maximum, points
 * only); (b) the grade goes to the recipient cohort frozen at submission time,
 * not to whoever is a member at grading time; (c) reopening withdraws the
 * submitted state of ungraded snapshots (STATUS_REOPENED sorts below draft so
 * "status >= submitted" consumers exclude it) while a graded snapshot and its
 * grade remain as history.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\grading_service
 * @covers     \mod_vimipad\local\service\workspace_service
 * @covers     \mod_vimipad\local\service\snapshot_service
 */
final class grading_lifecycle_test extends \advanced_testcase {
    /**
     * Create a course and a group-mode activity with two group members.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass, 4: \stdClass, 5: \stdClass}
     *     [course, instance, group, membera, memberb, workspace]
     */
    private function setup_group_activity(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $instance = $gen->create_module('vimipad', [
            'course' => $course->id, 'grade' => 100, 'collaborationmode' => workspace_service::MODE_GROUP,
        ]);
        $membera = $gen->create_user();
        $memberb = $gen->create_user();
        $gen->enrol_user($membera->id, $course->id, 'student');
        $gen->enrol_user($memberb->id, $course->id, 'student');
        $group = $gen->create_group(['courseid' => $course->id]);
        groups_add_member($group, $membera);
        groups_add_member($group, $memberb);

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => $group->id,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);

        return [$course, $instance, $group, $membera, $memberb, $workspace];
    }

    /**
     * An out-of-range grade is rejected server-side.
     *
     * @return void
     */
    public function test_grade_domain_is_validated(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, , , , $workspace] = $this->setup_group_activity();
        $snapshotid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspace->id, 'revision' => 1,
            'snapshotjson' => '{}', 'submittedby' => 0,
            'status' => snapshot_service::STATUS_SUBMITTED, 'timecreated' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('0');
        (new grading_service())->save_grade($instance, $workspace, $snapshotid, 150.0, '', FORMAT_PLAIN, 2);
    }

    /**
     * The grade goes to the cohort frozen at submission time even when the
     * group membership changed before grading.
     *
     * @return void
     */
    public function test_grade_uses_submission_time_cohort(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $instance, $group, $membera, $memberb, $workspace] = $this->setup_group_activity();

        // Submit through the real terminal step so the cohort gets frozen.
        $snapshot = (new snapshot_service())->finalize($instance, $workspace, (int) $membera->id);
        $cohort = json_decode((string) $snapshot->cohortjson ?? '', true);
        $this->assertEqualsCanonicalizing([(int) $membera->id, (int) $memberb->id], $cohort);

        // Membership changes after submission: B leaves, C joins.
        $memberc = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($memberc->id, $course->id, 'student');
        groups_remove_member($group, $memberb);
        groups_add_member($group, $memberc);

        (new grading_service())->save_grade($instance, $workspace, (int) $snapshot->id, 80.0, 'ok', FORMAT_PLAIN, 2);

        $this->assertTrue($DB->record_exists('vimipad_grade', ['userid' => $membera->id, 'vimipadid' => $instance->id]));
        $this->assertTrue($DB->record_exists('vimipad_grade', ['userid' => $memberb->id, 'vimipadid' => $instance->id]));
        $this->assertFalse($DB->record_exists('vimipad_grade', ['userid' => $memberc->id, 'vimipadid' => $instance->id]));
    }

    /**
     * Reopening marks ungraded submissions as reopened (excluded from every
     * "status >= submitted" consumer) and keeps graded history intact.
     *
     * @return void
     */
    public function test_reopen_lifecycle_contract(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, , $membera, , $workspace] = $this->setup_group_activity();

        // A graded snapshot from an earlier round, and a fresh submission.
        $gradedid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspace->id, 'revision' => 1, 'snapshotjson' => '{}',
            'submittedby' => $membera->id, 'status' => snapshot_service::STATUS_GRADED, 'timecreated' => time(),
        ]);
        $submitted = (new snapshot_service())->finalize($instance, $workspace, (int) $membera->id);
        $DB->insert_record('vimipad_grade', (object) [
            'vimipadid' => $instance->id, 'userid' => $membera->id, 'snapshotid' => $gradedid,
            'grade' => 70.0, 'feedback' => '', 'feedbackformat' => FORMAT_PLAIN, 'grader' => 2,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        (new workspace_service())->reopen((int) $workspace->id);

        // The fresh submission is withdrawn below the submitted threshold...
        $this->assertEquals(
            snapshot_service::STATUS_REOPENED,
            (int) $DB->get_field('vimipad_snapshot', 'status', ['id' => $submitted->id])
        );
        $this->assertLessThan(snapshot_service::STATUS_SUBMITTED, snapshot_service::STATUS_REOPENED);
        // ...the graded snapshot and the awarded grade remain as history...
        $this->assertEquals(
            snapshot_service::STATUS_GRADED,
            (int) $DB->get_field('vimipad_snapshot', 'status', ['id' => $gradedid])
        );
        $this->assertTrue($DB->record_exists('vimipad_grade', ['userid' => $membera->id]));
        // ...and the workspace is editable again for a fresh submission.
        $this->assertEquals(0, (int) $DB->get_field('vimipad_workspace', 'locked', ['id' => $workspace->id]));
    }
}
