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

namespace mod_vimipad\local\assess;

use core_component;

/**
 * Registry of installed automatic scorers (vimipadassess_* subplugins).
 *
 * Discovers every vimipadassess subplugin, instantiates its scorer and keys them
 * by subplugin name. This is the seam that lets new scoring strategies be added
 * as subplugins without the core module knowing about each one.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registry {
    /** @var scorer[]|null Cached scorers keyed by subplugin name. */
    private static ?array $cache = null;

    /**
     * All installed scorers, keyed by subplugin name.
     *
     * @return scorer[]
     */
    public static function all(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $scorers = [];
        foreach (array_keys(core_component::get_plugin_list('vimipadassess')) as $name) {
            $class = "vimipadassess_{$name}\\scorer";
            if (!class_exists($class)) {
                continue;
            }
            $scorer = new $class();
            if ($scorer instanceof scorer) {
                $scorers[$scorer->get_key()] = $scorer;
            }
        }

        self::$cache = $scorers;
        return self::$cache;
    }

    /**
     * A single scorer by key, or null if not installed.
     *
     * @param string $key The scorer key.
     * @return scorer|null
     */
    public static function get(string $key): ?scorer {
        return self::all()[$key] ?? null;
    }

    /**
     * Scorers that can handle the given diagram profile.
     *
     * @param string $profile The profile key.
     * @return scorer[]
     */
    public static function for_profile(string $profile): array {
        return array_filter(self::all(), static fn($scorer) => $scorer->supports_profile($profile));
    }

    /**
     * Forget the cached scorers (used by tests after installing fixtures).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$cache = null;
    }
}
