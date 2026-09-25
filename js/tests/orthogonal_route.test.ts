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
 * Right-angled connector routing.
 *
 * The property that matters to a reader is that the connector arrives
 * perpendicular to the edge it lands on: an arrowhead that runs along the side
 * of a box reads as pointing past the node rather than into it.
 *
 * @module mod_vimipad/tests/orthogonal_route
 */

import {orthogonalRoute, orthogonalSides} from '../src/canvas/node_geometry';
import {Point, Size} from '../src/types';

/** A typical process box. */
const BOX: Size = {w: 160, h: 44};

/**
 * The points of a path, in order.
 *
 * @param d The path data.
 * @returns The polyline points.
 */
function points(d: string): Point[] {
    return [...d.matchAll(/(-?[\d.]+) (-?[\d.]+)/g)].map(m => ({x: Number(m[1]), y: Number(m[2])}));
}

/**
 * The direction of the final segment, normalised to -1, 0 or 1 per axis.
 *
 * @param d The path data.
 * @returns The arrival direction.
 */
function arrival(d: string): {x: number; y: number} {
    const p = points(d);
    const last = p[p.length - 1];
    // Skip any zero-length tail so the direction is the real one.
    for (let i = p.length - 2; i >= 0; i--) {
        const dx = last.x - p[i].x;
        const dy = last.y - p[i].y;
        if (Math.abs(dx) > 0.01 || Math.abs(dy) > 0.01) {
            return {x: Math.sign(dx), y: Math.sign(dy)};
        }
    }
    return {x: 0, y: 0};
}

describe('orthogonal routing arrives perpendicular to the edge', () => {
    test('a node directly below is entered from above', () => {
        const route = orthogonalRoute(
            {x: 300, y: 100}, BOX, 'rect',
            {x: 300, y: 300}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: 0, y: 1});
        // The anchor sits on the target's top edge, centred.
        expect(route.to.x).toBeCloseTo(300, 5);
        expect(route.to.y).toBeLessThan(300);
    });

    test('a mostly sideways target is entered through its side', () => {
        // The retry edge from the screenshot: a short rise but a long run, so
        // the connector belongs on the side rather than on the bottom edge,
        // where its arrowhead used to run along the box.
        const route = orthogonalRoute(
            {x: 100, y: 340}, BOX, 'rect',
            {x: 305, y: 240}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: 1, y: 0});
        expect(route.to.x).toBeLessThan(305);
        expect(route.to.y).toBeCloseTo(240, 5);
    });

    test('a mostly downward but offset target is entered from above', () => {
        // Long drop, small sideways shift: the connector stays vertical and
        // lands on the top edge, perpendicular to it.
        const route = orthogonalRoute(
            {x: 100, y: 100}, BOX, 'rect',
            {x: 160, y: 420}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: 0, y: 1});
        expect(route.to.x).toBeCloseTo(160, 5);
        expect(route.to.y).toBeLessThan(420);
    });

    test('a node to the right is entered from the left', () => {
        const route = orthogonalRoute(
            {x: 100, y: 200}, BOX, 'rect',
            {x: 600, y: 210}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: 1, y: 0});
        expect(route.to.x).toBeLessThan(600);
        expect(route.to.y).toBeCloseTo(210, 5);
    });

    test('a node to the left is entered from the right', () => {
        const route = orthogonalRoute(
            {x: 600, y: 200}, BOX, 'rect',
            {x: 100, y: 205}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: -1, y: 0});
        expect(route.to.x).toBeGreaterThan(100);
    });

    test('a node above is entered from below', () => {
        const route = orthogonalRoute(
            {x: 300, y: 400}, BOX, 'rect',
            {x: 300, y: 150}, BOX, 'rect'
        );
        expect(arrival(route.d)).toEqual({x: 0, y: -1});
        expect(route.to.y).toBeGreaterThan(150);
    });
});

describe('orthogonal routing leaves the source cleanly', () => {
    test('the connector leaves through the edge facing the target', () => {
        const down = orthogonalRoute(
            {x: 300, y: 100}, BOX, 'rect',
            {x: 320, y: 400}, BOX, 'rect'
        );
        // Leaving downwards, so the start sits below the source centre.
        expect(down.from.y).toBeGreaterThan(100);
        expect(down.from.x).toBeCloseTo(300, 5);

        const right = orthogonalRoute(
            {x: 100, y: 200}, BOX, 'rect',
            {x: 700, y: 210}, BOX, 'rect'
        );
        expect(right.from.x).toBeGreaterThan(100);
        expect(right.from.y).toBeCloseTo(200, 5);
    });

    test('every segment is horizontal or vertical', () => {
        const route = orthogonalRoute(
            {x: 100, y: 340}, BOX, 'rect',
            {x: 305, y: 240}, BOX, 'rect'
        );
        const p = points(route.d);
        for (let i = 1; i < p.length; i++) {
            const dx = Math.abs(p[i].x - p[i - 1].x);
            const dy = Math.abs(p[i].y - p[i - 1].y);
            expect(dx < 0.01 || dy < 0.01).toBe(true);
        }
    });

    test('the path starts and ends at the reported anchors', () => {
        const route = orthogonalRoute(
            {x: 100, y: 340}, BOX, 'rect',
            {x: 305, y: 240}, BOX, 'rect'
        );
        const p = points(route.d);
        expect(p[0]).toEqual(route.from);
        expect(p[p.length - 1]).toEqual(route.to);
    });
});

describe('orthogonal routing respects node shape', () => {
    test('a decision diamond is entered at its vertex, not its bounding box', () => {
        const route = orthogonalRoute(
            {x: 300, y: 100}, BOX, 'rect',
            {x: 300, y: 300}, {w: 160, h: 90}, 'diamond'
        );
        // The diamond's top vertex is its highest point; entering from above
        // must land there rather than on the box corner.
        expect(route.to.x).toBeCloseTo(300, 5);
        expect(route.to.y).toBeCloseTo(300 - (90 / 2 + 2), 0);
    });

    test('a start/end capsule is entered on its flat side', () => {
        const route = orthogonalRoute(
            {x: 300, y: 400}, BOX, 'rect',
            {x: 300, y: 150}, BOX, 'terminator'
        );
        expect(arrival(route.d)).toEqual({x: 0, y: -1});
        expect(route.to.x).toBeCloseTo(300, 5);
    });
});

describe('parallel connectors keep their distance', () => {
    const A = {x: 300, y: 100};
    const B = {x: 300, y: 400};

    /**
     * Route the same pair at a given sibling slot.
     *
     * @param offset The sibling offset.
     * @returns The route.
     */
    function at(offset: number) {
        return orthogonalRoute(A, BOX, 'rect', B, BOX, 'rect', offset);
    }

    test('two connectors between the same pair do not overlap', () => {
        const left = at(-18);
        const right = at(18);
        expect(left.from.x).not.toBeCloseTo(right.from.x, 1);
        expect(left.to.x).not.toBeCloseTo(right.to.x, 1);
        expect(left.d).not.toBe(right.d);
    });

    test('the shared run is stepped as well, not just the anchors', () => {
        // Anchors apart but a common bend line would still overlap along it.
        const bend = (d: string): number => points(d)[1].y;
        expect(bend(at(-18).d)).not.toBeCloseTo(bend(at(18).d), 1);
    });

    test('a zero offset routes exactly as before', () => {
        expect(at(0)).toEqual(orthogonalRoute(A, BOX, 'rect', B, BOX, 'rect'));
    });

    test('the step stays on the node edge, however many siblings', () => {
        // A large offset must not start the connector beside the node.
        const far = at(500);
        expect(Math.abs(far.from.x - A.x)).toBeLessThanOrEqual(BOX.w / 2);
        expect(Math.abs(far.to.x - B.x)).toBeLessThanOrEqual(BOX.w / 2);
    });

    test('sideways routes step perpendicular too', () => {
        const C = {x: 700, y: 200};
        const D = {x: 100, y: 205};
        const up = orthogonalRoute(C, BOX, 'rect', D, BOX, 'rect', -14);
        const down = orthogonalRoute(C, BOX, 'rect', D, BOX, 'rect', 14);
        expect(up.from.y).not.toBeCloseTo(down.from.y, 1);
        expect(up.to.y).not.toBeCloseTo(down.to.y, 1);
    });

    test('arrival stays perpendicular when siblings are offset', () => {
        // The offset must not undo the fix that made arrows point into the node.
        for (const offset of [-18, 0, 18]) {
            expect(arrival(at(offset).d)).toEqual({x: 0, y: 1});
        }
    });
});

describe('connectors sharing one node edge', () => {
    test('the side used is reported for grouping', () => {
        // Grouping by node and side is what lets separate relations that happen
        // to meet the same edge be spread along it.
        expect(orthogonalSides({x: 0, y: 0}, {x: 0, y: 100})).toEqual({from: 'bottom', to: 'top'});
        expect(orthogonalSides({x: 0, y: 0}, {x: 0, y: -100})).toEqual({from: 'top', to: 'bottom'});
        expect(orthogonalSides({x: 0, y: 0}, {x: 100, y: 5})).toEqual({from: 'right', to: 'left'});
        expect(orthogonalSides({x: 0, y: 0}, {x: -100, y: 5})).toEqual({from: 'left', to: 'right'});
    });

    test('the two ends can be offset independently', () => {
        // The screenshot case: one relation arrives at a node's right edge and
        // another leaves from it. They belong to different pairs, so each end
        // gets its own slot rather than one shared offset.
        const shared = {x: 100, y: 200};
        const arriving = orthogonalRoute(
            {x: 500, y: 205}, BOX, 'rect', shared, BOX, 'rect', 0, -14
        );
        const leaving = orthogonalRoute(
            shared, BOX, 'rect', {x: 500, y: 60}, BOX, 'rect', 14, 0
        );
        // Both touch the same edge of the shared node, at different heights.
        expect(arriving.to.x).toBeCloseTo(leaving.from.x, 0);
        expect(arriving.to.y).not.toBeCloseTo(leaving.from.y, 1);
    });

    test('an unoffset end still sits on the edge centre', () => {
        const route = orthogonalRoute(
            {x: 100, y: 200}, BOX, 'rect', {x: 600, y: 205}, BOX, 'rect', 0, 0
        );
        expect(route.from.y).toBeCloseTo(200, 5);
        expect(route.to.y).toBeCloseTo(205, 5);
    });

    test('offsetting one end does not disturb the other', () => {
        const plain = orthogonalRoute({x: 300, y: 100}, BOX, 'rect', {x: 300, y: 400}, BOX, 'rect');
        const shifted = orthogonalRoute(
            {x: 300, y: 100}, BOX, 'rect', {x: 300, y: 400}, BOX, 'rect', 0, 16
        );
        expect(shifted.from).toEqual(plain.from);
        expect(shifted.to.x).not.toBeCloseTo(plain.to.x, 1);
    });
});
