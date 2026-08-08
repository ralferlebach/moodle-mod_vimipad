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
 * Behavioural test for typed edges (argument maps): an "attack" relation rests
 * at a longer length than an otherwise identical "support" relation, so
 * attacking branches sit further apart.
 *
 * @module     mod_vimipad/tests/refine_attack
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, defaultRefineOptions, RefineNode, RefineEdge} from '../src/graph/refine/refine_layout';

function dist(p: Record<string, {x: number; y: number}>, a: string, b: string): number {
    return Math.hypot(p[a].x - p[b].x, p[a].y - p[b].y);
}

describe('typed edges: attack repulsion', () => {
    // Two disjoint pairs at the same starting distance; one linked by support,
    // one by attack. Only the attack rest scale differs.
    const nodes: RefineNode[] = [
        {stableid: 's1', x: 100, y: 100, w: 60, h: 40},
        {stableid: 's2', x: 220, y: 100, w: 60, h: 40},
        {stableid: 'a1', x: 100, y: 400, w: 60, h: 40},
        {stableid: 'a2', x: 220, y: 400, w: 60, h: 40},
    ];
    const edges: RefineEdge[] = [
        {source: 's1', target: 's2', attack: false},
        {source: 'a1', target: 'a2', attack: true},
    ];

    test('attack rest scale > 1 spreads the attack pair further than the support pair', () => {
        const opts = {...defaultRefineOptions(), scale: 120, edgeTargetBlend: 0.7,
            edgeSpring: 0.5, attackRestScale: 1.8, maxIterations: 400};
        const p = refineLayout(nodes, edges, opts).positions;
        expect(dist(p, 'a1', 'a2')).toBeGreaterThan(dist(p, 's1', 's2') * 1.2);
    });

    test('with attackRestScale 1 the two pairs settle at the same distance', () => {
        const opts = {...defaultRefineOptions(), scale: 120, edgeTargetBlend: 0.7,
            edgeSpring: 0.5, attackRestScale: 1, maxIterations: 400};
        const p = refineLayout(nodes, edges, opts).positions;
        expect(Math.abs(dist(p, 'a1', 'a2') - dist(p, 's1', 's2'))).toBeLessThan(1);
    });
});
