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
 * Tests for the element lock metadata helpers.
 *
 * @module     mod_vimipad/tests/element_lock
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    isLocked, readLock, writeLock,
    readGroupLocks, writeGroupLocks, isGroupLocked, isAnyLocked,
} from '../src/canvas/element_lock';

describe('element_lock', () => {
    test('readLock defaults to unlocked with no editable fields', () => {
        expect(readLock(undefined)).toEqual({locked: false, editable: []});
        expect(readLock('')).toEqual({locked: false, editable: []});
        expect(readLock('not json')).toEqual({locked: false, editable: []});
        expect(readLock('{"shape":"rect"}')).toEqual({locked: false, editable: []});
    });

    test('readLock reads locked and the editable whitelist', () => {
        expect(readLock('{"locked":true,"editable":["label"]}')).toEqual({locked: true, editable: ['label']});
        expect(readLock('{"locked":true}')).toEqual({locked: true, editable: []});
    });

    test('isLocked is a shortcut for the locked flag', () => {
        expect(isLocked('{"locked":true}')).toBe(true);
        expect(isLocked('{"shape":"rect"}')).toBe(false);
    });

    test('writeLock preserves other metadata keys', () => {
        const out = writeLock('{"shape":"rect","fill":"#eee"}', {locked: true, editable: ['label']});
        const parsed = JSON.parse(out);
        expect(parsed.shape).toBe('rect');
        expect(parsed.fill).toBe('#eee');
        expect(parsed.locked).toBe(true);
        expect(parsed.editable).toEqual(['label']);
    });

    test('writeLock with an empty whitelist omits the editable key', () => {
        const parsed = JSON.parse(writeLock('{}', {locked: true, editable: []}));
        expect(parsed.locked).toBe(true);
        expect('editable' in parsed).toBe(false);
    });

    test('writeLock unlock removes both lock keys but keeps styling', () => {
        const parsed = JSON.parse(writeLock('{"shape":"rect","locked":true,"editable":["label"]}',
            {locked: false, editable: []}));
        expect(parsed.shape).toBe('rect');
        expect('locked' in parsed).toBe(false);
        expect('editable' in parsed).toBe(false);
    });

    test('readGroupLocks: a legacy global lock locks every group', () => {
        const g = readGroupLocks('{"locked":true}');
        expect(g).toEqual({move: true, color: true, text: true});
    });

    test('readGroupLocks: an unlocked element locks nothing', () => {
        expect(readGroupLocks(undefined)).toEqual({move: false, color: false, text: false});
        expect(readGroupLocks('{"shape":"rect"}')).toEqual({move: false, color: false, text: false});
    });

    test('readGroupLocks: a locks map is read per group', () => {
        const g = readGroupLocks('{"locked":true,"locks":{"move":true,"color":false,"text":true}}');
        expect(g).toEqual({move: true, color: false, text: true});
    });

    test('isGroupLocked and isAnyLocked reflect the group flags', () => {
        const meta = '{"locked":true,"locks":{"move":false,"color":true,"text":false}}';
        expect(isGroupLocked(meta, 'color')).toBe(true);
        expect(isGroupLocked(meta, 'move')).toBe(false);
        expect(isAnyLocked(meta)).toBe(true);
        expect(isAnyLocked('{"shape":"rect"}')).toBe(false);
    });

    test('writeGroupLocks: all-false fully unlocks and keeps styling', () => {
        const parsed = JSON.parse(writeGroupLocks(
            '{"shape":"rect","locked":true,"locks":{"move":true,"color":false,"text":false}}',
            {move: false, color: false, text: false}));
        expect(parsed.shape).toBe('rect');
        expect('locked' in parsed).toBe(false);
        expect('locks' in parsed).toBe(false);
    });

    test('writeGroupLocks: a partial lock writes locked plus the explicit map', () => {
        const parsed = JSON.parse(writeGroupLocks('{"shape":"rect"}',
            {move: true, color: false, text: true}));
        expect(parsed.shape).toBe('rect');
        expect(parsed.locked).toBe(true);
        expect(parsed.locks).toEqual({move: true, color: false, text: true});
    });

    test('writeGroupLocks round-trips through readGroupLocks', () => {
        const flags = {move: false, color: true, text: true};
        expect(readGroupLocks(writeGroupLocks(undefined, flags))).toEqual(flags);
    });
});
