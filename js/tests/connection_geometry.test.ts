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
    siblingOffsets,
    controlPoint,
    labelPoint,
    rectBorderPoint,
    markersFor,
} from '../src/canvas/connection_geometry';

describe('connection geometry', () => {
    describe('siblingOffsets (separating parallel connections)', () => {
        test('a single connection is centred (no offset)', () => {
            expect(siblingOffsets(1, 20)).toEqual([0]);
        });

        test('two connections are offset symmetrically', () => {
            expect(siblingOffsets(2, 20)).toEqual([-10, 10]);
        });

        test('three connections include a straight centre one', () => {
            expect(siblingOffsets(3, 20)).toEqual([-20, 0, 20]);
        });

        test('offsets are always symmetric around zero', () => {
            const offs = siblingOffsets(4, 10);
            const sum = offs.reduce((a, b) => a + b, 0);
            expect(sum).toBeCloseTo(0);
            expect(offs).toHaveLength(4);
        });
    });

    describe('controlPoint / labelPoint', () => {
        test('a zero offset yields a control point on the straight midline', () => {
            const c = controlPoint({x: 0, y: 0}, {x: 100, y: 0}, 0);
            expect(c).toEqual({x: 50, y: 0});
        });

        test('the label sits at the curve peak, offset perpendicular to the line', () => {
            // Horizontal line: perpendicular is vertical, so an offset moves y.
            const l = labelPoint({x: 0, y: 0}, {x: 100, y: 0}, 15);
            expect(l.x).toBeCloseTo(50);
            expect(Math.abs(l.y)).toBeCloseTo(15);
        });

        test('label peak is half the control offset (quadratic Bezier midpoint)', () => {
            const from = {x: 0, y: 0};
            const to = {x: 0, y: 100};
            const offset = 12;
            const c = controlPoint(from, to, offset);
            const l = labelPoint(from, to, offset);
            // Control is offset by 2*offset; label (Bezier midpoint) by offset.
            expect(Math.abs(c.x)).toBeCloseTo(2 * offset);
            expect(Math.abs(l.x)).toBeCloseTo(offset);
        });
    });

    describe('rectBorderPoint (anchor on the node border)', () => {
        test('exits the right edge toward a point to the right', () => {
            const p = rectBorderPoint({x: 0, y: 0}, 50, 16, {x: 200, y: 0});
            expect(p.x).toBeCloseTo(50);
            expect(p.y).toBeCloseTo(0);
        });

        test('exits the top edge toward a point straight above', () => {
            const p = rectBorderPoint({x: 0, y: 0}, 50, 16, {x: 0, y: -200});
            expect(p.y).toBeCloseTo(-16);
            expect(p.x).toBeCloseTo(0);
        });

        test('the anchor lies on the border, never past it', () => {
            const p = rectBorderPoint({x: 10, y: 10}, 40, 20, {x: 300, y: 60});
            // On the right or bottom edge of the box centred at (10,10).
            const onRight = Math.abs(p.x - (10 + 40)) < 1e-6;
            const onBottom = Math.abs(p.y - (10 + 20)) < 1e-6;
            expect(onRight || onBottom).toBe(true);
        });
    });

    describe('markersFor (arrows vs nubs)', () => {
        test('undirected (0) gets a nub at both ends', () => {
            expect(markersFor(0)).toEqual({start: 'nub', end: 'nub'});
        });

        test('directed (1) gets an arrow at the target end only', () => {
            expect(markersFor(1)).toEqual({start: 'none', end: 'arrow'});
        });

        test('bidirectional (2) gets an arrow at both ends', () => {
            expect(markersFor(2)).toEqual({start: 'arrow', end: 'arrow'});
        });
    });
});
