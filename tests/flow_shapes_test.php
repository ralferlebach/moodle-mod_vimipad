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

use mod_vimipad\api\value;
use mod_vimipad\local\form\base;
use mod_vimipad\local\form\registry;
use mod_vimipad\local\style\node_style;
use mod_vimipad\profile\profiles;

/**
 * Tests for the flowchart-specific node shapes (issue #14).
 *
 * A flowchart carries its meaning in its symbols, so the flow profile offers
 * process, start/end, decision and input/output rather than generic boxes. These
 * tests pin the two halves of that: the vocabulary is wide enough to store the
 * symbols, and narrow enough that no other profile silently gains them.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\style\node_style
 * @covers     \mod_vimipad\api\value
 */
final class flow_shapes_test extends \advanced_testcase {
    /** The shapes a flowchart needs. */
    private const FLOW_SHAPES = ['rect', 'terminator', 'diamond', 'parallelogram'];

    /**
     * The metadata validator accepts every flowchart symbol.
     *
     * @return void
     */
    public function test_node_style_accepts_flow_shapes(): void {
        $this->resetAfterTest();

        foreach (self::FLOW_SHAPES as $shape) {
            $json = json_encode(['shape' => $shape]);
            node_style::validate_metadata($json);
            $this->assertTrue(true, "node_style must accept the '{$shape}' shape.");
        }
    }

    /**
     * An unknown shape is still rejected.
     *
     * @return void
     */
    public function test_node_style_still_rejects_unknown_shapes(): void {
        $this->resetAfterTest();

        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata(json_encode(['shape' => 'star']));
    }

    /**
     * The flow profile offers the flowchart symbols and defaults to process.
     *
     * @return void
     */
    public function test_flow_profile_offers_flowchart_symbols(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $flow = registry::for_profile('flow');
        $this->assertSame(self::FLOW_SHAPES, $flow->get_allowed_shapes());
        $this->assertSame('rect', $flow->get_default_shape());
    }

    /**
     * No other profile gains the flowchart symbols.
     *
     * The vocabulary is shared, so the guard that keeps a decision diamond out of
     * a concept map is the profile's own allowed set - worth asserting directly.
     *
     * @return void
     */
    public function test_other_profiles_do_not_gain_flow_shapes(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        foreach (['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap'] as $profile) {
            $allowed = registry::for_profile($profile)->get_allowed_shapes();
            $this->assertSame(
                base::SHAPES,
                $allowed,
                "Profile {$profile} must keep the generic shape set."
            );
            foreach (['terminator', 'diamond', 'parallelogram'] as $flowshape) {
                $this->assertFalse(
                    profiles::is_shape_allowed($profile, $flowshape),
                    "Profile {$profile} must not offer the '{$flowshape}' symbol."
                );
            }
        }
    }

    /**
     * The public map contract accepts a flow map using every symbol.
     *
     * @return void
     */
    public function test_value_contract_accepts_a_flow_map(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $nodes = [];
        foreach (self::FLOW_SHAPES as $i => $shape) {
            $nodes[] = [
                'stableid' => 'node_' . str_pad((string) $i, 12, 'a'),
                'label' => ucfirst($shape),
                'metadatajson' => json_encode(['shape' => $shape]),
            ];
        }
        $json = json_encode(['profile' => 'flow', 'nodes' => $nodes, 'relations' => []]);

        $this->assertSame([], value::validate($json), 'A flow map using its own symbols must validate.');
    }

    /**
     * A shape the profile does not offer is reported, in both directions.
     *
     * @return void
     */
    public function test_value_contract_enforces_the_profile_subset(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $map = static function (string $profile, string $shape): string {
            return json_encode([
                'profile' => $profile,
                'nodes' => [[
                    'stableid' => 'node_aaaaaaaaaaaa',
                    'label' => 'x',
                    'metadatajson' => json_encode(['shape' => $shape]),
                ]],
                'relations' => [],
            ]);
        };

        // An ellipse is generic, but a flowchart does not use one.
        $this->assertContains('shapenotallowedbyprofile', value::validate($map('flow', 'ellipse')));
        // And a decision diamond has no place in a concept map.
        $this->assertContains('shapenotallowedbyprofile', value::validate($map('conceptmap', 'diamond')));
    }

    /**
     * Flow shapes survive an export/import round trip unchanged.
     *
     * @return void
     */
    public function test_flow_shapes_round_trip(): void {
        $this->resetAfterTest();

        $nodes = [];
        foreach (self::FLOW_SHAPES as $i => $shape) {
            $nodes[] = [
                'stableid' => 'node_' . str_pad((string) $i, 12, 'a'),
                'label' => ucfirst($shape),
                'metadatajson' => json_encode(['shape' => $shape]),
            ];
        }
        $envelope = [
            'generator' => 'mod_vimipad',
            'formatversion' => \mod_vimipad\local\service\export_service::FORMAT_VERSION,
            'data' => ['profile' => 'flow', 'nodes' => $nodes, 'relations' => []],
        ];

        $decoded = json_decode(json_encode($envelope), true);

        $roundtripped = [];
        foreach ($decoded['data']['nodes'] as $node) {
            $roundtripped[] = json_decode($node['metadatajson'], true)['shape'];
        }
        $this->assertSame(
            self::FLOW_SHAPES,
            $roundtripped,
            'Exporting and re-reading a flow map must not convert or drop its symbols.'
        );
    }

    /**
     * Maps written before the flowchart symbols existed still validate.
     *
     * @return void
     */
    public function test_existing_generic_maps_remain_compatible(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        foreach (['roundrect', 'rect', 'ellipse'] as $shape) {
            $json = json_encode([
                'profile' => 'conceptmap',
                'nodes' => [[
                    'stableid' => 'node_aaaaaaaaaaaa',
                    'label' => 'Legacy',
                    'metadatajson' => json_encode(['shape' => $shape]),
                ]],
                'relations' => [],
            ]);
            $this->assertSame([], value::validate($json), "A legacy '{$shape}' map must still validate.");
        }
    }
}
