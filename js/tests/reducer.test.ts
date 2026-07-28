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
 * Unit tests for the pure editor reducer and auto-layout.
 *
 * Run with: npm test (development only; not shipped to production).
 *
 * @module     mod_vimipad/tests/reducer
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {reduce, EditorState} from '../src/store/reducer';
import {computeLayout} from '../src/graph/autolayout';

const base: EditorState = {
    workspaceid: 1, revision: 0, locked: 0, profile: 'conceptmap', layoutjson: '',
    nodes: [
        {stableid: 'node_a', type: 'concept', label: 'A'},
        {stableid: 'node_b', type: 'concept', label: 'B'},
    ],
    relations: [
        {stableid: 'rel_1', sourceid: 'node_a', targetid: 'node_b', type: 'related', label: '', direction: 1},
    ],
};

describe('reducer', () => {
    it('adds a node immutably', () => {
        const next = reduce(base, {kind: 'addNode', node: {stableid: 'node_c', type: 'concept', label: 'C'}});
        expect(next.nodes).toHaveLength(3);
        expect(base.nodes).toHaveLength(2);
    });

    it('addNode is idempotent for an existing stable id', () => {
        const once = reduce(base, {kind: 'addNode', node: {stableid: 'node_c', type: 'concept', label: 'C'}});
        const twice = reduce(once, {kind: 'addNode', node: {stableid: 'node_c', type: 'concept', label: 'C'}});
        expect(twice.nodes).toHaveLength(3);
        expect(twice).toBe(once);
    });

    it('addRelation is idempotent for an existing stable id', () => {
        const rel = {stableid: 'rel_1', sourceid: 'node_a', targetid: 'node_b', type: 'related', label: '', direction: 1};
        const next = reduce(base, {kind: 'addRelation', relation: rel});
        expect(next.relations).toHaveLength(base.relations.length);
        expect(next).toBe(base);
    });

    it('deleting a node removes its relations', () => {
        const next = reduce(base, {kind: 'deleteNode', stableid: 'node_a'});
        expect(next.nodes).toHaveLength(1);
        expect(next.relations).toHaveLength(0);
    });

    it('retargets a relation source', () => {
        const withC = reduce(base, {kind: 'addNode', node: {stableid: 'node_c', type: 'concept', label: 'C'}});
        const next = reduce(withC, {kind: 'retargetRelation', stableid: 'rel_1', sourceid: 'node_c'});
        expect(next.relations[0].sourceid).toBe('node_c');
        expect(next.relations[0].targetid).toBe('node_b');
    });

    it('setRevision updates only the revision', () => {
        const next = reduce(base, {kind: 'setRevision', revision: 5});
        expect(next.revision).toBe(5);
        expect(next.nodes).toEqual(base.nodes);
    });
});

describe('autolayout', () => {
    it('honours stored positions and places the rest', () => {
        const stored = {node_a: {x: 100, y: 100}};
        const layout = computeLayout(base.nodes, stored);
        expect(layout.node_a).toEqual({x: 100, y: 100});
        expect(layout.node_b).toBeDefined();
    });

    it('is deterministic for the same input', () => {
        expect(computeLayout(base.nodes, {})).toEqual(computeLayout(base.nodes, {}));
    });
});

describe('reducer node style/content', () => {
    it('updateNode applies metadatajson and content while preserving the label', () => {
        const meta = JSON.stringify({shape: 'ellipse', fill: '#ff0000'});
        const next = reduce(base, {kind: 'updateNode', stableid: 'node_a', metadatajson: meta});
        const a = next.nodes.find(n => n.stableid === 'node_a');
        expect(a?.metadatajson).toBe(meta);
        expect(a?.label).toBe('A');
    });

    it('updateNode leaves unspecified fields untouched', () => {
        const withMeta = reduce(base, {kind: 'updateNode', stableid: 'node_a', metadatajson: '{"shape":"rect"}'});
        const renamed = reduce(withMeta, {kind: 'updateNode', stableid: 'node_a', label: 'A2'});
        const a = renamed.nodes.find(n => n.stableid === 'node_a');
        expect(a?.label).toBe('A2');
        expect(a?.metadatajson).toBe('{"shape":"rect"}');
    });
});
