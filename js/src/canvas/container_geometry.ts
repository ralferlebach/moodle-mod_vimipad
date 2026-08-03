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
 * Pure geometry helpers for canvas containers (background boxes/sections).
 *
 * A container's position and size live in its `geometryjson` as {x, y, w, h}
 * in canvas units. Keeping the codec here (out of React) makes it unit-testable
 * and lets both the renderer and the drawing interaction share one definition.
 *
 * @module     mod_vimipad/canvas/container_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Point} from '../types';

/** A container's box in canvas units. */
export interface ContainerBox {
    x: number;
    y: number;
    w: number;
    h: number;
}

/** The smallest container a drag is allowed to create, in canvas units. */
export const MIN_CONTAINER_SIZE = 40;

/**
 * Parse a container's geometry JSON into a box, or null if invalid/empty.
 *
 * @param geometryjson The stored geometry JSON.
 * @returns The box, or null when the JSON is missing or malformed.
 */
export function parseGeometry(geometryjson?: string): ContainerBox | null {
    if (!geometryjson) {
        return null;
    }
    let raw: unknown;
    try {
        raw = JSON.parse(geometryjson);
    } catch {
        return null;
    }
    if (!raw || typeof raw !== 'object') {
        return null;
    }
    const candidate = raw as Record<string, unknown>;
    const values = [candidate.x, candidate.y, candidate.w, candidate.h];
    if (!values.every((v) => typeof v === 'number' && Number.isFinite(v))) {
        return null;
    }
    return normalizeBox({
        x: candidate.x as number,
        y: candidate.y as number,
        w: candidate.w as number,
        h: candidate.h as number,
    });
}

/**
 * Serialise a box to geometry JSON, rounding to whole canvas units.
 *
 * @param box The box.
 * @returns The geometry JSON string.
 */
export function serializeGeometry(box: ContainerBox): string {
    return JSON.stringify({
        x: Math.round(box.x),
        y: Math.round(box.y),
        w: Math.round(box.w),
        h: Math.round(box.h),
    });
}

/**
 * Normalise a box so width and height are non-negative (top-left origin).
 *
 * @param box The box, possibly with negative extent.
 * @returns An equivalent box with a top-left origin and positive size.
 */
export function normalizeBox(box: ContainerBox): ContainerBox {
    const x = box.w < 0 ? box.x + box.w : box.x;
    const y = box.h < 0 ? box.y + box.h : box.y;
    return {x, y, w: Math.abs(box.w), h: Math.abs(box.h)};
}

/**
 * Build a normalised box from the two corners of a drag gesture.
 *
 * @param from The drag start point.
 * @param to The drag end point.
 * @returns The normalised box spanning the two points.
 */
export function boxFromDrag(from: Point, to: Point): ContainerBox {
    return normalizeBox({x: from.x, y: from.y, w: to.x - from.x, h: to.y - from.y});
}

/**
 * Whether a box is at least the minimum container size in both dimensions.
 *
 * @param box The box.
 * @returns True if the box is big enough to become a container.
 */
export function isDrawable(box: ContainerBox): boolean {
    return box.w >= MIN_CONTAINER_SIZE && box.h >= MIN_CONTAINER_SIZE;
}

/**
 * Translate a box by a delta.
 *
 * @param box The box.
 * @param dx Horizontal delta in canvas units.
 * @param dy Vertical delta in canvas units.
 * @returns The moved box.
 */
export function moveBox(box: ContainerBox, dx: number, dy: number): ContainerBox {
    return {x: box.x + dx, y: box.y + dy, w: box.w, h: box.h};
}

/**
 * Resize a box from its bottom-right corner by a delta, clamped to the minimum
 * size (the top-left origin stays fixed).
 *
 * @param box The box.
 * @param dx Horizontal delta in canvas units.
 * @param dy Vertical delta in canvas units.
 * @returns The resized box.
 */
export function resizeBox(box: ContainerBox, dx: number, dy: number): ContainerBox {
    return {
        x: box.x,
        y: box.y,
        w: Math.max(MIN_CONTAINER_SIZE, box.w + dx),
        h: Math.max(MIN_CONTAINER_SIZE, box.h + dy),
    };
}

/** A resize handle corner. */
export type BoxCorner = 'nw' | 'ne' | 'sw' | 'se';

/**
 * Resize a box by dragging one of its four corners, keeping the opposite corner
 * fixed and honouring the minimum size. This gives containers the same
 * four-corner resize affordance as nodes.
 *
 * @param box The starting box.
 * @param corner The corner being dragged.
 * @param dx The pointer delta x.
 * @param dy The pointer delta y.
 * @returns The resized box.
 */
export function resizeBoxCorner(box: ContainerBox, corner: BoxCorner, dx: number, dy: number): ContainerBox {
    const right = box.x + box.w;
    const bottom = box.y + box.h;
    let {x, y, w, h} = box;

    if (corner === 'se') {
        w = box.w + dx;
        h = box.h + dy;
    } else if (corner === 'ne') {
        w = box.w + dx;
        h = box.h - dy;
        y = box.y + dy;
    } else if (corner === 'sw') {
        w = box.w - dx;
        x = box.x + dx;
        h = box.h + dy;
    } else { // nw
        w = box.w - dx;
        x = box.x + dx;
        h = box.h - dy;
        y = box.y + dy;
    }

    // Clamp to the minimum, anchoring the opposite edge.
    if (w < MIN_CONTAINER_SIZE) {
        w = MIN_CONTAINER_SIZE;
        x = (corner === 'nw' || corner === 'sw') ? right - MIN_CONTAINER_SIZE : box.x;
    }
    if (h < MIN_CONTAINER_SIZE) {
        h = MIN_CONTAINER_SIZE;
        y = (corner === 'nw' || corner === 'ne') ? bottom - MIN_CONTAINER_SIZE : box.y;
    }
    return {x, y, w, h};
}

/**
 * Whether a point lies within a container box (inclusive of the border).
 *
 * @param center The point (a node's centre).
 * @param box The container box.
 * @returns True if the point is inside the box.
 */
export function centerInBox(center: Point, box: ContainerBox): boolean {
    return center.x >= box.x && center.x <= box.x + box.w
        && center.y >= box.y && center.y <= box.y + box.h;
}

/**
 * The bounding box of a set of boxes, expanded by a uniform padding and clamped
 * to the minimum container size. Returns null for an empty set.
 *
 * Used by re-arrange to refit a container around its (now relocated) member
 * nodes, so a node that was inside the container stays inside after re-layout.
 *
 * @param boxes The member boxes.
 * @param pad The padding to add on every side.
 * @returns The padded bounding box, or null if there are no boxes.
 */
export function boundingBox(boxes: ContainerBox[], pad: number): ContainerBox | null {
    if (boxes.length === 0) {
        return null;
    }
    let minx = Infinity;
    let miny = Infinity;
    let maxx = -Infinity;
    let maxy = -Infinity;
    for (const b of boxes) {
        minx = Math.min(minx, b.x);
        miny = Math.min(miny, b.y);
        maxx = Math.max(maxx, b.x + b.w);
        maxy = Math.max(maxy, b.y + b.h);
    }
    return normalizeBox({
        x: minx - pad,
        y: miny - pad,
        w: Math.max(MIN_CONTAINER_SIZE, (maxx - minx) + 2 * pad),
        h: Math.max(MIN_CONTAINER_SIZE, (maxy - miny) + 2 * pad),
    });
}

/** A container identified for nesting: its id and current box. */
export interface NestingItem {
    stableid: string;
    box: ContainerBox;
}

/**
 * The area of a box.
 *
 * @param box The box.
 * @returns Its area.
 */
export function boxArea(box: ContainerBox): number {
    return box.w * box.h;
}

/**
 * Assign each container its single nesting parent, acyclically.
 *
 * A container B is a child of A only if A is *strictly larger* than B (by area)
 * and B's centre lies inside A. Using strict area breaks the symmetry of two
 * overlapping same-size containers (where each centre lies in the other), which
 * previously made the nesting relation cyclic — the root cause of the
 * re-arrange runaway growth and the flipping hierarchy. Among all qualifying
 * ancestors, the *smallest* is chosen as the direct parent (the tightest
 * encloser). The result is a forest: every container has at most one parent and
 * no container is its own ancestor.
 *
 * @param items The containers with their current boxes.
 * @returns A map from container id to its parent id (absent = a root).
 */
export function nestingParents(items: NestingItem[]): Map<string, string> {
    const parent = new Map<string, string>();
    for (const child of items) {
        let best: NestingItem | null = null;
        const centre = {x: child.box.x + child.box.w / 2, y: child.box.y + child.box.h / 2};
        for (const cand of items) {
            if (cand.stableid === child.stableid) {
                continue;
            }
            // Strict-larger area guarantees asymmetry and acyclicity; ties
            // (equal area) never nest, so two equal boxes stay siblings.
            if (boxArea(cand.box) <= boxArea(child.box)) {
                continue;
            }
            if (!centerInBox(centre, cand.box)) {
                continue;
            }
            if (best === null || boxArea(cand.box) < boxArea(best.box)) {
                best = cand;
            }
        }
        if (best !== null) {
            parent.set(child.stableid, best.stableid);
        }
    }
    return parent;
}

/**
 * Order containers so that every child comes before its parent (deepest first).
 *
 * Re-arrange must refit inner containers before outer ones, so an outer
 * container refits around the *already refitted* inner box. Processing in
 * arbitrary order is what let sizes ping-pong between passes.
 *
 * @param items The containers.
 * @param parents The nesting parent map from {@link nestingParents}.
 * @returns The ids ordered deepest-child first.
 */
export function nestingOrder(items: NestingItem[], parents: Map<string, string>): string[] {
    const depth = (id: string): number => {
        let d = 0;
        let cur = parents.get(id);
        const seen = new Set<string>([id]);
        while (cur !== undefined && !seen.has(cur)) {
            d++;
            seen.add(cur);
            cur = parents.get(cur);
        }
        return d;
    };
    return items
        .map(i => i.stableid)
        .sort((a, b) => depth(b) - depth(a));
}

/**
 * Whether a node must keep its current position during a re-arrange.
 *
 * A node is pinned if it is itself move-locked, or if it currently sits inside
 * any move-locked container (so re-arrange cannot push it out of that
 * container's bounds). This is the pure decision behind the re-arrange lock
 * handling, kept here so it can be unit-tested in isolation.
 *
 * @param nodeMetadatajson The node's metadata JSON.
 * @param currentCenter The node's current centre point (or undefined).
 * @param moveLockedContainerBoxes The boxes of all move-locked containers.
 * @param isNodeMoveLocked Predicate: is the given metadata move-locked?
 * @returns True if the node must not be repositioned.
 */
export function isNodePinnedForRearrange(
    nodeMetadatajson: string | undefined,
    currentCenter: Point | undefined,
    moveLockedContainerBoxes: ContainerBox[],
    isNodeMoveLocked: (metadatajson?: string) => boolean
): boolean {
    if (isNodeMoveLocked(nodeMetadatajson)) {
        return true;
    }
    if (!currentCenter) {
        return false;
    }
    return moveLockedContainerBoxes.some(box => centerInBox(currentCenter, box));
}
