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
 * Mapping between client (screen) pixels and viewBox units.
 *
 * The canvas <svg> carries a viewBox but no preserveAspectRatio, so the browser
 * applies the default "xMidYMid meet": the viewBox is scaled *uniformly* to fit
 * inside the element and then centred, which leaves letterbox margins on the
 * axis with spare room.
 *
 * Assuming instead that the viewBox is stretched to fill the element (i.e.
 * dividing by the element's width and height separately) gives both a wrong
 * offset and a wrong scale, and a different error per axis — pointer positions
 * drift away from the cursor and drags move at the wrong speed. With a 1138x213
 * element and an 826x551 viewBox the scale is off by a factor of ~3.6.
 *
 * @module     mod_vimipad/canvas/viewport
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** A point in either coordinate space. */
export interface Point {
    x: number;
    y: number;
}

/** The rendered box of the SVG element, in client pixels. */
export interface ElementRect {
    left: number;
    top: number;
    width: number;
    height: number;
}

/** A viewBox: origin plus extent, in viewBox units. */
export interface ViewBox {
    x: number;
    y: number;
    w: number;
    h: number;
}

/**
 * The uniform scale the browser applies for preserveAspectRatio="… meet":
 * viewBox units multiplied by this give client pixels.
 *
 * @param view The viewBox.
 * @param rect The element's client rect.
 * @returns The scale factor (0 when either extent is degenerate).
 */
export function meetScale(view: ViewBox, rect: ElementRect): number {
    if (view.w <= 0 || view.h <= 0) {
        return 0;
    }
    return Math.min(rect.width / view.w, rect.height / view.h);
}

/**
 * The letterbox offset in client pixels introduced by centring ("xMidYMid").
 *
 * @param view The viewBox.
 * @param rect The element's client rect.
 * @returns The left/top offset of the rendered content within the element.
 */
export function meetOffset(view: ViewBox, rect: ElementRect): Point {
    const scale = meetScale(view, rect);
    return {
        x: (rect.width - view.w * scale) / 2,
        y: (rect.height - view.h * scale) / 2,
    };
}

/**
 * Convert a client point into viewBox coordinates, honouring the uniform scale
 * and the centring offset.
 *
 * This is the fallback used when the browser cannot supply a screen CTM (and in
 * tests); the live code prefers getScreenCTM(), which additionally accounts for
 * any CSS transform on an ancestor.
 *
 * @param client The client point (e.g. from a pointer event).
 * @param rect The element's client rect.
 * @param view The viewBox.
 * @returns The point in viewBox coordinates.
 */
export function screenToViewBox(client: Point, rect: ElementRect, view: ViewBox): Point {
    const scale = meetScale(view, rect);
    if (scale <= 0) {
        return {x: view.x, y: view.y};
    }
    const offset = meetOffset(view, rect);
    return {
        x: view.x + (client.x - rect.left - offset.x) / scale,
        y: view.y + (client.y - rect.top - offset.y) / scale,
    };
}
