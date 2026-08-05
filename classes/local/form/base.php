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
 * Base class for a diagram form (display type) definition.
 *
 * Each installed vimipadform_* subplugin extends this to declare how its
 * display type looks: which node shapes are offered, the default shape, the
 * connector line style and the bifurcation behaviour. The core registry
 * discovers these and the editor renders accordingly, so new display types can
 * be added by dropping in a subplugin rather than editing the core renderer.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string[] The universal node shapes a subplugin may draw from. */
    public const SHAPES = ['roundrect', 'rect', 'ellipse'];

    /** @var string[] The connector line styles a subplugin may choose. */
    public const LINES = ['straight', 'curved', 'orthogonal'];

    /** @var string[] The bifurcation behaviours a subplugin may choose. */
    public const BIFURCATIONS = ['individual', 'shared', 'radial'];

    /**
     * The profile key this definition applies to (e.g. 'tree').
     *
     * @return string
     */
    abstract public function get_profile(): string;

    /**
     * The node shapes offered for this display type, in menu order.
     *
     * @return string[]
     */
    abstract public function get_allowed_shapes(): array;

    /**
     * The default node shape for this display type.
     *
     * @return string
     */
    abstract public function get_default_shape(): string;

    /**
     * The connector line style for this display type.
     *
     * @return string One of self::LINES.
     */
    abstract public function get_line_style(): string;

    /**
     * The bifurcation behaviour for this display type.
     *
     * @return string One of self::BIFURCATIONS.
     */
    abstract public function get_bifurcation(): string;

    /**
     * The preferred flow direction for directed edges, as ['x' => float,
     * 'y' => float], or null for none.
     *
     * This is one of the layout-potential parameters the arrange refiner reads
     * instead of hard-coding per-profile behaviour. The default is free-form (no
     * preferred direction); hierarchical forms (e.g. tree) override it. A unit
     * vector is conventional but not required.
     *
     * @return array|null
     */
    public function get_layout_direction(): ?array {
        return null;
    }

    /**
     * Whether relations are treated as directed for layout alignment.
     *
     * The default is undirected; directed forms (e.g. tree) override it.
     *
     * @return bool
     */
    public function is_layout_directed(): bool {
        return false;
    }

    /**
     * The cross-axis along which sibling order is preserved, as ['x' => float,
     * 'y' => float], or null for none.
     *
     * The default preserves no order; forms with a meaningful sibling order
     * (e.g. tree, conceptmap) override it. Radial forms leave this null; their
     * cyclic order is a separate, later refinement.
     *
     * @return array|null
     */
    public function get_layout_order_axis(): ?array {
        return null;
    }

    /**
     * The component name of the subplugin providing this definition.
     *
     * Derived from the concrete class namespace (e.g. vimipadform_tree\form
     * yields 'vimipadform_tree'). The core fallback returns 'mod_vimipad'.
     *
     * @return string
     */
    public function get_component(): string {
        $parts = explode('\\', static::class);
        return $parts[0] === 'mod_vimipad' ? 'mod_vimipad' : $parts[0];
    }

    /**
     * The localised display name for this form.
     *
     * @return string
     */
    public function get_name(): string {
        $component = $this->get_component();
        if ($component === 'mod_vimipad') {
            return $this->get_profile();
        }
        return get_string('pluginname', $component);
    }

    /**
     * Whether the given shape is offered by this display type.
     *
     * @param string $shape The shape key.
     * @return bool
     */
    public function is_shape_allowed(string $shape): bool {
        return in_array($shape, $this->get_allowed_shapes(), true);
    }

    /**
     * Clamp a requested shape to an allowed one, falling back to the default.
     *
     * @param string|null $shape The requested shape, if any.
     * @return string
     */
    public function clamp_shape(?string $shape): string {
        if ($shape !== null && $this->is_shape_allowed($shape)) {
            return $shape;
        }
        return $this->get_default_shape();
    }

    /**
     * Serialise this definition for transport to the editor.
     *
     * @return array
     */
    public function to_array(): array {
        $layout = ['directed' => $this->is_layout_directed()];
        $direction = $this->get_layout_direction();
        if ($direction !== null) {
            $layout['direction'] = ['x' => (float) $direction['x'], 'y' => (float) $direction['y']];
        }
        $orderaxis = $this->get_layout_order_axis();
        if ($orderaxis !== null) {
            $layout['orderaxis'] = ['x' => (float) $orderaxis['x'], 'y' => (float) $orderaxis['y']];
        }
        return [
            'profile' => $this->get_profile(),
            'name' => $this->get_name(),
            'allowedshapes' => array_values($this->get_allowed_shapes()),
            'defaultshape' => $this->get_default_shape(),
            'line' => $this->get_line_style(),
            'bifurcation' => $this->get_bifurcation(),
            'layout' => $layout,
        ];
    }
}
