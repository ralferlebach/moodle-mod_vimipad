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
 * Editor icon assets.
 *
 * Menu and toolbar glyphs use Font Awesome 6 Free, which Moodle bundles and
 * loads on every page, so no icon dependency ships with the plugin. The three
 * node shapes and the three connection affordances have no clean Font Awesome
 * equivalent and are drawn as small inline SVGs here so they match exactly what
 * the canvas renders. Icons are decorative (aria-hidden); their accessible name
 * comes from the button that hosts them.
 *
 * @module     mod_vimipad/canvas/icons
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {NodeShape} from './shape_catalog';

/** Font Awesome 6 Free classes for the editor's menu and toolbar actions. */
export const FA = {
    addNode: 'fa-solid fa-plus',
    addRelation: 'fa-solid fa-arrow-right-arrow-left',
    canvasView: 'fa-solid fa-diagram-project',
    listView: 'fa-solid fa-list',
    submit: 'fa-solid fa-clipboard-check',
    format: 'fa-solid fa-pen',
    shape: 'fa-solid fa-shapes',
    fill: 'fa-solid fa-palette',
    text: 'fa-solid fa-font',
    fontSizeUp: 'fa-solid fa-plus',
    fontSizeDown: 'fa-solid fa-minus',
    textColor: 'fa-solid fa-palette',
    highlight: 'fa-solid fa-highlighter',
    reset: 'fa-solid fa-eraser',
    duplicate: 'fa-solid fa-clone',
    delete: 'fa-solid fa-trash',
    move: 'fa-solid fa-up-down-left-right',
    resize: 'fa-solid fa-up-right-and-down-left-from-center',
} as const;

/**
 * A Font Awesome glyph. Decorative; the hosting control carries the label.
 *
 * @param props The icon class name and optional extra classes.
 * @returns The icon element.
 */
export function Icon({name, className}: {name: string; className?: string}): React.ReactElement {
    return <i className={`${name}${className ? ' ' + className : ''}`} aria-hidden="true" />;
}

/**
 * A small inline-SVG preview of a node shape, for the shape picker.
 *
 * @param props The shape and pixel size.
 * @returns The shape glyph element.
 */
export function ShapeGlyph({shape, size = 20}: {shape: NodeShape; size?: number}): React.ReactElement {
    const common = {fill: 'none', stroke: 'currentColor', strokeWidth: 1.6};
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            {shape === 'ellipse'
                ? <ellipse cx={12} cy={12} rx={9} ry={6.5} {...common} />
                : <rect x={3} y={5.5} width={18} height={13} rx={shape === 'roundrect' ? 4 : 0} {...common} />}
        </svg>
    );
}

/** Two nodes joined by a directed connector (arrow to the target). */
export function ConnectDirectedGlyph({size = 20}: {size?: number}): React.ReactElement {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1={7} y1={12} x2={17} y2={12} stroke="currentColor" strokeWidth={1.6}
                markerEnd="url(#vimipad-glyph-arrow)" />
            <defs>
                <marker id="vimipad-glyph-arrow" viewBox="0 0 10 10" refX="8" refY="5"
                    markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                </marker>
            </defs>
            <circle cx={5} cy={12} r={3} fill="none" stroke="currentColor" strokeWidth={1.6} />
            <circle cx={19} cy={12} r={3} fill="none" stroke="currentColor" strokeWidth={1.6} />
        </svg>
    );
}

/** Two nodes joined by an undirected connector (a plain line). */
export function ConnectUndirectedGlyph({size = 20}: {size?: number}): React.ReactElement {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1={8} y1={12} x2={16} y2={12} stroke="currentColor" strokeWidth={1.6} />
            <circle cx={5} cy={12} r={3} fill="none" stroke="currentColor" strokeWidth={1.6} />
            <circle cx={19} cy={12} r={3} fill="none" stroke="currentColor" strokeWidth={1.6} />
        </svg>
    );
}

/** A connection to a single target node, whose centre is filled (connect zone). */
export function ConnectTargetGlyph({size = 20}: {size?: number}): React.ReactElement {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <line x1={3} y1={12} x2={15} y2={12} stroke="currentColor" strokeWidth={1.6}
                markerEnd="url(#vimipad-glyph-arrow2)" />
            <defs>
                <marker id="vimipad-glyph-arrow2" viewBox="0 0 10 10" refX="8" refY="5"
                    markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                </marker>
            </defs>
            <circle cx={19} cy={12} r={3.5} fill="none" stroke="currentColor" strokeWidth={1.6} />
            <circle cx={19} cy={12} r={1.4} fill="currentColor" />
        </svg>
    );
}
