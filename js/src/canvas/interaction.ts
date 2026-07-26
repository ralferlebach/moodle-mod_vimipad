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
 * Canvas interaction state: selection and inline text editing.
 *
 * Pure and side-effect free so the interaction rules can be unit tested without
 * a browser. Models the behaviours from the Visual Maps requirements: a click
 * selects/activates an element (and shows its menu affordances), a double-click
 * opens inline text editing, Enter ends editing, ESC clears the selection, and
 * Del removes the selected element (but never while text is being edited).
 *
 * @module     mod_vimipad/canvas/interaction
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** An addressable element on the canvas. */
export type Target =
    | {kind: 'node'; id: string}
    | {kind: 'relation'; id: string};

/** The interaction state for the canvas. */
export interface InteractionState {
    /** The currently selected/active element, or null. */
    selected: Target | null;
    /** The element whose text is being edited inline, or null. */
    editing: Target | null;
}

/** The empty interaction state: nothing selected, nothing editing. */
export const initialInteraction: InteractionState = {
    selected: null,
    editing: null,
};

/** Actions that drive the interaction state. */
export type InteractionAction =
    /** Single click on an element: select it (and end any active edit). */
    | {kind: 'select'; target: Target}
    /** ESC or click on empty canvas: clear selection and editing. */
    | {kind: 'clear'}
    /** Double click on an element's text: begin inline editing. */
    | {kind: 'startEditing'; target: Target}
    /** Enter or blur: stop editing (selection is kept). */
    | {kind: 'stopEditing'};

/**
 * Compare two targets for identity.
 *
 * @param a The first target, or null.
 * @param b The second target, or null.
 * @returns True if both are null or both address the same element.
 */
function sameTarget(a: Target | null, b: Target | null): boolean {
    if (a === null || b === null) {
        return a === b;
    }
    return a.kind === b.kind && a.id === b.id;
}

/**
 * Produce the next interaction state for an action. Pure.
 *
 * @param state The current state.
 * @param action The action to apply.
 * @returns The next state.
 */
export function interactionReduce(state: InteractionState, action: InteractionAction): InteractionState {
    switch (action.kind) {
        case 'select':
            // A single click selects and ends any active edit.
            return {selected: action.target, editing: null};
        case 'clear':
            return {selected: null, editing: null};
        case 'startEditing':
            return {selected: action.target, editing: action.target};
        case 'stopEditing':
            return {selected: state.selected, editing: null};
        default:
            return state;
    }
}

/**
 * The element Del should remove: the selection, but only when not editing.
 *
 * @param state The current state.
 * @returns The target to delete, or null if deletion is not applicable.
 */
export function deletableTarget(state: InteractionState): Target | null {
    if (state.editing !== null) {
        return null;
    }
    return state.selected;
}

/**
 * Whether the given element is currently selected.
 *
 * @param state The current state.
 * @param kind The element kind.
 * @param id The element id.
 * @returns True if selected.
 */
export function isSelected(state: InteractionState, kind: Target['kind'], id: string): boolean {
    return sameTarget(state.selected, {kind, id});
}

/**
 * Whether the given element's text is currently being edited.
 *
 * @param state The current state.
 * @param kind The element kind.
 * @param id The element id.
 * @returns True if editing.
 */
export function isEditing(state: InteractionState, kind: Target['kind'], id: string): boolean {
    return sameTarget(state.editing, {kind, id});
}
