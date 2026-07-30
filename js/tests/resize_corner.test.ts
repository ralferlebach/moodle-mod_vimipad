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
 * Tests for four-corner container resize (T4 node parity).
 *
 * @module     mod_vimipad/tests/resize_corner
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {MIN_CONTAINER_SIZE, resizeBoxCorner} from '../src/canvas/container_geometry';

const base = {x: 100, y: 100, w: 200, h: 120};

describe('resizeBoxCorner', () => {
    test('south-east grows width and height, origin fixed', () => {
        expect(resizeBoxCorner(base, 'se', 30, 20)).toEqual({x: 100, y: 100, w: 230, h: 140});
    });

    test('north-west moves origin and shrinks toward the fixed SE corner', () => {
        const r = resizeBoxCorner(base, 'nw', 40, 30);
        expect(r).toEqual({x: 140, y: 130, w: 160, h: 90});
        // The opposite (SE) corner stays put.
        expect(r.x + r.w).toBe(base.x + base.w);
        expect(r.y + r.h).toBe(base.y + base.h);
    });

    test('north-east keeps the SW corner fixed', () => {
        const r = resizeBoxCorner(base, 'ne', 20, 20);
        expect(r.x).toBe(base.x);
        expect(r.x + r.w).toBe(base.x + base.w + 20);
        expect(r.y).toBe(base.y + 20);
        expect(r.y + r.h).toBe(base.y + base.h);
    });

    test('south-west keeps the NE corner fixed', () => {
        const r = resizeBoxCorner(base, 'sw', 20, 20);
        expect(r.x).toBe(base.x + 20);
        expect(r.x + r.w).toBe(base.x + base.w);
        expect(r.y).toBe(base.y);
        expect(r.y + r.h).toBe(base.y + base.h + 20);
    });

    test('shrinking past the minimum clamps and anchors the opposite edge', () => {
        // Drag NW corner far past the SE corner.
        const r = resizeBoxCorner(base, 'nw', 500, 500);
        expect(r.w).toBe(MIN_CONTAINER_SIZE);
        expect(r.h).toBe(MIN_CONTAINER_SIZE);
        // SE corner still fixed.
        expect(r.x + r.w).toBe(base.x + base.w);
        expect(r.y + r.h).toBe(base.y + base.h);
    });
});
