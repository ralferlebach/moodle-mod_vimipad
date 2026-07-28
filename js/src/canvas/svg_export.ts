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
 * Client-side export of the live canvas SVG to a standalone file.
 *
 * The on-screen SVG is largely self-contained (inline fill/stroke attributes and
 * CSS-variable fallbacks), so a standalone export mainly needs a fixed viewBox
 * covering the content, an explicit text colour (for currentColor) and a
 * background. The bounds computation is pure and unit-tested; the serialization
 * step operates on a cloned DOM node.
 *
 * @module     mod_vimipad/canvas/svg_export
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, SizeMap, VimiNode} from '../types';

const SVG_NS = 'http://www.w3.org/2000/svg';
const DEFAULT_NODE_W = 120;
const DEFAULT_NODE_H = 44;
const TEXT_COLOR = '#495057';
const BACKGROUND = '#ffffff';

/** A rectangle in canvas coordinates. */
export interface Bounds {
    x: number;
    y: number;
    w: number;
    h: number;
}

/**
 * Compute the bounding box of all placed nodes, padded, for the export viewBox.
 *
 * Falls back to a sensible default box when there are no positioned nodes.
 *
 * @param nodes The nodes.
 * @param layout Stored node positions (centre points).
 * @param sizes Stored node sizes; defaults are used when absent.
 * @param pad Padding added on every side.
 * @param fallback Box to use when nothing is placed.
 * @returns The padded content bounds.
 */
export function computeContentBounds(
    nodes: VimiNode[],
    layout: LayoutMap,
    sizes: SizeMap,
    pad: number,
    fallback: Bounds
): Bounds {
    let minx = Infinity;
    let miny = Infinity;
    let maxx = -Infinity;
    let maxy = -Infinity;
    let found = false;

    for (const node of nodes) {
        const pos = layout[node.stableid];
        if (!pos) {
            continue;
        }
        found = true;
        const size = sizes[node.stableid];
        const halfw = (size?.w ?? DEFAULT_NODE_W) / 2;
        const halfh = (size?.h ?? DEFAULT_NODE_H) / 2;
        minx = Math.min(minx, pos.x - halfw);
        miny = Math.min(miny, pos.y - halfh);
        maxx = Math.max(maxx, pos.x + halfw);
        maxy = Math.max(maxy, pos.y + halfh);
    }

    if (!found) {
        return fallback;
    }

    return {
        x: Math.round(minx - pad),
        y: Math.round(miny - pad),
        w: Math.round(maxx - minx + pad * 2),
        h: Math.round(maxy - miny + pad * 2),
    };
}

/**
 * Serialize a canvas SVG element to a standalone SVG document string.
 *
 * @param svg The live SVG element to export.
 * @param bounds The viewBox/content bounds.
 * @returns The standalone SVG XML.
 */
export function serializeCanvasSvg(svg: SVGSVGElement, bounds: Bounds): string {
    const clone = svg.cloneNode(true) as SVGSVGElement;

    clone.setAttribute('viewBox', `${bounds.x} ${bounds.y} ${bounds.w} ${bounds.h}`);
    clone.setAttribute('width', String(bounds.w));
    clone.setAttribute('height', String(bounds.h));
    clone.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    clone.setAttribute('style', `color:${TEXT_COLOR}`);
    clone.removeAttribute('tabindex');

    // Drop interaction-only overlays that should not appear in the export.
    clone.querySelectorAll(
        '.vimipad-canvas-seloutline, .vimipad-canvas-handle, .vimipad-canvas-connector'
    ).forEach((el) => el.remove());

    // Opaque background so the map is not transparent in viewers.
    const bg = document.createElementNS(SVG_NS, 'rect');
    bg.setAttribute('x', String(bounds.x));
    bg.setAttribute('y', String(bounds.y));
    bg.setAttribute('width', String(bounds.w));
    bg.setAttribute('height', String(bounds.h));
    bg.setAttribute('fill', BACKGROUND);
    clone.insertBefore(bg, clone.firstChild);

    const xml = new XMLSerializer().serializeToString(clone);
    return `<?xml version="1.0" encoding="UTF-8"?>\n${xml}`;
}

/**
 * Trigger a browser download of the given SVG element as a standalone file.
 *
 * @param svg The live SVG element.
 * @param bounds The content bounds.
 * @param filename The download filename.
 * @returns void
 */
export function downloadCanvasSvg(svg: SVGSVGElement, bounds: Bounds, filename: string): void {
    const doc = serializeCanvasSvg(svg, bounds);
    const blob = new Blob([doc], {type: 'image/svg+xml;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
