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
 * Node visual style: shape and fill, plus text styling.
 *
 * The style lives in the node's metadatajson (revisioned via the operation log,
 * so it is part of the graded snapshot and propagates to collaborators like any
 * other node_update). This module parses that JSON into a typed style and
 * serialises a style back, dropping malformed values so a bad payload can never
 * crash rendering. It mirrors the server-side validation in
 * \mod_vimipad\local\style\node_style.
 *
 * @module     mod_vimipad/canvas/node_style
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ALL_SHAPES, NodeShape} from './shape_catalog';

/** A universal font family choice, applied to a text selection. */
export type FontFamily = 'sans' | 'serif' | 'mono';

/** The universal font families, in picker order. */
export const FONT_FAMILIES: readonly FontFamily[] = ['sans', 'serif', 'mono'];

/** Text styling applied to the node label. */
export interface TextStyle {
    /** Font family, or undefined for the theme default. */
    font?: FontFamily;
    /** Relative font scale in steps (…-1, 0, 1…); 0 is the base size. */
    size?: number;
    /** Text colour as #rrggbb, or undefined for the theme default. */
    color?: string;
    /** Background/highlight colour as #rrggbb, or undefined for none. */
    background?: string;
}

/** The parsed visual style of a node. All fields optional. */
export interface NodeStyle {
    shape?: NodeShape;
    /** Node fill colour as #rrggbb, or undefined for the theme default. */
    fill?: string;
    text?: TextStyle;
}

/** The smallest and largest font size step accepted. */
const MIN_SIZE_STEP = -3;
const MAX_SIZE_STEP = 6;

const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

/**
 * Return the value if it is a valid #rrggbb colour, else undefined.
 *
 * @param value The candidate value.
 * @returns A normalised lower-case colour, or undefined.
 */
function cleanColor(value: unknown): string | undefined {
    return typeof value === 'string' && HEX_COLOR.test(value) ? value.toLowerCase() : undefined;
}

/**
 * Return the value if it is a known shape, else undefined.
 *
 * @param value The candidate value.
 * @returns The shape, or undefined.
 */
function cleanShape(value: unknown): NodeShape | undefined {
    return typeof value === 'string' && (ALL_SHAPES as readonly string[]).includes(value)
        ? value as NodeShape
        : undefined;
}

/**
 * Return the value if it is a known font family, else undefined.
 *
 * @param value The candidate value.
 * @returns The font family, or undefined.
 */
function cleanFont(value: unknown): FontFamily | undefined {
    return typeof value === 'string' && (FONT_FAMILIES as readonly string[]).includes(value)
        ? value as FontFamily
        : undefined;
}

/**
 * Clamp a font size step to the accepted range, else undefined.
 *
 * @param value The candidate value.
 * @returns An integer step within range, or undefined.
 */
function cleanSize(value: unknown): number | undefined {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return undefined;
    }
    const step = Math.round(value);
    if (step === 0) {
        return undefined;
    }
    return Math.max(MIN_SIZE_STEP, Math.min(MAX_SIZE_STEP, step));
}

/**
 * Clamp the next relative font size step after applying a delta.
 *
 * @param current The current step, or undefined (treated as 0/base).
 * @param delta The change to apply (+1 larger, -1 smaller).
 * @returns The new step within the accepted range.
 */
export function nextSizeStep(current: number | undefined, delta: number): number {
    return Math.max(MIN_SIZE_STEP, Math.min(MAX_SIZE_STEP, (current ?? 0) + delta));
}

/**
 * Parse a node's metadatajson into a typed style, dropping malformed values.
 *
 * @param metadatajson The raw metadata JSON, or undefined.
 * @returns The parsed style; an empty object if absent or malformed.
 */
export function parseNodeStyle(metadatajson: string | undefined): NodeStyle {
    if (!metadatajson) {
        return {};
    }
    let raw: unknown;
    try {
        raw = JSON.parse(metadatajson);
    } catch {
        return {};
    }
    if (!raw || typeof raw !== 'object') {
        return {};
    }
    const obj = raw as Record<string, unknown>;
    const style: NodeStyle = {};

    const shape = cleanShape(obj.shape);
    if (shape) {
        style.shape = shape;
    }
    const fill = cleanColor(obj.fill);
    if (fill) {
        style.fill = fill;
    }

    if (obj.text && typeof obj.text === 'object') {
        const t = obj.text as Record<string, unknown>;
        const text: TextStyle = {};
        const font = cleanFont(t.font);
        if (font) {
            text.font = font;
        }
        const size = cleanSize(t.size);
        if (size !== undefined) {
            text.size = size;
        }
        const color = cleanColor(t.color);
        if (color) {
            text.color = color;
        }
        const background = cleanColor(t.background);
        if (background) {
            text.background = background;
        }
        if (Object.keys(text).length > 0) {
            style.text = text;
        }
    }

    return style;
}

/**
 * Merge a partial style change onto a node's existing metadatajson and return
 * the new metadatajson string. Only known, valid fields survive.
 *
 * @param metadatajson The node's current metadata JSON, or undefined.
 * @param change The style fields to set; a field set to undefined is removed.
 * @returns The serialised metadata JSON for the merged style.
 */
export function withNodeStyle(metadatajson: string | undefined, change: NodeStyle): string {
    const merged: NodeStyle = {...parseNodeStyle(metadatajson)};
    if ('shape' in change) {
        merged.shape = change.shape;
    }
    if ('fill' in change) {
        merged.fill = change.fill;
    }
    if ('text' in change) {
        merged.text = {...merged.text, ...change.text};
    }
    return serialiseNodeStyle(merged);
}

/**
 * Serialise a style to metadata JSON, omitting empty values.
 *
 * @param style The style to serialise.
 * @returns The metadata JSON string.
 */
export function serialiseNodeStyle(style: NodeStyle): string {
    const out: Record<string, unknown> = {};
    if (style.shape) {
        out.shape = style.shape;
    }
    if (style.fill) {
        out.fill = style.fill;
    }
    if (style.text) {
        const t: Record<string, unknown> = {};
        if (style.text.font) {
            t.font = style.text.font;
        }
        if (style.text.size) {
            t.size = style.text.size;
        }
        if (style.text.color) {
            t.color = style.text.color;
        }
        if (style.text.background) {
            t.background = style.text.background;
        }
        if (Object.keys(t).length > 0) {
            out.text = t;
        }
    }
    return JSON.stringify(out);
}
