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
 * Restrictive-swap repair: crossings are counted correctly, an eligible swap
 * removes a crossing, connected pairs are never swapped, and the energy budget
 * gates the repair.
 *
 * @module     mod_vimipad/tests/refine_swaps
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    buildProblem, countCrossings, restrictiveSwaps, defaultRefineOptions, RefineNode, RefineEdge,
} from '../src/graph/refine/refine_layout';

// A classic X: edges a-b and c-d cross; swapping b and c uncrosses them, and
// b, c are not connected to each other.
const nodes: RefineNode[] = [
    {stableid: 'a', x: 0, y: 0, w: 40, h: 40},
    {stableid: 'b', x: 200, y: 200, w: 40, h: 40},
    {stableid: 'c', x: 200, y: 0, w: 40, h: 40},
    {stableid: 'd', x: 0, y: 200, w: 40, h: 40},
];
const edges: RefineEdge[] = [{source: 'a', target: 'b'}, {source: 'c', target: 'd'}];

describe('restrictive swaps', () => {
    test('countCrossings detects the crossing', () => {
        const prob = buildProblem(nodes, edges, {...defaultRefineOptions(), scale: 200});
        expect(countCrossings(prob)).toBe(1);
    });

    test('an eligible swap removes the crossing', () => {
        const prob = buildProblem(nodes, edges, {
            ...defaultRefineOptions(), scale: 200, swaps: true, swapEnergyBudget: 1000,
        });
        const applied = restrictiveSwaps(prob, {
            ...defaultRefineOptions(), scale: 200, swaps: true, swapEnergyBudget: 1000,
        });
        expect(applied).toBeGreaterThan(0);
        expect(countCrossings(prob)).toBe(0);
    });

    test('a tight energy budget blocks the swap', () => {
        const prob = buildProblem(nodes, edges, {...defaultRefineOptions(), scale: 200});
        // A strongly negative budget makes no swap acceptable.
        const applied = restrictiveSwaps(prob, {
            ...defaultRefineOptions(), scale: 200, swaps: true, swapEnergyBudget: -1e9,
        });
        expect(applied).toBe(0);
        expect(countCrossings(prob)).toBe(1);
    });

    test('connected nodes are never swapped with each other', () => {
        const orig = nodes.map(nd => ({x: nd.x, y: nd.y}));
        const prob = buildProblem(nodes, edges, {
            ...defaultRefineOptions(), scale: 200, swaps: true, swapEnergyBudget: 1000,
        });
        restrictiveSwaps(prob, {
            ...defaultRefineOptions(), scale: 200, swaps: true, swapEnergyBudget: 1000,
        });
        const exchanged = (i: number, j: number): boolean =>
            prob.px[i] === orig[j].x && prob.py[i] === orig[j].y &&
            prob.px[j] === orig[i].x && prob.py[j] === orig[i].y;
        // The connected pairs a-b (0,1) and c-d (2,3) must never have exchanged.
        expect(exchanged(0, 1)).toBe(false);
        expect(exchanged(2, 3)).toBe(false);
    });
});
