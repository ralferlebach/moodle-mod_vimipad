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
 * Unit tests for the profile-specific "re-arrange" layout (R7): determinism,
 * degree-based centrality, even radial distribution and roughly equal edge
 * lengths.
 *
 * @module     mod_vimipad/tests/arrange_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {arrangeLayout, CANVAS_WIDTH, CANVAS_HEIGHT} from '../src/graph/autolayout';
import {VimiNode, VimiRelation} from '../src/types';

/** A hub node connected to n leaves, plus one detached node. */
function star(leaves: number): {nodes: VimiNode[]; relations: VimiRelation[]} {
    const nodes: VimiNode[] = [{stableid: 'hub', type: 'concept', label: 'Hub'}];
    const relations: VimiRelation[] = [];
    for (let i = 0; i < leaves; i++) {
        const id = `l${i}`;
        nodes.push({stableid: id, type: 'concept', label: id});
        relations.push({stableid: `e${i}`, sourceid: 'hub', targetid: id, type: 'r', label: '', direction: 1});
    }
    return {nodes, relations};
}

const dist = (a: {x: number; y: number}, b: {x: number; y: number}): number =>
    Math.hypot(a.x - b.x, a.y - b.y);

describe('arrangeLayout (R7)', () => {
    test('is deterministic: same input arranges identically', () => {
        const {nodes, relations} = star(6);
        const a = arrangeLayout(nodes, relations, 'semanticnetwork');
        const b = arrangeLayout(nodes, relations, 'semanticnetwork');
        expect(a).toEqual(b);
    });

    test('radial (mindmap): the hub is central and leaves ring around it evenly', () => {
        const {nodes, relations} = star(6);
        const layout = arrangeLayout(nodes, relations, 'mindmap');
        const centre = {x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT / 2};
        // The hub sits at the centre.
        expect(dist(layout.hub, centre)).toBeLessThan(1);
        // All leaves are the same distance from the hub (equal edge lengths).
        const radii = Object.keys(layout).filter(k => k !== 'hub').map(k => dist(layout[k], layout.hub));
        const min = Math.min(...radii);
        const max = Math.max(...radii);
        expect(max - min).toBeLessThan(1);
    });

    test('force (semantic network): the high-degree hub ends up more central than the leaves', () => {
        const {nodes, relations} = star(8);
        const layout = arrangeLayout(nodes, relations, 'semanticnetwork');
        const centre = {x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT / 2};
        const hubDist = dist(layout.hub, centre);
        const leafDists = Object.keys(layout).filter(k => k !== 'hub').map(k => dist(layout[k], centre));
        const avgLeaf = leafDists.reduce((s, d) => s + d, 0) / leafDists.length;
        expect(hubDist).toBeLessThan(avgLeaf);
    });

    test('force layout gives edges of roughly similar length', () => {
        const {nodes, relations} = star(8);
        const layout = arrangeLayout(nodes, relations, 'semanticnetwork');
        const lengths = relations.map(r => dist(layout[r.sourceid], layout[r.targetid]));
        const min = Math.min(...lengths);
        const max = Math.max(...lengths);
        // Spokes of a star settle to within a modest ratio of each other.
        expect(max / min).toBeLessThan(1.5);
    });

    test('tree/concept: children sit on the row below their parent (top-down)', () => {
        const nodes: VimiNode[] = [
            {stableid: 'root', type: 'concept', label: 'R'},
            {stableid: 'c1', type: 'concept', label: 'C1'},
            {stableid: 'c2', type: 'concept', label: 'C2'},
        ];
        const relations: VimiRelation[] = [
            {stableid: 'e1', sourceid: 'root', targetid: 'c1', type: 'r', label: '', direction: 1},
            {stableid: 'e2', sourceid: 'root', targetid: 'c2', type: 'r', label: '', direction: 1},
        ];
        const layout = arrangeLayout(nodes, relations, 'conceptmap');
        expect(layout.c1.y).toBeGreaterThan(layout.root.y);
        expect(layout.c2.y).toBeGreaterThan(layout.root.y);
        // The parent is centred over its two children.
        expect(Math.abs(layout.root.x - (layout.c1.x + layout.c2.x) / 2)).toBeLessThan(2);
    });

    test('an empty map arranges to an empty layout', () => {
        expect(arrangeLayout([], [], 'mindmap')).toEqual({});
    });
});
