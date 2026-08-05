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
 * Behavioural tests for cyclic (angular) order preservation on radial forms.
 *
 * The refiner keeps the initial cyclic order of a hub's neighbours around it,
 * so a radial fan cannot scramble on arrange. These tests cover the constraint
 * construction (which pairs are chained, and the >=3-neighbour gate), that the
 * term is actually engaged and firms up the ordering, and that the order is
 * preserved after a full refinement of a radial hub. The analytic gradient of
 * the term is checked separately in refine_gradient.test.ts.
 *
 * @module     mod_vimipad/tests/refine_cyclic
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, buildProblem, defaultRefineOptions, RefineNode, RefineEdge} from '../src/graph/refine/refine_layout';

/** Signed cross product (a-h) x (b-h): positive when b is CCW of a about h. */
function cross(p: Record<string, {x: number; y: number}>, h: string, a: string, b: string): number {
    const ax = p[a].x - p[h].x, ay = p[a].y - p[h].y;
    const bx = p[b].x - p[h].x, by = p[b].y - p[h].y;
    return ax * by - ay * bx;
}

/** A hub with four neighbours (right, up, left, down) plus a couple of leaves. */
function radialHub(): {nodes: RefineNode[]; edges: RefineEdge[]} {
    const nodes: RefineNode[] = [
        {stableid: 'h', x: 300, y: 300, w: 50, h: 40},
        {stableid: 'e', x: 460, y: 300, w: 50, h: 40}, // ~right
        {stableid: 'n', x: 300, y: 150, w: 50, h: 40}, // ~up
        {stableid: 'w', x: 140, y: 300, w: 50, h: 40}, // ~left
        {stableid: 's', x: 300, y: 460, w: 50, h: 40}, // ~down
    ];
    const edges: RefineEdge[] = [
        {source: 'h', target: 'e', directed: false},
        {source: 'h', target: 'n', directed: false},
        {source: 'h', target: 'w', directed: false},
        {source: 'h', target: 's', directed: false},
    ];
    return {nodes, edges};
}

describe('cyclic order preservation', () => {
    const base = {...defaultRefineOptions(), stabilityScale: 0.05, scale: 150, maxIterations: 400};

    test('a hub with k>=3 neighbours yields k-1 chained constraints', () => {
        const {nodes, edges} = radialHub();
        const prob = buildProblem(nodes, edges, {...base, cyclicStrength: 4});
        // Four neighbours around h => the chain skips the largest gap => 3 pairs.
        expect(prob.cyclic.length).toBe(3);
        // Every constraint is anchored at the hub.
        for (const c of prob.cyclic) {
            expect(c.h).toBe(0); // 'h' is node index 0
        }
    });

    test('no constraints are built when the term is disabled', () => {
        const {nodes, edges} = radialHub();
        const prob = buildProblem(nodes, edges, {...base, cyclicStrength: 0});
        expect(prob.cyclic.length).toBe(0);
    });

    test('a node with fewer than three neighbours contributes no constraint', () => {
        // A simple chain a-b-c: b has two neighbours, a and c one each -> no hub.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 100, w: 50, h: 40},
            {stableid: 'b', x: 250, y: 100, w: 50, h: 40},
            {stableid: 'c', x: 400, y: 100, w: 50, h: 40},
        ];
        const edges: RefineEdge[] = [
            {source: 'a', target: 'b', directed: false},
            {source: 'b', target: 'c', directed: false},
        ];
        const prob = buildProblem(nodes, edges, {...base, cyclicStrength: 4});
        expect(prob.cyclic.length).toBe(0);
    });

    test('the term is engaged: it firms up a hub\'s angular ordering', () => {
        // Two neighbours start almost collinear with the hub (a just above b on
        // the right), with leaves dragging a down and b up so they are pressed
        // toward each other's angle. The term should keep — and strengthen — the
        // counter-clockwise ordering, i.e. cross(a,b) stays clearly positive and
        // larger than without the term.
        const nodes: RefineNode[] = [
            {stableid: 'h', x: 300, y: 300, w: 50, h: 40},
            {stableid: 'a', x: 450, y: 296, w: 50, h: 40},
            {stableid: 'b', x: 450, y: 304, w: 50, h: 40},
            {stableid: 'c', x: 150, y: 300, w: 50, h: 40},
            {stableid: 'da', x: 455, y: 560, w: 30, h: 24}, // drags a down
            {stableid: 'db', x: 455, y: 40, w: 30, h: 24}, // drags b up
        ];
        const edges: RefineEdge[] = [
            {source: 'h', target: 'a', directed: false},
            {source: 'h', target: 'b', directed: false},
            {source: 'h', target: 'c', directed: false},
            {source: 'a', target: 'da', directed: false},
            {source: 'b', target: 'db', directed: false},
        ];
        const off = refineLayout(nodes, edges, {...base, cyclicStrength: 0}).positions;
        const on = refineLayout(nodes, edges, {...base, cyclicStrength: 6}).positions;
        const cOff = cross(off, 'h', 'a', 'b');
        const cOn = cross(on, 'h', 'a', 'b');
        expect(cOn).toBeGreaterThan(0); // order preserved (b stays CCW of a)
        expect(cOn).toBeGreaterThan(cOff); // the term strengthens the ordering
    });

    test('a radial hub keeps every neighbour in its initial cyclic order', () => {
        const {nodes, edges} = radialHub();
        // Sanity: the four neighbours are in CCW order e, s, w, n by construction
        // (screen y points down). After refinement with the term on, each of the
        // chained adjacent pairs must remain non-inverted (cross >= 0).
        const prob = buildProblem(nodes, edges, {...base, cyclicStrength: 4});
        const names = ['h', 'e', 'n', 'w', 's'];
        const res = refineLayout(nodes, edges, {...base, cyclicStrength: 4}).positions;
        for (const c of prob.cyclic) {
            const a = names[c.a];
            const b = names[c.b];
            expect(cross(res, 'h', a, b)).toBeGreaterThanOrEqual(0);
        }
    });
});
