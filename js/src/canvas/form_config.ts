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
 * Bridge between the backend form (display type) config and the renderer.
 *
 * The backend vimipadform subplugin registry ships the allowed shapes, default
 * shape, connector line style and bifurcation for the active profile. These
 * helpers prefer that config when present and fall back to the built-in
 * {@link module:mod_vimipad/canvas/shape_catalog} table and profile defaults
 * otherwise, so an older server (or a missing subplugin) still renders sanely.
 *
 * @module     mod_vimipad/canvas/form_config
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FormConfig} from '../types';
import {ALL_SHAPES, NodeShape, allowedShapes, defaultShape} from './shape_catalog';

/** The connector line styles the renderer understands. */
export type LineStyle = 'straight' | 'curved' | 'orthogonal';

/** The valid line styles, for validating backend-supplied values. */
const LINE_STYLES: readonly LineStyle[] = ['straight', 'curved', 'orthogonal'];

/**
 * Whether a string is one of the universal node shapes.
 *
 * @param value The candidate value.
 * @returns True if it is a known shape.
 */
function isNodeShape(value: string): value is NodeShape {
    return (ALL_SHAPES as readonly string[]).includes(value);
}

/**
 * The node shapes offered for this form, preferring the backend config.
 *
 * @param config The active form config, if any.
 * @param profile The active profile (used for the built-in fallback).
 * @returns The allowed shapes in picker order.
 */
export function formShapes(config: FormConfig | undefined, profile: string): readonly NodeShape[] {
    const fromConfig = (config?.allowedshapes ?? []).filter(isNodeShape);
    return fromConfig.length > 0 ? fromConfig : allowedShapes(profile);
}

/**
 * The default node shape for this form, preferring the backend config.
 *
 * @param config The active form config, if any.
 * @param profile The active profile (used for the built-in fallback).
 * @returns The default shape.
 */
export function formDefaultShape(config: FormConfig | undefined, profile: string): NodeShape {
    const fromConfig = config?.defaultshape;
    return fromConfig !== undefined && isNodeShape(fromConfig) ? fromConfig : defaultShape(profile);
}

/**
 * Clamp a stored shape to one this form permits, else its default.
 *
 * @param config The active form config, if any.
 * @param profile The active profile (used for the built-in fallback).
 * @param shape The stored shape, or undefined.
 * @returns A shape valid for this form.
 */
export function formClampShape(
    config: FormConfig | undefined,
    profile: string,
    shape: NodeShape | undefined
): NodeShape {
    const allowed = formShapes(config, profile);
    if (shape && allowed.includes(shape)) {
        return shape;
    }
    return formDefaultShape(config, profile);
}

/**
 * The connector line style for this form, preferring the backend config.
 *
 * @param config The active form config, if any.
 * @param fallback The built-in line style for the profile.
 * @returns The line style to render.
 */
export function formLine(config: FormConfig | undefined, fallback: LineStyle): LineStyle {
    const fromConfig = config?.line;
    return fromConfig !== undefined && (LINE_STYLES as readonly string[]).includes(fromConfig)
        ? fromConfig as LineStyle
        : fallback;
}

/**
 * Whether this form uses a shared bifurcation (org-chart bus routing).
 *
 * @param config The active form config, if any.
 * @param fallbackShared The built-in shared flag for the profile.
 * @returns True if connectors share a common trunk and bus.
 */
export function formShared(config: FormConfig | undefined, fallbackShared: boolean): boolean {
    return config !== undefined ? config.bifurcation === 'shared' : fallbackShared;
}
