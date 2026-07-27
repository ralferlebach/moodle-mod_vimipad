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
 * Unit tests for the layout channel codec (positions + sizes).
 *
 * @module     mod_vimipad/tests/layout_codec
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {decodeLayout, encodeLayout} from '../src/canvas/layout_codec';

describe('layout_codec', () => {
    it('reads the legacy bare position map with no sizes', () => {
        const legacy = JSON.stringify({node_a: {x: 10, y: 20}, node_b: {x: 30, y: 40}});
        expect(decodeLayout(legacy)).toEqual({
            positions: {node_a: {x: 10, y: 20}, node_b: {x: 30, y: 40}},
            sizes: {},
        });
    });

    it('reads a versioned envelope with positions and sizes', () => {
        const json = encodeLayout({node_a: {x: 1, y: 2}}, {node_a: {w: 120, h: 60}});
        expect(decodeLayout(json)).toEqual({
            positions: {node_a: {x: 1, y: 2}},
            sizes: {node_a: {w: 120, h: 60}},
        });
    });

    it('round-trips positions and sizes', () => {
        const pos = {n1: {x: 5, y: 6}, n2: {x: 7, y: 8}};
        const size = {n1: {w: 80, h: 40}};
        expect(decodeLayout(encodeLayout(pos, size))).toEqual({positions: pos, sizes: size});
    });

    it('returns empty maps for absent or malformed json', () => {
        expect(decodeLayout('')).toEqual({positions: {}, sizes: {}});
        expect(decodeLayout('nonsense')).toEqual({positions: {}, sizes: {}});
        expect(decodeLayout('[]')).toEqual({positions: {}, sizes: {}});
    });

    it('discards non-numeric points and non-positive sizes', () => {
        const json = JSON.stringify({
            v: 1,
            pos: {ok: {x: 1, y: 2}, bad: {x: 'nope', y: 2}},
            size: {ok: {w: 90, h: 50}, bad: {w: 0, h: 10}},
        });
        expect(decodeLayout(json)).toEqual({
            positions: {ok: {x: 1, y: 2}},
            sizes: {ok: {w: 90, h: 50}},
        });
    });
});
