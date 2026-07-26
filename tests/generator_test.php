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

/**
 * Tests for the test data generator.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * create_workspace seeds nodes and a locked, submitted snapshot.
     *
     * @return void
     */
    public function test_create_workspace_with_submission(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        /** @var \mod_vimipad_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');
        $ws = $gen->create_workspace($instance, (int) $user->id, [
            ['stableid' => 'node_seedaaaaaaa', 'label' => 'Energy'],
        ], true);

        $this->assertNotEmpty($ws->id);
        $this->assertObjectHasProperty('snapshotid', $ws);
        $this->assertSame(1, $DB->count_records('vimipad_node', ['workspaceid' => $ws->id]));

        $reloaded = $DB->get_record('vimipad_workspace', ['id' => $ws->id]);
        $this->assertSame(1, (int) $reloaded->locked);
        $this->assertEquals($ws->snapshotid, $reloaded->submittedsnapshotid);
    }
}
