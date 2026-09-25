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

/**
 * Constructs the geometry of an Ishikawa (fishbone) diagram.
 *
 * The generic force refiner can nudge edges toward diagonals, but it has no
 * notion of a spine or of attachment stations, so it cannot produce a diagram a
 * reader recognises as a fishbone. This module places the nodes explicitly from
 * the structure {@link module:mod_vimipad/graph/fishbone_topology} resolves:
 * the effect at the right end of a horizontal spine, main categories on
 * alternating bones attached at ordered stations, and causes further out along
 * their own branch.
 *
 * Deterministic: the same graph always yields the same positions, so pressing
 * Arrange twice does not move anything.
 *
 * @module     mod_vimipad/graph/fishbone_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CANVAS_HEIGHT, CANVAS_WIDTH} from './autolayout';
import {fishboneTopology, FishboneTopology} from './fishbone_topology';
import {LayoutMap, VimiNode, VimiRelation} from '../types';

/** Where the spine sits vertically. */
const SPINE_Y = CANVAS_HEIGHT / 2;

/** Left end of the spine. */
const SPINE_LEFT = 220;

/** Right end of the spine, where the effect sits. */
const SPINE_RIGHT = CANVAS_WIDTH - 260;

/** Horizontal gap kept between the last station and the effect. */
const HEAD_GAP = 200;

/** How far the first bone segment rises from the spine. */
const BONE_RISE = 190;

/** How far a bone leans against the spine direction, per level. */
const BONE_RUN = 150;

/**
 * How far a bone leans along the spine per unit of height.
 *
 * A category sits this much to the LEFT of the station it meets, so the bone
 * runs toward the effect. Routing uses the same ratio, otherwise the drawn bone
 * would not match the placed node.
 */
export const BONE_LEAN = BONE_RUN / BONE_RISE;

/** Distance each deeper level adds along the bone. */
const DEPTH_STEP = 210;

/** Distance between siblings at one depth, measured across the bone. */
const SIBLING_GAP = 210;

/** Row spacing used to park nodes that belong to no branch. */
const ORPHAN_SPACING = 150;

/**
 * Round to whole pixels so repeated runs compare equal.
 *
 * @param value The raw coordinate.
 * @returns The rounded coordinate.
 */
function px(value: number): number {
    return Math.round(value);
}

/**
 * Where a category attaches to the spine.
 *
 * Stations run left to right and stop short of the effect, so the last bone
 * never collides with the head.
 *
 * @param order The category's position along the spine.
 * @param count How many categories there are.
 * @returns The x coordinate of the station.
 */
export function stationX(order: number, count: number): number {
    const right = SPINE_RIGHT - HEAD_GAP;
    if (count <= 1) {
        return px((SPINE_LEFT + right) / 2);
    }
    const step = (right - SPINE_LEFT) / count;
    return px(SPINE_LEFT + step * (order + 0.5));
}

/**
 * Place every node of a fishbone map.
 *
 * @param nodes The nodes of the map.
 * @param relations The relations of the map.
 * @param current The existing positions, used to preserve authored ordering.
 * @returns A position for each node.
 */
export function fishboneLayout(
    nodes: VimiNode[],
    relations: VimiRelation[],
    current?: LayoutMap
): LayoutMap {
    const topology = fishboneTopology(nodes, relations, current);
    return layoutFromTopology(topology, nodes);
}

/**
 * Place the nodes for an already resolved topology.
 *
 * Kept separate so the renderer can reuse one resolved topology for both the
 * positions and the routed spine without resolving it twice.
 *
 * @param topology The resolved Ishikawa structure.
 * @param nodes The nodes of the map.
 * @returns A position for each node.
 */
export function layoutFromTopology(topology: FishboneTopology, nodes: VimiNode[]): LayoutMap {
    const layout: LayoutMap = {};
    const count = topology.categories.length;

    if (topology.head !== '') {
        layout[topology.head] = {x: px(SPINE_RIGHT), y: px(SPINE_Y)};
    }

    for (const category of topology.categories) {
        const sign = category.side === 'top' ? -1 : 1;
        const station = stationX(category.order, count);

        // The bone leaves its station at a constant angle, leaning away from the
        // effect, which is what gives a fishbone its slanted ribs.
        layout[category.id] = {
            x: px(station - BONE_RUN),
            y: px(SPINE_Y + sign * BONE_RISE),
        };

        // Causes continue outward along the same bone. Depth moves a cause
        // further along the rib; siblings at one depth step sideways across it.
        // Using the bone's own axes keeps the two apart from each other, so a
        // sub-cause can never drift onto a sibling further up the branch.
        const boneLen = Math.hypot(BONE_RUN, BONE_RISE);
        const alongX = -BONE_RUN / boneLen;
        const alongY = (sign * BONE_RISE) / boneLen;
        // Across the bone, pointing away from the effect.
        const acrossX = -alongY * sign;
        const acrossY = alongX * sign;

        const perDepth = new Map<number, number>();
        for (const ancestor of category.ancestors) {
            const index = perDepth.get(ancestor.depth) ?? 0;
            perDepth.set(ancestor.depth, index + 1);

            const along = boneLen + DEPTH_STEP * ancestor.depth;
            const across = SIBLING_GAP * index;
            layout[ancestor.id] = {
                x: px(station + alongX * along + acrossX * across),
                y: px(SPINE_Y + alongY * along + acrossY * across),
            };
        }
    }

    // Anything outside the Ishikawa structure is parked below the diagram in a
    // stable order rather than left wherever it happened to be.
    topology.orphans.forEach((id, i) => {
        layout[id] = {
            x: px(SPINE_LEFT + (i % 6) * ORPHAN_SPACING),
            y: px(CANVAS_HEIGHT - 160 - Math.floor(i / 6) * ORPHAN_SPACING),
        };
    });

    // A node the topology never mentioned (for example an empty map) still needs
    // somewhere to be.
    nodes.forEach((node, i) => {
        if (!layout[node.stableid]) {
            layout[node.stableid] = {
                x: px(SPINE_LEFT + (i % 6) * ORPHAN_SPACING),
                y: px(CANVAS_HEIGHT - 160),
            };
        }
    });

    return layout;
}

/**
 * The y coordinate of the spine, for the renderer.
 *
 * @returns The spine's vertical position.
 */
export function spineY(): number {
    return SPINE_Y;
}

/**
 * The horizontal extent of the spine, for the renderer.
 *
 * @returns The left and right ends of the spine.
 */
export function spineExtent(): {left: number; right: number} {
    return {left: SPINE_LEFT, right: SPINE_RIGHT};
}
