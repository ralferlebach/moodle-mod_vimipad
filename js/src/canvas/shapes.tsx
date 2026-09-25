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
 * Pure presentational helpers for the canvas: the shared node-label CSS and the
 * SVG shape outline element. Extracted from CanvasView so the component stays
 * focused on state and interaction.
 *
 * @module     mod_vimipad/canvas/shapes
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {NodeShape} from './shape_catalog';
import {TextStyle} from './node_style';

/** Base label font size, in canvas units; each size step adds 2. */
export const BASE_FONT = 13;

/**
 * Shared CSS for the node label div and its inline editor, so switching into
 * edit mode does not move or recolour the text. Centred, wrapping, multi-line.
 *
 * @param text The text style, if any.
 * @returns CSS properties for an HTML box.
 */
export function labelBox(text: TextStyle | undefined): React.CSSProperties {
    const family = text?.font === 'serif' ? 'Georgia, "Times New Roman", serif'
        : text?.font === 'mono' ? 'ui-monospace, Menlo, Consolas, monospace'
            : text?.font === 'sans' ? 'system-ui, -apple-system, "Segoe UI", sans-serif'
                : 'inherit';
    return {
        boxSizing: 'border-box',
        width: '100%',
        height: '100%',
        margin: 0,
        padding: '2px 6px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center',
        whiteSpace: 'pre-wrap',
        overflowWrap: 'anywhere',
        wordBreak: 'break-word',
        lineHeight: 1.2,
        fontFamily: family,
        fontSize: `${BASE_FONT + (text?.size ?? 0) * 2}px`,
        fontWeight: text?.bold ? 700 : undefined,
        fontStyle: text?.italic ? 'italic' : undefined,
        textDecoration: text?.underline ? 'underline' : undefined,
        color: text?.color ?? 'var(--vimipad-node-text, #212529)',
    };
}

/**
 * Render the outline element for a shape at the origin (node group is centred).
 *
 * @param shape The node shape.
 * @param w The box width.
 * @param h The box height.
 * @param extra Extra SVG props (fill, stroke, class …).
 * @returns The shape element.
 */
/**
 * How far a parallelogram is sheared, as a share of half its height.
 *
 * Keeping the slant proportional to the height means the input/output symbol
 * stays recognisable at any node size.
 */
export const PARALLELOGRAM_SLANT = 0.6;

export function shapeElement(
    shape: NodeShape,
    w: number,
    h: number,
    extra: React.SVGProps<SVGRectElement & SVGEllipseElement & SVGPolygonElement & SVGPathElement>
): React.ReactElement {
    const hw = w / 2;
    const hh = h / 2;

    if (shape === 'ellipse') {
        return <ellipse cx={0} cy={0} rx={hw} ry={hh} {...extra} />;
    }

    if (shape === 'diamond') {
        // Decision: a four-point rhombus touching the middle of each side.
        const points = `0,${-hh} ${hw},0 0,${hh} ${-hw},0`;
        return <polygon points={points} {...extra} />;
    }

    if (shape === 'parallelogram') {
        // Input / output: a rectangle sheared horizontally. The slant is a share
        // of the height so the symbol keeps its proportions as the node grows.
        const slant = Math.min(hw / 2, hh * PARALLELOGRAM_SLANT);
        const points = [
            `${-hw + slant},${-hh}`,
            `${hw},${-hh}`,
            `${hw - slant},${hh}`,
            `${-hw},${hh}`,
        ].join(' ');
        return <polygon points={points} {...extra} />;
    }

    if (shape === 'terminator') {
        // Start / end: a capsule, i.e. a rectangle whose corner radius is half
        // its height, so the short sides are exact semicircles.
        return <rect x={-hw} y={-hh} width={w} height={h} rx={hh} ry={hh} {...extra} />;
    }

    if (shape === 'stock') {
        // An accumulation is drawn as a plain box with a heavier outline, so it
        // reads as a container rather than as a step in a process.
        return <rect x={-hw} y={-hh} width={w} height={h} rx={2} strokeWidth={3} {...extra} />;
    }

    if (shape === 'cloud' || shape === 'cloudsink') {
        // Source and sink both sit outside the model boundary, but they are
        // opposites, so they must not look alike. Both are the same cloud, one
        // flipped: the source keeps a flat base and its lobes on top, as though
        // the flow gathered inside it, while the sink has a flat roof and its
        // lobes below, as though the flow rained out of it.
        const flip = shape === 'cloudsink' ? -1 : 1;
        const r = hh * 0.75;
        const y = (v: number): number => flip * v;
        const d = [
            `M ${-hw},${y(hh)}`,
            `L ${-hw + r * 0.3},${y(hh)}`,
            `A ${r},${r} 0 0 ${flip > 0 ? 1 : 0} ${-hw * 0.35},${y(-hh * 0.1)}`,
            `A ${r},${r} 0 0 ${flip > 0 ? 1 : 0} ${hw * 0.15},${y(-hh * 0.55)}`,
            `A ${r},${r} 0 0 ${flip > 0 ? 1 : 0} ${hw * 0.8},${y(hh * 0.1)}`,
            `A ${r * 0.8},${r * 0.8} 0 0 ${flip > 0 ? 1 : 0} ${hw},${y(hh)}`,
            'Z',
        ].join(' ');
        return <path d={d} {...extra} />;
    }

    if (shape === 'valve') {
        // A rate is the classic bow tie: two triangles meeting at the axis.
        const vw = hw * 0.8;
        const points = [
            `${-vw},${-hh}`, `${-vw},${hh}`, `${vw},${-hh}`, `${vw},${hh}`,
        ];
        const d = `M ${points[0]} L ${points[1]} L ${points[2]} L ${points[3]} Z`;
        return <path d={d} {...extra} />;
    }

    if (shape === 'delay') {
        // A delay is an hourglass: the bow tie turned onto its side, which is
        // how a lag reads next to a rate.
        const dh = hh * 0.9;
        const d = `M ${-hw},${-dh} L ${hw},${-dh} L ${-hw},${dh} L ${hw},${dh} Z`;
        return <path d={d} {...extra} />;
    }

    if (shape === 'parameter') {
        // A constant is an auxiliary with a marker: the ellipse plus a bar, so
        // it stays distinguishable from a derived variable at a glance.
        const d = [
            `M ${hw},0`,
            `A ${hw},${hh} 0 1 1 ${-hw},0`,
            `A ${hw},${hh} 0 1 1 ${hw},0`,
            'Z',
            `M ${-hw * 0.45},${hh * 0.55}`,
            `L ${hw * 0.45},${hh * 0.55}`,
        ].join(' ');
        return <path d={d} {...extra} />;
    }

    return <rect x={-hw} y={-hh} width={w} height={h} rx={shape === 'roundrect' ? 10 : 0} {...extra} />;
}
