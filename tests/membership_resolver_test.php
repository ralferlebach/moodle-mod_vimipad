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

use mod_vimipad\local\membership_resolver;

/**
 * Tests for the spatial membership derivation.
 *
 * The resolver mirrors the editor's centre-in-box rule (edges inclusive) on
 * the normalized workspace state, for both layout blob formats.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\membership_resolver
 */
final class membership_resolver_test extends \basic_testcase {
    /**
     * Build a container array with a box geometry.
     *
     * @param string $stableid The container stable id.
     * @param float $x Box left.
     * @param float $y Box top.
     * @param float $w Box width.
     * @param float $h Box height.
     * @return array The normalized container.
     */
    private function container(string $stableid, float $x, float $y, float $w, float $h): array {
        return [
            'stableid' => $stableid,
            'type' => 'group',
            'label' => null,
            'geometryjson' => json_encode(['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h]),
            'metadatajson' => null,
        ];
    }

    /**
     * Nodes inside are members, nodes outside are not, edges are inclusive.
     *
     * @return void
     */
    public function test_centre_in_box_rule(): void {
        $containers = [$this->container('container_aaaa', 100, 100, 200, 100)];
        $nodes = [
            ['stableid' => 'node_inside'],
            ['stableid' => 'node_outside'],
            ['stableid' => 'node_leftedge'],
            ['stableid' => 'node_bottomedge'],
        ];
        $layout = [
            'v' => 1,
            'pos' => [
                'node_inside' => ['x' => 150, 'y' => 150],
                'node_outside' => ['x' => 400, 'y' => 150],
                'node_leftedge' => ['x' => 100, 'y' => 150],
                'node_bottomedge' => ['x' => 150, 'y' => 200],
            ],
            'size' => [],
        ];

        $memberships = membership_resolver::derive($containers, $nodes, $layout);

        $memberids = array_column($memberships, 'itemstableid');
        $this->assertSame(['node_bottomedge', 'node_inside', 'node_leftedge'], $memberids);
        $this->assertSame('container_aaaa', $memberships[0]['containerstableid']);
        $this->assertSame('node', $memberships[0]['itemtype']);
    }

    /**
     * The legacy bare position map (no envelope) is understood.
     *
     * @return void
     */
    public function test_legacy_bare_position_map(): void {
        $containers = [$this->container('container_aaaa', 0, 0, 100, 100)];
        $nodes = [['stableid' => 'node_a'], ['stableid' => 'node_b']];
        $layout = [
            'node_a' => ['x' => 50, 'y' => 50],
            'node_b' => ['x' => 500, 'y' => 500],
        ];

        $memberships = membership_resolver::derive($containers, $nodes, $layout);

        $this->assertCount(1, $memberships);
        $this->assertSame('node_a', $memberships[0]['itemstableid']);
    }

    /**
     * Overlapping containers each claim the node (one membership per container).
     *
     * @return void
     */
    public function test_overlapping_containers(): void {
        $containers = [
            $this->container('container_bbbb', 0, 0, 100, 100),
            $this->container('container_aaaa', 50, 50, 100, 100),
        ];
        $nodes = [['stableid' => 'node_a']];
        $layout = ['node_a' => ['x' => 75, 'y' => 75]];

        $memberships = membership_resolver::derive($containers, $nodes, $layout);

        $this->assertCount(2, $memberships);
        // Deterministic ordering: by container stable id, then item stable id.
        $this->assertSame('container_aaaa', $memberships[0]['containerstableid']);
        $this->assertSame('container_bbbb', $memberships[1]['containerstableid']);
    }

    /**
     * Missing layout, unknown positions and malformed geometry yield no members.
     *
     * @return void
     */
    public function test_degenerate_inputs(): void {
        $containers = [$this->container('container_aaaa', 0, 0, 100, 100)];
        $nodes = [['stableid' => 'node_a']];

        // No layout at all.
        $this->assertSame([], membership_resolver::derive($containers, $nodes, null));

        // Node without a stored position.
        $this->assertSame([], membership_resolver::derive($containers, $nodes, ['node_other' => ['x' => 1, 'y' => 1]]));

        // Non-finite position is skipped.
        $this->assertSame([], membership_resolver::derive($containers, $nodes, ['node_a' => ['x' => NAN, 'y' => 1]]));

        // Malformed geometry is skipped.
        $broken = [['stableid' => 'container_x', 'geometryjson' => '{"x":1,"y":2}']];
        $this->assertSame([], membership_resolver::derive($broken, $nodes, ['node_a' => ['x' => 1, 'y' => 1]]));

        // Geometry that is not JSON is skipped.
        $notjson = [['stableid' => 'container_x', 'geometryjson' => 'nope']];
        $this->assertSame([], membership_resolver::derive($notjson, $nodes, ['node_a' => ['x' => 1, 'y' => 1]]));
    }
}
