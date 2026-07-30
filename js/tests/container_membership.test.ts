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
 * Tests for spatial container membership and refit used by re-arrange (T5).
 *
 * @module     mod_vimipad/tests/container_membership
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {boundingBox, centerInBox, MIN_CONTAINER_SIZE} from '../src/canvas/container_geometry';

describe('centerInBox', () => {
    const box = {x: 0, y: 0, w: 100, h: 100};

    test('a centre inside the box is a member', () => {
        expect(centerInBox({x: 50, y: 50}, box)).toBe(true);
    });

    test('a centre on the border counts as inside', () => {
        expect(centerInBox({x: 0, y: 100}, box)).toBe(true);
    });

    test('a centre outside is not a member', () => {
        expect(centerInBox({x: 150, y: 50}, box)).toBe(false);
        expect(centerInBox({x: 50, y: -1}, box)).toBe(false);
    });
});

describe('boundingBox (container refit)', () => {
    test('encloses all member boxes with padding', () => {
        const members = [
            {x: 100, y: 100, w: 40, h: 20},
            {x: 200, y: 160, w: 60, h: 30},
        ];
        const fit = boundingBox(members, 10);
        expect(fit).not.toBeNull();
        // min x = 100, min y = 100, max x = 260, max y = 190 -> pad 10.
        expect(fit).toEqual({x: 90, y: 90, w: 180, h: 110});
    });

    test('a single small member is clamped to the minimum size', () => {
        const fit = boundingBox([{x: 0, y: 0, w: 5, h: 5}], 0);
        expect(fit?.w).toBe(MIN_CONTAINER_SIZE);
        expect(fit?.h).toBe(MIN_CONTAINER_SIZE);
    });

    test('no members yields null (container left as-is)', () => {
        expect(boundingBox([], 10)).toBeNull();
    });
});
