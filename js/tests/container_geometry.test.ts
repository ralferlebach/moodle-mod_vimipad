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
 * Tests for the pure container geometry helpers.
 *
 * @module     mod_vimipad/tests/container_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    boundingBox, boxFromDrag, centerInBox, isDrawable, MIN_CONTAINER_SIZE, moveBox,
    normalizeBox, parseGeometry, resizeBox, serializeGeometry,
} from '../src/canvas/container_geometry';

describe('container_geometry', () => {
    test('parseGeometry returns null for empty or malformed input', () => {
        expect(parseGeometry(undefined)).toBeNull();
        expect(parseGeometry('')).toBeNull();
        expect(parseGeometry('not json')).toBeNull();
        expect(parseGeometry('{"x":1,"y":2}')).toBeNull();
        expect(parseGeometry('{"x":1,"y":2,"w":"a","h":4}')).toBeNull();
    });

    test('parseGeometry reads a valid box', () => {
        expect(parseGeometry('{"x":10,"y":20,"w":300,"h":200}')).toEqual({x: 10, y: 20, w: 300, h: 200});
    });

    test('serializeGeometry rounds to whole units and round-trips', () => {
        const json = serializeGeometry({x: 10.4, y: 20.6, w: 300.5, h: 199.5});
        expect(JSON.parse(json)).toEqual({x: 10, y: 21, w: 301, h: 200});
        expect(parseGeometry(json)).toEqual({x: 10, y: 21, w: 301, h: 200});
    });

    test('normalizeBox gives a top-left origin with positive size', () => {
        expect(normalizeBox({x: 100, y: 100, w: -40, h: -30})).toEqual({x: 60, y: 70, w: 40, h: 30});
    });

    test('boxFromDrag normalises regardless of drag direction', () => {
        const a = boxFromDrag({x: 200, y: 200}, {x: 50, y: 60});
        expect(a).toEqual({x: 50, y: 60, w: 150, h: 140});
    });

    test('isDrawable enforces the minimum size', () => {
        expect(isDrawable({x: 0, y: 0, w: MIN_CONTAINER_SIZE, h: MIN_CONTAINER_SIZE})).toBe(true);
        expect(isDrawable({x: 0, y: 0, w: MIN_CONTAINER_SIZE - 1, h: 100})).toBe(false);
    });

    test('moveBox translates without changing size', () => {
        expect(moveBox({x: 10, y: 20, w: 100, h: 80}, 5, -5)).toEqual({x: 15, y: 15, w: 100, h: 80});
    });

    test('resizeBox grows from the bottom-right and clamps to the minimum', () => {
        expect(resizeBox({x: 10, y: 20, w: 100, h: 80}, 20, 10)).toEqual({x: 10, y: 20, w: 120, h: 90});
        const clamped = resizeBox({x: 0, y: 0, w: 50, h: 50}, -100, -100);
        expect(clamped.w).toBe(MIN_CONTAINER_SIZE);
        expect(clamped.h).toBe(MIN_CONTAINER_SIZE);
    });

    test('centerInBox is inclusive of the border', () => {
        const box = {x: 0, y: 0, w: 100, h: 100};
        expect(centerInBox({x: 50, y: 50}, box)).toBe(true);
        expect(centerInBox({x: 0, y: 100}, box)).toBe(true);
        expect(centerInBox({x: 101, y: 50}, box)).toBe(false);
    });

    test('re-arrange refit around a nested container keeps the child enclosed', () => {
        // Reproduces the container-in-container collapse fix: an outer container
        // refits around a member node AND a nested child container, so its
        // bounding box must fully contain the child box (the outer no longer
        // collapses onto the inner one).
        const childBox = {x: 200, y: 200, w: 150, h: 120};
        const memberNodeBox = {x: 40, y: 40, w: 60, h: 30};
        const fit = boundingBox([memberNodeBox, childBox], 24);

        expect(fit).not.toBeNull();
        const box = fit as {x: number; y: number; w: number; h: number};
        // The child container is entirely inside the refitted outer box.
        expect(box.x).toBeLessThanOrEqual(childBox.x);
        expect(box.y).toBeLessThanOrEqual(childBox.y);
        expect(box.x + box.w).toBeGreaterThanOrEqual(childBox.x + childBox.w);
        expect(box.y + box.h).toBeGreaterThanOrEqual(childBox.y + childBox.h);
        // Sanity: the child's centre lies inside the refitted outer box.
        expect(centerInBox({x: childBox.x + childBox.w / 2, y: childBox.y + childBox.h / 2}, box)).toBe(true);
    });
});
