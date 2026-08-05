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
 * Behavioural guarantees of the layout refiner: it descends monotonically, is
 * deterministic, preserves a good human layout, separates overlaps, pulls a
 * badly-scaled edge toward the ideal length, and aligns directed edges — all
 * without ever re-initialising.
 *
 * @module     mod_vimipad/tests/refine_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    refineLayout, buildProblem, energyAndGradient, defaultRefineOptions, RefineNode, RefineEdge,
} from '../src/graph/refine/refine_layout';

const dist = (a: {x: number; y: number}, b: {x: number; y: number}): number => Math.hypot(a.x - b.x, a.y - b.y);

describe('refineLayout behaviour', () => {
    test('is deterministic: identical inputs give identical output', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 100, w: 80, h: 40},
            {stableid: 'b', x: 300, y: 110, w: 80, h: 40},
            {stableid: 'c', x: 210, y: 300, w: 80, h: 40},
        ];
        const edges: RefineEdge[] = [{source: 'a', target: 'b'}, {source: 'b', target: 'c'}];
        const r1 = refineLayout(nodes, edges);
        const r2 = refineLayout(nodes, edges);
        expect(r1.positions).toEqual(r2.positions);
    });

    test('energy never increases (monotone descent)', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 100, w: 80, h: 40},
            {stableid: 'b', x: 108, y: 104, w: 80, h: 40}, // overlapping
            {stableid: 'c', x: 600, y: 120, w: 80, h: 40}, // very long edge
        ];
        const edges: RefineEdge[] = [{source: 'a', target: 'c'}, {source: 'a', target: 'b'}];
        const res = refineLayout(nodes, edges);
        expect(res.energyEnd).toBeLessThanOrEqual(res.energyStart);
    });

    test('preserves a clean human layout: nodes barely move', () => {
        // Three nodes already ~L apart along a line, no overlaps.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 200, w: 60, h: 40},
            {stableid: 'b', x: 250, y: 200, w: 60, h: 40},
            {stableid: 'c', x: 400, y: 200, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [{source: 'a', target: 'b'}, {source: 'b', target: 'c'}];
        const res = refineLayout(nodes, edges, {stabilityScale: 2});
        for (const nd of nodes) {
            expect(dist(res.positions[nd.stableid], {x: nd.x, y: nd.y})).toBeLessThan(6);
        }
    });

    test('a high stability weight keeps movement smaller than a low one', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 100, w: 80, h: 40},
            {stableid: 'b', x: 118, y: 108, w: 80, h: 40}, // overlap to create pressure
        ];
        const edges: RefineEdge[] = [];
        const stiff = refineLayout(nodes, edges, {stabilityScale: 8});
        const loose = refineLayout(nodes, edges, {stabilityScale: 0.5});
        const moveStiff = dist(stiff.positions.a, {x: 100, y: 100}) + dist(stiff.positions.b, {x: 118, y: 108});
        const moveLoose = dist(loose.positions.a, {x: 100, y: 100}) + dist(loose.positions.b, {x: 118, y: 108});
        expect(moveStiff).toBeLessThan(moveLoose);
    });

    test('separates overlapping non-adjacent nodes', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 200, y: 200, w: 100, h: 50},
            {stableid: 'b', x: 210, y: 205, w: 100, h: 50}, // heavy overlap, no edge
            {stableid: 'c', x: 600, y: 200, w: 60, h: 40},
            {stableid: 'd', x: 600, y: 360, w: 60, h: 40},
        ];
        // Edges only to give a sensible scale L; a-b are NOT connected.
        const edges: RefineEdge[] = [{source: 'c', target: 'd'}];
        const before = dist({x: 200, y: 200}, {x: 210, y: 205});
        const res = refineLayout(nodes, edges, {stabilityScale: 0.5});
        const after = dist(res.positions.a, res.positions.b);
        expect(after).toBeGreaterThan(before);
    });

    test('pulls an over-long edge toward the ideal length L', () => {
        // Two connected nodes far apart; L comes from the two shorter edges so the
        // long a-d edge is well above L and should contract.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 300, w: 60, h: 40},
            {stableid: 'd', x: 900, y: 300, w: 60, h: 40},
            {stableid: 'e', x: 100, y: 460, w: 60, h: 40},
            {stableid: 'f', x: 260, y: 460, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [
            {source: 'a', target: 'd'}, // long
            {source: 'e', target: 'f'}, // ~160 -> sets L
        ];
        const res = refineLayout(nodes, edges, {stabilityScale: 0.3});
        const before = 800;
        const after = dist(res.positions.a, res.positions.d);
        expect(after).toBeLessThan(before);
    });

    test('aligns a directed edge toward the preferred direction', () => {
        // a -> b should point downward (preferredDir +y); start it pointing right.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 300, y: 300, w: 60, h: 40},
            {stableid: 'b', x: 460, y: 300, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [{source: 'a', target: 'b', directed: true}];
        const res = refineLayout(nodes, edges, {
            preferredDir: {x: 0, y: 1}, directionFloor: 0.2, stabilityScale: 0.2,
        });
        const dx = res.positions.b.x - res.positions.a.x;
        const dy = res.positions.b.y - res.positions.a.y;
        // The vertical component should grow relative to the horizontal one.
        expect(Math.abs(dy) / (Math.abs(dx) + 1e-6)).toBeGreaterThan(0);
        expect(dy).not.toBeCloseTo(0, 1);
    });

    test('respects a fixed node', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 200, y: 200, w: 80, h: 40, fixed: true},
            {stableid: 'b', x: 208, y: 205, w: 80, h: 40}, // overlaps the fixed a
        ];
        const res = refineLayout(nodes, [], {stabilityScale: 0.5});
        expect(res.positions.a).toEqual({x: 200, y: 200});
        expect(dist(res.positions.b, {x: 208, y: 205})).toBeGreaterThan(0);
    });

    test('regularises exact coincidences without randomness', () => {
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 300, y: 300, w: 60, h: 40},
            {stableid: 'b', x: 300, y: 300, w: 60, h: 40}, // identical position
        ];
        const r1 = refineLayout(nodes, []);
        const r2 = refineLayout(nodes, []);
        expect(dist(r1.positions.a, r1.positions.b)).toBeGreaterThan(0);
        expect(r1.positions).toEqual(r2.positions); // deterministic separation
    });

    test('the order term produces a restoring force as a pair nears a swap', () => {
        // Reference: a clearly left of b, so an order constraint is created.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 300, y: 300, w: 40, h: 40},
            {stableid: 'b', x: 500, y: 300, w: 40, h: 40},
        ];
        const opts = {...defaultRefineOptions(), scale: 200, orderAxis: {x: 1, y: 0}, orderStrength: 5};
        const prob = buildProblem(nodes, [], opts);
        expect(prob.order.length).toBe(1); // the a<b constraint exists

        // Push b to within the margin of a (nearly swapped): the order term must
        // pull a left (-x) and b right (+x) to restore the gap.
        prob.px[1] = 312; // b now only 12 to the right of a; margin is 0.1*L = 20.
        const gx = new Float64Array(2);
        const gy = new Float64Array(2);
        energyAndGradient(prob, [gx, gy]);
        // Force = -gradient. a should feel -x, b should feel +x.
        expect(-gx[0]).toBeLessThan(0); // a pushed left
        expect(-gx[1]).toBeGreaterThan(0); // b pushed right
    });

    test('by default (no order axis) the order term is inactive', () => {
        // Two well-separated nodes on a line; without an order axis nothing extra
        // constrains them, so a plain run and an axis-less run agree.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 200, y: 300, w: 60, h: 40},
            {stableid: 'b', x: 380, y: 300, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [{source: 'a', target: 'b'}];
        const a = refineLayout(nodes, edges);
        const b = refineLayout(nodes, edges, {orderAxis: null});
        expect(a.positions).toEqual(b.positions);
    });
});
