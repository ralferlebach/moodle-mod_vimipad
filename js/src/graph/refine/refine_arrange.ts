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
 * Adapter that drives the preservation-first refiner from the editor state: it
 * maps nodes, relations, containers, sizes and locks onto the refiner's inputs,
 * chooses per-profile potential parameters, and returns the refined node
 * positions and container geometries.
 *
 * The per-profile parameters live in {@link refineOptionsForProfile}. This is
 * the pragmatic form of the "layout potential provider" contract: each display
 * profile supplies the direction, order axis and behaviour it cares about.
 * These can later be sourced from the form subplugins' own config without
 * changing the engine.
 *
 * @module     mod_vimipad/graph/refine/refine_arrange
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {VimiNode, VimiRelation, VimiContainer, LayoutMap, SizeMap, FormConfig} from '../../types';
import {parseGeometry} from '../../canvas/container_geometry';
import {parseNodeStyle} from '../../canvas/node_style';
import {nodeWidth, nodeHeight} from '../../canvas/node_geometry';
import {
    refineLayout, RefineNode, RefineEdge, RefineContainer, RefineOptions, RefinedContainer,
} from './refine_layout';

/** Per-profile refinement behaviour. */
export interface ProfileRefine {
    /** Preferred direction for directed edges (null = none). */
    preferredDir: {x: number; y: number} | null;
    /** Whether relations are treated as directed for alignment. */
    directed: boolean;
    /** Cross-axis for order preservation (null = none). */
    orderAxis: {x: number; y: number} | null;
    /** Preserve the cyclic (angular) order of a hub's neighbours (radial forms). */
    cyclicOrder: boolean;
    /** Confine nodes onto a line parallel to this axis (linear forms, e.g. timeline). */
    lineAxis: {x: number; y: number} | null;
}

/**
 * The built-in fallback potential configuration for a display profile. This is
 * used only when no PHP subplugin layout config is supplied (legacy transports,
 * or a profile without an installed form subplugin); the authoritative source
 * is now each vimipadform subplugin's layout declaration, resolved by
 * {@link resolveProfileRefine}. Hierarchical profiles flow downward and keep
 * sibling left/right order; radial profiles are free (cyclic order is a later,
 * subplugin-supplied refinement); free-form profiles impose no axis.
 *
 * @param profile The profile key.
 * @returns The per-profile refinement behaviour.
 */
export function refineOptionsForProfile(profile: string): ProfileRefine {
    switch (profile) {
        case 'tree':
            return {preferredDir: {x: 0, y: 1}, directed: true, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null};
        case 'conceptmap':
            // Concept maps are hierarchical but their cross-links point every
            // which way; forcing a global direction would rotate the map. Keep
            // the sibling order axis, but do not impose a direction.
            return {preferredDir: null, directed: false, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null};
        case 'mindmap':
        case 'bubblemap':
            // Radial forms: no linear order axis, but keep the cyclic order of a
            // hub's branches so the fan cannot scramble on arrange.
            return {preferredDir: null, directed: false, orderAxis: null, cyclicOrder: true, lineAxis: null};
        case 'timeline':
            // Linear time flow: directed left-to-right, and confine events onto a
            // single horizontal time line.
            return {preferredDir: {x: 1, y: 0}, directed: true, orderAxis: null, cyclicOrder: false, lineAxis: {x: 1, y: 0}};
        default:
            return {preferredDir: null, directed: false, orderAxis: null, cyclicOrder: false, lineAxis: null};
    }
}

/**
 * Resolve the layout-potential parameters for the active profile, preferring
 * the PHP subplugin's declaration (transported in the form config) over the
 * built-in fallback. This is the single seam through which a vimipadform
 * subplugin controls how the arrange refiner treats its display type, so new
 * display types decide their own layout in PHP rather than in this file.
 *
 * @param profile The active profile key.
 * @param config The active form config, if transported.
 * @returns The per-profile refinement behaviour.
 */
export function resolveProfileRefine(profile: string, config?: FormConfig): ProfileRefine {
    const layout = config?.layout;
    if (!layout) {
        return refineOptionsForProfile(profile);
    }
    return {
        directed: layout.directed,
        preferredDir: layout.direction ?? null,
        orderAxis: layout.orderaxis ?? null,
        cyclicOrder: layout.cyclicorder ?? false,
        lineAxis: layout.lineaxis ?? null,
    };
}

/** Inputs the arrange handler already has to hand. */
export interface ArrangeInput {
    nodes: VimiNode[];
    relations: VimiRelation[];
    containers: VimiContainer[];
    profile: string;
    /** Current (human) node positions. */
    positions: LayoutMap;
    /** Current node sizes; missing entries fall back to a label-derived size. */
    sizes: SizeMap;
    /** Stable ids that must not move (move-locked, or pinned in a locked container). */
    pinned?: Set<string>;
    /** Container stable ids whose geometry is locked (never resized/moved). */
    lockedContainers?: Set<string>;
    /**
     * Whether containers may be resized to keep their members enclosed. Default
     * true: boxes grow to contain members with padding but, by default, do not
     * shrink (the human's chosen size is preserved). Set an override
     * containerShrinkRate > 0 to also tighten oversized boxes.
     */
    resizeContainers?: boolean;
    /**
     * Whether an oversized container may be shrunk (damped) toward its members
     * on arrange. Default true. When false, containers still grow to contain
     * overflow but never shrink below the author's drawn size — so a teacher's
     * deliberately-sized template box is preserved. Independent of
     * resizeContainers, which turns all container geometry changes off.
     */
    shrinkContainers?: boolean;
    /**
     * Solver iteration ceiling for this arrange (site-configurable). Higher
     * means a single press converges further; omitted uses the engine default.
     */
    maxIterations?: number;
    /** Optional option overrides (e.g. stabilityScale calibrated per site). */
    overrides?: Partial<RefineOptions>;
    /**
     * The active form config, if transported. Its layout declaration (from the
     * form's PHP subplugin) drives the per-profile behaviour; when absent the
     * refiner falls back to its built-in profile defaults.
     */
    formconfig?: FormConfig;
}

/** The refined arrangement. */
export interface ArrangeResult {
    positions: LayoutMap;
    containers: Record<string, RefinedContainer>;
    movement: number;
}

/** Whether a point lies inside a top-left box. */
function pointInBox(px: number, py: number, b: {x: number; y: number; w: number; h: number}): boolean {
    return px >= b.x && px <= b.x + b.w && py >= b.y && py <= b.y + b.h;
}

/**
 * Refine the current arrangement: gently improve the human layout in place. Node
 * positions come from the current layout (warm start), container membership is
 * read from the current geometry, and locked elements are frozen.
 *
 * @param input The editor state needed to arrange.
 * @returns The refined node positions and container geometries.
 */
export function refineArrangement(input: ArrangeInput): ArrangeResult {
    const {nodes, relations, containers, profile, positions, sizes, pinned, overrides} = input;
    const lockedContainers = input.lockedContainers;
    const resizeContainers = input.resizeContainers ?? true;
    const shrinkContainers = input.shrinkContainers ?? true;
    const prof = resolveProfileRefine(profile, input.formconfig);

    const posOf = (id: string): {x: number; y: number} =>
        positions[id] ?? {x: 0, y: 0};
    const sizeOf = (nd: VimiNode): {w: number; h: number} => {
        const s = sizes[nd.stableid];
        if (s) {
            return s;
        }
        const w = nodeWidth(nd.label ?? '');
        return {w, h: nodeHeight(nd.label ?? '', w)};
    };

    const rnodes: RefineNode[] = nodes.map(nd => {
        const p = posOf(nd.stableid);
        const s = sizeOf(nd);
        return {
            stableid: nd.stableid, x: p.x, y: p.y, w: s.w, h: s.h,
            fixed: pinned?.has(nd.stableid) ?? false,
        };
    });

    const redges: RefineEdge[] = relations.map(r => ({
        source: r.sourceid, target: r.targetid, directed: prof.directed && r.direction !== 0,
    }));

    // Container membership from the current geometry: a node whose centre sits
    // inside the container's shape is a member. Ellipse containers use the true
    // elliptical test, so a node in a box corner (outside the ellipse) is not a
    // spurious member.
    const rcontainers: RefineContainer[] = [];
    for (const c of containers) {
        const box = c.geometryjson ? parseGeometry(c.geometryjson) : null;
        if (!box) {
            continue;
        }
        const isEllipse = parseNodeStyle(c.metadatajson).shape === 'ellipse';
        const inside = (p: {x: number; y: number}): boolean => {
            if (isEllipse) {
                const ax = box.w / 2;
                const by = box.h / 2;
                const dx = (p.x - (box.x + ax)) / (ax || 1);
                const dy = (p.y - (box.y + by)) / (by || 1);
                return dx * dx + dy * dy <= 1;
            }
            return pointInBox(p.x, p.y, box);
        };
        const members: string[] = [];
        for (const nd of nodes) {
            if (inside(posOf(nd.stableid))) {
                members.push(nd.stableid);
            }
        }
        rcontainers.push({
            stableid: c.stableid, x: box.x, y: box.y, w: box.w, h: box.h,
            members, shape: isEllipse ? 'ellipse' : 'rect',
            fixed: !resizeContainers || (lockedContainers?.has(c.stableid) ?? false),
        });
    }

    const opts: Partial<RefineOptions> = {
        preferredDir: prof.preferredDir,
        directionFloor: prof.directed ? 0.15 : 0,
        orderAxis: prof.orderAxis,
        // Radial forms keep their branches' cyclic order around each hub.
        cyclicStrength: prof.cyclicOrder ? 2 : 0,
        // Linear forms (timeline) confine events onto one line.
        lineConfineStrength: prof.lineAxis ? 1.5 : 0,
        lineConfineAxis: prof.lineAxis,
        swaps: true,
        // "Anordnen" is an explicit rearrange request, so pull the layout toward a
        // clean force-directed state: edges converge toward a common length (via a
        // gentle long-range spring), nodes are free to move, and containers hug
        // their members. All terms converge, so repeated presses settle.
        edgeTargetBlend: 0.7,
        edgeSpring: 0.5,
        // A gentle central bowl confines the layout: repulsion alone would fling
        // an unconnected node off the canvas, and the whole map could drift
        // off-centre. Gravity reels both back without touching the interior.
        gravity: 0.1,
        // With gravity providing the anti-drift, the start-anchor no longer has
        // to carry it, so it drops to light damping. A strong anchor made every
        // press move only part-way toward the target, so the map only settled
        // after ~10 clicks; light damping lets a single press converge.
        stabilityScale: 0.08,
        containerShrinkRate: shrinkContainers ? 0.35 : 0,
        ...(input.maxIterations ? {maxIterations: input.maxIterations} : {}),
        ...overrides,
    };

    const res = refineLayout(rnodes, redges, opts, rcontainers);
    const outPos: LayoutMap = {};
    for (const nd of nodes) {
        const p = res.positions[nd.stableid];
        outPos[nd.stableid] = {x: Math.round(p.x), y: Math.round(p.y)};
    }
    const outContainers: Record<string, RefinedContainer> = {};
    for (const key of Object.keys(res.containers)) {
        const g = res.containers[key];
        outContainers[key] = {x: Math.round(g.x), y: Math.round(g.y), w: Math.round(g.w), h: Math.round(g.h)};
    }
    return {positions: outPos, containers: outContainers, movement: res.movement};
}
