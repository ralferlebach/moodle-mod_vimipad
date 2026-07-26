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
 * Editor entry point.
 *
 * `mount(element, config)` is the single, stable embedding contract: the host
 * (this activity today, a question type or database field later) supplies a
 * DOM element and configuration. The persistence transport is injectable, so
 * the same editor works against different back ends. When loaded as a plain
 * page script, the module also self-boots from a #vimipad-editor-root element.
 *
 * @module     mod_vimipad/mount
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createRoot} from 'react-dom/client';
import {ApiClient, createFetchTransport} from './api/service';
import {EditorApp} from './components/EditorApp';
import {MountConfig} from './types';

const FALLBACK_STRINGS: Record<string, string> = {
    'editor:add': 'Add',
    'editor:addnode': 'Add concept',
    'editor:addrelation': 'Add relation',
    'editor:actions': 'Actions',
    'editor:canvasaria': 'Map canvas with draggable concepts',
    'editor:canvasview': 'Canvas',
    'editor:canvasplaceholder': 'The graphical canvas will appear here in a later version.',
    'editor:deleterelation': 'Delete relation',
    'editor:dragnodes': 'Drag a concept onto a subject or object cell to retarget a relation',
    'editor:listview': 'List',
    'editor:loading': 'Loading…',
    'editor:locked': 'This map is locked and can no longer be edited.',
    'editor:nodelabel': 'Concept label',
    'editor:norelations': 'No relations yet. Add concepts, then connect them.',
    'editor:object': 'Object',
    'editor:relation': 'Relation',
    'editor:relations': 'Relations',
    'editor:retarget': 'Retarget',
    'editor:revision': 'Revision',
    'editor:subject': 'Subject',
    'editor:submit': 'Submit for grading',
    'editor:submitconfirm': 'Once submitted, the map is locked and can no longer be edited. Continue?',
};

/**
 * Resolve a string, preferring Moodle's string store, falling back to English.
 *
 * @param key The editor string key (without the component prefix).
 * @returns The resolved string.
 */
function resolveString(key: string): string {
    const moodle = window as unknown as {
        M?: {str?: {mod_vimipad?: Record<string, string>}; util?: {get_string?: (i: string, c: string) => string}};
    };
    // Moodle stores strings requested via strings_for_js under M.str.<component>.
    // The editor keys use a "editor:foo" convention; the string ids drop the colon
    // prefix collisions by using the same key verbatim in the language file.
    const store = moodle.M?.str?.mod_vimipad;
    if (store && store[key] !== undefined) {
        return store[key];
    }
    return FALLBACK_STRINGS[key] ?? key;
}

/**
 * Mount the editor into the given element.
 *
 * @param element The container element.
 * @param config The mount configuration.
 */
export function mount(element: HTMLElement, config: MountConfig): void {
    const transport = config.callService ?? createFetchTransport();
    const api = new ApiClient(transport, config.cmid);
    const t = config.getString ?? resolveString;

    const root = createRoot(element);
    root.render(<EditorApp api={api} t={t} />);
}

// Expose a global for the AMD bootstrap (mod_vimipad/init), which loads this
// bundle and calls mount() with an injected transport and string resolver. On
// Moodle 5.3+ this can be replaced by mounting through the core React runtime.
(window as unknown as {mod_vimipad_editor?: unknown}).mod_vimipad_editor = {mount};
