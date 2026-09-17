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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/vimipad/lib.php');

/**
 * Contract tests that walk every installed subplugin rather than a fixed list.
 *
 * The existing registry test hard-codes the five MVP profiles, so the eight
 * profiles added since were never checked, and three of the six scorers were
 * never instantiated. These tests discover the subplugins from disk instead, so
 * a profile or scorer added later is covered the moment it ships rather than
 * only if someone remembers to extend a list.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\form\registry
 * @covers     \mod_vimipad\local\assess\registry
 */
final class subplugin_contract_test extends \advanced_testcase {
    /** The node shapes the map value policy accepts. */
    private const VALID_SHAPES = ['roundrect', 'rect', 'ellipse'];

    /** The connector styles a profile may declare. */
    private const VALID_LINE_STYLES = ['straight', 'curved', 'orthogonal'];

    /** The bifurcation modes a profile may declare. */
    private const VALID_BIFURCATIONS = ['individual', 'shared', 'radial'];

    /**
     * Every directory under form/ that ships a subplugin.
     *
     * @return array<string, array{string}> Named data rows for the provider.
     */
    public static function form_subplugin_provider(): array {
        global $CFG;

        $rows = [];
        foreach (glob($CFG->dirroot . '/mod/vimipad/form/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            $rows[$name] = [$name];
        }
        return $rows;
    }

    /**
     * Every directory under assess/ that ships a subplugin.
     *
     * @return array<string, array{string}> Named data rows for the provider.
     */
    public static function assess_subplugin_provider(): array {
        global $CFG;

        $rows = [];
        foreach (glob($CFG->dirroot . '/mod/vimipad/assess/*', GLOB_ONLYDIR) as $dir) {
            $name = basename($dir);
            $rows[$name] = [$name];
        }
        return $rows;
    }

    /**
     * Each diagram profile declares a self-consistent configuration.
     *
     * @dataProvider form_subplugin_provider
     * @param string $profile The subplugin directory name.
     * @return void
     */
    public function test_form_subplugin_is_consistent(string $profile): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $definition = registry::for_profile($profile);

        $this->assertSame(
            $profile,
            $definition->get_profile(),
            "vimipadform_{$profile} must report the profile name its directory is called; " .
            'a mismatch makes registry lookups silently fall back to the default profile.'
        );

        $shapes = $definition->get_allowed_shapes();
        $this->assertNotEmpty($shapes, "vimipadform_{$profile} must allow at least one node shape.");
        foreach ($shapes as $shape) {
            $this->assertContains(
                $shape,
                self::VALID_SHAPES,
                "vimipadform_{$profile} allows shape '{$shape}', which the map value policy rejects."
            );
        }

        $this->assertContains(
            $definition->get_default_shape(),
            $shapes,
            "The default shape of vimipadform_{$profile} must be one it also allows."
        );

        $this->assertContains($definition->get_line_style(), self::VALID_LINE_STYLES);
        $this->assertContains($definition->get_bifurcation(), self::VALID_BIFURCATIONS);
    }

    /**
     * Every profile survives a round trip through to_array().
     *
     * The frontend consumes this array, so a missing key breaks the editor for
     * that profile only - exactly the kind of failure a fixed list misses.
     *
     * @dataProvider form_subplugin_provider
     * @param string $profile The subplugin directory name.
     * @return void
     */
    public function test_form_subplugin_to_array_is_complete(string $profile): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $array = registry::for_profile($profile)->to_array();

        $required = [
            'profile', 'name', 'allowedshapes', 'defaultshape', 'line',
            'bifurcation', 'relationtypes', 'relationlayout', 'layout',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey(
                $key,
                $array,
                "to_array() of vimipadform_{$profile} is missing '{$key}', which the editor reads."
            );
        }
        $this->assertSame($profile, $array['profile']);
    }

    /**
     * Each scorer can be instantiated and answers the contract methods.
     *
     * @dataProvider assess_subplugin_provider
     * @param string $key The subplugin directory name.
     * @return void
     */
    public function test_assess_subplugin_is_consistent(string $key): void {
        $this->resetAfterTest();

        $class = "\\vimipadassess_{$key}\\scorer";
        $this->assertTrue(
            class_exists($class),
            "vimipadassess_{$key} must provide {$class}; the registry loads scorers by that name."
        );

        $scorer = new $class();

        $this->assertSame(
            $key,
            $scorer->get_key(),
            "vimipadassess_{$key} must report the key its directory is called."
        );
        $this->assertNotEmpty(trim((string) $scorer->get_name()), 'A scorer needs a display name.');
        $this->assertIsBool($scorer->supports_profile('conceptmap'));
        $this->assertIsBool($scorer->uses_ai());
        $this->assertIsBool($scorer->requires_reference());
    }

    /**
     * The shipped profiles and scorers are all discoverable through the registry.
     *
     * @return void
     */
    public function test_every_shipped_subplugin_is_registered(): void {
        global $CFG;
        $this->resetAfterTest();
        registry::reset_cache();

        $ondisk = array_keys(self::form_subplugin_provider());
        $this->assertNotEmpty($ondisk, 'At least the MVP profiles must ship.');

        foreach ($ondisk as $profile) {
            $this->assertSame(
                $profile,
                registry::for_profile($profile)->get_profile(),
                "Profile {$profile} ships on disk but the registry does not resolve it."
            );
        }

        // Guard the fallback too: an unknown profile must not fatal.
        $fallback = registry::for_profile('definitely_not_a_profile');
        $this->assertNotEmpty($fallback->get_profile());
    }
}
