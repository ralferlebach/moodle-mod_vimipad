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
 * Unit tests for the hierarchical (tree) auto-layout.
 *
 * @module     mod_vimipad/tests/autolayout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {computeLayout} from '../src/graph/autolayout';
import {VimiNode, VimiRelation} from '../src/types';

const nodes: VimiNode[] = [
    {stableid: 'r', type: 'concept', label: 'Root'},
    {stableid: 'a', type: 'concept', label: 'A'},
    {stableid: 'b', type: 'concept', label: 'B'},
];

const relations: VimiRelation[] = [
    {stableid: 're1', sourceid: 'r', targetid: 'a', type: 'related', label: '', direction: 1},
    {stableid: 're2', sourceid: 'r', targetid: 'b', type: 'related', label: '', direction: 1},
];

describe('tree auto-layout', () => {
    test('places the root above its children on one level', () => {
        const layout = computeLayout(nodes, {}, relations, 'tree');
        expect(layout.r.y).toBeLessThan(layout.a.y);
        expect(layout.a.y).toBe(layout.b.y);
    });

    test('centres the parent over its children', () => {
        const layout = computeLayout(nodes, {}, relations, 'tree');
        const midpoint = (layout.a.x + layout.b.x) / 2;
        expect(layout.r.x).toBe(midpoint);
    });

    test('stored positions take precedence', () => {
        const layout = computeLayout(nodes, {r: {x: 7, y: 9}}, relations, 'tree');
        expect(layout.r).toEqual({x: 7, y: 9});
    });

    test('a disconnected node still receives a position', () => {
        const withOrphan = [...nodes, {stableid: 'o', type: 'concept', label: 'O'}];
        const layout = computeLayout(withOrphan, {}, relations, 'tree');
        expect(layout.o).toBeDefined();
    });

    test('non-tree profiles keep the circle layout', () => {
        const circle = computeLayout(nodes, {}, relations, 'conceptmap');
        const plain = computeLayout(nodes, {});
        expect(circle).toEqual(plain);
    });
});
