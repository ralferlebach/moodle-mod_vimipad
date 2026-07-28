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

use mod_vimipad\local\service\statistics_service;

/**
 * Tests for the edit-activity statistics aggregation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\statistics_service
 */
final class statistics_service_test extends \advanced_testcase {
    /**
     * Insert a workspace row for an instance.
     *
     * @param int $vimipadid The instance id.
     * @param int|null $userid Owner user id, or null.
     * @return int The new workspace id.
     */
    private function make_workspace(int $vimipadid, ?int $userid): int {
        global $DB;
        return (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $vimipadid,
            'userid' => $userid,
            'groupid' => null,
            'currentrevision' => 0,
            'locked' => 0,
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /**
     * Insert one operation-log row.
     *
     * @param int $workspaceid The workspace id.
     * @param int $revision The revision.
     * @param string $type The operation type.
     * @param int $userid The acting user.
     * @param int $time The timestamp.
     * @return void
     */
    private function log(int $workspaceid, int $revision, string $type, int $userid, int $time): void {
        global $DB;
        $DB->insert_record('vimipad_operation', (object) [
            'workspaceid' => $workspaceid,
            'revision' => $revision,
            'operationtype' => $type,
            'payloadjson' => '{}',
            'userid' => $userid,
            'timecreated' => $time,
        ]);
    }

    /**
     * workspace_summary counts totals, per type, per user and the time span.
     *
     * @return void
     */
    public function test_workspace_summary_aggregates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $u2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $wsid = $this->make_workspace((int) $instance->id, (int) $u1->id);
        $this->log($wsid, 1, 'node_create', (int) $u1->id, 100);
        $this->log($wsid, 2, 'node_create', (int) $u1->id, 110);
        $this->log($wsid, 3, 'node_update', (int) $u2->id, 120);
        $this->log($wsid, 4, 'relation_create', (int) $u2->id, 130);

        $summary = (new statistics_service())->workspace_summary($wsid);

        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(2, $summary['bytype']['node_create']);
        $this->assertEquals(1, $summary['bytype']['node_update']);
        $this->assertEquals(1, $summary['bytype']['relation_create']);
        $this->assertEquals(2, $summary['byuser'][(int) $u1->id]);
        $this->assertEquals(2, $summary['byuser'][(int) $u2->id]);
        $this->assertEquals(2, $summary['contributors']);
        $this->assertEquals(100, $summary['firstactivity']);
        $this->assertEquals(130, $summary['lastactivity']);
    }

    /**
     * instance_overview rolls up every workspace, including empty ones.
     *
     * @return void
     */
    public function test_instance_overview_rolls_up(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $u1 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $ws1 = $this->make_workspace((int) $instance->id, (int) $u1->id);
        $ws2 = $this->make_workspace((int) $instance->id, null);
        $this->log($ws1, 1, 'node_create', (int) $u1->id, 200);

        $overview = (new statistics_service())->instance_overview((int) $instance->id);

        $this->assertCount(2, $overview);
        $byid = [];
        foreach ($overview as $row) {
            $byid[$row['workspaceid']] = $row;
        }
        $this->assertEquals(1, $byid[$ws1]['total']);
        $this->assertEquals(1, $byid[$ws1]['contributors']);
        $this->assertEquals(200, $byid[$ws1]['lastactivity']);
        $this->assertEquals(0, $byid[$ws2]['total']);
        $this->assertEquals(0, $byid[$ws2]['contributors']);
        $this->assertEquals(0, $byid[$ws2]['lastactivity']);
    }
}
