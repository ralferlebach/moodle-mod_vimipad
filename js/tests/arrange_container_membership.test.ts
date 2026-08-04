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
 * Tests that the container-aware arrange preserves each node's container
 * membership across re-arrange (R8): intersections and subsets survive.
 *
 * @module     mod_vimipad/tests/arrange_container_membership
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {arrangeLayout, NamedBox} from '../src/graph/autolayout';
import {centerInBox} from '../src/canvas/container_geometry';
import {LayoutMap, VimiNode, VimiRelation} from '../src/types';

// Two overlapping containers. C1 covers x in [100,500], C2 covers x in [300,700];
// their overlap is x in [300,500]. Both span y in [100,500].
const C1 = {x: 100, y: 100, w: 400, h: 400};
const C2 = {x: 300, y: 100, w: 400, h: 400};
const containers: NamedBox[] = [{id: 'C1', box: C1}, {id: 'C2', box: C2}];

const nodes: VimiNode[] = [
    {stableid: 'A', type: 'concept', label: 'A'},
    {stableid: 'B', type: 'concept', label: 'B'},
    {stableid: 'C', type: 'concept', label: 'C'},
    {stableid: 'D', type: 'concept', label: 'D'},
];

// Current positions encoding the reclamation's example:
//  A in the overlap (both C1 and C2), B in C1 only, C in C2 only, D outside.
const current: LayoutMap = {
    A: {x: 400, y: 300}, // in C1 ∩ C2
    B: {x: 150, y: 300}, // in C1 only
    C: {x: 650, y: 300}, // in C2 only
    D: {x: 1000, y: 1000}, // outside both
};

const relations: VimiRelation[] = [
    {stableid: 'e1', sourceid: 'A', targetid: 'B', type: 'r', label: '', direction: 1},
    {stableid: 'e2', sourceid: 'A', targetid: 'C', type: 'r', label: '', direction: 1},
];

/** The set of container ids whose box holds the given point. */
function membership(p: {x: number; y: number}): string[] {
    return containers.filter(c => centerInBox(p, c.box)).map(c => c.id).sort();
}

describe('container membership preservation (R8)', () => {
    test('A stays in both containers, B/C each in one, D outside — after arrange', () => {
        const layout = arrangeLayout(nodes, relations, 'conceptmap', containers, current);
        expect(membership(layout.A)).toEqual(['C1', 'C2']);
        expect(membership(layout.B)).toEqual(['C1']);
        expect(membership(layout.C)).toEqual(['C2']);
        expect(membership(layout.D)).toEqual([]);
    });

    test('every node keeps exactly its original membership signature', () => {
        const layout = arrangeLayout(nodes, relations, 'mindmap', containers, current);
        for (const n of nodes) {
            expect(membership(layout[n.stableid])).toEqual(membership(current[n.stableid]));
        }
    });

    test('the arrange is deterministic with containers', () => {
        const a = arrangeLayout(nodes, relations, 'conceptmap', containers, current);
        const b = arrangeLayout(nodes, relations, 'conceptmap', containers, current);
        expect(a).toEqual(b);
    });

    test('with no containers it falls back to the plain profile arrange', () => {
        const withBoxes = arrangeLayout(nodes, relations, 'mindmap', containers, current);
        const plain = arrangeLayout(nodes, relations, 'mindmap');
        // The plain arrange ignores membership, so the results differ.
        expect(withBoxes).not.toEqual(plain);
    });
});
