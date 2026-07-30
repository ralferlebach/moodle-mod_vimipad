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
use mod_vimipad\external\get_constraint_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the get_constraint_status external function.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\get_constraint_status
 */
final class get_constraint_status_test extends externallib_advanced_testcase {
    /**
     * Add a concept node to a workspace.
     *
     * @param int $workspaceid The workspace id.
     * @param string $stableid The stable id.
     * @param string $label The concept label.
     * @return void
     */
    private function add_node(int $workspaceid, string $stableid, string $label): void {
        global $DB;
        $now = time();
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspaceid, 'stableid' => $stableid, 'type' => 'concept', 'label' => $label,
            'content' => null, 'contentformat' => FORMAT_HTML, 'createdby' => 1, 'modifiedby' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
    }

    /**
     * A configured constraint reports violations, then satisfaction once fixed,
     * and the return value matches the declared structure.
     *
     * @return void
     */
    public function test_reports_violation_then_satisfaction(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'requiredconcepts' => 'Mitochondria',
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $student->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $this->add_node($wsid, 'node_aaaaaaaaaaaa', 'Cell');

        $result = get_constraint_status::execute($instance->cmid, $wsid);
        $result = \core_external\external_api::clean_returnvalue(
            get_constraint_status::execute_returns(),
            $result
        );

        $this->assertTrue($result['configured']);
        $this->assertFalse($result['satisfied']);
        $this->assertContains('mitochondria', $result['requiredmissing']);
        $this->assertNotEmpty($result['messages']);

        // Add the required concept and re-check.
        $this->add_node($wsid, 'node_bbbbbbbbbbbb', 'Mitochondria');
        $result = get_constraint_status::execute($instance->cmid, $wsid);
        $this->assertTrue($result['satisfied']);
        $this->assertSame([], $result['requiredmissing']);
    }

    /**
     * With no constraints configured the status is satisfied and not configured.
     *
     * @return void
     */
    public function test_no_constraints_is_satisfied(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $student->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $result = get_constraint_status::execute($instance->cmid, $wsid);
        $this->assertFalse($result['configured']);
        $this->assertTrue($result['satisfied']);
    }
}
