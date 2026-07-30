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
 * Tests for container actions in the editor reducer.
 *
 * @module     mod_vimipad/tests/container_reducer
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {EditorState, reduce} from '../src/store/reducer';

const base: EditorState = {
    workspaceid: 1, revision: 0, locked: 0, profile: 'conceptmap',
    layoutjson: '', nodes: [], relations: [],
};

const container = (stableid: string, label = 'Box'): {stableid: string; type: string; label: string; geometryjson: string} => ({
    stableid, type: 'group', label, geometryjson: '{"x":0,"y":0,"w":100,"h":100}',
});

describe('reducer container actions', () => {
    test('addContainer appends and is idempotent', () => {
        const s1 = reduce(base, {kind: 'addContainer', container: container('container_a')});
        expect(s1.containers).toHaveLength(1);
        const s2 = reduce(s1, {kind: 'addContainer', container: container('container_a')});
        expect(s2.containers).toHaveLength(1);
        expect(s2).toBe(s1);
    });

    test('updateContainer changes only the targeted fields', () => {
        const s1 = reduce(base, {kind: 'addContainer', container: container('container_a', 'Old')});
        const s2 = reduce(s1, {
            kind: 'updateContainer', stableid: 'container_a',
            label: 'New', geometryjson: '{"x":5,"y":5,"w":200,"h":150}',
        });
        expect(s2.containers?.[0].label).toBe('New');
        expect(s2.containers?.[0].geometryjson).toBe('{"x":5,"y":5,"w":200,"h":150}');
        expect(s2.containers?.[0].type).toBe('group');
    });

    test('deleteContainer removes it', () => {
        const s1 = reduce(base, {kind: 'addContainer', container: container('container_a')});
        const s2 = reduce(s1, {kind: 'deleteContainer', stableid: 'container_a'});
        expect(s2.containers).toHaveLength(0);
    });

    test('load carries containers from the backend state', () => {
        const loaded = reduce(base, {
            kind: 'load',
            state: {...base, containers: [container('container_x')]},
        });
        expect(loaded.containers).toHaveLength(1);
    });
});
