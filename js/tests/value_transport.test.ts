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
 * Unit tests for the value-backed transport: it seeds from a value, applies the
 * activity's operations through the real reducer, and reports the serialised
 * value after each change. No editor mount, no DOM, no network.
 *
 * @module     mod_vimipad/tests/value_transport
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createValueTransport} from '../src/value_transport';

/** Build the apply_operation args for a given operation type and payload. */
function op(operationtype: string, payload: Record<string, unknown>): Record<string, unknown> {
    return {operationtype, payloadjson: JSON.stringify(payload)};
}

describe('value transport', () => {
    test('get_workspace returns an empty workspace for an empty value', async () => {
        const {transport} = createValueTransport('', {profile: 'mindmap'});
        const ws = await transport('mod_vimipad_get_workspace', {}) as Record<string, unknown>;
        expect(ws.profile).toBe('mindmap');
        expect(ws.nodes).toEqual([]);
        expect(ws.relations).toEqual([]);
    });

    test('seeds from an existing value and round-trips node labels', async () => {
        const value = JSON.stringify({
            profile: 'conceptmap',
            nodes: [{stableid: 'n1', type: 'concept', label: 'Water'}],
            relations: [],
        });
        const {transport, getValue} = createValueTransport(value);
        const ws = await transport('mod_vimipad_get_workspace', {}) as {nodes: {label: string}[]};
        expect(ws.nodes).toHaveLength(1);
        expect(ws.nodes[0].label).toBe('Water');
        expect(JSON.parse(getValue()).nodes[0].label).toBe('Water');
    });

    test('applying node_create updates the value and fires onChange', async () => {
        const changes: string[] = [];
        const {transport, getValue} = createValueTransport('', {onChange: (v) => changes.push(v)});

        const res = await transport('mod_vimipad_apply_operation',
            op('node_create', {stableid: 'n1', type: 'concept', label: 'Ice'})) as {revision: number};

        expect(res.revision).toBeGreaterThan(1);
        expect(changes).toHaveLength(1);
        const value = JSON.parse(getValue());
        expect(value.nodes).toHaveLength(1);
        expect(value.nodes[0].label).toBe('Ice');
    });

    test('node_update changes only the given field', async () => {
        const seed = JSON.stringify({
            profile: 'conceptmap',
            nodes: [{stableid: 'n1', type: 'concept', label: 'Old'}],
            relations: [],
        });
        const {transport, getValue} = createValueTransport(seed);
        await transport('mod_vimipad_apply_operation',
            op('node_update', {stableid: 'n1', label: 'New'}));
        const node = JSON.parse(getValue()).nodes[0];
        expect(node.label).toBe('New');
        expect(node.type).toBe('concept');
    });

    test('relation_create is reflected in the value', async () => {
        const seed = JSON.stringify({
            profile: 'conceptmap',
            nodes: [
                {stableid: 'a', type: 'concept', label: 'A'},
                {stableid: 'b', type: 'concept', label: 'B'},
            ],
            relations: [],
        });
        const {transport, getValue} = createValueTransport(seed);
        await transport('mod_vimipad_apply_operation',
            op('relation_create', {stableid: 'r1', sourceid: 'a', targetid: 'b', label: 'links'}));
        const relations = JSON.parse(getValue()).relations;
        expect(relations).toHaveLength(1);
        expect(relations[0].sourceid).toBe('a');
        expect(relations[0].targetid).toBe('b');
        expect(relations[0].label).toBe('links');
    });

    test('save_layout stores the layout and fires onChange', async () => {
        const changes: string[] = [];
        const {transport, getValue} = createValueTransport('', {onChange: (v) => changes.push(v)});
        await transport('mod_vimipad_save_layout', {layoutjson: '{"n1":{"x":10,"y":20}}'});
        expect(changes).toHaveLength(1);
        expect(JSON.parse(getValue()).layoutjson).toBe('{"n1":{"x":10,"y":20}}');
    });

    test('read-only ignores operations and layout writes', async () => {
        const changes: string[] = [];
        const {transport, getValue} = createValueTransport('', {readonly: true, onChange: (v) => changes.push(v)});
        await transport('mod_vimipad_apply_operation',
            op('node_create', {stableid: 'n1', type: 'concept', label: 'Nope'}));
        await transport('mod_vimipad_save_layout', {layoutjson: '{"x":1}'});
        expect(changes).toHaveLength(0);
        expect(JSON.parse(getValue()).nodes).toEqual([]);
    });

    test('a malformed value seeds an empty workspace instead of throwing', async () => {
        const {transport} = createValueTransport('not json');
        const ws = await transport('mod_vimipad_get_workspace', {}) as {nodes: unknown[]};
        expect(ws.nodes).toEqual([]);
    });
});
