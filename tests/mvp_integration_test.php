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

/**
 * End-to-end MVP integration: install artefacts and activity creation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad
 */
final class mvp_integration_test extends \advanced_testcase {
    /**
     * The plugin registers its capabilities and web service functions.
     *
     * @return void
     */
    public function test_install_artefacts_present(): void {
        global $DB;
        $this->resetAfterTest();

        // Capabilities defined in db/access.php are installed.
        $caps = $DB->count_records_select('capabilities', "name LIKE 'mod/vimipad:%'");
        $this->assertGreaterThanOrEqual(10, $caps);

        // External functions from db/services.php are registered.
        $services = $DB->count_records_select('external_functions', "name LIKE 'mod_vimipad_%'");
        $this->assertGreaterThanOrEqual(4, $services);
    }

    /**
     * Creating an activity via the module API sets up the instance and a
     * gradebook item.
     *
     * @return void
     */
    public function test_create_activity_and_gradebook(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id,
            'name' => 'Knowledge map',
            'defaultprofile' => 'conceptmap',
            'collaborationmode' => 0,
            'grade' => 100,
        ]);

        // Instance row has the expected fields.
        $record = $DB->get_record('vimipad', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('conceptmap', $record->defaultprofile);
        $this->assertSame(100, (int) $record->grade);

        // A gradebook item was created for the activity.
        $gradeitems = $DB->count_records(
            'grade_items',
            ['itemmodule' => 'vimipad', 'iteminstance' => $instance->id]
        );
        $this->assertSame(1, $gradeitems);

        // The activity supports the features the MVP relies on.
        $this->assertTrue(vimipad_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(vimipad_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(vimipad_supports(FEATURE_COMPLETION_HAS_RULES));
    }

    /**
     * Deleting the activity removes the instance and its gradebook item.
     *
     * @return void
     */
    public function test_delete_activity_cleans_up(): void {
        global $DB;
        require_once(__DIR__ . '/../lib.php');

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);

        $this->assertTrue(vimipad_delete_instance($instance->id));
        $this->assertSame(0, $DB->count_records('vimipad', ['id' => $instance->id]));
        $this->assertSame(0, $DB->count_records(
            'grade_items',
            ['itemmodule' => 'vimipad', 'iteminstance' => $instance->id]
        ));
    }
}
