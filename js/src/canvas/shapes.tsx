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
export function shapeElement(
    shape: NodeShape,
    w: number,
    h: number,
    extra: React.SVGProps<SVGRectElement & SVGEllipseElement>
): React.ReactElement {
    if (shape === 'ellipse') {
        return <ellipse cx={0} cy={0} rx={w / 2} ry={h / 2} {...extra} />;
    }
    return <rect x={-w / 2} y={-h / 2} width={w} height={h} rx={shape === 'roundrect' ? 10 : 0} {...extra} />;
}
