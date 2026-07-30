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
 * Tests for the departure/arrival angles and parallel routing of connections.
 *
 * @module     mod_vimipad/tests/connector_angles
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    bisectAngles, connectorExitAngle, freeConnectorPath, labelPoint, offsetAnchors, orientationOf,
    siblingOffsets,
} from '../src/canvas/connection_geometry';

const deg = (radians: number): number => radians * 180 / Math.PI;

describe('connector orientation and angles', () => {
    test('the direct line decides horizontal vs vertical', () => {
        expect(orientationOf({x: 0, y: 0}, {x: 100, y: 20})).toBe('horizontal');
        expect(orientationOf({x: 0, y: 0}, {x: 20, y: 100})).toBe('vertical');
    });

    test('bisectAngles takes the shorter way around the circle', () => {
        expect(deg(bisectAngles(0, Math.PI / 2))).toBeCloseTo(45, 6);
        // 350 deg and 10 deg bisect at 0, not at 180.
        expect(deg(bisectAngles(-Math.PI / 18, Math.PI / 18))).toBeCloseTo(0, 6);
    });

    test('a purely horizontal connection leaves and arrives horizontally', () => {
        const from = {x: 0, y: 0};
        const to = {x: 200, y: 0};
        expect(deg(connectorExitAngle(from, to, true))).toBeCloseTo(0, 6);
        // Measured outward from the target, i.e. back towards the source.
        expect(Math.abs(deg(connectorExitAngle(from, to, false)))).toBeCloseTo(180, 6);
    });

    test('a diagonal connection leaves on the bisector of axis and direct line', () => {
        // 45 deg direct line, dominant axis horizontal -> 22.5 deg departure.
        const angle = connectorExitAngle({x: 0, y: 0}, {x: 100, y: 100}, true);
        expect(deg(angle)).toBeCloseTo(22.5, 6);
    });

    test('a vertical connection uses the vertical axis as its perpendicular', () => {
        // Direct line 63.43 deg, dominant axis vertical (90 deg) -> 76.7 deg.
        const angle = connectorExitAngle({x: 0, y: 0}, {x: 50, y: 100}, true);
        expect(deg(angle)).toBeCloseTo((90 + deg(Math.atan2(100, 50))) / 2, 6);
    });

    test('departure and arrival are mirror images for a symmetric pair', () => {
        const from = {x: 0, y: 0};
        const to = {x: 100, y: 100};
        const start = deg(connectorExitAngle(from, to, true));
        const end = deg(connectorExitAngle(from, to, false));
        expect(start).toBeCloseTo(22.5, 6);
        expect(end).toBeCloseTo(-180 + 22.5, 6);
    });
});

describe('parallel sibling connections', () => {
    test('offsets are symmetric around zero', () => {
        expect(siblingOffsets(1, 14)).toEqual([0]);
        expect(siblingOffsets(2, 14)).toEqual([-7, 7]);
        expect(siblingOffsets(3, 14)).toEqual([-14, 0, 14]);
    });

    test('both anchors shift perpendicular, keeping the connection parallel', () => {
        const from = {x: 0, y: 0};
        const to = {x: 100, y: 0};
        const shifted = offsetAnchors(from, to, 10);
        // Perpendicular to a horizontal line is vertical, and both ends move alike.
        expect(shifted.from).toEqual({x: 0, y: 10});
        expect(shifted.to).toEqual({x: 100, y: 10});

        const before = {x: to.x - from.x, y: to.y - from.y};
        const after = {x: shifted.to.x - shifted.from.x, y: shifted.to.y - shifted.from.y};
        expect(after).toEqual(before);
    });

    test('opposite offsets land on opposite sides', () => {
        const a = offsetAnchors({x: 0, y: 0}, {x: 100, y: 0}, 10);
        const b = offsetAnchors({x: 0, y: 0}, {x: 100, y: 0}, -10);
        expect(a.from.y).toBeCloseTo(-b.from.y, 6);
    });
});

describe('free connector path', () => {
    test('starts and ends with a straight run of the arrow length', () => {
        const d = freeConnectorPath({x: 0, y: 0}, {x: 200, y: 0}, 12);
        // M start, L stub, C curve, L end.
        expect(d).toMatch(/^M 0 0 L 12 0 C .* L 200 0$/);
    });

    test('the straight run follows the departure angle', () => {
        const from = {x: 0, y: 0};
        const to = {x: 100, y: 100};
        const d = freeConnectorPath(from, to, 12);
        const stub = d.match(/^M [\d.-]+ [\d.-]+ L ([\d.-]+) ([\d.-]+)/);
        expect(stub).not.toBeNull();
        const angle = deg(Math.atan2(Number(stub?.[2]), Number(stub?.[1])));
        expect(angle).toBeCloseTo(22.5, 1);
    });

    test('coincident anchors do not produce NaN', () => {
        const d = freeConnectorPath({x: 5, y: 5}, {x: 5, y: 5}, 12);
        expect(d.includes('NaN')).toBe(false);
    });
});

describe('label follows its own connector', () => {
    const from = {x: 0, y: 0};
    const to = {x: 200, y: 0};

    test('siblings get labels on different sides of the centre line', () => {
        const offsets = siblingOffsets(2, 16); // [-8, 8]
        const a = offsetAnchors(from, to, offsets[0]);
        const b = offsetAnchors(from, to, offsets[1]);
        const la = labelPoint(a.from, a.to, offsets[0]);
        const lb = labelPoint(b.from, b.to, offsets[1]);
        // A horizontal pair offsets vertically; the two labels straddle y = 0.
        expect(Math.sign(la.y)).toBe(-Math.sign(lb.y));
        expect(la.y).toBeCloseTo(-lb.y, 6);
        expect(la.y).not.toBeCloseTo(0, 1);
    });

    test('the single-connection label stays on the centre line', () => {
        const l = labelPoint(from, to, 0);
        expect(l).toEqual({x: 100, y: 0});
    });

    test('the label sits near the peak of its own curved path', () => {
        const offset = 12;
        const shifted = offsetAnchors(from, to, offset);
        const label = labelPoint(shifted.from, shifted.to, offset);
        // The path bulges to roughly the same perpendicular distance as the label.
        const d = freeConnectorPath(shifted.from, shifted.to, 12);
        const ys = [...d.matchAll(/-?\d+(?:\.\d+)?/g)].map(Number).filter((_, i) => i % 2 === 1);
        const maxAbsY = Math.max(...ys.map(Math.abs));
        // Label offset and the path's vertical extent are the same order of size.
        expect(Math.abs(label.y)).toBeGreaterThan(offset - 1);
        expect(maxAbsY).toBeGreaterThan(offset - 1);
    });
});
