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
        layoutjson: layoutFromValue(data),
        revision: typeof data.revision === 'number' ? data.revision : 1,
        nodes: Array.isArray(data.nodes) ? data.nodes as WorkspaceState['nodes'] : [],
        relations: Array.isArray(data.relations)
            ? (data.relations as WorkspaceState['relations']).map(normaliseRelation)
            : [],
        containers: Array.isArray(data.containers) ? data.containers as WorkspaceState['containers'] : [],
    };
}

/**
 * Read the stored positions from either value shape.
 *
 * The editor's own values carry them as a JSON string under "layoutjson". A
 * submitted snapshot carries them decoded, as an object under "layout" - that is
 * how snapshot_service writes them. Reading only the first meant a snapshot
 * shown through this transport, as the gallery does, arrived without positions,
 * and the editor invented a fallback layout: the same map, drawn with a
 * different shape.
 *
 * @param data The parsed value.
 * @returns The layout as a JSON string, or '' when there is none.
 */
function layoutFromValue(data: Record<string, unknown>): string {
    if (typeof data.layoutjson === 'string') {
        return data.layoutjson;
    }
    if (data.layout && typeof data.layout === 'object') {
        return JSON.stringify(data.layout);
    }
    return '';
}

/**
 * Give a relation the numeric direction the canvas compares against.
 *
 * A snapshot stores relation fields as the database returned them, so the
 * direction is the string "1". The canvas draws an arrowhead only for
 * direction === 1 or 2, so a string silently dropped every arrow.
 *
 * @param relation The relation as stored.
 * @returns The relation with a numeric direction.
 */
function normaliseRelation<T extends {direction?: unknown}>(relation: T): T {
    const direction = Number(relation.direction ?? 0);
    return {...relation, direction: Number.isFinite(direction) ? direction : 0};
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
                const operationtype = String(args.operationtype ?? '');
                const op: PolledOperation = {
                    revision: state.revision + 1,
                    operationtype,
                    // The server mints the stable id when a create arrives
                    // without one - and the editor relies on that. Leaving it
                    // out here dropped the element from the value and returned
                    // an empty id, see withMintedStableId().
                    payloadjson: withMintedStableId(operationtype, String(args.payloadjson ?? '{}')),
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
            case 'mod_vimipad_acquire_lock':
            case 'mod_vimipad_renew_lock':
                // A value has a single author, so every lease is granted. The
                // editor reads "acquired" before a drag or an inline edit; the
                // empty reply this used to give read as "refused", and the drag
                // was dropped before the pointer had even moved - a node in a
                // database field or a quiz answer could never be dragged.
                return {
                    acquired: !options.readonly,
                    userid: 0,
                    timeexpires: Math.floor(Date.now() / 1000) + 3600,
                };
            default:
                // Snapshots, presence and any other call are benign no-ops for
                // a self-contained value with a single author.
                return {};
        }
    };

    return {
        transport,
        getValue: (): string => serialise(state),
        getState: (): WorkspaceState => state,
    };
}

/** Stable id prefix per create operation, as the server's stable_id class uses. */
const CREATE_PREFIX: Record<string, string> = {
    node_create: 'node',
    relation_create: 'rel',
    container_create: 'cont',
};

/**
 * Give a create operation the stable id the server would have minted.
 *
 * The editor creates nodes and relations without a stable id and takes the id
 * the server returns. This transport stands in for the server in a database
 * field or a quiz question, but it did not mint ids: operationToAction() could
 * not build an element without one, so the value never changed and onChange
 * never fired - the form saved an empty map - while the editor received an
 * empty id. Every new element then shared that empty id, so a second node never
 * appeared and the first could no longer be moved. The format matches
 * \\mod_vimipad\\local\\id\\stable_id: a prefix and twelve hex characters.
 *
 * @param operationtype The operation type.
 * @param payloadjson The operation payload.
 * @returns The payload, with a stable id added where a create lacked one.
 */
export function withMintedStableId(operationtype: string, payloadjson: string): string {
    const prefix = CREATE_PREFIX[operationtype];
    if (!prefix) {
        return payloadjson;
    }
    let payload: Record<string, unknown>;
    try {
        payload = JSON.parse(payloadjson) as Record<string, unknown>;
    } catch {
        return payloadjson;
    }
    if (typeof payload.stableid === 'string' && payload.stableid !== '') {
        return payloadjson;
    }
    payload.stableid = prefix + '_' + randomHex(12);
    return JSON.stringify(payload);
}

/**
 * Random lowercase hex characters.
 *
 * @param length How many characters.
 * @returns The hex string.
 */
function randomHex(length: number): string {
    const bytes = new Uint8Array(Math.ceil(length / 2));
    const cryptoapi = (globalThis as {crypto?: Crypto}).crypto;
    if (cryptoapi && typeof cryptoapi.getRandomValues === 'function') {
        cryptoapi.getRandomValues(bytes);
    } else {
        for (let i = 0; i < bytes.length; i++) {
            bytes[i] = Math.floor(Math.random() * 256);
        }
    }
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('').slice(0, length);
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
