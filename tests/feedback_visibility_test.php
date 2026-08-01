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
 * Learner-facing feedback visibility.
 *
 * A learner can retrieve their own grade and feedback once their submission is
 * graded, and nothing before that — the data behind the feedback tab.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\grading_service::get_feedback_for_user
 * @covers     \mod_vimipad\local\output\feedback_panel
 */
final class feedback_visibility_test extends \advanced_testcase {
    /**
     * Set up a course, an individual-mode activity and an enrolled learner.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass}
     *     [course, instance, learner, workspace]
     */
    private function setup_activity(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $instance = $gen->create_module('vimipad', ['course' => $course->id, 'grade' => 100]);
        $learner = $gen->create_and_enrol($course, 'student');

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $learner->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);

        return [$course, $instance, $learner, $workspace];
    }

    /**
     * Before grading, a learner has no feedback.
     *
     * @return void
     */
    public function test_no_feedback_before_grading(): void {
        $this->resetAfterTest();
        [, $instance, $learner] = $this->setup_activity();

        $this->assertNull((new grading_service())->get_feedback_for_user($instance, (int) $learner->id));
    }

    /**
     * After grading, the learner sees their grade, feedback text and graded
     * snapshot.
     *
     * @return void
     */
    public function test_feedback_after_grading(): void {
        $this->resetAfterTest();
        [, $instance, $learner, $workspace] = $this->setup_activity();

        // Submit and grade.
        $snapshot = (new snapshot_service())->finalize($instance, $workspace, (int) $learner->id);
        (new grading_service())->save_grade(
            $instance,
            $workspace,
            (int) $snapshot->id,
            87.5,
            'Well structured map.',
            FORMAT_PLAIN,
            2
        );

        $feedback = (new grading_service())->get_feedback_for_user($instance, (int) $learner->id);
        $this->assertNotNull($feedback);
        $this->assertEquals(87.5, $feedback->grade);
        $this->assertEquals(100.0, $feedback->grademax);
        $this->assertSame('Well structured map.', $feedback->feedback);
        $this->assertEquals((int) $snapshot->id, $feedback->snapshotid);
        $this->assertSame((int) $snapshot->revision, $feedback->snapshotrevision);
        $this->assertSame((int) $workspace->id, $feedback->snapshotworkspaceid);
        $this->assertGreaterThan(0, $feedback->dategraded);
    }

    /**
     * A graded submission embeds the assessed-map viewer: the panel renders the
     * "view the assessed map" button and needs_viewer() reports true.
     *
     * @return void
     */
    public function test_feedback_embeds_assessed_map(): void {
        $this->resetAfterTest();
        [, $instance, $learner, $workspace] = $this->setup_activity();
        $cm = get_coursemodule_from_instance('vimipad', (int) $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Before grading: no viewer.
        $this->assertFalse(
            \mod_vimipad\local\output\feedback_panel::needs_viewer($instance, (int) $learner->id)
        );

        $snapshot = (new snapshot_service())->finalize($instance, $workspace, (int) $learner->id);
        (new grading_service())->save_grade(
            $instance,
            $workspace,
            (int) $snapshot->id,
            70.0,
            'Nice work.',
            FORMAT_PLAIN,
            2
        );

        // After grading: the viewer is present.
        $this->assertTrue(
            \mod_vimipad\local\output\feedback_panel::needs_viewer($instance, (int) $learner->id)
        );
        $html = \mod_vimipad\local\output\feedback_panel::render($context, $instance, (int) $learner->id);
        $this->assertStringContainsString(get_string('feedback:showmap', 'mod_vimipad'), $html);
        $this->assertStringContainsString('data-vimipad-revision', $html);
        $this->assertStringContainsString('vimipad-revision-viewer', $html);
    }

    /**
     * The rendered panel shows the notice before grading and the grade after.
     *
     * @return void
     */
    public function test_panel_render(): void {
        $this->resetAfterTest();
        [, $instance, $learner, $workspace] = $this->setup_activity();
        $cm = get_coursemodule_from_instance('vimipad', (int) $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $before = \mod_vimipad\local\output\feedback_panel::render($context, $instance, (int) $learner->id);
        $this->assertStringContainsString(get_string('feedback:none', 'mod_vimipad'), $before);

        $snapshot = (new snapshot_service())->finalize($instance, $workspace, (int) $learner->id);
        (new grading_service())->save_grade(
            $instance,
            $workspace,
            (int) $snapshot->id,
            60.0,
            'Good start.',
            FORMAT_PLAIN,
            2
        );

        $after = \mod_vimipad\local\output\feedback_panel::render($context, $instance, (int) $learner->id);
        $this->assertStringContainsString('Good start.', $after);
        $this->assertStringContainsString('60.00', $after);
    }
}
