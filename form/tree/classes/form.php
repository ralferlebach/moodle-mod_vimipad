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

namespace vimipadform_tree;

/**
 * Form definition for the Tree display type.
 *
 * Declares the node shapes, default shape, connector line style and
 * bifurcation behaviour the editor uses when this display type is active.
 *
 * @package    vimipadform_tree
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
        return 'tree';
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
        return 'rect';
    }

    /**
     * The connector line style for this display type.
     *
     * @return string
     */
    public function get_line_style(): string {
        return 'orthogonal';
    }

    /**
     * The bifurcation behaviour for this display type.
     *
     * @return string
     */
    public function get_bifurcation(): string {
        return 'shared';
    }

    /**
     * Trees flow downward: directed edges are aligned along +y.
     *
     * @return array|null
     */
    public function get_layout_direction(): ?array {
        return ['x' => 0.0, 'y' => 1.0];
    }

    /**
     * Trees treat relations as directed (parent to child).
     *
     * @return bool
     */
    public function is_layout_directed(): bool {
        return true;
    }

    /**
     * Siblings are ordered left to right, so the order axis is +x.
     *
     * @return array|null
     */
    public function get_layout_order_axis(): ?array {
        return ['x' => 1.0, 'y' => 0.0];
    }
}
