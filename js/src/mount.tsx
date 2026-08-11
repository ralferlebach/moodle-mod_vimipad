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
import {RevisionViewer} from './components/RevisionViewer';
import {RevisionPlayer} from './components/RevisionPlayer';
import {MountConfig, RevisionConfig} from './types';
import {createValueTransport, ValueTransportHandle} from './value_transport';

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
    // No embedded fallback language layer: a missing Moodle language string
    // surfaces as its raw key, so gaps are visible in CI and testing instead
    // of silently rendering mixed-language UI.
    return key;
}

/**
 * Mount the editor into the given element.
 *
 * @param element The container element.
 * @param config The mount configuration.
 */
export function mount(element: HTMLElement, config: MountConfig): void {
    const transport = config.callService ?? createFetchTransport();
    const api = new ApiClient(transport, config.cmid, config.readonly ?? false);
    // Fall back to resolveString (M.str, then bundled English) whenever the host
    // has no translation for a key, instead of rendering the raw key.
    const provided = config.getString;
    const t = provided ? (key: string): string => provided(key) ?? resolveString(key) : resolveString;

    const root = createRoot(element);
    root.render(<EditorApp
        api={api}
        t={t}
        groupid={config.groupid ?? 0}
        initialView={config.initialView ?? 'canvas'}
        targetUserid={config.targetUserid ?? 0}
        arrangeIterations={config.arrangeIterations}
        arrangeShrink={config.arrangeShrink}
        embedded={config.embedded ?? false}
        showViewToggle={config.showViewToggle ?? false}
    />);
}

/**
 * Configuration for {@link mountValue}: embedding the editor on a single value.
 */
export interface ValueMountConfig {
    /** The initial serialised map value (empty for a blank map). */
    value: string;
    /** Called with the re-serialised value after every edit. */
    onChange: (valuejson: string) => void;
    /** The diagram profile to constrain the map to. Default 'conceptmap'. */
    profile?: string;
    /** The profile form config (node/relation types, shapes) from the activity. */
    formconfig?: Record<string, unknown>;
    /** Show an in-editor Map/List toggle. */
    showViewToggle?: boolean;
    /** View-only when true (submitted attempts, teacher inspection). */
    readonly?: boolean;
    /** Which view opens first. */
    initialView?: 'canvas' | 'list';
    /** Optional string getter for i18n; if absent, keys are echoed. */
    getString?: (key: string) => string | undefined;
}

/**
 * Mount the editor bound to a single self-contained value.
 *
 * For hosts that store the whole map as one value (a question attempt, a
 * database field) rather than a live activity workspace. The editor is wired to
 * an in-memory {@link createValueTransport}; the host receives the serialised
 * value through `onChange` and typically mirrors it into a hidden form field.
 *
 * @param element The container element.
 * @param config The value mount configuration.
 * @returns The value transport handle (value/state accessors).
 */
export function mountValue(element: HTMLElement, config: ValueMountConfig): ValueTransportHandle {
    const handle = createValueTransport(config.value, {
        profile: config.profile,
        onChange: config.onChange,
        readonly: config.readonly,
        formconfig: config.formconfig,
    });
    mount(element, {
        cmid: 0,
        callService: handle.transport,
        readonly: config.readonly,
        initialView: config.initialView,
        getString: config.getString,
        embedded: true,
        showViewToggle: config.showViewToggle,
    });
    return handle;
}

// The bundle is emitted as the AMD module mod_vimipad/editor_lazy. The init
// module require()s it and calls mount() with an injected transport and string
// resolver. On Moodle 5.3+ this can be replaced by the core React runtime.
export default {mount, mountRevision, mountPlayer, mountValue, createValueTransport};

/**
 * Mount the read-only revision viewer into the given element.
 *
 * @param element The container element.
 * @param config The revision configuration.
 */
export function mountRevision(element: HTMLElement, config: RevisionConfig): void {
    const transport = config.callService ?? createFetchTransport();
    const api = new ApiClient(transport, config.cmid, true);
    // Fall back to resolveString (M.str, then bundled English) whenever the host
    // has no translation for a key, instead of rendering the raw key.
    const provided = config.getString;
    const t = provided ? (key: string): string => provided(key) ?? resolveString(key) : resolveString;

    const root = createRoot(element);
    root.render(<RevisionViewer
        api={api}
        workspaceid={config.workspaceid}
        revision={config.revision}
        t={t}
    />);
}

/**
 * Mount the revision player (animated replay) into the given element.
 *
 * @param element The container element.
 * @param config The revision configuration; `maxRevision` bounds the replay
 *     (defaults to `revision`).
 */
export function mountPlayer(element: HTMLElement, config: RevisionConfig): void {
    const transport = config.callService ?? createFetchTransport();
    const api = new ApiClient(transport, config.cmid, true);
    const provided = config.getString;
    const t = provided ? (key: string): string => provided(key) ?? resolveString(key) : resolveString;

    const root = createRoot(element);
    root.render(<RevisionPlayer
        api={api}
        workspaceid={config.workspaceid}
        maxRevision={config.maxRevision ?? config.revision}
        t={t}
    />);
}
