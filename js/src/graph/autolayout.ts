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
import {centerInBox, ContainerBox} from '../canvas/container_geometry';

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

// ---------------------------------------------------------------------------
// Profile-specific "arrange" layout (the explicit "re-arrange" action).
//
// Unlike computeLayout (the cheap live path that only places nodes without a
// stored position), arrangeLayout recomputes a full, high-quality layout for
// every node. It follows four rules from the product spec, adapted per profile:
//   - central elements (high degree) sit centrally;
//   - nodes spread evenly rather than clumping;
//   - related nodes end up roughly equidistant (similar edge lengths);
//   - the profile's base orientation is respected (tree/concept: top-down;
//     mindmap/bubble: radial; semantic network: free force-directed).
// Everything here is deterministic: positions are seeded from a fixed order and
// the iteration counts are fixed, so the same map always arranges identically.
// ---------------------------------------------------------------------------

/** An undirected adjacency map and per-node degree, derived from relations. */
interface Graph {
    adj: Map<string, Set<string>>;
    degree: Map<string, number>;
}

/**
 * Build an undirected adjacency map (and degrees) over the given node ids.
 *
 * @param ids The set of node ids to include.
 * @param relations The relations to derive edges from.
 * @returns The graph.
 */
function buildGraph(ids: Set<string>, relations: VimiRelation[]): Graph {
    const adj = new Map<string, Set<string>>();
    for (const id of ids) {
        adj.set(id, new Set());
    }
    for (const rel of relations) {
        if (!ids.has(rel.sourceid) || !ids.has(rel.targetid) || rel.sourceid === rel.targetid) {
            continue;
        }
        adj.get(rel.sourceid)!.add(rel.targetid);
        adj.get(rel.targetid)!.add(rel.sourceid);
    }
    const degree = new Map<string, number>();
    for (const [id, set] of adj) {
        degree.set(id, set.size);
    }
    return {adj, degree};
}

/**
 * The most central node id: highest degree, ties broken by the node order
 * (first wins) so the choice is deterministic.
 *
 * @param nodes The nodes, in their canonical order.
 * @param degree The degree map.
 * @returns The central node id, or null when there are no nodes.
 */
function mostCentral(nodes: VimiNode[], degree: Map<string, number>): string | null {
    let best: string | null = null;
    let bestDeg = -1;
    for (const n of nodes) {
        const d = degree.get(n.stableid) ?? 0;
        if (d > bestDeg) {
            bestDeg = d;
            best = n.stableid;
        }
    }
    return best;
}

/**
 * Radial layout: the most central node in the middle, the rest on concentric
 * rings by graph distance, spread evenly by angle. Used for mindmap and bubble
 * map, whose base orientation is a central hub with branches radiating out.
 *
 * Children are ordered near their parent's angle so branches stay together and
 * edges stay short and similar in length.
 *
 * @param nodes The nodes to place.
 * @param relations The relations providing the hierarchy.
 * @returns A complete position map.
 */
function radialArrange(nodes: VimiNode[], relations: VimiRelation[]): LayoutMap {
    const ids = new Set(nodes.map(n => n.stableid));
    const {adj, degree} = buildGraph(ids, relations);
    const centre = mostCentral(nodes, degree);
    const cx = CANVAS_WIDTH / 2;
    const cy = CANVAS_HEIGHT / 2;
    const result: LayoutMap = {};
    if (centre === null) {
        return result;
    }

    // BFS from the centre assigns each node a ring (graph distance). The BFS
    // order (stable, seeded from node order) determines angular order so the
    // result is deterministic and branches stay contiguous.
    const ring = new Map<string, number>();
    ring.set(centre, 0);
    const order: string[] = [centre];
    let head = 0;
    const neighboursOf = (id: string): string[] =>
        nodes.filter(n => adj.get(id)!.has(n.stableid)).map(n => n.stableid);
    while (head < order.length) {
        const id = order[head++];
        for (const nb of neighboursOf(id)) {
            if (!ring.has(nb)) {
                ring.set(nb, (ring.get(id) ?? 0) + 1);
                order.push(nb);
            }
        }
    }
    // Unreached nodes (disconnected) go on a trailing ring so nothing is lost.
    let maxRing = 0;
    for (const r of ring.values()) {
        maxRing = Math.max(maxRing, r);
    }
    for (const n of nodes) {
        if (!ring.has(n.stableid)) {
            ring.set(n.stableid, maxRing + 1);
        }
    }

    // Group by ring, preserving BFS/node order within each ring.
    const byRing = new Map<number, string[]>();
    for (const n of nodes) {
        const r = ring.get(n.stableid) ?? 0;
        const list = byRing.get(r) ?? [];
        list.push(n.stableid);
        byRing.set(r, list);
    }

    const RING_GAP = 220;
    result[centre] = {x: Math.round(cx), y: Math.round(cy)};
    for (const [r, members] of byRing) {
        if (r === 0) {
            continue;
        }
        const radius = r * RING_GAP;
        const n = members.length;
        members.forEach((id, i) => {
            const angle = (2 * Math.PI * i) / n - Math.PI / 2;
            result[id] = {
                x: Math.round(cx + radius * Math.cos(angle)),
                y: Math.round(cy + radius * Math.sin(angle)),
            };
        });
    }
    return result;
}

/**
 * Deterministic force-directed layout (Fruchterman–Reingold). High-degree nodes
 * are pulled to the middle by their many edges, so the most connected concepts
 * settle centrally; repulsion spreads nodes evenly and the spring model makes
 * edges roughly equal in length. Used for the semantic-network profile.
 *
 * Positions are seeded on a circle in node order (no randomness) and the
 * iteration count is fixed, so the layout is fully reproducible.
 *
 * @param nodes The nodes to place.
 * @param relations The relations providing the edges.
 * @returns A complete position map.
 */
function forceArrange(nodes: VimiNode[], relations: VimiRelation[]): LayoutMap {
    const ids = new Set(nodes.map(n => n.stableid));
    const {adj} = buildGraph(ids, relations);
    const n = nodes.length;
    const cx = CANVAS_WIDTH / 2;
    const cy = CANVAS_HEIGHT / 2;
    const result: LayoutMap = {};
    if (n === 0) {
        return result;
    }
    if (n === 1) {
        result[nodes[0].stableid] = {x: Math.round(cx), y: Math.round(cy)};
        return result;
    }

    // Ideal edge length k, sized so n nodes use a comfortable central area.
    const area = (CANVAS_WIDTH * 0.6) * (CANVAS_HEIGHT * 0.6);
    const k = Math.sqrt(area / n);

    // Seed deterministically on a circle in node order.
    const pos = new Map<string, Point>();
    const seedR = Math.min(CANVAS_WIDTH, CANVAS_HEIGHT) / 4;
    nodes.forEach((node, i) => {
        const a = (2 * Math.PI * i) / n - Math.PI / 2;
        pos.set(node.stableid, {x: cx + seedR * Math.cos(a), y: cy + seedR * Math.sin(a)});
    });

    const ITERATIONS = 220;
    let temp = k * 2;
    const cool = temp / (ITERATIONS + 1);

    for (let it = 0; it < ITERATIONS; it++) {
        const disp = new Map<string, Point>();
        for (const node of nodes) {
            disp.set(node.stableid, {x: 0, y: 0});
        }
        // Repulsion between every pair (n is bounded for interactive maps).
        for (let i = 0; i < n; i++) {
            for (let j = i + 1; j < n; j++) {
                const a = nodes[i].stableid;
                const b = nodes[j].stableid;
                const pa = pos.get(a)!;
                const pb = pos.get(b)!;
                let dx = pa.x - pb.x;
                let dy = pa.y - pb.y;
                let dist = Math.hypot(dx, dy);
                if (dist < 0.01) {
                    // Deterministic tiny separation for coincident seeds.
                    dx = (i - j) * 0.01;
                    dy = 0.01;
                    dist = Math.hypot(dx, dy);
                }
                const force = (k * k) / dist;
                const fx = (dx / dist) * force;
                const fy = (dy / dist) * force;
                const da = disp.get(a)!;
                const db = disp.get(b)!;
                da.x += fx; da.y += fy;
                db.x -= fx; db.y -= fy;
            }
        }
        // Attraction along edges.
        for (const node of nodes) {
            for (const nb of adj.get(node.stableid)!) {
                if (node.stableid >= nb) {
                    continue; // Each edge once.
                }
                const pa = pos.get(node.stableid)!;
                const pb = pos.get(nb)!;
                const dx = pa.x - pb.x;
                const dy = pa.y - pb.y;
                const dist = Math.hypot(dx, dy) || 0.01;
                const force = (dist * dist) / k;
                const fx = (dx / dist) * force;
                const fy = (dy / dist) * force;
                const da = disp.get(node.stableid)!;
                const db = disp.get(nb)!;
                da.x -= fx; da.y -= fy;
                db.x += fx; db.y += fy;
            }
        }
        // Apply displacement, capped by the cooling temperature.
        for (const node of nodes) {
            const d = disp.get(node.stableid)!;
            const p = pos.get(node.stableid)!;
            const len = Math.hypot(d.x, d.y) || 0.01;
            p.x += (d.x / len) * Math.min(len, temp);
            p.y += (d.y / len) * Math.min(len, temp);
        }
        temp = Math.max(0, temp - cool);
    }

    // Centre the result on the canvas centre.
    let sx = 0;
    let sy = 0;
    for (const p of pos.values()) {
        sx += p.x; sy += p.y;
    }
    const mx = sx / n - cx;
    const my = sy / n - cy;
    for (const node of nodes) {
        const p = pos.get(node.stableid)!;
        result[node.stableid] = clampToCanvas({x: Math.round(p.x - mx), y: Math.round(p.y - my)});
    }
    return result;
}

/**
 * Full profile-specific arrangement (no container awareness).
 *
 *   - tree, conceptmap: tidy top-down hierarchy (parents centred over children);
 *   - mindmap, bubblemap: radial hub-and-spoke around the most connected node;
 *   - semanticnetwork and anything else: deterministic force-directed graph.
 *
 * @param nodes The nodes to place.
 * @param relations The relations.
 * @param profile The active diagram profile.
 * @returns A complete position map for every node.
 */
function arrangePlain(
    nodes: VimiNode[],
    relations: VimiRelation[] = [],
    profile: string = ''
): LayoutMap {
    if (nodes.length === 0) {
        return {};
    }
    switch (profile) {
        case 'tree':
        case 'conceptmap':
            return treeLayout(nodes, relations);
        case 'mindmap':
        case 'bubblemap':
            return radialArrange(nodes, relations);
        case 'semanticnetwork':
        default:
            return forceArrange(nodes, relations);
    }
}

/** A container identified by id together with its geometry box. */
export interface NamedBox {
    id: string;
    box: ContainerBox;
}

/**
 * The overlap rectangle of a set of boxes, or null if they do not all overlap.
 *
 * @param boxes The boxes to intersect.
 * @returns The intersection box, or null when empty.
 */
function intersectionRect(boxes: ContainerBox[]): ContainerBox | null {
    if (boxes.length === 0) {
        return null;
    }
    let left = -Infinity;
    let top = -Infinity;
    let right = Infinity;
    let bottom = Infinity;
    for (const b of boxes) {
        left = Math.max(left, b.x);
        top = Math.max(top, b.y);
        right = Math.min(right, b.x + b.w);
        bottom = Math.min(bottom, b.y + b.h);
    }
    if (right <= left || bottom <= top) {
        return null;
    }
    return {x: left, y: top, w: right - left, h: bottom - top};
}

/**
 * Grid points inside a region that lie inside every "must be inside" box and
 * outside every "must be outside" box, in deterministic row-major order.
 *
 * @param region The rectangle to scan.
 * @param cell The grid spacing.
 * @param inside Boxes the point must be inside.
 * @param outside Boxes the point must be outside.
 * @returns The accepted points.
 */
function acceptedCells(
    region: ContainerBox,
    cell: number,
    inside: ContainerBox[],
    outside: ContainerBox[]
): Point[] {
    const cells: Point[] = [];
    const x0 = region.x + cell / 2;
    const y0 = region.y + cell / 2;
    for (let y = y0; y < region.y + region.h; y += cell) {
        for (let x = x0; x < region.x + region.w; x += cell) {
            const p = {x, y};
            if (inside.every(b => centerInBox(p, b)) && !outside.some(b => centerInBox(p, b))) {
                cells.push(p);
            }
        }
    }
    return cells;
}

/**
 * Container-membership-preserving arrangement.
 *
 * Each node's membership signature — the set of containers whose box currently
 * holds it — is preserved exactly. Containers stay put; a node is re-placed only
 * within the region its signature demands (the intersection of the containers it
 * belongs to, and outside every container it does not), so intersections and
 * subsets survive re-arrange: a node shared by two containers stays in both, a
 * node in one stays only in that one, and a free node stays outside all.
 *
 * Free nodes (in no container) are first laid out by the profile algorithm for a
 * good graph layout, then any that landed inside a container are evicted to the
 * nearest free grid cell. Contained nodes are packed into their region.
 *
 * @param nodes The nodes to place.
 * @param relations The relations.
 * @param profile The active diagram profile.
 * @param containers The containers with their boxes.
 * @param current The current positions (used to read each node's membership).
 * @returns A complete position map preserving every node's membership.
 */
function containerAwareArrange(
    nodes: VimiNode[],
    relations: VimiRelation[],
    profile: string,
    containers: NamedBox[],
    current: LayoutMap
): LayoutMap {
    const boxById = new Map(containers.map(c => [c.id, c.box]));

    // Membership signature per node, from its current centre.
    const signature = new Map<string, string[]>();
    for (const node of nodes) {
        const c = current[node.stableid];
        const sig = c
            ? containers.filter(cb => centerInBox(c, cb.box)).map(cb => cb.id).sort()
            : [];
        signature.set(node.stableid, sig);
    }

    // Group nodes by signature key.
    const groups = new Map<string, string[]>();
    for (const node of nodes) {
        const key = (signature.get(node.stableid) ?? []).join('\u0001');
        const list = groups.get(key) ?? [];
        list.push(node.stableid);
        groups.set(key, list);
    }

    const CELL = 130;
    const result: LayoutMap = {};

    // Free nodes: lay out with the profile algorithm, then evict any inside a
    // container to the nearest free cell so they stay outside all containers.
    const freeIds = groups.get('') ?? [];
    if (freeIds.length > 0) {
        const freeSet = new Set(freeIds);
        const freeNodes = nodes.filter(n => freeSet.has(n.stableid));
        const freeRel = relations.filter(r => freeSet.has(r.sourceid) && freeSet.has(r.targetid));
        const plain = arrangePlain(freeNodes, freeRel, profile);
        const allBoxes = containers.map(c => c.box);
        const freeRegion = {x: 80, y: 80, w: CANVAS_WIDTH - 160, h: CANVAS_HEIGHT - 160};
        const freeCells = acceptedCells(freeRegion, CELL, [], allBoxes);
        let nextCell = 0;
        for (const node of freeNodes) {
            const p = plain[node.stableid];
            if (p && !allBoxes.some(b => centerInBox(p, b))) {
                result[node.stableid] = p;
            } else {
                const cell = freeCells[nextCell++] ?? p ?? {x: freeRegion.x, y: freeRegion.y};
                result[node.stableid] = {x: Math.round(cell.x), y: Math.round(cell.y)};
            }
        }
    }

    // Contained groups: pack into the region their signature demands.
    for (const [key, ids] of groups) {
        if (key === '') {
            continue;
        }
        const sig = key.split('\u0001');
        const insideBoxes = sig.map(id => boxById.get(id)).filter((b): b is ContainerBox => !!b);
        const outsideBoxes = containers.filter(c => !sig.includes(c.id)).map(c => c.box);
        const region = intersectionRect(insideBoxes);
        if (!region) {
            // Degenerate (containers no longer overlap): keep old positions.
            for (const id of ids) {
                if (current[id]) {
                    result[id] = current[id];
                }
            }
            continue;
        }
        let cells = acceptedCells(region, CELL, insideBoxes, outsideBoxes);
        if (cells.length === 0) {
            // No cell satisfies the constraints at this spacing: fall back to the
            // region centre so the nodes at least stay in the right containers.
            cells = [{x: region.x + region.w / 2, y: region.y + region.h / 2}];
        }
        ids.forEach((id, i) => {
            const cell = cells[Math.min(i, cells.length - 1)];
            result[id] = {x: Math.round(cell.x), y: Math.round(cell.y)};
        });
    }

    return result;
}

/**
 * Full profile-specific arrangement for the explicit "re-arrange" action.
 *
 * When containers are supplied, each node's container membership is preserved
 * exactly (see {@see containerAwareArrange}); otherwise the pure profile layout
 * is used. Everything is deterministic.
 *
 * @param nodes The nodes to place.
 * @param relations The relations.
 * @param profile The active diagram profile.
 * @param containers The containers with their boxes (optional).
 * @param current The current positions, used to read membership (optional).
 * @returns A complete position map for every node.
 */
export function arrangeLayout(
    nodes: VimiNode[],
    relations: VimiRelation[] = [],
    profile: string = '',
    containers: NamedBox[] = [],
    current: LayoutMap = {}
): LayoutMap {
    if (nodes.length === 0) {
        return {};
    }
    if (containers.length === 0) {
        return arrangePlain(nodes, relations, profile);
    }
    return containerAwareArrange(nodes, relations, profile, containers, current);
}
