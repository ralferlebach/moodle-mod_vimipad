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
 * Container behaviour of the layout refiner: members are confined inside,
 * non-members are pushed out (so overlapping-container membership is preserved),
 * and boxes are only minimally re-fitted — a foreign node never shrinks a
 * container.
 *
 * @module     mod_vimipad/tests/refine_containers
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, RefineNode, RefineEdge, RefineContainer} from '../src/graph/refine/refine_layout';
import {softCosh, softCoshDeriv} from '../src/graph/refine/potentials';

/** Whether a point lies inside a top-left box. */
function inBox(p: {x: number; y: number}, b: {x: number; y: number; w: number; h: number}): boolean {
    return p.x >= b.x && p.x <= b.x + b.w && p.y >= b.y && p.y <= b.y + b.h;
}

describe('softCosh (finite-difference and cap)', () => {
    const fd = (f: (x: number) => number, x: number, e = 1e-6): number => (f(x + e) - f(x - e)) / (2 * e);
    test('derivative matches inside and beyond the cap; force is bounded', () => {
        for (const u of [-8, -6, -2, 0, 1.5, 6, 9]) {
            expect(softCoshDeriv(u, 6)).toBeCloseTo(fd(x => softCosh(x, 6), u), 3);
        }
        // Beyond the cap the force stays bounded (linear tail), not explosive.
        expect(Math.abs(softCoshDeriv(1000, 6))).toBeCloseTo(Math.sinh(6), 6);
    });
});

describe('refineLayout containers', () => {
    const box = {x: 250, y: 250, w: 260, h: 200};

    test('a member starting outside is pulled inside the container', () => {
        const nodes: RefineNode[] = [
            {stableid: 'm', x: 620, y: 350, w: 60, h: 40}, // well outside to the right
            {stableid: 'anchor', x: 380, y: 350, w: 60, h: 40}, // gives a scale via the edge
        ];
        const edges: RefineEdge[] = [{source: 'anchor', target: 'm'}];
        const containers: RefineContainer[] = [{stableid: 'c', ...box, members: ['m', 'anchor']}];
        const res = refineLayout(nodes, edges, {stabilityScale: 0.2, containerIn: 3}, containers);
        // It moved substantially toward the box interior.
        expect(res.positions.m.x).toBeLessThan(620);
    });

    test('a non-member starting inside is pushed out of the container', () => {
        const nodes: RefineNode[] = [
            {stableid: 'f', x: 330, y: 310, w: 60, h: 40}, // inside but off-centre, not a member
            {stableid: 'p', x: 900, y: 350, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [{source: 'f', target: 'p'}];
        const containers: RefineContainer[] = [{stableid: 'c', ...box, members: []}];
        const res = refineLayout(nodes, edges, {stabilityScale: 0.2, containerOut: 4}, containers);
        // The foreign node moved away from the box centre.
        const before = Math.hypot(330 - (box.x + box.w / 2), 310 - (box.y + box.h / 2));
        const after = Math.hypot(res.positions.f.x - (box.x + box.w / 2), res.positions.f.y - (box.y + box.h / 2));
        expect(after).toBeGreaterThan(before);
    });

    test('overlapping containers: shared member stays in both, others stay separated', () => {
        // Two overlapping boxes. A is member of both, B of C1 only, C of C2 only.
        const c1 = {x: 200, y: 200, w: 300, h: 300};
        const c2 = {x: 400, y: 200, w: 300, h: 300}; // overlap x in [400,500]
        const nodes: RefineNode[] = [
            {stableid: 'A', x: 450, y: 350, w: 50, h: 40}, // in overlap
            {stableid: 'B', x: 260, y: 350, w: 50, h: 40}, // C1 only
            {stableid: 'C', x: 640, y: 350, w: 50, h: 40}, // C2 only
        ];
        const containers: RefineContainer[] = [
            {stableid: 'C1', ...c1, members: ['A', 'B']},
            {stableid: 'C2', ...c2, members: ['A', 'C']},
        ];
        const res = refineLayout(nodes, [], {stabilityScale: 1, containerIn: 2}, containers);
        const g1 = res.containers.C1;
        const g2 = res.containers.C2;
        // A inside both; B inside C1 not C2; C inside C2 not C1.
        expect(inBox(res.positions.A, g1) && inBox(res.positions.A, g2)).toBe(true);
        expect(inBox(res.positions.B, g1)).toBe(true);
        expect(inBox(res.positions.B, g2)).toBe(false);
        expect(inBox(res.positions.C, g2)).toBe(true);
        expect(inBox(res.positions.C, g1)).toBe(false);
    });

    test('the box grows to contain a member and the member ends up inside', () => {
        // A member near the right wall; the fit must ensure it stays enclosed.
        const nodes: RefineNode[] = [
            {stableid: 'm', x: 495, y: 350, w: 60, h: 40},
            {stableid: 'n', x: 320, y: 350, w: 60, h: 40},
        ];
        const containers: RefineContainer[] = [{stableid: 'c', ...box, members: ['m', 'n']}];
        const res = refineLayout(nodes, [{source: 'm', target: 'n'}], {stabilityScale: 1}, containers);
        expect(inBox(res.positions.m, res.containers.c)).toBe(true);
        expect(inBox(res.positions.n, res.containers.c)).toBe(true);
    });

    test('a foreign node does not shrink the container around its members', () => {
        const nodes: RefineNode[] = [
            {stableid: 'm1', x: 300, y: 320, w: 50, h: 40},
            {stableid: 'm2', x: 440, y: 380, w: 50, h: 40},
            {stableid: 'f', x: 380, y: 350, w: 40, h: 30}, // foreign, sitting inside
        ];
        const containers: RefineContainer[] = [{stableid: 'c', ...box, members: ['m1', 'm2']}];
        const res = refineLayout(nodes, [], {stabilityScale: 2, containerOut: 3}, containers);
        // The fitted box still comfortably encloses both members.
        expect(inBox(res.positions.m1, res.containers.c)).toBe(true);
        expect(inBox(res.positions.m2, res.containers.c)).toBe(true);
        // And it did not collapse to a sliver.
        expect(res.containers.c.w).toBeGreaterThan(60);
        expect(res.containers.c.h).toBeGreaterThan(40);
    });

    test('a fixed container box is never resized', () => {
        const nodes: RefineNode[] = [{stableid: 'm', x: 380, y: 350, w: 50, h: 40}];
        const containers: RefineContainer[] = [{stableid: 'c', ...box, members: ['m'], fixed: true}];
        const res = refineLayout(nodes, [], {}, containers);
        expect(res.containers.c).toEqual(box);
    });
});
