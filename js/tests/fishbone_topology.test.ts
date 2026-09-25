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
 * Tests for the Ishikawa topology resolver.
 *
 * @module mod_vimipad/tests/fishbone_topology
 */

import {fishboneTopology} from '../src/graph/fishbone_topology';
import {LayoutMap, VimiNode, VimiRelation} from '../src/types';

/**
 * Build a node list from ids.
 *
 * @param ids The stable ids.
 * @returns Minimal nodes.
 */
function nodes(...ids: string[]): VimiNode[] {
    return ids.map(id => ({stableid: id, label: id} as VimiNode));
}

/**
 * Build relations from "source>target" pairs.
 *
 * @param pairs The edges.
 * @returns Minimal relations.
 */
function rels(...pairs: string[]): VimiRelation[] {
    return pairs.map((p, i) => {
        const [sourceid, targetid] = p.split('>');
        return {stableid: `rel_${i}`, sourceid, targetid, label: ''} as VimiRelation;
    });
}

describe('fishbone topology: head detection', () => {
    test('the unique sink is the effect', () => {
        const t = fishboneTopology(nodes('effect', 'a', 'b'), rels('a>effect', 'b>effect'));
        expect(t.head).toBe('effect');
        expect(t.ambiguous).toBe(false);
    });

    test('several sinks are reported as ambiguous but still resolved', () => {
        const t = fishboneTopology(
            nodes('e1', 'e2', 'a', 'b'),
            rels('a>e1', 'b>e2', 'a>e2')
        );
        // e2 has two incoming relations, so it wins the tie-break.
        expect(t.head).toBe('e2');
        expect(t.ambiguous).toBe(true);
    });

    test('a cycle does not prevent a head being chosen', () => {
        const t = fishboneTopology(nodes('a', 'b', 'c'), rels('a>b', 'b>c', 'c>a'));
        expect(t.head).not.toBe('');
        expect(t.ambiguous).toBe(true);
    });

    test('an empty map yields an empty topology', () => {
        const t = fishboneTopology([], []);
        expect(t).toEqual({head: '', categories: [], orphans: [], ambiguous: false});
    });
});

describe('fishbone topology: categories', () => {
    const map = () => fishboneTopology(
        nodes('effect', 'method', 'machine', 'material', 'people'),
        rels('method>effect', 'machine>effect', 'material>effect', 'people>effect')
    );

    test('the head\'s direct predecessors are the main categories', () => {
        expect(map().categories.map(c => c.id).sort())
            .toEqual(['machine', 'material', 'method', 'people']);
    });

    test('categories alternate above and below the spine', () => {
        const sides = map().categories.map(c => c.side);
        for (let i = 1; i < sides.length; i++) {
            expect(sides[i]).not.toBe(sides[i - 1]);
        }
    });

    test('categories carry a strictly increasing spine order', () => {
        expect(map().categories.map(c => c.order)).toEqual([0, 1, 2, 3]);
    });

    test('ordering is deterministic for the same graph', () => {
        expect(map()).toEqual(map());
    });

    test('existing positions decide the spine order, not stable ids', () => {
        // zulu sits left of alpha, so it must come first on the spine even
        // though its id sorts last.
        const layout: LayoutMap = {
            effect: {x: 900, y: 300},
            zulu: {x: 200, y: 200},
            alpha: {x: 500, y: 400},
        };
        const t = fishboneTopology(
            nodes('effect', 'alpha', 'zulu'),
            rels('alpha>effect', 'zulu>effect'),
            layout
        );
        expect(t.categories.map(c => c.id)).toEqual(['zulu', 'alpha']);
        // And each keeps the side it was drawn on, relative to the effect.
        expect(t.categories[0].side).toBe('top');
        expect(t.categories[1].side).toBe('bottom');
    });

    test('a lopsided layout is rebalanced to alternating sides', () => {
        // Both categories drawn above the spine: a fishbone alternates, so the
        // resolver spreads them rather than stacking one branch.
        const layout: LayoutMap = {
            effect: {x: 900, y: 300},
            a: {x: 200, y: 100},
            b: {x: 500, y: 120},
        };
        const t = fishboneTopology(nodes('effect', 'a', 'b'), rels('a>effect', 'b>effect'), layout);
        expect(t.categories.map(c => c.side)).toEqual(['top', 'bottom']);
    });
});

describe('fishbone topology: causes and depth', () => {
    test('causes attach to their category, with increasing depth', () => {
        const t = fishboneTopology(
            nodes('effect', 'cat', 'cause', 'subcause'),
            rels('cat>effect', 'cause>cat', 'subcause>cause')
        );
        const cat = t.categories[0];
        expect(cat.id).toBe('cat');
        expect(cat.ancestors.map(a => [a.id, a.depth, a.parent])).toEqual([
            ['cause', 1, 'cat'],
            ['subcause', 2, 'cause'],
        ]);
    });

    test('a fourth level stays on the same branch', () => {
        const t = fishboneTopology(
            nodes('effect', 'cat', 'c1', 'c2', 'c3'),
            rels('cat>effect', 'c1>cat', 'c2>c1', 'c3>c2')
        );
        expect(t.categories[0].ancestors.map(a => a.depth)).toEqual([1, 2, 3]);
        expect(t.orphans).toEqual([]);
    });

    test('branches stay disjoint when a cause feeds two categories', () => {
        const t = fishboneTopology(
            nodes('effect', 'catA', 'catB', 'shared'),
            rels('catA>effect', 'catB>effect', 'shared>catA', 'shared>catB')
        );
        const owners = t.categories.filter(c => c.ancestors.some(a => a.id === 'shared'));
        expect(owners).toHaveLength(1);
    });

    test('a cycle inside a branch terminates', () => {
        const t = fishboneTopology(
            nodes('effect', 'cat', 'x', 'y'),
            rels('cat>effect', 'x>cat', 'y>x', 'x>y')
        );
        const ids = t.categories[0].ancestors.map(a => a.id).sort();
        expect(ids).toEqual(['x', 'y']);
    });
});

describe('fishbone topology: leftovers', () => {
    test('disconnected nodes are reported as orphans', () => {
        const t = fishboneTopology(
            nodes('effect', 'cat', 'lonely'),
            rels('cat>effect')
        );
        expect(t.orphans).toEqual(['lonely']);
    });

    test('relations pointing at unknown nodes are ignored', () => {
        const t = fishboneTopology(nodes('effect', 'cat'), rels('cat>effect', 'ghost>effect'));
        expect(t.categories.map(c => c.id)).toEqual(['cat']);
    });
});
