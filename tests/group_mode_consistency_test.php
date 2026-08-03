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

use mod_vimipad\local\service\workspace_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vimipad/lib.php');
require_once($CFG->dirroot . '/mod/vimipad/mod_form.php');

/**
 * Group-map / core group-mode consistency.
 *
 * The activity form validates the coupling bidirectionally (via the pure
 * decision function group_mode_error); instance creation from non-form paths
 * (backup/restore, web services) repairs the invariant non-destructively via
 * vimipad_enforce_group_consistency() instead of failing.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::vimipad_enforce_group_consistency
 * @covers     \mod_vimipad_mod_form::group_mode_error
 */
final class group_mode_consistency_test extends \advanced_testcase {
    /**
     * Create a course module with an explicit group mode and return its cm id.
     *
     * @param \stdClass $course The course.
     * @param int $collaborationmode The initial map mode.
     * @param int $groupmode The initial course-module group mode.
     * @return int The course module id.
     */
    private function make_cm(\stdClass $course, int $collaborationmode, int $groupmode): int {
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id,
            'collaborationmode' => $collaborationmode,
            'groupmode' => $groupmode,
        ]);
        return (int) get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST)->id;
    }

    /**
     * The form decision function flags both inconsistent directions.
     *
     * @return void
     */
    public function test_form_rule(): void {
        $this->assertSame(
            'groupmode',
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_GROUP, NOGROUPS, false)[0]
        );
        $this->assertSame(
            'collaborationmode',
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_GROUP, NOGROUPS, true)[0]
        );
        $this->assertSame(
            'collaborationmode',
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_INDIVIDUAL, SEPARATEGROUPS, false)[0]
        );
        $this->assertNull(
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_GROUP, SEPARATEGROUPS, false)
        );
        $this->assertNull(
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_INDIVIDUAL, NOGROUPS, false)
        );
        $this->assertNull(
            \mod_vimipad_mod_form::group_mode_error(workspace_service::MODE_COURSE, NOGROUPS, false)
        );
    }

    /**
     * A group map without a group mode is repaired to separate groups.
     *
     * @return void
     */
    public function test_group_map_without_group_mode_gets_separate_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->make_cm($course, workspace_service::MODE_INDIVIDUAL, NOGROUPS);

        $data = (object) [
            'coursemodule' => $cmid,
            'collaborationmode' => workspace_service::MODE_GROUP,
            'groupmode' => NOGROUPS,
        ];
        vimipad_enforce_group_consistency($data);

        $this->assertEquals(SEPARATEGROUPS, (int) $data->groupmode);
        $this->assertEquals(SEPARATEGROUPS, (int) $DB->get_field('course_modules', 'groupmode', ['id' => $cmid]));
    }

    /**
     * A non-group map carrying a group mode has it cleared (course not forcing).
     *
     * @return void
     */
    public function test_non_group_map_with_group_mode_is_cleared(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->make_cm($course, workspace_service::MODE_GROUP, SEPARATEGROUPS);

        $data = (object) [
            'coursemodule' => $cmid,
            'collaborationmode' => workspace_service::MODE_INDIVIDUAL,
            'groupmode' => SEPARATEGROUPS,
        ];
        vimipad_enforce_group_consistency($data);

        $this->assertEquals(NOGROUPS, (int) $data->groupmode);
        $this->assertEquals(workspace_service::MODE_INDIVIDUAL, (int) $data->collaborationmode);
        $this->assertEquals(NOGROUPS, (int) $DB->get_field('course_modules', 'groupmode', ['id' => $cmid]));
    }

    /**
     * Under a forced course group mode, a non-group map is promoted to a group
     * map rather than clearing the (unremovable) group mode.
     *
     * @return void
     */
    public function test_forced_group_mode_promotes_map(): void {
        global $DB, $COURSE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $cmid = $this->make_cm($course, workspace_service::MODE_GROUP, SEPARATEGROUPS);
        $COURSE = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);

        $data = (object) [
            'coursemodule' => $cmid,
            'collaborationmode' => workspace_service::MODE_INDIVIDUAL,
            'groupmode' => SEPARATEGROUPS,
        ];
        vimipad_enforce_group_consistency($data);

        $this->assertEquals(workspace_service::MODE_GROUP, (int) $data->collaborationmode);
        $this->assertEquals(SEPARATEGROUPS, (int) $DB->get_field('course_modules', 'groupmode', ['id' => $cmid]));
    }

    /**
     * Consistent input is left unchanged.
     *
     * @return void
     */
    public function test_consistent_input_is_untouched(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->make_cm($course, workspace_service::MODE_GROUP, SEPARATEGROUPS);

        $data = (object) [
            'coursemodule' => $cmid,
            'collaborationmode' => workspace_service::MODE_GROUP,
            'groupmode' => SEPARATEGROUPS,
        ];
        vimipad_enforce_group_consistency($data);
        $this->assertEquals(SEPARATEGROUPS, (int) $data->groupmode);
        $this->assertEquals(workspace_service::MODE_GROUP, (int) $data->collaborationmode);
    }

    /**
     * The double-negative quadrant is a no-op: no group mode plus a non-group
     * map is already consistent and must be left exactly as-is (neither the map
     * mode nor the group mode is touched).
     *
     * @return void
     */
    public function test_no_group_mode_and_non_group_map_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        foreach ([workspace_service::MODE_INDIVIDUAL, workspace_service::MODE_COURSE] as $mode) {
            $cmid = $this->make_cm($course, $mode, NOGROUPS);
            $data = (object) [
                'coursemodule' => $cmid,
                'collaborationmode' => $mode,
                'groupmode' => NOGROUPS,
            ];
            vimipad_enforce_group_consistency($data);

            $this->assertEquals($mode, (int) $data->collaborationmode);
            $this->assertEquals(NOGROUPS, (int) $data->groupmode);
            $this->assertEquals(NOGROUPS, (int) $DB->get_field('course_modules', 'groupmode', ['id' => $cmid]));
        }
    }
}
