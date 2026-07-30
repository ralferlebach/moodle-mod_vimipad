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
 * Pure geometry helpers for the canvas: node box sizing, edge boundary points
 * and connector path routing. Extracted from CanvasView so the maths is
 * independently testable and the component stays focused on rendering.
 *
 * @module     mod_vimipad/canvas/node_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CANVAS_HEIGHT, CANVAS_WIDTH} from '../graph/autolayout';
import {Point, Size} from '../types';
import {LineStyle} from './form_config';

/** Default node box height, in canvas units. */
export const DEFAULT_NODE_HEIGHT = 40;

/** Minimum node box width. */
export const MIN_W = 60;

/** Minimum node box height. */
export const MIN_H = 32;

/** Maximum node box width. */
export const MAX_W = 360;

/** Maximum node box height. */
export const MAX_H = 240;

/**
 * Clamp a viewport rectangle so it stays within the canvas bounds.
 *
 * @param v The viewport rectangle.
 * @returns The clamped rectangle.
 */
export function clampView(
    v: {x: number; y: number; w: number; h: number}
): {x: number; y: number; w: number; h: number} {
    return {
        w: v.w,
        h: v.h,
        x: Math.min(Math.max(0, v.x), Math.max(0, CANVAS_WIDTH - v.w)),
        y: Math.min(Math.max(0, v.y), Math.max(0, CANVAS_HEIGHT - v.h)),
    };
}

/**
 * Default width of a node box: from the longest line, capped to the max width.
 *
 * @param label The node label.
 * @returns The box width in canvas units.
 */
export const nodeWidth = (label: string): number => {
    const longest = label.split('\n').reduce((max, line) => Math.max(max, line.length), 1);
    return Math.max(70, Math.min(MAX_W, longest * 8 + 20));
};

/**
 * Auto-grown node box height for a label, from its explicit and wrapped lines.
 *
 * @param label The node label.
 * @param width The node box width the text wraps within.
 * @returns The box height in canvas units.
 */
export const nodeHeight = (label: string, width: number): number => {
    const perLine = Math.max(1, Math.floor((width - 12) / 7));
    let lines = 0;
    for (const segment of label.split('\n')) {
        lines += Math.max(1, Math.ceil((segment.length || 1) / perLine));
    }
    return Math.max(DEFAULT_NODE_HEIGHT, Math.min(MAX_H, 16 + lines * 18));
};

/**
 * The connector line style for a display type (profile).
 *
 * @param profile The display-type key.
 * @returns The line style for that display type.
 */
export function profileLine(profile: string): LineStyle {
    switch (profile) {
        case 'tree':
            return 'orthogonal';
        case 'mindmap':
        case 'bubblemap':
            return 'curved';
        default:
            return 'straight';
    }
}

/**
 * Point on a node's box boundary in the direction of a target point.
 *
 * @param center The node centre.
 * @param size The node size.
 * @param towards The point to aim at.
 * @returns The boundary point.
 */
export function edgePoint(center: Point, size: Size, towards: Point): Point {
    const dx = towards.x - center.x;
    const dy = towards.y - center.y;
    if (dx === 0 && dy === 0) {
        return center;
    }
    const hw = size.w / 2 + 2;
    const hh = size.h / 2 + 2;
    const scale = 1 / Math.max(Math.abs(dx) / hw, Math.abs(dy) / hh);
    return {x: center.x + dx * scale, y: center.y + dy * scale};
}

/**
 * Orthogonal "org-chart" routing for a tree edge.
 *
 * @param sc Source (parent) centre.
 * @param ss Source size.
 * @param tc Target (child) centre.
 * @param ts Target size.
 * @returns A path `d` string.
 */
export function treeBusPath(sc: Point, ss: Size, tc: Point, ts: Size): string {
    const fromX = sc.x;
    const fromY = sc.y + ss.h / 2;
    const toX = tc.x;
    const toY = tc.y - ts.h / 2;
    let busY = fromY + 24;
    if (busY > toY - 8) {
        busY = (fromY + toY) / 2;
    }
    return `M ${fromX} ${fromY} L ${fromX} ${busY} L ${toX} ${busY} L ${toX} ${toY}`;
}

/**
 * SVG path for a connector, or null when a straight <line> should be used.
 *
 * @param from Source point.
 * @param to Target point.
 * @param line The line style.
 * @returns A path `d` string, or null for straight lines.
 */
export function relLinePath(from: Point, to: Point, line: LineStyle): string | null {
    if (line === 'curved') {
        const dy = to.y - from.y;
        const dir = dy >= 0 ? 1 : -1;
        const k = Math.max(24, Math.abs(dy) * 0.5);
        const c1y = from.y + dir * k;
        const c2y = to.y - dir * k;
        return `M ${from.x} ${from.y} C ${from.x} ${c1y} ${to.x} ${c2y} ${to.x} ${to.y}`;
    }
    if (line === 'orthogonal') {
        const mx = (from.x + to.x) / 2;
        return `M ${from.x} ${from.y} L ${mx} ${from.y} L ${mx} ${to.y} L ${to.x} ${to.y}`;
    }
    return null;
}

/**
 * Clamp a proposed node size to the allowed minimum/maximum, rounding.
 *
 * @param w The proposed width.
 * @param h The proposed height.
 * @returns The clamped size.
 */
export function clampSize(w: number, h: number): Size {
    return {
        w: Math.max(MIN_W, Math.min(MAX_W, Math.round(w))),
        h: Math.max(MIN_H, Math.min(MAX_H, Math.round(h))),
    };
}
