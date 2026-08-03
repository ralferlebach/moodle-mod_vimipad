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

namespace mod_vimipad\profile;

use mod_vimipad\local\form\registry;

/**
 * Public, stable, context-free facade for profile validation.
 *
 * A profile (e.g. 'conceptmap', 'tree') defines the allowed shapes, default
 * shape, line style and bifurcation for a map. Dependent plugins (a question
 * type, a database field, a standalone embedding) need to validate a profile
 * and resolve its form configuration WITHOUT a Moodle activity context or a
 * stored vimipad instance — this facade provides exactly that.
 *
 * This class is part of the intentionally stable contract under
 * \mod_vimipad\profile and its signatures are treated as stable across minor
 * releases; the internal implementation under \mod_vimipad\local may change
 * freely.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profiles {
    /**
     * Every profile key on offer (built-ins plus installed form subplugins).
     *
     * @return string[] The known profile keys.
     */
    public static function all(): array {
        return registry::known_profiles();
    }

    /**
     * Whether a profile key is known (built-in or installed as a subplugin).
     *
     * @param string $profile The profile key to check.
     * @return bool True if the profile is known.
     */
    public static function exists(string $profile): bool {
        return in_array($profile, registry::known_profiles(), true);
    }

    /**
     * The form configuration for a profile: allowed shapes, default shape,
     * line style, bifurcation and localised name. A safe fallback is returned
     * for an unknown profile, so the result is always a complete config.
     *
     * @param string $profile The profile key.
     * @return array The form configuration (see form\base::to_array()).
     */
    public static function form_config(string $profile): array {
        return registry::for_profile($profile)->to_array();
    }

    /**
     * Whether a shape is allowed under a profile.
     *
     * @param string $profile The profile key.
     * @param string $shape The shape name (e.g. 'rectangle', 'ellipse').
     * @return bool True if the shape is allowed for the profile.
     */
    public static function is_shape_allowed(string $profile, string $shape): bool {
        return registry::for_profile($profile)->is_shape_allowed($shape);
    }

    /**
     * Clamp a shape to one allowed by the profile: returns the shape unchanged
     * if allowed, otherwise the profile's default shape. Accepts null.
     *
     * @param string $profile The profile key.
     * @param string|null $shape The requested shape, or null.
     * @return string A shape guaranteed to be valid for the profile.
     */
    public static function clamp_shape(string $profile, ?string $shape): string {
        return registry::for_profile($profile)->clamp_shape($shape);
    }
}
