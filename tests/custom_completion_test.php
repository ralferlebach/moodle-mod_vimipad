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

use mod_vimipad\completion\custom_completion;

/**
 * Tests for the custom completion detail rules.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\completion\custom_completion
 */
final class custom_completion_test extends \advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private $instance;

    /** @var \cm_info|\stdClass The course module. */
    private $cm;

    /** @var int The workspace id. */
    private $workspaceid;

    /** @var int The acting user id. */
    private $userid;

    /**
     * Set up a course, activity, user and an empty individual workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0]
        );
        $this->cm = get_coursemodule_from_instance('vimipad', $this->instance->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userid = (int) $user->id;

        $now = time();
        $this->workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id,
            'userid' => $this->userid,
            'groupid' => null,
            'currentrevision' => 0,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Insert a live node into the workspace.
     *
     * @param string $stableid The node stable id.
     * @return void
     */
    private function add_node(string $stableid): void {
        global $DB;
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $this->workspaceid,
            'stableid' => $stableid,
            'type' => 'concept',
            'contentformat' => FORMAT_HTML,
            'timecreated' => time(),
            'timemodified' => time(),
            'deleted' => 0,
        ]);
    }

    /**
     * Build the completion helper for the test user.
     *
     * @return custom_completion
     */
    private function completion(): custom_completion {
        return new custom_completion($this->cm, $this->userid);
    }

    /**
     * The "minimum concepts" rule completes only once the threshold is met.
     *
     * @return void
     */
    public function test_min_nodes_rule(): void {
        global $DB;
        $DB->set_field('vimipad', 'completionminnodes', 2, ['id' => $this->instance->id]);

        $this->add_node('node_a');
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion()->get_state('completionminnodes'));

        $this->add_node('node_b');
        $this->assertSame(COMPLETION_COMPLETE, $this->completion()->get_state('completionminnodes'));
    }

    /**
     * A zero threshold leaves the rule incomplete (treated as off).
     *
     * @return void
     */
    public function test_min_nodes_zero_is_incomplete(): void {
        global $DB;
        $DB->set_field('vimipad', 'completionminnodes', 0, ['id' => $this->instance->id]);
        $this->add_node('node_a');
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion()->get_state('completionminnodes'));
    }

    /**
     * The "graded" rule completes once a snapshot reaches the graded status.
     *
     * @return void
     */
    public function test_graded_rule(): void {
        global $DB;
        $DB->set_field('vimipad', 'completiongraded', 1, ['id' => $this->instance->id]);

        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion()->get_state('completiongraded'));

        $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $this->workspaceid,
            'revision' => 1,
            'snapshotjson' => '{}',
            'submittedby' => $this->userid,
            'status' => \mod_vimipad\local\service\snapshot_service::STATUS_GRADED,
            'timecreated' => time(),
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $this->completion()->get_state('completiongraded'));
    }
}
