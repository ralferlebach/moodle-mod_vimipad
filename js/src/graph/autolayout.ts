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

import {LayoutMap, Point, VimiNode, VimiRelation} from '../types';

export const CANVAS_WIDTH = 2400;
export const CANVAS_HEIGHT = 1600;

/** Vertical distance between tree levels. */
const TREE_LEVEL_GAP = 110;

/** Horizontal distance between adjacent leaf columns in a tree. */
const TREE_COLUMN_GAP = 150;

/** Top margin for the root row of a tree. */
const TREE_TOP = 70;

/**
 * Compute positions for all nodes, honouring any stored positions.
 *
 * For the tree profile a tidy top-down hierarchical layout is used (parents
 * centred over their children); every other profile uses the deterministic
 * circle layout. Stored (manual) positions always take precedence.
 *
 * @param nodes The nodes to place.
 * @param stored Previously stored positions keyed by stable id.
 * @param relations The relations, used to derive the tree hierarchy.
 * @param profile The active diagram profile.
 * @returns A complete position map covering every node.
 */
export function computeLayout(
    nodes: VimiNode[],
    stored: LayoutMap,
    relations: VimiRelation[] = [],
    profile: string = ''
): LayoutMap {
    if (profile === 'tree') {
        const auto = treeLayout(nodes, relations);
        const result: LayoutMap = {};
        for (const node of nodes) {
            result[node.stableid] = stored[node.stableid] ?? auto[node.stableid];
        }
        return result;
    }

    return circleLayout(nodes, stored);
}

/**
 * Deterministic circle layout: stored nodes keep their position, the rest are
 * placed evenly on a circle.
 *
 * @param nodes The nodes to place.
 * @param stored Previously stored positions keyed by stable id.
 * @returns A complete position map.
 */
function circleLayout(nodes: VimiNode[], stored: LayoutMap): LayoutMap {
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
    const count = Math.max(unplaced.length, 1);
    // Space nodes ~110px apart along the ring, but keep the layout compact within
    // the (now large) canvas rather than spreading to its edges.
    const maxRadius = Math.min(CANVAS_WIDTH, CANVAS_HEIGHT) / 2 - 90;
    const radius = Math.min(maxRadius, Math.max(170, (count * 110) / (2 * Math.PI)));

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
 * Tidy top-down tree layout.
 *
 * Derives a forest from the relations (each relation is parent → child; the
 * first parent of a node wins, so multi-parent or cyclic graphs still lay out).
 * Leaves are assigned successive columns left to right and each parent is
 * centred over its children; depth sets the row. Nodes not reachable from any
 * root are placed in a trailing row so nothing is lost.
 *
 * @param nodes The nodes to place.
 * @param relations The relations providing the hierarchy.
 * @returns A complete position map.
 */
function treeLayout(nodes: VimiNode[], relations: VimiRelation[]): LayoutMap {
    const ids = new Set(nodes.map(n => n.stableid));
    const children = new Map<string, string[]>();
    const hasParent = new Set<string>();

    for (const rel of relations) {
        if (!ids.has(rel.sourceid) || !ids.has(rel.targetid) || rel.sourceid === rel.targetid) {
            continue;
        }
        if (hasParent.has(rel.targetid)) {
            continue;
        }
        const list = children.get(rel.sourceid) ?? [];
        list.push(rel.targetid);
        children.set(rel.sourceid, list);
        hasParent.add(rel.targetid);
    }

    const roots = nodes.filter(n => !hasParent.has(n.stableid));
    const rootsToUse = roots.length > 0 ? roots : nodes;

    const column: Record<string, number> = {};
    const depth: Record<string, number> = {};
    const visited = new Set<string>();
    let nextLeaf = 0;

    const place = (id: string, level: number): void => {
        if (visited.has(id)) {
            return;
        }
        visited.add(id);
        depth[id] = level;
        const kids = (children.get(id) ?? []).filter(k => !visited.has(k));
        if (kids.length === 0) {
            column[id] = nextLeaf;
            nextLeaf += 1;
            return;
        }
        for (const kid of kids) {
            place(kid, level + 1);
        }
        const cols = kids.map(k => column[k]);
        column[id] = cols.reduce((a, b) => a + b, 0) / cols.length;
    };

    for (const root of rootsToUse) {
        place(root.stableid, 0);
    }

    // Any node not reached (disconnected or cycle remnant) gets a trailing slot.
    let maxDepth = 0;
    for (const d of Object.values(depth)) {
        maxDepth = Math.max(maxDepth, d);
    }
    for (const node of nodes) {
        if (!visited.has(node.stableid)) {
            depth[node.stableid] = maxDepth + 1;
            column[node.stableid] = nextLeaf;
            nextLeaf += 1;
        }
    }

    const columns = Math.max(nextLeaf, 1);
    const totalWidth = (columns - 1) * TREE_COLUMN_GAP;
    const startX = Math.round(CANVAS_WIDTH / 2 - totalWidth / 2);

    const result: LayoutMap = {};
    for (const node of nodes) {
        result[node.stableid] = {
            x: Math.round(startX + (column[node.stableid] ?? 0) * TREE_COLUMN_GAP),
            y: Math.round(TREE_TOP + (depth[node.stableid] ?? 0) * TREE_LEVEL_GAP),
        };
    }
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
