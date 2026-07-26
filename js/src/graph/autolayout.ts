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
 * Deterministic automatic layout.
 *
 * Produces a stable position for every node so the canvas is usable before any
 * manual arrangement. Stored (manual) positions always take precedence; nodes
 * without a stored position are placed on a circle in a deterministic order.
 *
 * @module     mod_vimipad/graph/autolayout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, Point, VimiNode} from '../types';

export const CANVAS_WIDTH = 800;
export const CANVAS_HEIGHT = 520;

/**
 * Compute positions for all nodes, honouring any stored positions.
 *
 * @param nodes The nodes to place.
 * @param stored Previously stored positions keyed by stable id.
 * @returns A complete position map covering every node.
 */
export function computeLayout(nodes: VimiNode[], stored: LayoutMap): LayoutMap {
    const result: LayoutMap = {};
    const unplaced: VimiNode[] = [];

    for (const node of nodes) {
        if (stored[node.stableid]) {
            result[node.stableid] = stored[node.stableid];
        } else {
            unplaced.push(node);
        }
    }

    const centreX = CANVAS_WIDTH / 2;
    const centreY = CANVAS_HEIGHT / 2;
    const radius = Math.min(CANVAS_WIDTH, CANVAS_HEIGHT) / 2 - 90;
    const count = Math.max(unplaced.length, 1);

    unplaced.forEach((node, index) => {
        const angle = (2 * Math.PI * index) / count - Math.PI / 2;
        result[node.stableid] = {
            x: Math.round(centreX + radius * Math.cos(angle)),
            y: Math.round(centreY + radius * Math.sin(angle)),
        };
    });

    return result;
}

/**
 * Clamp a point to the canvas bounds, leaving room for the node box.
 *
 * @param point The candidate point.
 * @returns The clamped point.
 */
export function clampToCanvas(point: Point): Point {
    return {
        x: Math.max(60, Math.min(CANVAS_WIDTH - 60, point.x)),
        y: Math.max(24, Math.min(CANVAS_HEIGHT - 24, point.y)),
    };
}
