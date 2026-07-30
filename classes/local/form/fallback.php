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

/**
 * Safe default form definition.
 *
 * Used when no vimipadform_* subplugin is installed for the active profile, so
 * the editor always has sensible rendering rules to fall back on: all universal
 * shapes, a rounded-rectangle default, straight connectors and individual
 * bifurcation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fallback extends base {
    /** @var string The profile this fallback stands in for. */
    private string $profile;

    /**
     * Constructor.
     *
     * @param string $profile The profile key this fallback represents.
     */
    public function __construct(string $profile) {
        $this->profile = $profile;
    }

    /**
     * The profile this fallback stands in for.
     *
     * @return string
     */
    public function get_profile(): string {
        return $this->profile;
    }

    /**
     * All universal node shapes.
     *
     * @return string[]
     */
    public function get_allowed_shapes(): array {
        return self::SHAPES;
    }

    /**
     * The default node shape (rounded rectangle).
     *
     * @return string
     */
    public function get_default_shape(): string {
        return 'roundrect';
    }

    /**
     * The connector line style (straight).
     *
     * @return string
     */
    public function get_line_style(): string {
        return 'straight';
    }

    /**
     * The bifurcation behaviour (individual).
     *
     * @return string
     */
    public function get_bifurcation(): string {
        return 'individual';
    }
}
