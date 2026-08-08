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
use mod_vimipad\local\service\statistics_service;

/**
 * Tests that teacher- and learner-facing list queries can be paged, so a large
 * course never loads an unbounded number of rows into one page request.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\journal_service
 * @covers     \mod_vimipad\local\service\statistics_service
 */
final class pagination_test extends \advanced_testcase {
    /**
     * Journal history pages: each page is bounded, pages do not overlap, and
     * together they cover every entry.
     *
     * @return void
     */
    public function test_journal_history_pages(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $now = time();
        $workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => (int) $user->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        for ($i = 0; $i < 12; $i++) {
            $DB->insert_record('vimipad_journalentry', (object) [
                'workspaceid' => $workspaceid, 'userid' => (int) $user->id,
                'entrytext' => 'entry ' . $i, 'visibility' => journal_service::VISIBILITY_TEACHER,
                'timecreated' => $now + $i, 'timemodified' => $now + $i,
            ]);
        }

        $service = new journal_service();
        $this->assertSame(12, $service->count_entries_for_user($workspaceid, (int) $user->id));

        $page1 = $service->get_entries_for_user($workspaceid, (int) $user->id, 0, 5);
        $page2 = $service->get_entries_for_user($workspaceid, (int) $user->id, 5, 5);
        $page3 = $service->get_entries_for_user($workspaceid, (int) $user->id, 10, 5);
        $this->assertCount(5, $page1);
        $this->assertCount(5, $page2);
        $this->assertCount(2, $page3);

        $ids = array_merge(
            array_column($page1, 'id'),
            array_column($page2, 'id'),
            array_column($page3, 'id')
        );
        $this->assertCount(12, array_unique($ids), 'pages must not overlap');

        // Teacher-visible view pages the same way.
        $this->assertSame(12, $service->count_teacher_visible($workspaceid));
        $this->assertCount(5, $service->get_teacher_visible($workspaceid, 0, 5));

        // The default call is unchanged (no limit) for existing callers.
        $this->assertCount(12, $service->get_entries_for_user($workspaceid, (int) $user->id));
    }

    /**
     * The activity-wide statistics overview pages by workspace, so one row per
     * participant does not mean one page for the whole cohort.
     *
     * @return void
     */
    public function test_statistics_overview_pages(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        for ($i = 0; $i < 7; $i++) {
            $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $DB->insert_record('vimipad_workspace', (object) [
                'vimipadid' => $instance->id, 'userid' => (int) $user->id, 'groupid' => null,
                'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        $stats = new statistics_service();
        $this->assertSame(7, $stats->count_instance_workspaces((int) $instance->id));

        $page1 = $stats->instance_overview((int) $instance->id, 0, 3);
        $page2 = $stats->instance_overview((int) $instance->id, 3, 3);
        $page3 = $stats->instance_overview((int) $instance->id, 6, 3);
        $this->assertCount(3, $page1);
        $this->assertCount(3, $page2);
        $this->assertCount(1, $page3);

        $wsids = array_merge(
            array_column($page1, 'workspaceid'),
            array_column($page2, 'workspaceid'),
            array_column($page3, 'workspaceid')
        );
        $this->assertCount(7, array_unique($wsids), 'pages must not overlap');

        // Unlimited default keeps existing behaviour for other callers.
        $this->assertCount(7, $stats->instance_overview((int) $instance->id));
    }
}
