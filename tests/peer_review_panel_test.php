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

use mod_vimipad\local\output\peer_review_panel;
use mod_vimipad\local\service\peer_review_service;

/**
 * Tests for the peer review panel, exercised the way view.php calls it.
 *
 * view.php resolves the course module with get_course_and_cm_from_cmid(), which
 * returns a cm_info. Rendering here with a real cm_info keeps parameter-type
 * mismatches out of the browser.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\output\peer_review_panel
 */
final class peer_review_panel_test extends \advanced_testcase {
    /**
     * Create an activity with peer review on, two submissions and allocations.
     *
     * @return array{0: \cm_info, 1: \stdClass, 2: \context_module, 3: \stdClass}
     */
    private function setup_peer_activity(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'peerreviewmode' => 1, 'peerreviewcount' => 1,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $students = [];
        foreach ([1, 2] as $index) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $generator->create_workspace($module, (int) $student->id, [
                ['stableid' => 'node_' . sprintf('%012x', $index), 'label' => 'Concept ' . $index],
            ], true);
            $students[] = $student;
        }

        $instance = $DB->get_record('vimipad', ['id' => $module->id], '*', MUST_EXIST);
        (new peer_review_service())->allocate($instance);

        [, $cm] = get_course_and_cm_from_cmid($module->cmid, 'vimipad');
        $context = \context_module::instance($cm->id);

        return [$cm, $instance, $context, $students[0]];
    }

    /**
     * A reviewer's list of allocated submissions renders without error.
     *
     * @return void
     */
    public function test_render_list_with_cm_info(): void {
        global $PAGE, $DB;

        $this->resetAfterTest();
        [$cm, $instance, $context] = $this->setup_peer_activity();

        $allocation = $DB->get_record_sql('SELECT * FROM {vimipad_peerreview} ORDER BY id ASC', [], IGNORE_MULTIPLE);
        $this->setUser($allocation->reviewerid);
        $PAGE->set_url('/mod/vimipad/view.php', ['id' => $cm->id]);
        $PAGE->set_context($context);

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $allocation->reviewerid);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertInstanceOf(\cm_info::class, $cm);
    }

    /**
     * A user with no allocations still gets a rendered (empty) panel.
     *
     * @return void
     */
    public function test_render_without_allocations(): void {
        global $PAGE;

        $this->resetAfterTest();
        [$cm, $instance, $context] = $this->setup_peer_activity();

        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $PAGE->set_url('/mod/vimipad/view.php', ['id' => $cm->id]);
        $PAGE->set_context($context);

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $outsider->id);
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    /**
     * The detail URL accepts a cm_info and carries the review id.
     *
     * @return void
     */
    public function test_detail_url(): void {
        $this->resetAfterTest();
        [$cm] = $this->setup_peer_activity();

        $url = peer_review_panel::detail_url($cm, 7)->out(false);

        $this->assertStringContainsString('id=' . $cm->id, $url);
        $this->assertStringContainsString('7', $url);
    }

    /**
     * Handling with no action parameters leaves the review untouched.
     *
     * @return void
     */
    public function test_handle_action_without_action_is_noop(): void {
        global $DB;

        $this->resetAfterTest();
        [$cm, $instance, $context] = $this->setup_peer_activity();

        $allocation = $DB->get_record_sql('SELECT * FROM {vimipad_peerreview} ORDER BY id ASC', [], IGNORE_MULTIPLE);
        $this->setUser($allocation->reviewerid);

        peer_review_panel::handle_action($cm, $context, $instance, (int) $allocation->reviewerid);

        $after = $DB->get_record('vimipad_peerreview', ['id' => $allocation->id], '*', MUST_EXIST);
        $this->assertSame((int) $allocation->status, (int) $after->status);
    }
}
