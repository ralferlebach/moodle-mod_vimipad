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
 * A ServiceTransport that persists the editor to a single JSON value.
 *
 * The activity persists the editor against service.php and its revisioned
 * operation log. A derived host - a question type's attempt, a database field's
 * value - has no such workspace: the whole map is one self-contained value in a
 * form field. This transport bridges the two. It seeds an in-memory workspace
 * from a JSON value, answers `get_workspace` from it, applies each incoming
 * operation through the activity's own reducer (so the stored value evolves
 * exactly as the editor's optimistic store does), and reports the re-serialised
 * value back to the host after every change. No network, no service.php.
 *
 * The serialised value is the same snapshot shape the activity exports, so it
 * round-trips through this transport and is directly scorable through
 * {@link \mod_vimipad\api\score}.
 *
 * @module     mod_vimipad/value_transport
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {operationToAction} from './collab/apply_remote';
import {reduce} from './store/reducer';
import {PolledOperation, ServiceTransport, WorkspaceState} from './types';

/** Options for {@link createValueTransport}. */
export interface ValueTransportOptions {
    /** The diagram profile to seed when the value carries none. Default 'conceptmap'. */
    profile?: string;
    /** Called with the re-serialised value JSON after every change. */
    onChange?: (valuejson: string) => void;
    /** When true, operations and layout writes are ignored (view-only). */
    readonly?: boolean;
    /** The profile form config (node/relation types, shapes) from the activity. */
    formconfig?: Record<string, unknown>;
}

/** The handle returned by {@link createValueTransport}. */
export interface ValueTransportHandle {
    /** The transport to hand to mount()/mountValue() via config.callService. */
    transport: ServiceTransport;
    /** The current serialised value JSON. */
    getValue: () => string;
    /** The current in-memory workspace state. */
    getState: () => WorkspaceState;
}

/**
 * An empty workspace for a given profile.
 *
 * @param profile The diagram profile key.
 * @returns A fresh, empty workspace state.
 */
function emptyState(profile: string): WorkspaceState {
    return {
        workspaceid: 1,
        revision: 1,
        locked: 0,
        profile,
        layoutjson: '',
        nodes: [],
        relations: [],
        containers: [],
    };
}

/**
 * Build a workspace state from a serialised value, falling back to empty.
 *
 * @param valuejson The serialised value, or ''.
 * @param profile The profile to use when the value carries none.
 * @returns The seeded workspace state.
 */
function stateFromValue(valuejson: string, profile: string): WorkspaceState {
    const base = emptyState(profile);
    if (!valuejson || valuejson.trim() === '') {
        return base;
    }
    let data: Record<string, unknown>;
    try {
        data = JSON.parse(valuejson) as Record<string, unknown>;
    } catch {
        return base;
    }
    if (!data || typeof data !== 'object') {
        return base;
    }
    return {
        ...base,
        profile: typeof data.profile === 'string' ? data.profile : profile,
        layoutjson: typeof data.layoutjson === 'string' ? data.layoutjson : '',
        revision: typeof data.revision === 'number' ? data.revision : 1,
        nodes: Array.isArray(data.nodes) ? data.nodes as WorkspaceState['nodes'] : [],
        relations: Array.isArray(data.relations) ? data.relations as WorkspaceState['relations'] : [],
        containers: Array.isArray(data.containers) ? data.containers as WorkspaceState['containers'] : [],
    };
}

/**
 * Serialise a workspace state to the stored value shape.
 *
 * @param state The workspace state.
 * @returns The value JSON.
 */
function serialise(state: WorkspaceState): string {
    return JSON.stringify({
        profile: state.profile,
        layoutjson: state.layoutjson,
        revision: state.revision,
        nodes: state.nodes,
        relations: state.relations,
        containers: state.containers ?? [],
    });
}

/**
 * Create a value-backed transport seeded from a serialised map value.
 *
 * @param valuejson The initial value JSON (empty for a blank map).
 * @param options Profile, change callback and read-only flag.
 * @returns A handle with the transport and value/state accessors.
 */
export function createValueTransport(
    valuejson: string,
    options: ValueTransportOptions = {}
): ValueTransportHandle {
    const profile = options.profile ?? 'conceptmap';
    let state = stateFromValue(valuejson, profile);

    const emit = (): void => {
        if (options.onChange) {
            options.onChange(serialise(state));
        }
    };

    const transport: ServiceTransport = async (
        method: string,
        args: Record<string, unknown>
    ): Promise<unknown> => {
        switch (method) {
            case 'mod_vimipad_get_workspace':
                return {
                    ...state,
                    formconfig: options.formconfig,
                    canmanage: false,
                };
            case 'mod_vimipad_get_constraint_status':
                return {configured: false, satisfied: true, messages: []};
            case 'mod_vimipad_get_journal_entries':
                return {entries: []};
            case 'mod_vimipad_poll_changes':
                return {
                    revision: state.revision,
                    locked: state.locked,
                    profile: state.profile,
                    operations: [],
                    hasmore: false,
                    layoutjson: state.layoutjson,
                    leases: [],
                };
            case 'mod_vimipad_apply_operation': {
                const stableid = typeof args.stableid === 'string' ? args.stableid : '';
                if (options.readonly) {
                    return {revision: state.revision, stableid};
                }
                const op: PolledOperation = {
                    revision: state.revision + 1,
                    operationtype: String(args.operationtype ?? ''),
                    payloadjson: String(args.payloadjson ?? '{}'),
                    userid: 0,
                };
                const action = operationToAction(op);
                if (action) {
                    state = reduce(state, action);
                    state = {...state, revision: state.revision + 1};
                    emit();
                }
                const payloadid = payloadStableId(op.payloadjson);
                return {revision: state.revision, stableid: payloadid || stableid};
            }
            case 'mod_vimipad_save_layout': {
                if (options.readonly) {
                    return {};
                }
                state = {...state, layoutjson: String(args.layoutjson ?? '')};
                emit();
                return {};
            }
            default:
                // Locks, snapshots, presence and any other call are benign no-ops
                // for a self-contained value with a single author.
                return {};
        }
    };

    return {
        transport,
        getValue: (): string => serialise(state),
        getState: (): WorkspaceState => state,
    };
}

/**
 * The stableid carried by an operation payload, if any.
 *
 * @param payloadjson The operation payload JSON.
 * @returns The stableid, or ''.
 */
function payloadStableId(payloadjson: string): string {
    try {
        const payload = JSON.parse(payloadjson) as Record<string, unknown>;
        return typeof payload.stableid === 'string' ? payload.stableid : '';
    } catch {
        return '';
    }
}
