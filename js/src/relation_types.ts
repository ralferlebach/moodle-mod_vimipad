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
 * Single source of truth for how typed relations look.
 *
 * A display type declares which relation types it offers (via its PHP
 * subplugin's get_relation_types, transported as formconfig.relationtypes). The
 * relation menu, the list view and the canvas all read the style here so a new
 * typed relation only needs an entry in this map plus a lang string
 * `editor:reltype_<key>` — no per-component special casing.
 *
 * Colours use CSS custom properties with a literal fallback, matching the rest
 * of the editor, so they survive the standalone SVG/PNG export.
 *
 * @module     mod_vimipad/relation_types
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** How a typed relation is drawn. */
export interface RelationTypeStyle {
    /** Stroke colour (CSS var with fallback). */
    color: string;
    /** Optional stroke dash pattern; omitted for a solid line. */
    dash?: string;
}

/** The style for each known relation type key. Unlisted types render plain. */
export const RELATION_TYPE_STYLES: Record<string, RelationTypeStyle> = {
    // Argument maps.
    support: {color: 'var(--vimipad-relation-support, #2e7d32)'},
    attack: {color: 'var(--vimipad-relation-attack, #c0392b)', dash: '7 4'},
    // Causal / system maps (link polarity).
    positive: {color: 'var(--vimipad-relation-positive, #2e7d32)'},
    negative: {color: 'var(--vimipad-relation-negative, #c0392b)', dash: '4 4'},
    // Ontology (typed knowledge relations).
    isa: {color: 'var(--vimipad-relation-isa, #1565c0)'},
    partof: {color: 'var(--vimipad-relation-partof, #6a1b9a)', dash: '2 3'},
    associated: {color: 'var(--vimipad-relation-associated, #607d8b)'},
    // Semantic network (further link types).
    instanceof: {color: 'var(--vimipad-relation-instanceof, #00838f)'},
    hasproperty: {color: 'var(--vimipad-relation-hasproperty, #ef6c00)', dash: '1 3'},
    // Flow chart (decision branches); 'sequence' is the neutral default (no style).
    yes: {color: 'var(--vimipad-relation-yes, #2e7d32)'},
    no: {color: 'var(--vimipad-relation-no, #c0392b)', dash: '6 4'},
};

/**
 * The style for a relation type, or null for an untyped/plain relation.
 *
 * @param type The relation type key (e.g. 'attack', 'positive'); may be undefined.
 * @returns The style, or null when the type is unset or unknown.
 */
export function relationTypeStyle(type?: string): RelationTypeStyle | null {
    return type ? (RELATION_TYPE_STYLES[type] ?? null) : null;
}
