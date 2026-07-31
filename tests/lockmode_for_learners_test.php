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

use mod_vimipad\local\service\operation_service;
use mod_vimipad\external\apply_operation;
use mod_vimipad\external\get_workspace;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * The lockmodeforlearners activity setting decides whether a learner may edit a
 * locked element and whether the editor offers lock mode to them.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\apply_operation
 */
final class lockmode_for_learners_test extends \externallib_advanced_testcase {
    /**
     * Build an activity (with the given setting), an individual workspace for the
     * student, and a single node locked by the teacher.
     *
     * @param int $lockmode 1 to allow learners lock mode, else 0.
     * @return array{instance: \stdClass, student: \stdClass, wsid: int, nodeid: string}
     */
    private function scenario(int $lockmode): array {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id,
            'collaborationmode' => 0,
            'lockmodeforlearners' => $lockmode,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $student->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        // A node the teacher has locked (bypass service, as an author would).
        $nodeid = 'node_aaaaaaaaaaaa';
        (new operation_service(true))->apply($wsid, 0, 'node_create', [
            'stableid' => $nodeid, 'type' => 'concept', 'label' => 'Fixed',
            'metadatajson' => json_encode(['locked' => true]),
        ], 1);

        return ['instance' => $instance, 'student' => $student, 'wsid' => $wsid, 'nodeid' => $nodeid];
    }

    /**
     * With the setting on, a student may edit the locked node via the service.
     *
     * @return void
     */
    public function test_learner_may_not_edit_locked_node_even_when_enabled(): void {
        global $DB;
        $s = $this->scenario(1);
        $this->setUser($s['student']);

        // Template protection and cooperative lock mode are separate concepts:
        // opting learners into lock mode grants collaboration leases, but it
        // must not disable teacher-authored element locks.
        $this->expectException(\moodle_exception::class);
        apply_operation::execute(
            (int) $s['instance']->cmid,
            $s['wsid'],
            (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $s['wsid']]),
            'node_update',
            json_encode(['stableid' => $s['nodeid'], 'label' => 'Changed'])
        );
    }

    /**
     * With the setting off, the same student is blocked by the lock.
     *
     * @return void
     */
    public function test_learner_blocked_when_disabled(): void {
        global $DB;
        $s = $this->scenario(0);
        $this->setUser($s['student']);

        $this->expectException(\moodle_exception::class);
        apply_operation::execute(
            (int) $s['instance']->cmid,
            $s['wsid'],
            (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $s['wsid']]),
            'node_update',
            json_encode(['stableid' => $s['nodeid'], 'label' => 'Changed'])
        );
    }

    /**
     * get_workspace reports the setting so the editor can offer lock mode.
     *
     * @return void
     */
    public function test_get_workspace_reports_the_flag(): void {
        $s = $this->scenario(1);
        $this->setUser($s['student']);

        $result = get_workspace::execute((int) $s['instance']->cmid);
        $result = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $result);
        $this->assertTrue((bool) $result['lockmodeforlearners']);
    }
}
