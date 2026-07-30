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

/** Whether a connection runs basically horizontally or vertically. */
export type Orientation = 'horizontal' | 'vertical';

/**
 * Classify a connection by the angle of the direct line between the two nodes.
 *
 * @param from The start point.
 * @param to The end point.
 * @returns The dominant orientation.
 */
export function orientationOf(from: Point, to: Point): Orientation {
    return Math.abs(to.x - from.x) >= Math.abs(to.y - from.y) ? 'horizontal' : 'vertical';
}

/**
 * Normalise an angle into (-PI, PI].
 *
 * @param angle The angle in radians.
 * @returns The normalised angle.
 */
function wrapAngle(angle: number): number {
    let a = angle;
    while (a <= -Math.PI) {
        a += 2 * Math.PI;
    }
    while (a > Math.PI) {
        a -= 2 * Math.PI;
    }
    return a;
}

/**
 * The bisector of two angles, taking the shorter way around.
 *
 * @param a The first angle in radians.
 * @param b The second angle in radians.
 * @returns The bisecting angle in radians.
 */
export function bisectAngles(a: number, b: number): number {
    return wrapAngle(a + wrapAngle(b - a) / 2);
}

/**
 * The angle at which a connection leaves the node it is anchored on, measured
 * outward from that node.
 *
 * Per the agreed construction: the direct line decides whether the connection is
 * basically horizontal or vertical; the perpendicular of the corresponding node
 * side (the "Lot", i.e. the axis-aligned outward direction) is bisected with the
 * direct line's angle, and that bisector is the departure/arrival angle outside
 * the node shape. The arrow head uses the same angle, which is why the path runs
 * straight for the arrow's length before curving.
 *
 * @param from The source anchor.
 * @param to The target anchor.
 * @param atStart True for the source end, false for the target end.
 * @returns The outward angle in radians.
 */
export function connectorExitAngle(from: Point, to: Point, atStart: boolean): number {
    const dx = to.x - from.x;
    const dy = to.y - from.y;
    if (dx === 0 && dy === 0) {
        return 0;
    }

    const lineOut = atStart ? Math.atan2(dy, dx) : Math.atan2(-dy, -dx);
    let axisOut: number;
    if (orientationOf(from, to) === 'horizontal') {
        const east = dx >= 0;
        axisOut = (atStart ? east : !east) ? 0 : Math.PI;
    } else {
        const south = dy >= 0;
        axisOut = (atStart ? south : !south) ? Math.PI / 2 : -Math.PI / 2;
    }

    return bisectAngles(axisOut, lineOut);
}

/**
 * Shift both anchors of a connection perpendicular to the direct line, so that
 * several relations between the same pair of nodes run parallel instead of on
 * top of each other.
 *
 * @param from The source anchor.
 * @param to The target anchor.
 * @param offset The signed perpendicular offset (see siblingOffsets).
 * @returns The shifted anchors.
 */
export function offsetAnchors(from: Point, to: Point, offset: number): {from: Point; to: Point} {
    if (offset === 0) {
        return {from, to};
    }
    const perp = unitPerpendicular(from, to);
    return {
        from: {x: from.x + perp.x * offset, y: from.y + perp.y * offset},
        to: {x: to.x + perp.x * offset, y: to.y + perp.y * offset},
    };
}

/**
 * Round for path output, keeping the generated `d` attribute stable.
 *
 * @param value The value.
 * @returns The rounded value.
 */
function r(value: number): number {
    return Math.round(value * 100) / 100;
}

/**
 * Build the path of a freely routed connection.
 *
 * The path leaves each anchor straight for `stub` units along the departure
 * angle — long enough to cover the arrow head, so the head always points the way
 * the connection actually goes — and only then curves. The curve's handles
 * continue those same directions, so the joins are smooth.
 *
 * @param from The source anchor.
 * @param to The target anchor.
 * @param stub The straight run at each end, in canvas units.
 * @returns The SVG path data.
 */
export function freeConnectorPath(from: Point, to: Point, stub: number): string {
    const aOut = connectorExitAngle(from, to, true);
    const bOut = connectorExitAngle(from, to, false);

    const p1 = {x: from.x + Math.cos(aOut) * stub, y: from.y + Math.sin(aOut) * stub};
    const p2 = {x: to.x + Math.cos(bOut) * stub, y: to.y + Math.sin(bOut) * stub};

    const span = Math.hypot(p2.x - p1.x, p2.y - p1.y);
    const k = Math.max(16, span * 0.4);
    const c1 = {x: p1.x + Math.cos(aOut) * k, y: p1.y + Math.sin(aOut) * k};
    const c2 = {x: p2.x + Math.cos(bOut) * k, y: p2.y + Math.sin(bOut) * k};

    return `M ${r(from.x)} ${r(from.y)} L ${r(p1.x)} ${r(p1.y)} `
        + `C ${r(c1.x)} ${r(c1.y)} ${r(c2.x)} ${r(c2.y)} ${r(p2.x)} ${r(p2.y)} `
        + `L ${r(to.x)} ${r(to.y)}`;
}
