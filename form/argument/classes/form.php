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

namespace vimipadform_argument;

/**
 * Form definition for the Argument map display type.
 *
 * Claims sit on top with supporting/attacking reasons below pointing upward:
 * the layout is directed along -y with siblings ordered along +x. Relations are
 * typed as support or attack; attack relations repel (branch apart) on arrange.
 *
 * @package    vimipadform_argument
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends \mod_vimipad\local\form\base {
    /**
     * The profile key this definition applies to.
     *
     * @return string
     */
    public function get_profile(): string {
        return 'argument';
    }

    /**
     * The node shapes offered for this display type, in menu order.
     *
     * @return string[]
     */
    public function get_allowed_shapes(): array {
        return self::SHAPES;
    }

    /**
     * The default node shape for this display type.
     *
     * @return string
     */
    public function get_default_shape(): string {
        return 'roundrect';
    }

    /**
     * The connector line style for this display type.
     *
     * @return string
     */
    public function get_line_style(): string {
        return 'straight';
    }

    /**
     * The bifurcation behaviour for this display type.
     *
     * @return string
     */
    public function get_bifurcation(): string {
        return 'individual';
    }

    /**
     * Support and attack relations (untyped links are not used here).
     *
     * @return string[]
     */
    public function get_relation_types(): array {
        return ['support', 'attack'];
    }

    /**
     * Relations are directed (reason to claim).
     *
     * @return bool
     */
    public function is_layout_directed(): bool {
        return true;
    }

    /**
     * Reasons point upward toward the claim they support: direction -y.
     *
     * @return array|null
     */
    public function get_layout_direction(): ?array {
        return ['x' => 0.0, 'y' => -1.0];
    }

    /**
     * Siblings are ordered left to right along +x.
     *
     * @return array|null
     */
    public function get_layout_order_axis(): ?array {
        return ['x' => 1.0, 'y' => 0.0];
    }
}
