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

use mod_vimipad\local\service\layout_service;

/**
 * Tests for the layout service (non-revisioned presentation state).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\layout_service
 */
final class layout_service_test extends \advanced_testcase {
    /** @var int Workspace id under test. */
    private $workspaceid;

    /** @var int Acting user id. */
    private $userid;

    /**
     * Create a course, activity, user and empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userid = (int) $user->id;

        global $DB;
        $now = time();
        $this->workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $this->userid, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Saving inserts a layout row and get returns it.
     *
     * @return void
     */
    public function test_save_and_get(): void {
        global $DB;
        $service = new layout_service();

        $this->assertSame('', $service->get_layout_json($this->workspaceid, 'conceptmap'));

        $layout = '{"node_a":{"x":10,"y":20}}';
        $service->save($this->workspaceid, 'conceptmap', $layout, '{"zoom":1}', $this->userid);

        $this->assertSame($layout, $service->get_layout_json($this->workspaceid, 'conceptmap'));
        $this->assertSame(1, $DB->count_records(
            'vimipad_layout',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        ));
    }

    /**
     * Saving again updates in place rather than inserting a duplicate.
     *
     * @return void
     */
    public function test_save_is_upsert(): void {
        global $DB;
        $service = new layout_service();

        $service->save($this->workspaceid, 'conceptmap', '{"a":1}', '', $this->userid);
        $service->save($this->workspaceid, 'conceptmap', '{"a":2}', '', $this->userid);

        $this->assertSame(1, $DB->count_records(
            'vimipad_layout',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        ));
        $this->assertSame('{"a":2}', $service->get_layout_json($this->workspaceid, 'conceptmap'));
    }

    /**
     * Layouts are stored independently per profile.
     *
     * @return void
     */
    public function test_layout_is_per_profile(): void {
        $service = new layout_service();

        $service->save($this->workspaceid, 'conceptmap', '{"c":1}', '', $this->userid);
        $service->save($this->workspaceid, 'mindmap', '{"m":1}', '', $this->userid);

        $this->assertSame('{"c":1}', $service->get_layout_json($this->workspaceid, 'conceptmap'));
        $this->assertSame('{"m":1}', $service->get_layout_json($this->workspaceid, 'mindmap'));
    }
}
