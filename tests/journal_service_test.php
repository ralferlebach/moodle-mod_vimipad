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

use mod_vimipad\local\service\journal_service;

/**
 * Tests for the learner journal service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\journal_service
 */
final class journal_service_test extends \advanced_testcase {
    /**
     * Insert a workspace row.
     *
     * @param int $vimipadid The instance id.
     * @return int The new workspace id.
     */
    private function make_workspace(int $vimipadid): int {
        global $DB;
        return (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $vimipadid,
            'userid' => null,
            'groupid' => null,
            'currentrevision' => 0,
            'locked' => 0,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /**
     * Entries are returned per author, newest first.
     *
     * @return void
     */
    public function test_add_and_get_own_entries(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $u2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $wsid = $this->make_workspace((int) $instance->id);
        $service = new journal_service();

        $first = $service->add_entry($wsid, (int) $u1->id, 'first', FORMAT_PLAIN, 0);
        $this->waitForSecond();
        $second = $service->add_entry($wsid, (int) $u1->id, 'second', FORMAT_PLAIN, 1);
        $service->add_entry($wsid, (int) $u2->id, 'other user', FORMAT_PLAIN, 0);

        $entries = $service->get_entries_for_user($wsid, (int) $u1->id);
        $this->assertCount(2, $entries);
        // Newest first.
        $this->assertEquals($second, (int) $entries[0]->id);
        $this->assertEquals($first, (int) $entries[1]->id);
    }

    /**
     * Any non-teacher visibility value is stored as private.
     *
     * @return void
     */
    public function test_visibility_normalisation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $wsid = $this->make_workspace((int) $instance->id);
        $service = new journal_service();

        $service->add_entry($wsid, (int) $u1->id, 'private?', FORMAT_PLAIN, 7);
        $service->add_entry($wsid, (int) $u1->id, 'teacher', FORMAT_PLAIN, journal_service::VISIBILITY_TEACHER);

        $entries = $service->get_entries_for_user($wsid, (int) $u1->id);
        $visibilities = array_map(static fn($e) => (int) $e->visibility, $entries);
        sort($visibilities);
        $this->assertEquals([journal_service::VISIBILITY_PRIVATE, journal_service::VISIBILITY_TEACHER], $visibilities);
    }

    /**
     * Teacher view returns only teacher-visible entries across authors.
     *
     * @return void
     */
    public function test_get_teacher_visible_filters(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $u2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $wsid = $this->make_workspace((int) $instance->id);
        $service = new journal_service();

        $service->add_entry($wsid, (int) $u1->id, 'u1 private', FORMAT_PLAIN, 0);
        $service->add_entry($wsid, (int) $u1->id, 'u1 shared', FORMAT_PLAIN, 1);
        $service->add_entry($wsid, (int) $u2->id, 'u2 shared', FORMAT_PLAIN, 1);

        $visible = $service->get_teacher_visible($wsid);
        $this->assertCount(2, $visible);
        foreach ($visible as $entry) {
            $this->assertEquals(journal_service::VISIBILITY_TEACHER, (int) $entry->visibility);
        }
    }
}
