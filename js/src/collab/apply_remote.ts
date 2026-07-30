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
 * Translate a polled server operation into a local reducer action.
 *
 * The poll loop returns operations that other users applied; this maps each to
 * the equivalent editor action so remote changes appear locally. Layout-only
 * changes are not operations (they travel on the separate layout channel) and
 * unknown types are ignored. Pure and unit tested.
 *
 * @module     mod_vimipad/collab/apply_remote
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {EditorAction} from '../store/reducer';
import {PolledOperation, VimiNode} from '../types';

/**
 * Convert a polled operation into a reducer action, or null if it has no local
 * effect (unknown type, or malformed payload).
 *
 * @param op The polled operation.
 * @returns The corresponding action, or null.
 */
export function operationToAction(op: PolledOperation): EditorAction | null {
    let payload: Record<string, unknown>;
    try {
        payload = JSON.parse(op.payloadjson) as Record<string, unknown>;
    } catch {
        return null;
    }

    const str = (key: string): string => String(payload[key] ?? '');
    const num = (key: string, fallback: number): number =>
        typeof payload[key] === 'number' ? payload[key] as number : fallback;

    switch (op.operationtype) {
        case 'node_create': {
            if (!payload.stableid) {
                return null;
            }
            const node: VimiNode = {
                stableid: str('stableid'), type: str('type') || 'concept', label: str('label'),
            };
            if ('content' in payload) {
                node.content = str('content');
            }
            if ('metadatajson' in payload) {
                node.metadatajson = str('metadatajson');
            }
            return {kind: 'addNode', node};
        }
        case 'node_update': {
            // A node_update may touch any subset of fields; only forward the
            // keys that are actually present so a style change never blanks the
            // label (and vice versa).
            const action: EditorAction = {kind: 'updateNode', stableid: str('stableid')};
            if ('label' in payload) {
                action.label = str('label');
            }
            if ('type' in payload) {
                action.type = str('type');
            }
            if ('content' in payload) {
                action.content = str('content');
            }
            if ('metadatajson' in payload) {
                action.metadatajson = str('metadatajson');
            }
            return action;
        }
        case 'node_delete':
            return {kind: 'deleteNode', stableid: str('stableid')};
        case 'relation_create':
            if (!payload.stableid) {
                return null;
            }
            return {
                kind: 'addRelation',
                relation: {
                    stableid: str('stableid'),
                    sourceid: str('sourceid'),
                    targetid: str('targetid'),
                    type: str('type') || 'related',
                    label: str('label'),
                    direction: num('direction', 1),
                },
            };
        case 'relation_update': {
            const action: EditorAction = {kind: 'updateRelation', stableid: str('stableid')};
            if ('label' in payload) {
                action.label = str('label');
            }
            if ('type' in payload) {
                action.type = str('type');
            }
            if ('direction' in payload) {
                action.direction = num('direction', 1);
            }
            if ('metadatajson' in payload) {
                action.metadatajson = str('metadatajson');
            }
            return action;
        }
        case 'relation_delete':
            return {kind: 'deleteRelation', stableid: str('stableid')};
        case 'relation_retarget': {
            const action: EditorAction = {kind: 'retargetRelation', stableid: str('stableid')};
            if (payload.newsource) {
                action.sourceid = str('newsource');
            }
            if (payload.newtarget) {
                action.targetid = str('newtarget');
            }
            return action;
        }
        default:
            return null;
    }
}
