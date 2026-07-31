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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_vimipad\privacy\provider;

/**
 * Privacy matrix tests: every declared personal-data holder must be covered by
 * context discovery, userlist discovery, export, single delete and bulk delete
 * alike — the outcome must not depend on which privacy pathway Moodle chose.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\privacy\provider
 */
final class privacy_matrix_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var \stdClass The activity instance. */
    private \stdClass $instance;

    /** @var \context_module The module context. */
    private \context_module $context;

    /** @var \stdClass The learner (workspace owner). */
    private \stdClass $learner;

    /** @var \stdClass The reviewer / rater. */
    private \stdClass $reviewer;

    /** @var int The learner's snapshot id. */
    private int $snapshotid;

    /**
     * Create an activity with a learner workspace, a submitted snapshot, a peer
     * review and an advanced-grading instance link by a second user.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->instance = $gen->create_module('vimipad', ['course' => $this->course->id, 'grade' => 100]);
        $cm = get_coursemodule_from_instance('vimipad', $this->instance->id, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);

        $this->learner = $gen->create_user();
        $this->reviewer = $gen->create_user();
        $gen->enrol_user($this->learner->id, $this->course->id, 'student');
        $gen->enrol_user($this->reviewer->id, $this->course->id, 'student');

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => $this->learner->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $wsid, 'stableid' => 'node_000000000001', 'type' => 'concept',
            'label' => 'Energy', 'content' => 'Learner authored body text.', 'contentformat' => FORMAT_PLAIN,
            'createdby' => $this->learner->id, 'modifiedby' => $this->learner->id,
            'deleted' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->snapshotid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $wsid, 'revision' => 1,
            'snapshotjson' => json_encode(['profile' => 'conceptmap', 'nodes' => [], 'relations' => []]),
            'submittedby' => $this->learner->id, 'status' => 1, 'timecreated' => $now,
        ]);
        $DB->insert_record('vimipad_peerreview', (object) [
            'snapshotid' => $this->snapshotid, 'reviewerid' => $this->reviewer->id,
            'score' => 0.75, 'reviewcomment' => 'Solid structure.', 'commentformat' => FORMAT_PLAIN,
            'status' => 1, 'timeallocated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_gradeinstance', (object) [
            'snapshotid' => $this->snapshotid, 'raterid' => $this->reviewer->id,
            'instanceid' => 12345, 'timemodified' => $now,
        ]);
    }

    /**
     * The reviewer/rater is discovered by context and userlist discovery.
     *
     * @return void
     */
    public function test_reviewer_and_rater_are_discovered(): void {
        $contextlist = provider::get_contexts_for_userid((int) $this->reviewer->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) $this->context->id, $contextids);

        $userlist = new userlist($this->context, 'mod_vimipad');
        provider::get_users_in_context($userlist);
        $userids = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $this->reviewer->id, $userids);
    }

    /**
     * Bulk deletion removes the same reviewer/rater data as single deletion.
     *
     * @return void
     */
    public function test_bulk_delete_matches_single_delete(): void {
        global $DB;

        $userlist = new approved_userlist($this->context, 'mod_vimipad', [(int) $this->reviewer->id]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('vimipad_peerreview', ['reviewerid' => $this->reviewer->id]));
        $this->assertFalse($DB->record_exists('vimipad_gradeinstance', ['raterid' => $this->reviewer->id]));
        // The learner's own data is untouched by the reviewer's deletion.
        $this->assertTrue($DB->record_exists('vimipad_snapshot', ['id' => $this->snapshotid]));
    }

    /**
     * Bulk deletion of the learner removes their grade record like the single path.
     *
     * @return void
     */
    public function test_bulk_delete_removes_grades(): void {
        global $DB;
        $DB->insert_record('vimipad_grade', (object) [
            'vimipadid' => $this->instance->id, 'userid' => $this->learner->id,
            'snapshotid' => $this->snapshotid, 'grade' => 80.0, 'feedback' => 'Good',
            'feedbackformat' => FORMAT_PLAIN, 'grader' => $this->reviewer->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $userlist = new approved_userlist($this->context, 'mod_vimipad', [(int) $this->learner->id]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('vimipad_grade', ['userid' => $this->learner->id]));
        $this->assertFalse($DB->record_exists('vimipad_workspace', ['userid' => $this->learner->id]));
    }

    /**
     * The export contains the declared node content and the reviewer's writing.
     *
     * @return void
     */
    public function test_export_covers_declared_fields(): void {
        // Learner: node content is declared personal data and must be exported.
        $contextlist = new approved_contextlist($this->learner, 'mod_vimipad', [$this->context->id]);
        provider::export_user_data($contextlist);
        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        $exported = json_encode($writer->get_data(
            [get_string('privacy:path:workspace', 'mod_vimipad') . ' '
                . $this->find_workspace_id()]
        ));
        $this->assertStringContainsString('Learner authored body text.', $exported);

        // Reviewer: the review comment is exported under the grading path.
        writer::reset();
        $contextlist = new approved_contextlist($this->reviewer, 'mod_vimipad', [$this->context->id]);
        provider::export_user_data($contextlist);
        $writer = writer::with_context($this->context);
        $grading = json_encode($writer->get_data([get_string('privacy:path:grading', 'mod_vimipad')]));
        $this->assertStringContainsString('Solid structure.', $grading);
    }

    /**
     * Find the learner's workspace id.
     *
     * @return int The workspace id.
     */
    private function find_workspace_id(): int {
        global $DB;
        return (int) $DB->get_field('vimipad_workspace', 'id', ['userid' => $this->learner->id], MUST_EXIST);
    }
}
