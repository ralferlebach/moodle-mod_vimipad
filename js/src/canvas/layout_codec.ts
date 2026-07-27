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
 * Layout channel codec: node positions and sizes.
 *
 * Position and size are non-revisioned layout state (they never conflict on the
 * operation log). They share the single layoutjson blob, which the server
 * stores opaquely. The versioned envelope {v:1, pos, size} is parsed
 * back-compatibly: a bare position map (the previous format, no `v`) is read as
 * positions with no sizes, so older stored layouts keep working. Pure and unit
 * tested.
 *
 * @module     mod_vimipad/canvas/layout_codec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, Point, SizeMap} from '../types';

/** The decoded layout: positions and sizes, each keyed by node stable id. */
export interface Layout {
    positions: LayoutMap;
    sizes: SizeMap;
}

/** The current envelope version. */
const VERSION = 1;

/**
 * Whether a value is a finite-number point.
 *
 * @param value The candidate value.
 * @returns True if it is a {x, y} point.
 */
function isPoint(value: unknown): value is Point {
    const p = value as Point;
    return !!value && typeof p.x === 'number' && typeof p.y === 'number'
        && Number.isFinite(p.x) && Number.isFinite(p.y);
}

/**
 * Read a positions map from a plain object, keeping only valid points.
 *
 * @param obj The source object.
 * @returns A positions map.
 */
function readPositions(obj: Record<string, unknown>): LayoutMap {
    const out: LayoutMap = {};
    for (const [id, value] of Object.entries(obj)) {
        if (isPoint(value)) {
            out[id] = {x: value.x, y: value.y};
        }
    }
    return out;
}

/**
 * Read a sizes map from a plain object, keeping only valid positive sizes.
 *
 * @param obj The source object.
 * @returns A sizes map.
 */
function readSizes(obj: Record<string, unknown>): SizeMap {
    const out: SizeMap = {};
    for (const [id, value] of Object.entries(obj)) {
        const s = value as {w?: unknown; h?: unknown};
        if (s && typeof s.w === 'number' && typeof s.h === 'number'
            && Number.isFinite(s.w) && Number.isFinite(s.h) && s.w > 0 && s.h > 0) {
            out[id] = {w: s.w, h: s.h};
        }
    }
    return out;
}

/**
 * Decode a stored layoutjson into positions and sizes.
 *
 * @param json The stored layout JSON, possibly empty or in the legacy format.
 * @returns The decoded layout; empty maps if absent or malformed.
 */
export function decodeLayout(json: string): Layout {
    const empty: Layout = {positions: {}, sizes: {}};
    if (!json) {
        return empty;
    }
    let raw: unknown;
    try {
        raw = JSON.parse(json);
    } catch {
        return empty;
    }
    if (!raw || typeof raw !== 'object') {
        return empty;
    }
    const obj = raw as Record<string, unknown>;

    // Versioned envelope {v, pos, size}.
    if (obj.v === VERSION) {
        return {
            positions: readPositions((obj.pos as Record<string, unknown>) ?? {}),
            sizes: readSizes((obj.size as Record<string, unknown>) ?? {}),
        };
    }

    // Legacy format: a bare id -> point map with no sizes.
    return {positions: readPositions(obj), sizes: {}};
}

/**
 * Encode positions and sizes into a versioned layoutjson.
 *
 * @param positions The node positions.
 * @param sizes The node sizes.
 * @returns The serialised layout JSON.
 */
export function encodeLayout(positions: LayoutMap, sizes: SizeMap): string {
    return JSON.stringify({v: VERSION, pos: positions, size: sizes});
}
