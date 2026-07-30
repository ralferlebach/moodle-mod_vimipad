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
use mod_vimipad\external\get_workspace;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests that get_workspace returns canvas containers alongside nodes/relations.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\get_workspace
 */
final class get_workspace_containers_test extends externallib_advanced_testcase {
    /**
     * A container stored in the workspace is returned with its geometry, and the
     * return value validates against the declared structure.
     *
     * @return void
     */
    public function test_workspace_returns_containers(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        // Resolve (create) the student's workspace via the API itself.
        $first = get_workspace::execute($instance->cmid);
        $first = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $first);
        $this->assertArrayHasKey('containers', $first);
        $this->assertSame([], $first['containers']);

        // Insert a container directly, then re-fetch.
        $geometry = json_encode(['x' => 40, 'y' => 60, 'w' => 300, 'h' => 200]);
        $DB->insert_record('vimipad_container', (object) [
            'workspaceid' => (int) $first['workspaceid'],
            'stableid' => 'container_aaaaaaa',
            'type' => 'group',
            'label' => 'Section A',
            'geometryjson' => $geometry,
            'metadatajson' => null,
            'deleted' => 0,
        ]);

        $result = get_workspace::execute($instance->cmid);
        $result = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $result);

        $this->assertCount(1, $result['containers']);
        $container = $result['containers'][0];
        $this->assertSame('container_aaaaaaa', $container['stableid']);
        $this->assertSame('group', $container['type']);
        $this->assertSame('Section A', $container['label']);
        $this->assertSame($geometry, $container['geometryjson']);
    }

    /**
     * A soft-deleted container is not returned.
     *
     * @return void
     */
    public function test_deleted_container_is_excluded(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $ws = get_workspace::execute($instance->cmid);
        $DB->insert_record('vimipad_container', (object) [
            'workspaceid' => (int) $ws['workspaceid'],
            'stableid' => 'container_deletedx',
            'type' => 'group',
            'label' => 'Gone',
            'geometryjson' => null,
            'metadatajson' => null,
            'deleted' => 1,
        ]);

        $result = get_workspace::execute($instance->cmid);
        $this->assertSame([], $result['containers']);
    }

    /**
     * The canmanage flag reflects the manageprofiles capability.
     *
     * @return void
     */
    public function test_canmanage_reflects_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $asstudent = get_workspace::execute($instance->cmid);
        $asstudent = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $asstudent);
        $this->assertFalse((bool) $asstudent['canmanage']);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $asteacher = get_workspace::execute($instance->cmid);
        $asteacher = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $asteacher);
        $this->assertTrue((bool) $asteacher['canmanage']);
    }
}
