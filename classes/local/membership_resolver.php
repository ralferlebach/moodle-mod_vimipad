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

namespace mod_vimipad\local;

/**
 * Derives container membership spatially from geometry and layout.
 *
 * Membership truth is spatial: a node belongs to a container when its centre
 * lies inside the container's box, mirroring the editor's rule
 * (container_geometry.ts::centerInBox, edges inclusive). The derivation runs at
 * the materialization points (snapshot creation and export), so the assessment
 * and export always see exactly what the canvas shows. The vimipad_membership
 * table (written by the import round-trip and the membership operations) is a
 * compatibility store and is not consulted for truth.
 *
 * Nodes without a stored layout position have no location and therefore belong
 * to no container. Only nodes participate in derived membership; visual
 * container nesting carries no semantic membership.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class membership_resolver {
    /**
     * Derive the membership list for a normalized workspace state.
     *
     * @param array $containers Normalized containers: arrays with at least
     *     'stableid' and 'geometryjson'.
     * @param array $nodes Normalized nodes: arrays with at least 'stableid'.
     * @param array|null $layout The decoded layout blob: either the versioned
     *     envelope {v, pos, size} or the legacy bare position map.
     * @return array Membership arrays (containerstableid, itemtype, itemstableid,
     *     role, sortorder), ordered by container then item stable id.
     */
    public static function derive(array $containers, array $nodes, ?array $layout): array {
        $positions = self::positions($layout);
        if (empty($positions) || empty($containers)) {
            return [];
        }

        $memberships = [];
        foreach ($containers as $container) {
            $stableid = (string) ($container['stableid'] ?? '');
            $box = self::box($container['geometryjson'] ?? null);
            if ($stableid === '' || $box === null) {
                continue;
            }
            foreach ($nodes as $node) {
                $nodeid = (string) ($node['stableid'] ?? '');
                if ($nodeid === '' || !isset($positions[$nodeid])) {
                    continue;
                }
                [$x, $y] = $positions[$nodeid];
                if (
                    $x >= $box['x'] && $x <= $box['x'] + $box['w']
                        && $y >= $box['y'] && $y <= $box['y'] + $box['h']
                ) {
                    $memberships[] = [
                        'containerstableid' => $stableid,
                        'itemtype' => 'node',
                        'itemstableid' => $nodeid,
                        'role' => null,
                        'sortorder' => 0,
                    ];
                }
            }
        }

        usort($memberships, static function (array $a, array $b): int {
            return [$a['containerstableid'], $a['itemstableid']] <=> [$b['containerstableid'], $b['itemstableid']];
        });
        return $memberships;
    }

    /**
     * Extract finite node centre positions from a decoded layout blob.
     *
     * Accepts the versioned envelope ({v, pos, size}) and the legacy bare
     * position map; non-finite or malformed entries are skipped.
     *
     * @param array|null $layout The decoded layout blob.
     * @return array Map of node stable id to [x, y].
     */
    private static function positions(?array $layout): array {
        if (!is_array($layout)) {
            return [];
        }
        $posmap = (isset($layout['v']) && isset($layout['pos']) && is_array($layout['pos']))
            ? $layout['pos']
            : $layout;

        $positions = [];
        foreach ($posmap as $stableid => $point) {
            if (!is_array($point) && !is_object($point)) {
                continue;
            }
            $point = (array) $point;
            if (!isset($point['x']) || !isset($point['y'])) {
                continue;
            }
            $x = $point['x'];
            $y = $point['y'];
            if (!is_numeric($x) || !is_numeric($y) || !is_finite((float) $x) || !is_finite((float) $y)) {
                continue;
            }
            $positions[(string) $stableid] = [(float) $x, (float) $y];
        }
        return $positions;
    }

    /**
     * Parse a container geometry JSON blob into a finite box.
     *
     * @param string|null $geometryjson The geometry JSON ({x, y, w, h}).
     * @return array|null The box as ['x','y','w','h'] floats, or null if malformed.
     */
    private static function box(?string $geometryjson): ?array {
        if ($geometryjson === null || $geometryjson === '') {
            return null;
        }
        $raw = json_decode($geometryjson, true);
        if (!is_array($raw)) {
            return null;
        }
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (!isset($raw[$key]) || !is_numeric($raw[$key]) || !is_finite((float) $raw[$key])) {
                return null;
            }
        }
        return ['x' => (float) $raw['x'], 'y' => (float) $raw['y'], 'w' => (float) $raw['w'], 'h' => (float) $raw['h']];
    }
}
