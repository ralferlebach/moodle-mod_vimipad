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

        $first = $service->add_entry($wsid, (int) $u1->id, 'first', FORMAT_PLAIN, true, true);
        $this->waitForSecond();
        $second = $service->add_entry($wsid, (int) $u1->id, 'second', FORMAT_PLAIN, false, true);
        $service->add_entry($wsid, (int) $u2->id, 'other user', FORMAT_PLAIN, true, true);

        $entries = $service->get_entries_for_user($wsid, (int) $u1->id);
        $this->assertCount(2, $entries);
        // Newest first.
        $this->assertEquals($second, (int) $entries[0]->id);
        $this->assertEquals($first, (int) $entries[1]->id);
    }

    /**
     * Teachers can read the journal by default: an entry is only private when
     * the author asks for it AND the activity allows private entries. When the
     * activity forbids private entries, a private request is ignored (stored
     * teacher-visible).
     *
     * @return void
     */
    public function test_private_requires_allowprivate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $wsid = $this->make_workspace((int) $instance->id);
        $service = new journal_service();

        // Private requested but the activity forbids it -> teacher-visible.
        $service->add_entry($wsid, (int) $u1->id, 'forced visible', FORMAT_PLAIN, true, false);
        // Private requested and allowed -> private.
        $service->add_entry($wsid, (int) $u1->id, 'really private', FORMAT_PLAIN, true, true);
        // Default (no private request) -> teacher-visible.
        $service->add_entry($wsid, (int) $u1->id, 'default', FORMAT_PLAIN, false, true);

        $entries = $service->get_entries_for_user($wsid, (int) $u1->id);
        $visibilities = array_map(static fn($e) => (int) $e->visibility, $entries);
        sort($visibilities);
        // With 0=teacher-visible, 1=private: two teacher-visible, one private.
        $this->assertEquals(
            [
                journal_service::VISIBILITY_TEACHER,
                journal_service::VISIBILITY_TEACHER,
                journal_service::VISIBILITY_PRIVATE,
            ],
            $visibilities
        );
        $this->assertCount(2, $service->get_teacher_visible($wsid));
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

        $service->add_entry($wsid, (int) $u1->id, 'u1 private', FORMAT_PLAIN, true, true);
        $service->add_entry($wsid, (int) $u1->id, 'u1 shared', FORMAT_PLAIN, false, true);
        $service->add_entry($wsid, (int) $u2->id, 'u2 shared', FORMAT_PLAIN, false, true);

        $visible = $service->get_teacher_visible($wsid);
        $this->assertCount(2, $visible);
        foreach ($visible as $entry) {
            $this->assertEquals(journal_service::VISIBILITY_TEACHER, (int) $entry->visibility);
        }
    }
}
