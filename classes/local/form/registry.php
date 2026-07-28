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

namespace mod_vimipad\local\form;

use core_component;

/**
 * Registry of installed diagram form (display type) definitions.
 *
 * Discovers every vimipadform_* subplugin, instantiates its definition and
 * keys them by profile. Consumers ask for a profile and always receive a usable
 * definition: a subplugin's if installed, otherwise a safe built-in fallback.
 * This is the seam that lets display types be added as subplugins without the
 * core editor knowing about each one.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var string[] The built-in profiles the core module always offers. */
    public const BUILTIN_PROFILES = ['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap'];

    /** @var base[]|null Cached definitions keyed by profile, or null if not yet built. */
    private static ?array $cache = null;

    /**
     * All installed form definitions, keyed by profile.
     *
     * @return base[]
     */
    public static function all(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $definitions = [];
        foreach (array_keys(core_component::get_plugin_list('vimipadform')) as $name) {
            $class = "vimipadform_{$name}\\form";
            if (!class_exists($class)) {
                continue;
            }
            $definition = new $class();
            if ($definition instanceof base) {
                $definitions[$definition->get_profile()] = $definition;
            }
        }

        self::$cache = $definitions;
        return self::$cache;
    }

    /**
     * The definition for a profile, or a safe fallback if none is installed.
     *
     * @param string $profile The profile key (e.g. 'tree').
     * @return base
     */
    public static function for_profile(string $profile): base {
        $all = self::all();
        return $all[$profile] ?? new fallback($profile);
    }

    /**
     * Forget the cached definitions (used by tests after installing fixtures).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$cache = null;
    }

    /**
     * Every profile key on offer: the built-in profiles plus any installed
     * subplugins not already covered.
     *
     * @return string[]
     */
    public static function known_profiles(): array {
        $profiles = self::BUILTIN_PROFILES;
        foreach (array_keys(self::all()) as $profile) {
            if (!in_array($profile, $profiles, true)) {
                $profiles[] = $profile;
            }
        }
        return $profiles;
    }

    /**
     * Profile => localised label options for a settings menu. A subplugin's own
     * name is used when installed, otherwise the core language string.
     *
     * @return array<string,string>
     */
    public static function menu_options(): array {
        $all = self::all();
        $options = [];
        foreach (self::known_profiles() as $profile) {
            $options[$profile] = isset($all[$profile])
                ? $all[$profile]->get_name()
                : get_string('profile_' . $profile, 'mod_vimipad');
        }
        return $options;
    }
}
