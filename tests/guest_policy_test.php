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

use context_module;
use mod_vimipad\local\access;
use mod_vimipad\local\service\workspace_service;
use mod_vimipad\external\get_workspace;

/**
 * The guest policy: guests may read but never write, and reading never creates
 * a workspace.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\access
 * @covers     \mod_vimipad\local\service\workspace_service
 */
final class guest_policy_test extends \advanced_testcase {
    /**
     * Create a course, an individual-mode vimipad and its context.
     *
     * @return array{0: \stdClass, 1: context_module}
     */
    private function make(): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => workspace_service::MODE_INDIVIDUAL]
        );
        return [$instance, context_module::instance($instance->cmid)];
    }

    /**
     * require_edit refuses the guest user regardless of role configuration.
     *
     * @return void
     */
    public function test_require_edit_rejects_guest(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        [$instance, $context] = $this->make();
        $guestid = (int) $CFG->siteguest;
        $workspace = (object) ['id' => 1, 'userid' => $guestid, 'groupid' => null];

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Guests cannot edit/');
        access::require_edit($instance, $context, $workspace, $guestid);
    }

    /**
     * Resolving a workspace for reading never creates one, whereas the editing
     * path does. This is what lets a guest read without leaving a workspace behind.
     *
     * @return void
     */
    public function test_reading_does_not_create_a_workspace(): void {
        global $DB;
        $this->resetAfterTest();
        [$instance, $context] = $this->make();
        $student = $this->getDataGenerator()->create_and_enrol(
            get_course($instance->course),
            'student'
        );
        $service = new workspace_service();

        // Read path: no workspace yet, nothing created.
        $found = $service->find_existing_for_user($instance, $context, (int) $student->id);
        $this->assertNull($found);
        $this->assertEquals(0, $DB->count_records('vimipad_workspace', ['vimipadid' => $instance->id]));

        // Edit path: creates exactly one.
        $created = $service->get_or_create_for_user($instance, $context, (int) $student->id);
        $this->assertNotNull($created);
        $this->assertEquals(1, $DB->count_records('vimipad_workspace', ['vimipadid' => $instance->id]));

        // Reading again now finds that one, still without creating more.
        $foundnow = $service->find_existing_for_user($instance, $context, (int) $student->id);
        $this->assertEquals((int) $created->id, (int) $foundnow->id);
        $this->assertEquals(1, $DB->count_records('vimipad_workspace', ['vimipadid' => $instance->id]));
    }

    /**
     * The get_workspace external never creates a workspace for a guest, even if
     * the guest role is (mis)configured with an edit capability. Reading returns
     * the empty read-only state and leaves no workspace behind.
     *
     * @return void
     */
    public function test_get_workspace_guest_never_creates(): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => workspace_service::MODE_INDIVIDUAL]
        );
        $context = context_module::instance($instance->cmid);

        // Enable guest access on the course so a guest can reach the module.
        $plugin = enrol_get_plugin('guest');
        $enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $plugin->update_status($enrolinstance, ENROL_INSTANCE_ENABLED);

        // Misconfigure the guest role with view + editown at this context.
        $guestrole = $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        assign_capability('mod/vimipad:view', CAP_ALLOW, $guestrole, $context->id, true);
        assign_capability('mod/vimipad:editown', CAP_ALLOW, $guestrole, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setGuestUser();
        $result = get_workspace::execute($instance->cmid);
        $result = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $result);

        // No workspace row was created for the guest.
        $this->assertEquals(0, $DB->count_records('vimipad_workspace', ['vimipadid' => $instance->id]));
    }
}
