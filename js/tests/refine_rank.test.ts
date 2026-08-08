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
 * Behavioural test for rank layering (flow/process): a directed chain that
 * starts compressed along the flow axis is spread into distinct layers, each
 * edge advancing by at least the rank gap.
 *
 * @module     mod_vimipad/tests/refine_rank
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, buildProblem, defaultRefineOptions, RefineNode, RefineEdge} from '../src/graph/refine/refine_layout';

describe('rank layering (flow/process)', () => {
    const base = {...defaultRefineOptions(), scale: 120, stabilityScale: 0.05,
        preferredDir: {x: 0, y: 1}, directionFloor: 0.15, maxIterations: 500};

    // A directed chain a->b->c->d, all crammed at nearly the same y.
    const nodes: RefineNode[] = [
        {stableid: 'a', x: 200, y: 200, w: 60, h: 40},
        {stableid: 'b', x: 210, y: 210, w: 60, h: 40},
        {stableid: 'c', x: 205, y: 205, w: 60, h: 40},
        {stableid: 'd', x: 215, y: 215, w: 60, h: 40},
    ];
    const edges: RefineEdge[] = [
        {source: 'a', target: 'b', directed: true},
        {source: 'b', target: 'c', directed: true},
        {source: 'c', target: 'd', directed: true},
    ];

    test('each directed edge advances downward by a clear margin', () => {
        const p = refineLayout(nodes, edges, {...base, rankStrength: 3, rankGap: 1.2}).positions;
        // b below a, c below b, d below c — a monotone rank order along +y.
        expect(p.b.y).toBeGreaterThan(p.a.y + 40);
        expect(p.c.y).toBeGreaterThan(p.b.y + 40);
        expect(p.d.y).toBeGreaterThan(p.c.y + 40);
    });

    test('the rank term is only built with a flow axis and positive strength', () => {
        expect(buildProblem(nodes, edges, {...base, rankStrength: 0}).kRank).toBe(0);
        expect(buildProblem(nodes, edges, {...base, rankStrength: 3, rankGap: 1.2}).kRank).toBeGreaterThan(0);
        // No preferred direction => no rank axis, so no layering even if strong.
        expect(buildProblem(nodes, edges, {...base, preferredDir: null, rankStrength: 3}).kRank).toBe(0);
    });
});
