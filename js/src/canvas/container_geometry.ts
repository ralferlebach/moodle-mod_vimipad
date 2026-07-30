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
