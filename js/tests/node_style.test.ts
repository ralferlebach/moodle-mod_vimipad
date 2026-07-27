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
 * Unit tests for node style parsing, serialising and merging.
 *
 * @module     mod_vimipad/tests/node_style
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    parseNodeStyle,
    serialiseNodeStyle,
    withNodeStyle,
} from '../src/canvas/node_style';

describe('node_style', () => {
    it('parses a full valid style', () => {
        const json = JSON.stringify({
            shape: 'ellipse', fill: '#AABBCC',
            text: {font: 'serif', size: 2, color: '#112233', background: '#ffffff'},
        });
        expect(parseNodeStyle(json)).toEqual({
            shape: 'ellipse', fill: '#aabbcc',
            text: {font: 'serif', size: 2, color: '#112233', background: '#ffffff'},
        });
    });

    it('returns an empty style for absent or malformed metadata', () => {
        expect(parseNodeStyle(undefined)).toEqual({});
        expect(parseNodeStyle('')).toEqual({});
        expect(parseNodeStyle('not json')).toEqual({});
        expect(parseNodeStyle('[]')).toEqual({});
    });

    it('drops invalid values', () => {
        const json = JSON.stringify({
            shape: 'triangle', fill: 'red', text: {font: 'comic', size: 'big', color: '#zzzzzz'},
        });
        expect(parseNodeStyle(json)).toEqual({});
    });

    it('clamps an out-of-range font size and ignores a zero step', () => {
        expect(parseNodeStyle(JSON.stringify({text: {size: 99}}))).toEqual({text: {size: 6}});
        expect(parseNodeStyle(JSON.stringify({text: {size: 0}}))).toEqual({});
    });

    it('merges a partial change without disturbing other fields', () => {
        const base = JSON.stringify({shape: 'rect', fill: '#eeeeee', text: {size: 1}});
        const merged = withNodeStyle(base, {fill: '#ff0000'});
        expect(parseNodeStyle(merged)).toEqual({shape: 'rect', fill: '#ff0000', text: {size: 1}});
    });

    it('round-trips through serialise/parse and omits empties', () => {
        expect(serialiseNodeStyle({})).toBe('{}');
        const style = {shape: 'roundrect' as const, fill: '#123456'};
        expect(parseNodeStyle(serialiseNodeStyle(style))).toEqual(style);
    });
});
