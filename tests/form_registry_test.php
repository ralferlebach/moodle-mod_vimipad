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

namespace mod_vimipad;

use mod_vimipad\local\form\registry;
use mod_vimipad\local\form\fallback;

/**
 * Tests for the diagram form (display type) registry.
 *
 * Exercises discovery of the bundled vimipadform_* subplugins and the safe
 * fallback used when no subplugin is installed for a profile.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\form\registry
 */
final class form_registry_test extends \advanced_testcase {
    /**
     * Each bundled subplugin exposes the connector style and bifurcation the
     * accepted design table specifies.
     *
     * @return void
     */
    public function test_bundled_profiles_expose_expected_config(): void {
        registry::reset_cache();

        $expected = [
            'conceptmap' => ['line' => 'straight', 'bifurcation' => 'individual', 'defaultshape' => 'roundrect'],
            'mindmap' => ['line' => 'curved', 'bifurcation' => 'radial', 'defaultshape' => 'ellipse'],
            'tree' => ['line' => 'orthogonal', 'bifurcation' => 'shared', 'defaultshape' => 'rect'],
            'semanticnetwork' => ['line' => 'straight', 'bifurcation' => 'individual', 'defaultshape' => 'ellipse'],
            'bubblemap' => ['line' => 'curved', 'bifurcation' => 'radial', 'defaultshape' => 'ellipse'],
        ];

        foreach ($expected as $profile => $want) {
            $definition = registry::for_profile($profile);
            $this->assertSame($profile, $definition->get_profile());
            $this->assertSame($want['line'], $definition->get_line_style());
            $this->assertSame($want['bifurcation'], $definition->get_bifurcation());
            $this->assertSame($want['defaultshape'], $definition->get_default_shape());
            $this->assertContains($want['defaultshape'], $definition->get_allowed_shapes());
        }
    }

    /**
     * An unknown profile yields the safe built-in fallback rather than an error.
     *
     * @return void
     */
    public function test_unknown_profile_falls_back(): void {
        registry::reset_cache();

        $definition = registry::for_profile('doesnotexist');

        $this->assertInstanceOf(fallback::class, $definition);
        $this->assertSame('doesnotexist', $definition->get_profile());
        $this->assertSame('straight', $definition->get_line_style());
        $this->assertSame('individual', $definition->get_bifurcation());
        $this->assertSame('roundrect', $definition->get_default_shape());
    }

    /**
     * The serialised form config carries every field the editor needs.
     *
     * @return void
     */
    public function test_to_array_shape(): void {
        registry::reset_cache();

        $array = registry::for_profile('tree')->to_array();

        $this->assertSame('tree', $array['profile']);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('allowedshapes', $array);
        $this->assertSame('rect', $array['defaultshape']);
        $this->assertSame('orthogonal', $array['line']);
        $this->assertSame('shared', $array['bifurcation']);
        $this->assertArrayHasKey('layout', $array);
        $this->assertTrue($array['layout']['directed']);
    }

    /**
     * Each bundled subplugin declares the layout-potential parameters the
     * arrange refiner reads (directed flag, preferred direction, order axis).
     * Hierarchical forms flow/order; radial and free forms declare neither.
     *
     * @return void
     */
    public function test_layout_potential_declarations(): void {
        registry::reset_cache();

        // Tree: directed, flows downward (+y), siblings ordered along +x, linear.
        $tree = registry::for_profile('tree')->to_array()['layout'];
        $this->assertTrue($tree['directed']);
        $this->assertSame(['x' => 0.0, 'y' => 1.0], $tree['direction']);
        $this->assertSame(['x' => 1.0, 'y' => 0.0], $tree['orderaxis']);
        $this->assertFalse($tree['cyclicorder']);

        // Concept map: undirected, no forced direction, keeps sibling order, linear.
        $concept = registry::for_profile('conceptmap')->to_array()['layout'];
        $this->assertFalse($concept['directed']);
        $this->assertArrayNotHasKey('direction', $concept);
        $this->assertSame(['x' => 1.0, 'y' => 0.0], $concept['orderaxis']);
        $this->assertFalse($concept['cyclicorder']);

        // Radial forms: undirected, no linear order axis, but cyclic order on.
        foreach (['mindmap', 'bubblemap'] as $profile) {
            $layout = registry::for_profile($profile)->to_array()['layout'];
            $this->assertFalse($layout['directed'], "$profile should be undirected");
            $this->assertArrayNotHasKey('direction', $layout, "$profile should force no direction");
            $this->assertArrayNotHasKey('orderaxis', $layout, "$profile should keep no linear order axis");
            $this->assertTrue($layout['cyclicorder'], "$profile should preserve cyclic order");
        }

        // Semantic network: free — undirected, no direction, no order of any kind.
        $semantic = registry::for_profile('semanticnetwork')->to_array()['layout'];
        $this->assertFalse($semantic['directed']);
        $this->assertArrayNotHasKey('direction', $semantic);
        $this->assertArrayNotHasKey('orderaxis', $semantic);
        $this->assertFalse($semantic['cyclicorder']);

        // Timeline: directed left-to-right, events confined onto one line (+x),
        // no linear sibling order and no cyclic order.
        $timeline = registry::for_profile('timeline')->to_array()['layout'];
        $this->assertTrue($timeline['directed']);
        $this->assertSame(['x' => 1.0, 'y' => 0.0], $timeline['direction']);
        $this->assertSame(['x' => 1.0, 'y' => 0.0], $timeline['lineaxis']);
        $this->assertArrayNotHasKey('orderaxis', $timeline);
        $this->assertFalse($timeline['cyclicorder']);

        // An unknown profile falls back to the free-form default too.
        $fallback = registry::for_profile('doesnotexist')->to_array()['layout'];
        $this->assertFalse($fallback['directed']);
        $this->assertArrayNotHasKey('direction', $fallback);
        $this->assertArrayNotHasKey('orderaxis', $fallback);
        $this->assertFalse($fallback['cyclicorder']);
    }

    /**
     * The registry exposes the built-in profiles as localised menu options.
     *
     * @return void
     */
    public function test_menu_options_cover_builtin_profiles(): void {
        $this->resetAfterTest();

        $known = registry::known_profiles();
        foreach (registry::BUILTIN_PROFILES as $profile) {
            $this->assertContains($profile, $known);
        }

        $options = registry::menu_options();
        foreach (registry::BUILTIN_PROFILES as $profile) {
            $this->assertArrayHasKey($profile, $options);
            $this->assertNotSame('', (string) $options[$profile]);
        }
    }
}
