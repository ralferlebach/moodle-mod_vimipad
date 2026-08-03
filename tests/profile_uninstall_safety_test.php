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

use mod_vimipad\local\form\registry;
use mod_vimipad\local\form\fallback;
use mod_vimipad\profile\profiles;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vimipad/lib.php');

/**
 * The form subplugins are separately installable/uninstallable. Uninstalling
 * one must not break an activity that uses its profile: the editor degrades to
 * the fallback definition, the menu still offers the built-in profiles, and an
 * unknown profile reaching a non-form path is repaired to the safe default.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\form\registry
 * @covers     \mod_vimipad\profile\profiles
 */
final class profile_uninstall_safety_test extends \advanced_testcase {
    /**
     * A profile whose subplugin is absent resolves to the safe fallback
     * definition rather than throwing — the editor keeps working.
     *
     * @return void
     */
    public function test_absent_profile_degrades_to_fallback(): void {
        $this->resetAfterTest();

        // A profile key with no installed vimipadform_* subplugin.
        $def = registry::for_profile('a_profile_with_no_subplugin');
        $this->assertInstanceOf(fallback::class, $def);
        // The fallback still yields complete, sensible rendering rules.
        $this->assertNotEmpty($def->get_allowed_shapes());
        $this->assertNotEmpty($def->get_default_shape());
        $this->assertNotEmpty($def->get_line_style());
    }

    /**
     * The built-in profiles remain offered in the settings menu regardless of
     * which subplugins are installed, so an activity can always be configured.
     *
     * @return void
     */
    public function test_builtin_profiles_always_offered(): void {
        $this->resetAfterTest();

        $options = registry::menu_options();
        foreach (['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap'] as $profile) {
            $this->assertArrayHasKey($profile, $options);
            $this->assertTrue(profiles::exists($profile));
        }
    }

    /**
     * Saving an instance with an unknown profile (as backup/restore or a web
     * service might) repairs it to the safe default instead of persisting a
     * dangling value.
     *
     * @return void
     */
    public function test_unknown_profile_is_normalised_on_save(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $data = (object) [
            'course' => $course->id,
            'name' => 'Test map',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'defaultprofile' => 'profile_from_an_uninstalled_subplugin',
            'collaborationmode' => 0,
        ];
        $id = vimipad_add_instance($data);
        $stored = $DB->get_record('vimipad', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('conceptmap', $stored->defaultprofile);

        // A known profile is left untouched.
        $data2 = clone $data;
        $data2->defaultprofile = 'tree';
        $id2 = vimipad_add_instance($data2);
        $stored2 = $DB->get_record('vimipad', ['id' => $id2], '*', MUST_EXIST);
        $this->assertSame('tree', $stored2->defaultprofile);
    }
}
