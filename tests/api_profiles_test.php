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

use mod_vimipad\profile\profiles;

/**
 * Tests for the public, context-free profile API (\mod_vimipad\profile).
 *
 * The point of this facade is that it works WITHOUT a Moodle activity context
 * or a stored vimipad instance, so a dependent plugin can validate profiles
 * standalone. These tests deliberately create no course, module or workspace.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\profile\profiles
 */
final class api_profiles_test extends \advanced_testcase {
    /**
     * The built-in profiles are all known, and an obvious non-profile is not.
     *
     * @return void
     */
    public function test_known_profiles_exist(): void {
        $this->resetAfterTest();

        foreach (['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap'] as $profile) {
            $this->assertTrue(profiles::exists($profile), "profile '$profile' should exist");
        }
        $this->assertContains('conceptmap', profiles::all());
        $this->assertFalse(profiles::exists('not_a_real_profile'));
    }

    /**
     * The form config is always complete, even for an unknown profile (safe
     * fallback), and carries the expected keys.
     *
     * @return void
     */
    public function test_form_config_is_complete(): void {
        $this->resetAfterTest();

        $config = profiles::form_config('conceptmap');
        foreach (['profile', 'name', 'allowedshapes', 'defaultshape', 'line', 'bifurcation'] as $key) {
            $this->assertArrayHasKey($key, $config);
        }
        $this->assertSame('conceptmap', $config['profile']);
        $this->assertIsArray($config['allowedshapes']);
        $this->assertNotEmpty($config['allowedshapes']);

        // Unknown profile still yields a complete fallback config.
        $fallback = profiles::form_config('not_a_real_profile');
        $this->assertArrayHasKey('defaultshape', $fallback);
        $this->assertNotEmpty($fallback['defaultshape']);
    }

    /**
     * Shape clamping returns an allowed shape unchanged and coerces a
     * disallowed or null shape to the profile default.
     *
     * @return void
     */
    public function test_clamp_shape_coerces_to_allowed(): void {
        $this->resetAfterTest();

        $config = profiles::form_config('conceptmap');
        $allowed = $config['allowedshapes'][0];
        $default = $config['defaultshape'];

        // An allowed shape survives.
        $this->assertSame($allowed, profiles::clamp_shape('conceptmap', $allowed));
        $this->assertTrue(profiles::is_shape_allowed('conceptmap', $allowed));

        // A nonsense shape is coerced to the default.
        $this->assertSame($default, profiles::clamp_shape('conceptmap', 'no_such_shape_xyz'));
        $this->assertFalse(profiles::is_shape_allowed('conceptmap', 'no_such_shape_xyz'));

        // Null is coerced to the default.
        $this->assertSame($default, profiles::clamp_shape('conceptmap', null));
    }
}
