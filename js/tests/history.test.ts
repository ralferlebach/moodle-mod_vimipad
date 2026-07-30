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
 * Unit tests for the undo/redo history stack.
 *
 * @module     mod_vimipad/tests/history
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {History, HistoryEntry} from '../src/store/history';

const entry = (id: string): HistoryEntry => ({
    undo: [{type: 'node_delete', payload: {stableid: id}}],
    redo: [{type: 'node_create', payload: {stableid: id}}],
});

describe('history', () => {
    test('starts empty', () => {
        const history = new History();
        expect(history.canUndo()).toBe(false);
        expect(history.canRedo()).toBe(false);
    });

    test('push enables undo and clears redo', () => {
        const history = new History();
        history.push(entry('a'));
        expect(history.canUndo()).toBe(true);

        const undone = history.takeUndo();
        expect(undone).not.toBeNull();
        expect(history.canRedo()).toBe(true);

        // A new change after an undo drops the redo branch.
        history.push(entry('b'));
        expect(history.canRedo()).toBe(false);
    });

    test('takeUndo then takeRedo round-trips the same entry', () => {
        const history = new History();
        const e = entry('a');
        history.push(e);
        expect(history.takeUndo()).toBe(e);
        expect(history.canUndo()).toBe(false);
        expect(history.takeRedo()).toBe(e);
        expect(history.canUndo()).toBe(true);
        expect(history.canRedo()).toBe(false);
    });

    test('take on an empty stack returns null', () => {
        const history = new History();
        expect(history.takeUndo()).toBeNull();
        expect(history.takeRedo()).toBeNull();
    });

    test('respects the depth limit', () => {
        const history = new History(2);
        history.push(entry('a'));
        history.push(entry('b'));
        history.push(entry('c'));
        // Only two entries are retained: c and b.
        expect(history.takeUndo()?.redo[0].payload.stableid).toBe('c');
        expect(history.takeUndo()?.redo[0].payload.stableid).toBe('b');
        expect(history.canUndo()).toBe(false);
    });

    test('clear empties both stacks', () => {
        const history = new History();
        history.push(entry('a'));
        history.takeUndo();
        history.clear();
        expect(history.canUndo()).toBe(false);
        expect(history.canRedo()).toBe(false);
    });
});
