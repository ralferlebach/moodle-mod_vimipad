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
 * Pure reducer for the editor state.
 *
 * The reducer never talks to the server: it applies the local effect of an
 * operation so the UI can update optimistically, and the caller reconciles the
 * server-assigned revision afterwards.
 *
 * @module     mod_vimipad/store/reducer
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {VimiContainer, VimiNode, VimiRelation, WorkspaceState} from '../types';

export type EditorState = WorkspaceState;

export type EditorAction =
    | {kind: 'load'; state: WorkspaceState}
    | {kind: 'setRevision'; revision: number}
    | {kind: 'addNode'; node: VimiNode}
    | {kind: 'updateNode'; stableid: string; label?: string; type?: string;
        content?: string; metadatajson?: string}
    | {kind: 'deleteNode'; stableid: string}
    | {kind: 'addRelation'; relation: VimiRelation}
    | {kind: 'updateRelation'; stableid: string; label?: string; type?: string; direction?: number;
        metadatajson?: string}
    | {kind: 'deleteRelation'; stableid: string}
    | {kind: 'retargetRelation'; stableid: string; sourceid?: string; targetid?: string}
    | {kind: 'addContainer'; container: VimiContainer}
    | {kind: 'updateContainer'; stableid: string; label?: string; type?: string;
        geometryjson?: string; metadatajson?: string}
    | {kind: 'deleteContainer'; stableid: string}
    | {kind: 'setLocked'; locked: number}
    | {kind: 'setProfile'; profile: string};

/**
 * Produce the next state for an action. Pure and side-effect free.
 *
 * @param state The current state.
 * @param action The action to apply.
 * @returns The next state.
 */
export function reduce(state: EditorState, action: EditorState | EditorAction): EditorState {
    // Allow direct state replacement for convenience.
    if ('nodes' in action && 'relations' in action) {
        return action as EditorState;
    }
    const act = action as EditorAction;
    switch (act.kind) {
        case 'load':
            return act.state;
        case 'setRevision':
            return {...state, revision: act.revision};
        case 'setLocked':
            return state.locked === act.locked ? state : {...state, locked: act.locked};
        case 'setProfile':
            return state.profile === act.profile ? state : {...state, profile: act.profile};
        case 'addNode':
            // Idempotent: applying the same create twice (e.g. the author's own
            // operation echoed back by the poll feed) must not duplicate it.
            return state.nodes.some(n => n.stableid === act.node.stableid)
                ? state
                : {...state, nodes: [...state.nodes, act.node]};
        case 'updateNode':
            return {
                ...state,
                nodes: state.nodes.map(n => n.stableid === act.stableid
                    ? {
                        ...n,
                        label: act.label ?? n.label,
                        type: act.type ?? n.type,
                        content: act.content ?? n.content,
                        metadatajson: act.metadatajson ?? n.metadatajson,
                    }
                    : n),
            };
        case 'deleteNode':
            return {
                ...state,
                nodes: state.nodes.filter(n => n.stableid !== act.stableid),
                relations: state.relations.filter(
                    r => r.sourceid !== act.stableid && r.targetid !== act.stableid
                ),
            };
        case 'addRelation':
            // Idempotent for the same reason as addNode.
            return state.relations.some(r => r.stableid === act.relation.stableid)
                ? state
                : {...state, relations: [...state.relations, act.relation]};
        case 'updateRelation':
            return {
                ...state,
                relations: state.relations.map(r => r.stableid === act.stableid
                    ? {...r, label: act.label ?? r.label, type: act.type ?? r.type,
                        direction: act.direction ?? r.direction,
                        metadatajson: act.metadatajson ?? r.metadatajson}
                    : r),
            };
        case 'deleteRelation':
            return {
                ...state,
                relations: state.relations.filter(r => r.stableid !== act.stableid),
            };
        case 'retargetRelation':
            return {
                ...state,
                relations: state.relations.map(r => r.stableid === act.stableid
                    ? {
                        ...r,
                        sourceid: act.sourceid ?? r.sourceid,
                        targetid: act.targetid ?? r.targetid,
                    }
                    : r),
            };
        case 'addContainer': {
            const containers = state.containers ?? [];
            return containers.some(c => c.stableid === act.container.stableid)
                ? state
                : {...state, containers: [...containers, act.container]};
        }
        case 'updateContainer': {
            const containers = state.containers ?? [];
            return {
                ...state,
                containers: containers.map(c => c.stableid === act.stableid
                    ? {
                        ...c,
                        label: act.label ?? c.label,
                        type: act.type ?? c.type,
                        geometryjson: act.geometryjson ?? c.geometryjson,
                        metadatajson: act.metadatajson ?? c.metadatajson,
                    }
                    : c),
            };
        }
        case 'deleteContainer': {
            const containers = state.containers ?? [];
            return {
                ...state,
                containers: containers.filter(c => c.stableid !== act.stableid),
            };
        }
        default:
            return state;
    }
}

/**
 * Look up a node label by its stable id, for list rendering.
 *
 * @param state The current state.
 * @param stableid The node stable id.
 * @returns The label, or the stable id if the node is missing.
 */
export function labelFor(state: EditorState, stableid: string): string {
    const node = state.nodes.find(n => n.stableid === stableid);
    return node ? node.label : stableid;
}
