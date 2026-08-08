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
 * Behavioural test for 1D line confinement (timeline profile): nodes spread
 * across the page are pulled onto a single line parallel to the confine axis.
 *
 * @module     mod_vimipad/tests/refine_line_confine
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, buildProblem, defaultRefineOptions, RefineNode, RefineEdge} from '../src/graph/refine/refine_layout';

/** Vertical spread: max distance of any node from the mean y. */
function ySpread(p: Record<string, {x: number; y: number}>): number {
    const ys = Object.values(p).map(v => v.y);
    const mean = ys.reduce((a, b) => a + b, 0) / ys.length;
    return Math.max(...ys.map(y => Math.abs(y - mean)));
}

describe('1D line confinement (timeline)', () => {
    const base = {...defaultRefineOptions(), stabilityScale: 0.05, scale: 150, maxIterations: 400};

    function chain(): {nodes: RefineNode[]; edges: RefineEdge[]} {
        // Four events, deliberately scattered in y, connected in sequence.
        const nodes: RefineNode[] = [
            {stableid: 'a', x: 100, y: 120, w: 60, h: 40},
            {stableid: 'b', x: 260, y: 360, w: 60, h: 40},
            {stableid: 'c', x: 420, y: 180, w: 60, h: 40},
            {stableid: 'd', x: 580, y: 420, w: 60, h: 40},
        ];
        const edges: RefineEdge[] = [
            {source: 'a', target: 'b', directed: true},
            {source: 'b', target: 'c', directed: true},
            {source: 'c', target: 'd', directed: true},
        ];
        return {nodes, edges};
    }

    test('no confinement leaves the vertical spread largely intact', () => {
        const {nodes, edges} = chain();
        const res = refineLayout(nodes, edges, {...base, lineConfineStrength: 0}).positions;
        // Without the term the events keep a substantial vertical spread.
        expect(ySpread(res)).toBeGreaterThan(80);
    });

    test('confinement collapses the events onto a near-single line', () => {
        const {nodes, edges} = chain();
        const before = ySpread({
            a: {x: 100, y: 120}, b: {x: 260, y: 360}, c: {x: 420, y: 180}, d: {x: 580, y: 420},
        });
        const res = refineLayout(nodes, edges, {
            ...base, lineConfineStrength: 4, lineConfineAxis: {x: 1, y: 0},
        }).positions;
        const after = ySpread(res);
        expect(after).toBeLessThan(before * 0.5); // pulled strongly toward one line
    });

    test('the confinement is only built when strength and axis are set', () => {
        const {nodes, edges} = chain();
        expect(buildProblem(nodes, edges, {...base, lineConfineStrength: 0}).lineK).toBe(0);
        expect(buildProblem(nodes, edges, {
            ...base, lineConfineStrength: 4, lineConfineAxis: {x: 1, y: 0},
        }).lineK).toBeGreaterThan(0);
    });
});
