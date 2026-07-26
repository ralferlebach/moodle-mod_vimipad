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

use core_privacy\local\request\writer;
use core_privacy\local\request\approved_contextlist;
use mod_vimipad\privacy\provider;

/**
 * Privacy provider tests for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\privacy\provider
 */
final class privacy_test extends \core_privacy\tests\provider_testcase {
    /**
     * The provider declares metadata for its user-data tables.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('mod_vimipad');
        $collection = provider::get_metadata($collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * A user's workspace makes its module context appear in the context list,
     * export produces data, and deletion removes it.
     *
     * @return void
     */
    public function test_contexts_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => 0]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('vimipad', $instance->id);
        $context = \context_module::instance($cm->id);

        $now = time();
        $workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $user->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspaceid, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept',
            'label' => 'Energy', 'contentformat' => FORMAT_HTML, 'createdby' => $user->id,
            'modifiedby' => $user->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);

        // Contexts for the user include this module context.
        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) $context->id, $contextids);

        // Export produces data for the context.
        $this->export_context_data_for_user((int) $user->id, $context, 'mod_vimipad');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // Delete for this user removes their workspace.
        $approved = new approved_contextlist($user, 'mod_vimipad', [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('vimipad_workspace', ['id' => $workspaceid]));
    }

    /**
     * Deleting all users in a context removes all workspaces of the instance.
     *
     * @return void
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('vimipad', $instance->id);
        $context = \context_module::instance($cm->id);

        $now = time();
        $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $user->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('vimipad_workspace', ['vimipadid' => $instance->id]));
    }
}
