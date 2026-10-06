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
 * The specialised layouts must produce real coordinates whatever loads first.
 *
 * autolayout once imported the fishbone layout while the fishbone layout took
 * the canvas size from autolayout. When autolayout loaded first - the editor's
 * order - the fishbone layout read the size before it was defined and drew the
 * whole diagram at NaN: one invisible spine, every node off the canvas. The
 * other suites import the layout directly, so the cycle never showed there.
 * This file imports autolayout first on purpose.
 *
 * @module mod_vimipad/tests/layout_load_order
 */

import {arrangeLayout, computeLayout} from '../src/graph/autolayout';
import {fishboneLayout} from '../src/graph/fishbone_layout';
import {fishboneRouting} from '../src/canvas/fishbone_geometry';
import {fishboneTopology} from '../src/graph/fishbone_topology';
import {LayoutMap, VimiNode, VimiRelation} from '../src/types';

const NODES: VimiNode[] = ['effect', 'method', 'machine', 'cause'].map(
    id => ({stableid: id, label: id} as VimiNode));
const RELS: VimiRelation[] = [
    ['method', 'effect'], ['machine', 'effect'], ['cause', 'method'],
].map(([s, t], i) => ({stableid: `r${i}`, sourceid: s, targetid: t, label: ''} as VimiRelation));

/**
 * Every coordinate of a layout is a finite number.
 *
 * @param layout The layout to check.
 */
function expectFinite(layout: LayoutMap): void {
    for (const [id, p] of Object.entries(layout)) {
        expect([id, Number.isFinite(p.x), Number.isFinite(p.y)]).toEqual([id, true, true]);
    }
}

describe('layout modules after autolayout has loaded', () => {
    test('the fishbone layout yields finite coordinates', () => {
        expectFinite(fishboneLayout(NODES, RELS));
    });

    test('the initial layout of a fishbone yields finite coordinates', () => {
        // The path a map with no stored positions takes when it first opens.
        expectFinite(computeLayout(NODES, {}, RELS, 'fishbone'));
    });

    test('the arrange path for a fishbone yields finite coordinates', () => {
        expectFinite(arrangeLayout(NODES, RELS, 'fishbone'));
    });

    test('the spine is a real, horizontal line of some length', () => {
        const layout = fishboneLayout(NODES, RELS);
        const routing = fishboneRouting(fishboneTopology(NODES, RELS, layout), layout, RELS);
        const {from, to} = routing.spine;
        for (const v of [from.x, from.y, to.x, to.y]) {
            expect(Number.isFinite(v)).toBe(true);
        }
        expect(to.x - from.x).toBeGreaterThan(100);
        expect(to.y).toBe(from.y);
    });
});
