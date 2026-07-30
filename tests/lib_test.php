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
 * Basic lifecycle tests for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad_generator
 */
final class lib_test extends \advanced_testcase {
    /**
     * An instance can be created via the generator and appears in the course.
     *
     * @return void
     */
    public function test_create_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id,
            'name' => 'Testmap',
        ]);

        $this->assertTrue($DB->record_exists('vimipad', ['id' => $instance->id]));
        $record = $DB->get_record('vimipad', ['id' => $instance->id]);
        $this->assertSame('Testmap', $record->name);
        $this->assertSame('conceptmap', $record->defaultprofile);

        $cm = get_coursemodule_from_instance('vimipad', $instance->id);
        $this->assertNotEmpty($cm);
    }

    /**
     * Deleting an instance removes the record.
     *
     * @return void
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);

        $this->assertTrue(vimipad_delete_instance($instance->id));
        $this->assertFalse($DB->record_exists('vimipad', ['id' => $instance->id]));
        $this->assertFalse(vimipad_delete_instance($instance->id));
    }
}
