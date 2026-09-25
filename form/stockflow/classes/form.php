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

namespace vimipadform_stockflow;

/**
 * Stock-and-Flow / System Dynamics diagrams in the style of Meadows.
 *
 * A stock-and-flow model is built from the same two things every other ViMi Pad
 * map is: nodes and named relations. What makes it a system diagram is the role
 * each node plays — stock, valve, delay and so on — which is carried in the
 * node's own metadata rather than inferred from its shape, so re-styling a map
 * never changes its meaning.
 *
 * Deliberately excluded: polarity, feedback loops and any loop classification.
 * The meaning of an influence lives in the relation's label ("increases",
 * "limits"), not in a separate property.
 *
 * The existing causal profile is untouched and keeps serving Causal Loop
 * Diagrams.
 *
 * @package    vimipadform_stockflow
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends \mod_vimipad\local\form\base {
    /**
     * The profile key.
     *
     * @return string
     */
    public function get_profile(): string {
        return 'stockflow';
    }

    /**
     * The node shapes a stock-and-flow map offers.
     *
     * Each one stands for a system role: an accumulation, the boundary of the
     * model, a rate, a delay, a derived variable and a constant.
     *
     * @return string[]
     */
    public function get_allowed_shapes(): array {
        return ['stock', 'cloud', 'valve', 'delay', 'ellipse', 'parameter', 'roundrect'];
    }

    /**
     * The shape a new node gets.
     *
     * A generic element, so adding a node never asserts a system role the
     * author did not choose.
     *
     * @return string
     */
    public function get_default_shape(): string {
        return 'roundrect';
    }

    /**
     * Connector style.
     *
     * Straight connectors keep a material-flow chain readable as a chain.
     *
     * @return string
     */
    public function get_line_style(): string {
        return 'straight';
    }

    /**
     * How branches leave a node.
     *
     * @return string
     */
    public function get_bifurcation(): string {
        return 'individual';
    }

    /**
     * The relation types a stock-and-flow map offers.
     *
     * Inflow and outflow are not separate types: they follow from the direction
     * of a flow relative to a stock. Anything finer is said in the label.
     *
     * @return string[]
     */
    public function get_relation_types(): array {
        return ['flow', 'influence', 'relation'];
    }
}
