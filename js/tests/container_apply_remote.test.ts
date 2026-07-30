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
 * Tests that operationToAction maps remote container operations.
 *
 * @module     mod_vimipad/tests/container_apply_remote
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {operationToAction} from '../src/collab/apply_remote';
import {PolledOperation} from '../src/types';

const op = (operationtype: string, payload: Record<string, unknown>): PolledOperation => ({
    revision: 1, operationtype, payloadjson: JSON.stringify(payload), userid: 2,
});

describe('operationToAction container operations', () => {
    test('container_create maps to addContainer', () => {
        const action = operationToAction(op('container_create', {
            stableid: 'container_a', type: 'group', label: 'A', geometryjson: '{"x":0,"y":0,"w":100,"h":80}',
        }));
        expect(action).toEqual({
            kind: 'addContainer',
            container: {stableid: 'container_a', type: 'group', label: 'A', geometryjson: '{"x":0,"y":0,"w":100,"h":80}'},
        });
    });

    test('container_create without stableid is ignored', () => {
        expect(operationToAction(op('container_create', {type: 'group'}))).toBeNull();
    });

    test('container_update forwards only present fields', () => {
        const action = operationToAction(op('container_update', {
            stableid: 'container_a', geometryjson: '{"x":5,"y":5,"w":200,"h":150}',
        }));
        expect(action).toEqual({
            kind: 'updateContainer', stableid: 'container_a', geometryjson: '{"x":5,"y":5,"w":200,"h":150}',
        });
    });

    test('container_delete maps to deleteContainer', () => {
        expect(operationToAction(op('container_delete', {stableid: 'container_a'})))
            .toEqual({kind: 'deleteContainer', stableid: 'container_a'});
    });
});
