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

namespace vimipadform_causal;

/**
 * Form definition for the Causal/system map display type.
 *
 * A directed network of cause-effect links in which feedback loops are allowed:
 * no global flow direction is imposed (that would fight the cycles), so the
 * layout is free like a semantic network, but relations are directed by default
 * and curved connectors read the loops clearly.
 *
 * @package    vimipadform_causal
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
        return 'causal';
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
     * Curved connectors read feedback loops clearly.
     *
     * @return string
     */
    public function get_line_style(): string {
        return 'curved';
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
     * Causal links carry a polarity: positive (same direction) or negative
     * (opposite direction), as in causal-loop / system diagrams.
     *
     * @return string[]
     */
    public function get_relation_types(): array {
        return ['positive', 'negative'];
    }
}
