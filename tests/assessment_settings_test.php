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
use mod_vimipad\local\service\assess_service;
use mod_vimipad\local\service\peer_review_service;

/**
 * Tests for the activity-level assessment settings and the peer review tab.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\output\peer_review_panel
 */
final class assessment_settings_test extends \advanced_testcase {
    /**
     * Create an activity with two submitted maps and allocated peer reviews.
     *
     * @param array $overrides Extra activity settings.
     * @return array{0: \stdClass, 1: \cm_info, 2: \context_module, 3: array, 4: array}
     */
    private function setup_activity(array $overrides = []): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $settings = array_merge(
            ['course' => $course->id, 'peerreviewmode' => 1, 'peerreviewcount' => 1],
            $overrides
        );
        $module = $this->getDataGenerator()->create_module('vimipad', $settings);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $students = [];
        $snapshots = [];
        for ($i = 0; $i < 2; $i++) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $workspace = $generator->create_workspace($module, (int) $student->id, [
                ['stableid' => 'node_' . sprintf('%012x', $i + 1), 'label' => 'Concept ' . $i],
            ], true);
            $students[] = $student;
            $snapshots[] = (int) $workspace->snapshotid;
        }

        [, $cm] = get_course_and_cm_from_cmid($module->cmid, 'vimipad');
        $context = \context_module::instance($cm->id);
        $instance = $DB->get_record('vimipad', ['id' => $module->id], '*', MUST_EXIST);

        return [$instance, $cm, $context, $students, $snapshots];
    }

    /**
     * An empty scorer selection runs every installed scorer.
     *
     * @return void
     */
    public function test_empty_selection_enables_all(): void {
        $this->resetAfterTest();
        [$instance] = $this->setup_activity();
        $service = new assess_service();

        $this->assertEmpty($instance->activescorers);
        $this->assertTrue($service->scorer_enabled($instance, 'reference'));
        $this->assertTrue($service->scorer_enabled($instance, 'structure'));
    }

    /**
     * A selection restricts which scorers run, including in score_all.
     *
     * @return void
     */
    public function test_selection_restricts_scorers(): void {
        $this->resetAfterTest();
        [$instance, , , , $snapshots] = $this->setup_activity(['activescorers' => 'structure']);
        $service = new assess_service();

        $this->assertTrue($service->scorer_enabled($instance, 'structure'));
        $this->assertFalse($service->scorer_enabled($instance, 'reference'));

        $instance->referencesnapshotid = $snapshots[1];
        $results = $service->score_all($instance, $snapshots[0]);

        $this->assertArrayHasKey('structure', $results);
        $this->assertArrayNotHasKey('reference', $results);
    }

    /**
     * The form's multi-select array is stored as a comma-separated list.
     *
     * @return void
     */
    public function test_form_data_is_normalised(): void {
        $this->resetAfterTest();

        $data = (object) ['activescorers' => ['structure', 'reference']];
        vimipad_prepare_scorer_fields($data);
        $this->assertSame('structure,reference', $data->activescorers);

        // A value already in stored form is left untouched.
        $stored = (object) ['activescorers' => 'tree'];
        vimipad_prepare_scorer_fields($stored);
        $this->assertSame('tree', $stored->activescorers);
    }

    /**
     * The peer tab renders a reviewer's allocation list without revealing authors.
     *
     * @return void
     */
    public function test_peer_tab_lists_allocations_anonymously(): void {
        $this->resetAfterTest();
        [$instance, $cm, $context, $students] = $this->setup_activity();
        (new peer_review_service())->allocate($instance);

        // The first student reviews the second student's map.
        $this->setUser($students[0]);

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $students[0]->id);
        $output = ob_get_clean();

        $this->assertStringContainsString('Submission 1', $output);
        $this->assertStringNotContainsString($students[1]->firstname, $output);
        $this->assertStringNotContainsString($students[1]->lastname, $output);
    }

    /**
     * Opening an allocated review shows the map and the review form.
     *
     * @return void
     */
    public function test_peer_tab_renders_review_form(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        [$instance, $cm, $context, $students] = $this->setup_activity();
        $service = new peer_review_service();
        $service->allocate($instance);

        $review = $DB->get_record_sql(
            'SELECT * FROM {vimipad_peerreview} WHERE reviewerid = ? ORDER BY id ASC',
            [$students[0]->id],
            IGNORE_MULTIPLE
        );
        $this->assertNotEmpty($review);

        $this->setUser($students[0]);
        $PAGE->set_url('/mod/vimipad/view.php', ['id' => $cm->id]);
        $PAGE->set_context($context);
        $_GET['reviewid'] = (int) $review->id;

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $students[0]->id);
        $output = ob_get_clean();
        unset($_GET['reviewid']);

        $this->assertStringContainsString('peerscore', $output);
        $this->assertStringContainsString('peercomment', $output);
    }

    /**
     * The tab reports that peer review is off when the setting is disabled.
     *
     * @return void
     */
    public function test_peer_tab_disabled_notice(): void {
        $this->resetAfterTest();
        [$instance, $cm, $context, $students] = $this->setup_activity(['peerreviewmode' => 0]);
        $this->setUser($students[0]);

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $students[0]->id);
        $output = ob_get_clean();

        $this->assertStringContainsString(
            get_string('peerreviewdisabled', 'mod_vimipad'),
            html_to_text($output, 0, false)
        );
    }

    /**
     * A reviewer cannot open a review allocated to somebody else.
     *
     * @return void
     */
    public function test_foreign_review_falls_back_to_list(): void {
        global $DB;

        $this->resetAfterTest();
        [$instance, $cm, $context, $students] = $this->setup_activity();
        (new peer_review_service())->allocate($instance);

        $foreign = $DB->get_record_sql(
            'SELECT * FROM {vimipad_peerreview} WHERE reviewerid <> ? ORDER BY id ASC',
            [$students[0]->id],
            IGNORE_MULTIPLE
        );
        $this->assertNotEmpty($foreign);

        $this->setUser($students[0]);
        $_GET['reviewid'] = (int) $foreign->id;

        ob_start();
        peer_review_panel::render($cm, $context, $instance, (int) $students[0]->id);
        $output = ob_get_clean();
        unset($_GET['reviewid']);

        // Falls back to the reviewer's own list rather than showing someone else's task.
        $this->assertStringNotContainsString('peercomment', $output);
    }
}
