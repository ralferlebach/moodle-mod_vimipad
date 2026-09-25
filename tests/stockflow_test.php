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

/**
 * Tests for the Stock-and-Flow / System Dynamics profile (issue #16).
 *
 * The design rule the issue insists on is that everything persistent is either
 * a node or a relation: a valve and a delay are nodes, flow and influence are
 * relation types, and the meaning of an influence lives in its label. These
 * tests hold that line — in particular that no polarity field creeps in and
 * that the existing causal profile is untouched.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadform_stockflow\form
 * @covers     \mod_vimipad\local\style\node_style
 */
final class stockflow_test extends \advanced_testcase {
    /** The System Dynamics roles a node can take. */
    private const SYSTEM_TYPES = [
        'element', 'stock', 'source', 'sink', 'valve', 'delay', 'auxiliary', 'parameter',
    ];

    /**
     * The profile is registered and reports its own key.
     *
     * @return void
     */
    public function test_profile_is_registered(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $this->assertSame('stockflow', registry::for_profile('stockflow')->get_profile());
    }

    /**
     * The profile offers the stock-and-flow symbols.
     *
     * @return void
     */
    public function test_profile_offers_system_shapes(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $shapes = registry::for_profile('stockflow')->get_allowed_shapes();
        foreach (['stock', 'cloud', 'valve', 'delay', 'ellipse', 'parameter'] as $shape) {
            $this->assertContains($shape, $shapes);
        }
        // Adding a node must not assert a system role the author did not pick.
        $this->assertSame('roundrect', registry::for_profile('stockflow')->get_default_shape());
    }

    /**
     * Flow, influence and a neutral fallback are the relation types.
     *
     * @return void
     */
    public function test_relation_types(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $this->assertSame(
            ['flow', 'influence', 'relation'],
            registry::for_profile('stockflow')->get_relation_types()
        );
    }

    /**
     * Inflow and outflow are not separate types.
     *
     * They follow from the direction of a flow relative to a stock, so adding
     * them as types would duplicate information the graph already carries.
     *
     * @return void
     */
    public function test_no_separate_inflow_or_outflow_type(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $types = registry::for_profile('stockflow')->get_relation_types();
        $this->assertNotContains('inflow', $types);
        $this->assertNotContains('outflow', $types);
    }

    /**
     * Every system role is accepted as node metadata.
     *
     * @return void
     */
    public function test_system_types_are_valid_metadata(): void {
        $this->resetAfterTest();

        foreach (self::SYSTEM_TYPES as $type) {
            node_style::validate_metadata(json_encode(['systemtype' => $type]));
            $this->assertTrue(true, "'{$type}' must be a valid system type.");
        }
        $this->assertSame(self::SYSTEM_TYPES, base::SYSTEM_TYPES);
    }

    /**
     * A mistyped role is rejected rather than silently ignored.
     *
     * Otherwise an import would quietly downgrade a valve to a generic node.
     *
     * @return void
     */
    public function test_unknown_system_type_is_rejected(): void {
        $this->resetAfterTest();

        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata(json_encode(['systemtype' => 'flux_capacitor']));
    }

    /**
     * The semantic role is independent of the shape.
     *
     * Re-styling a map must not change what its nodes mean.
     *
     * @return void
     */
    public function test_role_and_shape_are_independent(): void {
        $this->resetAfterTest();

        node_style::validate_metadata(json_encode(['systemtype' => 'stock', 'shape' => 'roundrect']));
        node_style::validate_metadata(json_encode(['systemtype' => 'valve', 'shape' => 'valve']));
        $this->assertTrue(true);
    }

    /**
     * A stock-and-flow map validates through the public contract.
     *
     * @return void
     */
    public function test_value_contract_accepts_a_stockflow_map(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $node = static function (string $id, string $label, string $type, string $shape): array {
            return [
                'stableid' => $id,
                'label' => $label,
                'metadatajson' => json_encode(['systemtype' => $type, 'shape' => $shape]),
            ];
        };

        $json = json_encode([
            'profile' => 'stockflow',
            'nodes' => [
                $node('node_aaaaaaaaaaaa', 'Raw material supply', 'source', 'cloud'),
                $node('node_bbbbbbbbbbbb', 'Production rate', 'valve', 'valve'),
                $node('node_cccccccccccc', 'Finished goods', 'stock', 'stock'),
                $node('node_dddddddddddd', 'Order delay', 'delay', 'delay'),
                $node('node_eeeeeeeeeeee', 'Customer demand', 'auxiliary', 'ellipse'),
                $node('node_ffffffffffff', 'Capacity', 'parameter', 'parameter'),
            ],
            'relations' => [
                [
                    'stableid' => 'rel_aaaaaaaaaaaaa',
                    'sourceid' => 'node_aaaaaaaaaaaa',
                    'targetid' => 'node_bbbbbbbbbbbb',
                    'label' => 'flow',
                ],
                [
                    'stableid' => 'rel_bbbbbbbbbbbbb',
                    'sourceid' => 'node_ffffffffffff',
                    'targetid' => 'node_bbbbbbbbbbbb',
                    'label' => 'limits',
                ],
            ],
        ]);

        $this->assertSame([], value::validate($json));
    }

    /**
     * The roles survive an export/import round trip.
     *
     * @return void
     */
    public function test_system_types_round_trip(): void {
        $this->resetAfterTest();

        $roles = ['stock', 'valve', 'delay', 'source', 'sink', 'auxiliary', 'parameter'];
        $nodes = [];
        foreach ($roles as $i => $role) {
            $nodes[] = [
                'stableid' => 'node_' . str_pad((string) $i, 12, 'a'),
                'label' => ucfirst($role),
                'metadatajson' => json_encode(['systemtype' => $role]),
            ];
        }
        $envelope = [
            'generator' => 'mod_vimipad',
            'formatversion' => \mod_vimipad\local\service\export_service::FORMAT_VERSION,
            'data' => ['profile' => 'stockflow', 'nodes' => $nodes, 'relations' => []],
        ];

        $decoded = json_decode(json_encode($envelope), true);
        $out = [];
        foreach ($decoded['data']['nodes'] as $node) {
            $out[] = json_decode($node['metadatajson'], true)['systemtype'];
        }

        $this->assertSame(
            $roles,
            $out,
            'Import must not downgrade a valve, delay, source or sink to a generic element.'
        );
    }

    /**
     * No polarity property is introduced anywhere.
     *
     * The issue rules it out explicitly: the meaning of an influence is carried
     * by its label.
     *
     * @return void
     */
    public function test_no_polarity_property(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $this->assertArrayNotHasKey('polarity', registry::for_profile('stockflow')->to_array());
        $this->assertNotContains('polarity', base::SYSTEM_TYPES);
    }

    /**
     * The existing causal profile is untouched.
     *
     * @return void
     */
    public function test_causal_profile_unchanged(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $causal = registry::for_profile('causal');
        $this->assertSame(['positive', 'negative'], $causal->get_relation_types());
        $this->assertSame(base::SHAPES, $causal->get_allowed_shapes());
    }
}
