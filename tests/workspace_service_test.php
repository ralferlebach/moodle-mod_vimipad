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
use mod_vimipad\local\service\workspace_service;

/**
 * Tests for workspace resolution across collaboration modes.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\workspace_service
 */
final class workspace_service_test extends \advanced_testcase {
    /**
     * Create a course, a vimipad in the given collaboration mode and its context.
     *
     * @param int $mode 0 individual, 1 group, 2 course.
     * @return array{0: \stdClass, 1: \stdClass, 2: context_module}
     */
    private function make(int $mode): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'collaborationmode' => $mode]
        );
        return [$course, $instance, context_module::instance($instance->cmid)];
    }

    /**
     * Individual mode yields a stable per-user workspace.
     *
     * @return void
     */
    public function test_individual_mode_is_per_user(): void {
        $this->resetAfterTest();
        [$course, $instance, $context] = $this->make(0);
        $a = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $b = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $service = new workspace_service();

        $wa1 = $service->get_or_create_for_user($instance, $context, (int) $a->id);
        $wa2 = $service->get_or_create_for_user($instance, $context, (int) $a->id);
        $wb = $service->get_or_create_for_user($instance, $context, (int) $b->id);

        $this->assertEquals($wa1->id, $wa2->id);
        $this->assertNotEquals($wa1->id, $wb->id);
        $this->assertEquals((int) $a->id, (int) $wa1->userid);
        $this->assertNull($wa1->groupid);
    }

    /**
     * Course mode yields a single shared workspace for everyone.
     *
     * @return void
     */
    public function test_course_mode_is_shared(): void {
        $this->resetAfterTest();
        [$course, $instance, $context] = $this->make(2);
        $a = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $b = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $service = new workspace_service();

        $wa = $service->get_or_create_for_user($instance, $context, (int) $a->id);
        $wb = $service->get_or_create_for_user($instance, $context, (int) $b->id);

        $this->assertEquals($wa->id, $wb->id);
        $this->assertNull($wa->userid);
        $this->assertNull($wa->groupid);
    }

    /**
     * Group mode auto-selects the user's own group.
     *
     * @return void
     */
    public function test_group_mode_uses_member_group(): void {
        $this->resetAfterTest();
        [$course, $instance, $context] = $this->make(1);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $service = new workspace_service();

        $ws = $service->get_or_create_for_user($instance, $context, (int) $student->id);

        $this->assertEquals((int) $group->id, (int) $ws->groupid);
        $this->assertNull($ws->userid);
    }

    /**
     * A learner may not open a group they do not belong to.
     *
     * @return void
     */
    public function test_group_mode_rejects_foreign_group(): void {
        $this->resetAfterTest();
        [$course, $instance, $context] = $this->make(1);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $mine = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $mine->id, 'userid' => $student->id]);
        $other = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $service = new workspace_service();

        $this->expectException(\moodle_exception::class);
        $service->get_or_create_for_user($instance, $context, (int) $student->id, (int) $other->id);
    }

    /**
     * A teacher who is in no group but may access all groups falls back to the
     * first course group instead of erroring.
     *
     * @return void
     */
    public function test_group_mode_teacher_without_group_falls_back(): void {
        $this->resetAfterTest();
        [$course, $instance, $context] = $this->make(1);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $service = new workspace_service();

        $ws = $service->get_or_create_for_user($instance, $context, (int) $teacher->id);

        $this->assertEquals((int) $group->id, (int) $ws->groupid);
    }

    /**
     * Reopening a locked workspace unlocks it for revision.
     *
     * @return void
     */
    public function test_reopen_unlocks(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $wsid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 3, 'locked' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        (new workspace_service())->reopen((int) $wsid);

        $this->assertSame(0, (int) $DB->get_field('vimipad_workspace', 'locked', ['id' => $wsid]));
        // The revision (and thus the map) is untouched.
        $this->assertSame(3, (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $wsid]));
    }
}
