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

use externallib_advanced_testcase;
use mod_vimipad\external\add_journal_entry;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Role matrix for add_journal_entry: writing a journal entry requires the
 * comment capability, not merely edit access.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\add_journal_entry
 */
final class add_journal_entry_capability_test extends externallib_advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;
    /** @var \stdClass The vimipad instance. */
    private \stdClass $instance;
    /** @var \stdClass The course module. */
    private \stdClass $cm;
    /** @var int The student role id. */
    private int $studentroleid;

    /**
     * Common fixture: an individual-mode activity and the student role id.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $this->course->id,
            'collaborationmode' => 0,
        ]);
        $this->cm = get_coursemodule_from_instance('vimipad', $this->instance->id);
        // Use the built-in student role: it carries course access, so overriding
        // just the vimipad capabilities isolates the comment check under test.
        $this->studentroleid = (int) $this->getDataGenerator()->create_role();
        $studentarchetype = get_archetype_roles('student');
        if ($studentarchetype) {
            $this->studentroleid = (int) reset($studentarchetype)->id;
        }
    }

    /**
     * Enrol a fresh user with an explicit role, so capabilities can be
     * overridden per test, and return them.
     *
     * @return \stdClass The enrolled user.
     */
    private function enrol_with_role(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $this->studentroleid);
        return $user;
    }

    /**
     * Grant a capability to the test role in the module context.
     *
     * @param string $capability The capability name.
     * @param int $permission CAP_ALLOW or CAP_PROHIBIT.
     * @return void
     */
    private function set_cap(string $capability, int $permission): void {
        $context = \context_module::instance($this->cm->id);
        assign_capability($capability, $permission, $this->studentroleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The default workspace id for a user (create it via the read path).
     *
     * @param \stdClass $user The owner.
     * @return int The workspace id.
     */
    private function workspace_for(\stdClass $user): int {
        global $DB;
        $ws = $DB->get_record('vimipad_workspace', [
            'vimipadid' => $this->instance->id, 'userid' => $user->id,
        ]);
        if ($ws) {
            return (int) $ws->id;
        }
        $ws = (object) [
            'vimipadid' => $this->instance->id, 'userid' => $user->id, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        return (int) $DB->insert_record('vimipad_workspace', $ws);
    }

    /**
     * editown allowed but comment prohibited: the call is rejected.
     *
     * @return void
     */
    public function test_editown_but_comment_prohibited_is_rejected(): void {
        $user = $this->enrol_with_role();
        $this->set_cap('mod/vimipad:editown', CAP_ALLOW);
        $this->set_cap('mod/vimipad:comment', CAP_PROHIBIT);
        $this->setUser($user);
        $wsid = $this->workspace_for($user);

        $this->expectException(\required_capability_exception::class);
        add_journal_entry::execute($this->cm->id, $wsid, 'hello', false);
    }

    /**
     * comment allowed and it is the user's own workspace: the call succeeds.
     *
     * @return void
     */
    public function test_comment_allowed_own_workspace_succeeds(): void {
        $user = $this->enrol_with_role();
        $this->set_cap('mod/vimipad:editown', CAP_ALLOW);
        $this->set_cap('mod/vimipad:comment', CAP_ALLOW);
        $this->setUser($user);
        $wsid = $this->workspace_for($user);

        $result = add_journal_entry::execute($this->cm->id, $wsid, 'hello', false);
        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);
    }

    /**
     * comment allowed but the workspace belongs to another user: rejected by
     * the edit-access check (not the caller's own map).
     *
     * @return void
     */
    public function test_comment_allowed_foreign_workspace_is_rejected(): void {
        $owner = $this->enrol_with_role();
        $other = $this->enrol_with_role();
        $this->set_cap('mod/vimipad:editown', CAP_ALLOW);
        $this->set_cap('mod/vimipad:comment', CAP_ALLOW);

        // Create the owner's workspace, then act as the other user.
        $this->setUser($owner);
        $ownerws = $this->workspace_for($owner);
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        add_journal_entry::execute($this->cm->id, $ownerws, 'hello', false);
    }
}
