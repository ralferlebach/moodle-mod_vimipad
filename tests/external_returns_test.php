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

use core_external\external_api;
use mod_vimipad\external\cancel_consensus;
use mod_vimipad\external\confirm_consensus;
use mod_vimipad\external\get_consensus_status;
use mod_vimipad\external\get_journal_entries;
use mod_vimipad\external\get_layout_history;
use mod_vimipad\external\import_map;
use mod_vimipad\external\start_consensus;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vimipad/lib.php');

/**
 * Executes the external functions that no other test invoked.
 *
 * The service layer behind these was already covered, but the external wrapper
 * was not, and that is where a distinct class of failure lives: a returns
 * declaration that does not match what execute() actually returns only blows up
 * when clean_returnvalue() runs, i.e. on a real web-service call from the
 * editor. Every test here therefore calls execute() and then validates the
 * payload against execute_returns(), exactly as Moodle does in production.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\start_consensus
 * @covers     \mod_vimipad\external\confirm_consensus
 * @covers     \mod_vimipad\external\cancel_consensus
 * @covers     \mod_vimipad\external\get_consensus_status
 * @covers     \mod_vimipad\external\get_journal_entries
 * @covers     \mod_vimipad\external\get_layout_history
 * @covers     \mod_vimipad\external\import_map
 */
final class external_returns_test extends \advanced_testcase {
    /** @var \stdClass The activity instance. */
    private $instance;

    /** @var \stdClass The group workspace. */
    private $workspace;

    /** @var int First group member. */
    private $u1;

    /** @var int Second group member. */
    private $u2;

    /**
     * Build a group activity with consensus enabled and a group workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => 1, 'requireallteamsubmit' => 1,
        ]);

        $user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->u1 = (int) $user1->id;
        $this->u2 = (int) $user2->id;

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $user1);
        groups_add_member($group, $user2);

        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => null, 'groupid' => $group->id,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);
    }

    /**
     * The consensus trio returns payloads that match their declarations.
     *
     * @return void
     */
    public function test_consensus_functions_return_declared_structure(): void {
        $cmid = (int) $this->instance->cmid;
        // The consensus functions take a group id; 0 auto-selects the caller's.
        $groupid = 0;

        $this->setUser($this->u1);

        $status = get_consensus_status::execute($cmid, $groupid);
        $status = external_api::clean_returnvalue(get_consensus_status::execute_returns(), $status);
        $this->assertIsArray($status);

        $started = start_consensus::execute($cmid, $groupid);
        $started = external_api::clean_returnvalue(start_consensus::execute_returns(), $started);
        $this->assertIsArray($started);

        $confirmed = confirm_consensus::execute($cmid, $groupid);
        $confirmed = external_api::clean_returnvalue(confirm_consensus::execute_returns(), $confirmed);
        $this->assertIsArray($confirmed);

        // The status now reports the voting state to the second member too.
        $this->setUser($this->u2);
        $status = get_consensus_status::execute($cmid, $groupid);
        $status = external_api::clean_returnvalue(get_consensus_status::execute_returns(), $status);
        $this->assertIsArray($status);

        $cancelled = cancel_consensus::execute($cmid, $groupid);
        $cancelled = external_api::clean_returnvalue(cancel_consensus::execute_returns(), $cancelled);
        $this->assertIsArray($cancelled);
    }

    /**
     * Reading journal entries returns the declared structure, empty or not.
     *
     * @return void
     */
    public function test_get_journal_entries_returns_declared_structure(): void {
        $this->setUser($this->u1);
        $cmid = (int) $this->instance->cmid;
        $wsid = (int) $this->workspace->id;

        // The empty case is what a learner sees first, so it must validate too.
        $result = get_journal_entries::execute($cmid, $wsid);
        $result = external_api::clean_returnvalue(get_journal_entries::execute_returns(), $result);
        $this->assertIsArray($result);

        \mod_vimipad\external\add_journal_entry::execute($cmid, $wsid, 'A note about my map', 0);

        $result = get_journal_entries::execute($cmid, $wsid);
        $result = external_api::clean_returnvalue(get_journal_entries::execute_returns(), $result);
        $this->assertIsArray($result);
    }

    /**
     * Reading the layout history returns the declared structure.
     *
     * @return void
     */
    public function test_get_layout_history_returns_declared_structure(): void {
        $this->setUser($this->u1);

        $result = get_layout_history::execute((int) $this->instance->cmid, (int) $this->workspace->id);
        $result = external_api::clean_returnvalue(get_layout_history::execute_returns(), $result);
        $this->assertIsArray($result);
    }

    /**
     * Importing a map returns the declared structure and stores the nodes.
     *
     * @return void
     */
    public function test_import_map_returns_declared_structure(): void {
        global $DB;
        $this->setUser($this->u1);

        // An import document is an envelope: the bare map alone is rejected.
        $payload = json_encode([
            'generator' => 'mod_vimipad',
            'formatversion' => \mod_vimipad\local\service\export_service::FORMAT_VERSION,
            'data' => [
                'profile' => 'conceptmap',
                'nodes' => [
                    ['stableid' => 'node_aaaaaaaaaaaa', 'label' => 'Water'],
                    ['stableid' => 'node_bbbbbbbbbbbb', 'label' => 'Ice'],
                ],
                'relations' => [[
                    'stableid' => 'rel_aaaaaaaaaaaaa',
                    'sourceid' => 'node_aaaaaaaaaaaa',
                    'targetid' => 'node_bbbbbbbbbbbb',
                    'label' => 'freezes to',
                ]],
            ],
        ]);

        $result = import_map::execute(
            (int) $this->instance->cmid,
            (int) $this->workspace->id,
            $payload,
            'replace'
        );
        $result = external_api::clean_returnvalue(import_map::execute_returns(), $result);
        $this->assertIsArray($result);

        $this->assertGreaterThan(
            0,
            $DB->count_records('vimipad_node', ['workspaceid' => $this->workspace->id]),
            'Importing a map must create its nodes.'
        );
    }
}
