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

use mod_vimipad\local\output\grading_panel;

/**
 * Tests for the grading panel, exercised the way view.php calls it.
 *
 * view.php resolves the course module with get_course_and_cm_from_cmid(), which
 * returns a cm_info rather than a stdClass. These tests call the panel with a
 * real cm_info so a mismatched parameter type is caught here rather than only in
 * a browser (Behat) run.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\output\grading_panel
 */
final class grading_panel_test extends \advanced_testcase {
    /**
     * Create a course, activity, teacher and a submitted snapshot.
     *
     * @return array{0: \cm_info, 1: \stdClass, 2: \stdClass, 3: \stdClass, 4: \context_module}
     */
    private function setup_submission(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id, 'grade' => 100]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');
        $workspace = $generator->create_workspace($module, (int) $student->id, [
            ['stableid' => 'node_00000000000a', 'label' => 'Plant'],
            ['stableid' => 'node_00000000000b', 'label' => 'Oxygen'],
        ], true);

        // Exactly how view.php resolves the course module.
        [, $cm] = get_course_and_cm_from_cmid($module->cmid, 'vimipad');
        $context = \context_module::instance($cm->id);
        $instance = $DB->get_record('vimipad', ['id' => $module->id], '*', MUST_EXIST);
        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => $workspace->snapshotid], '*', MUST_EXIST);
        $freshworkspace = $DB->get_record('vimipad_workspace', ['id' => $workspace->id], '*', MUST_EXIST);

        $this->setUser($teacher);

        return [$cm, $instance, $snapshot, $freshworkspace, $context];
    }

    /**
     * The detail URL accepts a cm_info and points at the grading tab.
     *
     * @return void
     */
    public function test_detail_url_accepts_cm_info(): void {
        $this->resetAfterTest();
        [$cm, , $snapshot] = $this->setup_submission();

        $this->assertInstanceOf(\cm_info::class, $cm);

        $url = grading_panel::detail_url($cm, (int) $snapshot->id)->out(false);
        $this->assertStringContainsString('tab=grade', $url);
        $this->assertStringContainsString('snapshotid=' . $snapshot->id, $url);
    }

    /**
     * The detail URL also accepts the stdClass form of a course module.
     *
     * @return void
     */
    public function test_detail_url_accepts_stdclass(): void {
        $this->resetAfterTest();
        [$cm, $instance, $snapshot] = $this->setup_submission();

        $legacycm = get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST);
        $this->assertNotInstanceOf(\cm_info::class, $legacycm);

        $url = grading_panel::detail_url($legacycm, (int) $snapshot->id)->out(false);
        $this->assertStringContainsString('id=' . $cm->id, $url);
    }

    /**
     * Rendering the grading detail with a cm_info produces the panel without error.
     *
     * @return void
     */
    public function test_render_with_cm_info(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$cm, $instance, $snapshot, $workspace, $context] = $this->setup_submission();

        $PAGE->set_url('/mod/vimipad/view.php', ['id' => $cm->id]);
        $PAGE->set_context($context);

        ob_start();
        grading_panel::render($cm, $context, $instance, $snapshot, $workspace);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('snapshotid=' . $snapshot->id, $output);
    }

    /**
     * Handling an action with no action parameters present is a harmless no-op.
     *
     * @return void
     */
    public function test_handle_action_without_action_is_noop(): void {
        global $DB;

        $this->resetAfterTest();
        [$cm, $instance, $snapshot, $workspace, $context] = $this->setup_submission();
        $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);

        grading_panel::handle_action($cm, $course, $context, $instance, $snapshot, $workspace);

        // No grade should have been recorded by a no-op call.
        $this->assertFalse($DB->record_exists('vimipad_grade', ['snapshotid' => $snapshot->id]));
    }
}
