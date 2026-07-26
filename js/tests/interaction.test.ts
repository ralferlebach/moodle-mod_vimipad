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

import {
    interactionReduce,
    initialInteraction,
    deletableTarget,
    isSelected,
    isEditing,
    Target,
} from '../src/canvas/interaction';

const node = (id: string): Target => ({kind: 'node', id});
const rel = (id: string): Target => ({kind: 'relation', id});

describe('interaction state machine', () => {
    test('starts with nothing selected or editing', () => {
        expect(initialInteraction.selected).toBeNull();
        expect(initialInteraction.editing).toBeNull();
    });

    test('clicking a node selects it', () => {
        const s = interactionReduce(initialInteraction, {kind: 'select', target: node('n1')});
        expect(s.selected).toEqual(node('n1'));
        expect(isSelected(s, 'node', 'n1')).toBe(true);
        expect(isSelected(s, 'node', 'n2')).toBe(false);
    });

    test('clicking a connection selects it', () => {
        const s = interactionReduce(initialInteraction, {kind: 'select', target: rel('r1')});
        expect(isSelected(s, 'relation', 'r1')).toBe(true);
    });

    test('selecting a different element moves the selection', () => {
        let s = interactionReduce(initialInteraction, {kind: 'select', target: node('n1')});
        s = interactionReduce(s, {kind: 'select', target: node('n2')});
        expect(isSelected(s, 'node', 'n1')).toBe(false);
        expect(isSelected(s, 'node', 'n2')).toBe(true);
    });

    test('ESC (clear) deselects everything', () => {
        let s = interactionReduce(initialInteraction, {kind: 'select', target: node('n1')});
        s = interactionReduce(s, {kind: 'clear'});
        expect(s.selected).toBeNull();
        expect(s.editing).toBeNull();
    });

    test('double-click starts editing and also selects', () => {
        const s = interactionReduce(initialInteraction, {kind: 'startEditing', target: node('n1')});
        expect(isEditing(s, 'node', 'n1')).toBe(true);
        expect(isSelected(s, 'node', 'n1')).toBe(true);
    });

    test('Enter (stopEditing) ends editing but keeps the selection', () => {
        let s = interactionReduce(initialInteraction, {kind: 'startEditing', target: node('n1')});
        s = interactionReduce(s, {kind: 'stopEditing'});
        expect(isEditing(s, 'node', 'n1')).toBe(false);
        expect(isSelected(s, 'node', 'n1')).toBe(true);
    });

    test('a single click while editing ends the edit', () => {
        let s = interactionReduce(initialInteraction, {kind: 'startEditing', target: node('n1')});
        s = interactionReduce(s, {kind: 'select', target: node('n1')});
        expect(isEditing(s, 'node', 'n1')).toBe(false);
        expect(isSelected(s, 'node', 'n1')).toBe(true);
    });

    test('ESC also cancels an active edit', () => {
        let s = interactionReduce(initialInteraction, {kind: 'startEditing', target: rel('r1')});
        s = interactionReduce(s, {kind: 'clear'});
        expect(s.editing).toBeNull();
        expect(s.selected).toBeNull();
    });

    test('Del deletes the selected element when not editing', () => {
        const s = interactionReduce(initialInteraction, {kind: 'select', target: node('n1')});
        expect(deletableTarget(s)).toEqual(node('n1'));
    });

    test('Del does nothing while editing (typing Del in text must not delete the node)', () => {
        const s = interactionReduce(initialInteraction, {kind: 'startEditing', target: node('n1')});
        expect(deletableTarget(s)).toBeNull();
    });

    test('Del does nothing when nothing is selected', () => {
        expect(deletableTarget(initialInteraction)).toBeNull();
    });
});
