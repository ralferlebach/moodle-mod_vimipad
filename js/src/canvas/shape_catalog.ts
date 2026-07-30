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
 * Node shape catalogue, keyed by diagram profile.
 *
 * The available node shapes and the default shape depend on the active profile
 * (Darstellungsform). Each profile permits a subset of the universal shapes and
 * names one default. When a workspace is switched to another profile in the
 * activity settings, a node may carry a shape the new profile does not permit;
 * {@link clampShape} performs that conversion by falling back to the profile's
 * default. Pure and unit tested; a later profile subplugin registry can replace
 * this table without changing the callers.
 *
 * @module     mod_vimipad/canvas/shape_catalog
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The universal node shapes. */
export type NodeShape = 'roundrect' | 'rect' | 'ellipse';

/** The universal default shapes, in picker order. */
export const ALL_SHAPES: readonly NodeShape[] = ['roundrect', 'rect', 'ellipse'];

/** Per-profile allowed shapes and default. */
interface ProfileShapes {
    allowed: readonly NodeShape[];
    default: NodeShape;
}

/**
 * The MVP profile table. Every current profile permits all three universal
 * shapes but names a profile-appropriate default; future profiles may restrict
 * the allowed set, at which point {@link clampShape} converts existing nodes.
 */
const PROFILE_SHAPES: Record<string, ProfileShapes> = {
    conceptmap: {allowed: ALL_SHAPES, default: 'roundrect'},
    mindmap: {allowed: ALL_SHAPES, default: 'ellipse'},
    tree: {allowed: ALL_SHAPES, default: 'rect'},
    semanticnetwork: {allowed: ALL_SHAPES, default: 'ellipse'},
    bubblemap: {allowed: ALL_SHAPES, default: 'ellipse'},
};

/** The fallback used for an unknown profile. */
const FALLBACK: ProfileShapes = {allowed: ALL_SHAPES, default: 'roundrect'};

/**
 * Resolve the shape entry for a profile, falling back for unknown profiles.
 *
 * @param profile The active diagram profile.
 * @returns The profile's shape configuration.
 */
function entryFor(profile: string): ProfileShapes {
    return PROFILE_SHAPES[profile] ?? FALLBACK;
}

/**
 * The shapes a profile permits, for a shape picker.
 *
 * @param profile The active diagram profile.
 * @returns The allowed shapes in picker order.
 */
export function allowedShapes(profile: string): readonly NodeShape[] {
    return entryFor(profile).allowed;
}

/**
 * The default shape for a profile (used when a node stores none).
 *
 * @param profile The active diagram profile.
 * @returns The default shape.
 */
export function defaultShape(profile: string): NodeShape {
    return entryFor(profile).default;
}

/**
 * Whether a profile permits a given shape.
 *
 * @param profile The active diagram profile.
 * @param shape The candidate shape.
 * @returns True if the shape is permitted.
 */
export function isShapeAllowed(profile: string, shape: NodeShape): boolean {
    return entryFor(profile).allowed.includes(shape);
}

/**
 * Convert a stored shape to one valid for the profile.
 *
 * Returns the shape unchanged if the profile permits it, otherwise the profile
 * default. Undefined (no stored shape) resolves to the default too. This is the
 * conversion rule applied when a workspace's profile is changed in the settings.
 *
 * @param profile The active diagram profile.
 * @param shape The stored shape, or undefined.
 * @returns A shape valid for the profile.
 */
export function clampShape(profile: string, shape: NodeShape | undefined): NodeShape {
    if (shape && isShapeAllowed(profile, shape)) {
        return shape;
    }
    return defaultShape(profile);
}
