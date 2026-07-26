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

namespace mod_vimipad\api;

use mod_vimipad\local\id\stable_id;

/**
 * Public, stable facade for stable-id handling.
 *
 * This class is part of the intentionally stable contract under
 * \mod_vimipad\api and may be used by dependent plugins (e.g. a question type
 * or database field reusing the ViMi editor and profiles). Its signatures are
 * treated as stable across minor releases; the internal implementation under
 * \mod_vimipad\local may change freely.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ids {
    /**
     * Generate a new stable id for a node.
     *
     * @return string
     */
    public static function new_node_id(): string {
        return stable_id::generate('node');
    }

    /**
     * Generate a new stable id for a relation.
     *
     * @return string
     */
    public static function new_relation_id(): string {
        return stable_id::generate('relation');
    }

    /**
     * Generate a new stable id for a container.
     *
     * @return string
     */
    public static function new_container_id(): string {
        return stable_id::generate('container');
    }

    /**
     * Validate a stable id, optionally enforcing an entity kind.
     *
     * @param string $value The candidate identifier.
     * @param string|null $kind One of 'node', 'relation', 'container', or null for any.
     * @return bool
     */
    public static function is_valid(string $value, ?string $kind = null): bool {
        return stable_id::is_valid($value, $kind);
    }
}
