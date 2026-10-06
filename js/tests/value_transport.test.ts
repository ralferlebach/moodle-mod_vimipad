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

describe('reading a submitted snapshot', () => {
    /**
     * A value in the shape snapshot_service writes: the layout as a decoded
     * object under "layout", and every relation field straight from the
     * database, so the direction is the string "1" rather than the number 1.
     * This is exactly what the gallery hands to the editor.
     */
    const snapshot = JSON.stringify({
        profile: 'conceptmap',
        revision: 7,
        nodes: [
            {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'Free trade', metadatajson: '{}'},
            {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'Tariffs', metadatajson: '{}'},
        ],
        relations: [{
            stableid: 'rel_aaaaaaaaaaaaa', sourceid: 'node_aaaaaaaaaaaa', targetid: 'node_bbbbbbbbbbbb',
            type: '', label: 'reduces', direction: '1', metadatajson: '{}',
        }],
        containers: [],
        layout: {node_aaaaaaaaaaaa: {x: 860, y: 230}, node_bbbbbbbbbbbb: {x: 560, y: 325}},
    });

    test('the stored positions reach the editor', async () => {
        // Without them the editor invents a fallback layout, which is why a map
        // looked topologically different in the gallery than in the activity.
        const {transport} = createValueTransport(snapshot);
        const ws = await transport('mod_vimipad_get_workspace', {}) as {layoutjson: string};
        expect(ws.layoutjson).not.toBe('');
        const layout = JSON.parse(ws.layoutjson);
        expect(layout.node_aaaaaaaaaaaa).toEqual({x: 860, y: 230});
        expect(layout.node_bbbbbbbbbbbb).toEqual({x: 560, y: 325});
    });

    test('a relation direction from the database becomes a number', async () => {
        // The canvas draws an arrowhead only for direction === 1 or 2. The
        // string "1" from a snapshot failed that test and lost its arrow.
        const {transport} = createValueTransport(snapshot);
        const ws = await transport('mod_vimipad_get_workspace', {}) as {relations: {direction: unknown}[]};
        expect(ws.relations[0].direction).toBe(1);
    });

    test('a value in the editor\'s own shape still reads as before', async () => {
        const own = JSON.stringify({
            profile: 'conceptmap', layoutjson: '{"node_aaaaaaaaaaaa":{"x":1,"y":2}}',
            revision: 1, nodes: [], relations: [], containers: [],
        });
        const {transport} = createValueTransport(own);
        const ws = await transport('mod_vimipad_get_workspace', {}) as {layoutjson: string};
        expect(JSON.parse(ws.layoutjson)).toEqual({node_aaaaaaaaaaaa: {x: 1, y: 2}});
    });
});
