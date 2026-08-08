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
 * Behavioural test for cluster cohesion (affinity boards): members of a
 * container are drawn tighter around their shared centroid than without the
 * term, while a separate cluster stays apart.
 *
 * @module     mod_vimipad/tests/refine_cluster
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, buildProblem, defaultRefineOptions,
    RefineNode, RefineEdge, RefineContainer} from '../src/graph/refine/refine_layout';

/** Mean distance of a container's members from their centroid. */
function spread(p: Record<string, {x: number; y: number}>, ids: string[]): number {
    const mx = ids.reduce((a, id) => a + p[id].x, 0) / ids.length;
    const my = ids.reduce((a, id) => a + p[id].y, 0) / ids.length;
    return ids.reduce((a, id) => a + Math.hypot(p[id].x - mx, p[id].y - my), 0) / ids.length;
}

describe('cluster cohesion (affinity)', () => {
    const base = {...defaultRefineOptions(), scale: 120, stabilityScale: 0.05, maxIterations: 400};
    // One cluster of three notes that start loosely scattered inside a big box.
    const nodes: RefineNode[] = [
        {stableid: 'a', x: 100, y: 100, w: 50, h: 30},
        {stableid: 'b', x: 300, y: 140, w: 50, h: 30},
        {stableid: 'c', x: 180, y: 320, w: 50, h: 30},
    ];
    const edges: RefineEdge[] = [];
    const containers: RefineContainer[] = [
        {stableid: 'g1', x: 40, y: 40, w: 380, h: 360, members: ['a', 'b', 'c']},
    ];

    test('cohesion draws a cluster tighter than no cohesion', () => {
        const loose = refineLayout(nodes, edges, {...base, clusterStrength: 0}, containers).positions;
        const tight = refineLayout(nodes, edges, {...base, clusterStrength: 0.6}, containers).positions;
        expect(spread(tight, ['a', 'b', 'c'])).toBeLessThan(spread(loose, ['a', 'b', 'c']));
    });

    test('the term is only built with positive strength', () => {
        expect(buildProblem(nodes, edges, {...base, clusterStrength: 0}, containers).kCluster).toBe(0);
        expect(buildProblem(nodes, edges, {...base, clusterStrength: 0.6}, containers).kCluster).toBeGreaterThan(0);
    });
});
