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

use mod_vimipad\local\service\snapshot_service;

/**
 * Tests for due-date lateness detection and the map_updated event.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\snapshot_service::is_late
 * @covers     \mod_vimipad\event\map_updated
 */
final class duedate_and_events_test extends \advanced_testcase {
    /**
     * A zero due date never marks a submission as late.
     *
     * @return void
     */
    public function test_no_due_date_is_never_late(): void {
        $instance = (object) ['duedate' => 0];
        $this->assertFalse(snapshot_service::is_late($instance, time()));
        $this->assertFalse(snapshot_service::is_late($instance, time() + DAYSECS));
    }

    /**
     * A submission after the due date is late; on or before it is not.
     *
     * @return void
     */
    public function test_late_only_after_due_date(): void {
        $due = 1000000;
        $instance = (object) ['duedate' => $due];

        $this->assertFalse(snapshot_service::is_late($instance, $due - 1), 'before due');
        $this->assertFalse(snapshot_service::is_late($instance, $due), 'exactly on due');
        $this->assertTrue(snapshot_service::is_late($instance, $due + 1), 'after due');
    }

    /**
     * A missing duedate property is treated as no due date.
     *
     * @return void
     */
    public function test_missing_due_date_property(): void {
        $this->assertFalse(snapshot_service::is_late((object) [], time() + DAYSECS));
    }

    /**
     * Triggering map_updated logs an event with the operation type in it.
     *
     * @return void
     */
    public function test_map_updated_event_is_logged(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);

        $sink = $this->redirectEvents();
        \mod_vimipad\event\map_updated::create([
            'context' => $context,
            'objectid' => 4242,
            'other' => ['operationtype' => 'node_create', 'revision' => 7],
        ])->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\mod_vimipad\event\map_updated::class, $event);
        $this->assertSame('u', $event->crud);
        $this->assertSame(4242, (int) $event->objectid);
        $this->assertStringContainsString('node_create', $event->get_description());
        $this->assertNotEmpty($event::get_name());
    }
}
