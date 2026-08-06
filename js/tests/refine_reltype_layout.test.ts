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
 * Per-relation-type layout effects (ontology): an explicit per-edge rest scale
 * binds part-of pairs tighter than neutral associated pairs, and the
 * profile-resolver maps the ontology layout hints through.
 *
 * @module     mod_vimipad/tests/refine_reltype_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineLayout, defaultRefineOptions, RefineNode, RefineEdge} from '../src/graph/refine/refine_layout';
import {resolveProfileRefine, refineOptionsForProfile} from '../src/graph/refine/refine_arrange';

function dist(p: Record<string, {x: number; y: number}>, a: string, b: string): number {
    return Math.hypot(p[a].x - p[b].x, p[a].y - p[b].y);
}

describe('per-type rest scale (ontology part-of)', () => {
    const nodes: RefineNode[] = [
        {stableid: 'p1', x: 100, y: 100, w: 60, h: 40},
        {stableid: 'p2', x: 240, y: 100, w: 60, h: 40},
        {stableid: 'a1', x: 100, y: 400, w: 60, h: 40},
        {stableid: 'a2', x: 240, y: 400, w: 60, h: 40},
    ];
    const edges: RefineEdge[] = [
        {source: 'p1', target: 'p2', restScale: 0.6},  // part-of: tight
        {source: 'a1', target: 'a2', restScale: 1},    // associated: neutral
    ];

    test('a shorter rest scale settles the pair closer together', () => {
        const opts = {...defaultRefineOptions(), scale: 130, edgeTargetBlend: 0.7,
            edgeSpring: 0.5, maxIterations: 400};
        const p = refineLayout(nodes, edges, opts).positions;
        expect(dist(p, 'p1', 'p2')).toBeLessThan(dist(p, 'a1', 'a2') * 0.85);
    });
});

describe('ontology profile resolution', () => {
    test('the built-in ontology maps is-a directed and part-of to a short rest', () => {
        const o = refineOptionsForProfile('ontology');
        expect(o.directed).toBe(true);
        expect(o.preferredDir).toEqual({x: 0, y: -1});
        const byType = new Map(o.relationLayout.map(r => [r.type, r]));
        expect(byType.get('isa')!.directed).toBe(true);
        expect(byType.get('partof')!.restscale).toBeLessThan(1);
    });

    test('a PHP relationlayout is transported into ProfileRefine', () => {
        const r = resolveProfileRefine('ontology', {
            profile: 'ontology', name: 'Ontology', allowedshapes: ['roundrect'],
            defaultshape: 'roundrect', line: 'straight', bifurcation: 'individual',
            relationtypes: ['isa', 'partof', 'associated'],
            relationlayout: [{type: 'isa', directed: true}, {type: 'partof', restscale: 0.6}],
            layout: {directed: true, direction: {x: 0, y: -1}},
        });
        expect(r.relationLayout).toEqual([{type: 'isa', directed: true}, {type: 'partof', restscale: 0.6}]);
    });
});
