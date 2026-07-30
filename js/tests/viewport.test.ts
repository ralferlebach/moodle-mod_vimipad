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
 * Tests for the client-to-viewBox mapping, pinned to a real reported geometry.
 *
 * @module     mod_vimipad/tests/viewport
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ElementRect, meetOffset, meetScale, screenToViewBox, ViewBox} from '../src/canvas/viewport';

// Measured in a real editor session: a wide, short element showing a much
// taller viewBox, so "meet" scales down and letterboxes horizontally.
const VIEW: ViewBox = {
    x: 792.7863876018533,
    y: 521.6327762845567,
    w: 826.4462809917354,
    h: 550.9641873278235,
};
const RECT: ElementRect = {left: 0, top: 0, width: 1138, height: 213.1999969482422};

describe('viewport mapping', () => {
    test('uses the smaller of the two ratios as a uniform scale', () => {
        // height is the constraining axis here: 213.2 / 550.96.
        expect(meetScale(VIEW, RECT)).toBeCloseTo(0.386958, 5);
    });

    test('centres the scaled content, leaving side margins', () => {
        const offset = meetOffset(VIEW, RECT);
        expect(offset.x).toBeCloseTo(409.1, 1);
        expect(offset.y).toBeCloseTo(0, 5);
    });

    test('the element centre maps to the viewBox centre', () => {
        const centre = screenToViewBox(
            {x: RECT.width / 2, y: RECT.height / 2},
            RECT,
            VIEW
        );
        expect(centre.x).toBeCloseTo(VIEW.x + VIEW.w / 2, 3);
        expect(centre.y).toBeCloseTo(VIEW.y + VIEW.h / 2, 3);
    });

    test('a drag moves the cursor and the content at the same speed', () => {
        const a = screenToViewBox({x: 500, y: 100}, RECT, VIEW);
        const b = screenToViewBox({x: 600, y: 100}, RECT, VIEW);
        // 100 client px at scale 0.386958 are ~258.4 viewBox units.
        expect(b.x - a.x).toBeCloseTo(100 / 0.386958, 2);
    });

    test('the naive stretch assumption would be off by the aspect mismatch', () => {
        // What the old code computed for the same 100 px drag.
        const naive = 100 / RECT.width * VIEW.w;
        const correct = 100 / meetScale(VIEW, RECT);
        expect(naive).toBeCloseTo(72.6, 1);
        expect(correct / naive).toBeGreaterThan(3.5);
    });

    test('degenerate geometry does not produce NaN', () => {
        const zero = screenToViewBox({x: 10, y: 10}, {left: 0, top: 0, width: 0, height: 0}, VIEW);
        expect(Number.isFinite(zero.x)).toBe(true);
        expect(Number.isFinite(zero.y)).toBe(true);
    });
});
