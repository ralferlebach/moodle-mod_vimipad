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
 * Geometry for drawing connections between nodes.
 *
 * Pure and unit tested. Covers the drawing rules from the Visual Maps
 * requirements: several connections between the same pair of nodes are fanned
 * out so they stay visually separated; each connection anchors on the node
 * border (so end markers sit at the edge, not the centre); labels sit at the
 * curve peak; and the end markers are arrows for directed connections or small
 * nubs for undirected ones.
 *
 * @module     mod_vimipad/canvas/connection_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Point} from '../types';

/** The kind of marker drawn at a connection end. */
export type EndMarker = 'none' | 'arrow' | 'nub';

/** Markers for the two ends of a connection. */
export interface Markers {
    start: EndMarker;
    end: EndMarker;
}

/**
 * Signed perpendicular offsets that fan out `count` parallel connections
 * between the same node pair, symmetric around zero and evenly spaced.
 *
 * count 1 -> [0]; count 2 -> [-s/2, +s/2]; count 3 -> [-s, 0, +s]; and so on.
 *
 * @param count The number of parallel connections.
 * @param spacing The spacing between adjacent connections.
 * @returns The signed offset for each connection, in order.
 */
export function siblingOffsets(count: number, spacing: number): number[] {
    const offsets: number[] = [];
    for (let i = 0; i < count; i++) {
        offsets.push((i - (count - 1) / 2) * spacing);
    }
    return offsets;
}

/**
 * The unit vector perpendicular to the line from `from` to `to`.
 *
 * @param from The start point.
 * @param to The end point.
 * @returns A unit perpendicular vector (zero vector if the points coincide).
 */
function unitPerpendicular(from: Point, to: Point): Point {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    const len = Math.hypot(dx, dy);
    if (len === 0) {
        return {x: 0, y: 0};
    }
    // Rotate the direction by 90 degrees and normalise.
    return {x: -dy / len, y: dx / len};
}

/**
 * The quadratic Bezier control point for a connection with the given offset.
 *
 * The control point is displaced perpendicular to the line by twice the offset,
 * so that the curve's midpoint (the label position) sits exactly `offset` away
 * from the straight line.
 *
 * @param from The start point.
 * @param to The end point.
 * @param offset The perpendicular offset of the curve peak.
 * @returns The control point.
 */
export function controlPoint(from: Point, to: Point, offset: number): Point {
    const mid = {x: (from.x + to.x) / 2, y: (from.y + to.y) / 2};
    const perp = unitPerpendicular(from, to);
    return {x: mid.x + perp.x * 2 * offset, y: mid.y + perp.y * 2 * offset};
}

/**
 * The label anchor for a connection: the peak of the curve.
 *
 * @param from The start point.
 * @param to The end point.
 * @param offset The perpendicular offset of the curve peak.
 * @returns The label point.
 */
export function labelPoint(from: Point, to: Point, offset: number): Point {
    const mid = {x: (from.x + to.x) / 2, y: (from.y + to.y) / 2};
    const perp = unitPerpendicular(from, to);
    return {x: mid.x + perp.x * offset, y: mid.y + perp.y * offset};
}

/**
 * The point on an axis-aligned rectangle border, from its centre toward a
 * target point. Used to anchor a connection on a node's edge.
 *
 * @param center The rectangle centre.
 * @param halfW Half the rectangle width.
 * @param halfH Half the rectangle height.
 * @param toward The point the connection heads toward.
 * @returns The border point.
 */
export function rectBorderPoint(center: Point, halfW: number, halfH: number, toward: Point): Point {
    const dx = toward.x - center.x;
    const dy = toward.y - center.y;
    if (dx === 0 && dy === 0) {
        return {x: center.x, y: center.y};
    }
    // Scale the direction so it just reaches the nearest border.
    const scaleX = dx === 0 ? Infinity : halfW / Math.abs(dx);
    const scaleY = dy === 0 ? Infinity : halfH / Math.abs(dy);
    const scale = Math.min(scaleX, scaleY);
    return {x: center.x + dx * scale, y: center.y + dy * scale};
}

/**
 * The end markers for a connection given its direction.
 *
 * direction 0 = undirected (nubs at both ends); 1 = directed source->target
 * (arrow at the target); 2 = bidirectional (arrows at both ends).
 *
 * @param direction The relation direction (0, 1 or 2).
 * @returns The markers for the start and end.
 */
export function markersFor(direction: number): Markers {
    switch (direction) {
        case 1:
            return {start: 'none', end: 'arrow'};
        case 2:
            return {start: 'arrow', end: 'arrow'};
        default:
            return {start: 'nub', end: 'nub'};
    }
}
