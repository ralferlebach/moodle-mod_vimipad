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
 * Geometric invariants of the Ishikawa layout.
 *
 * These assert the properties a reader uses to recognise a fishbone, not exact
 * coordinates, so the constants can be tuned without rewriting the suite.
 *
 * @module mod_vimipad/tests/fishbone_layout
 */

import {fishboneLayout, spineY, stationX} from '../src/graph/fishbone_layout';
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

/** A four-category diagram with causes and one third-level sub-cause. */
const CLASSIC = {
    nodes: nodes(
        'effect',
        'method', 'machine', 'material', 'people',
        'm1', 'm2', 'ma1', 'p1', 'deep'
    ),
    relations: rels(
        'method>effect', 'machine>effect', 'material>effect', 'people>effect',
        'm1>method', 'm2>method',
        'ma1>material',
        'p1>people',
        'deep>m1'
    ),
};

describe('fishbone layout: spine and head', () => {
    const layout: LayoutMap = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);
    const topology = fishboneTopology(CLASSIC.nodes, CLASSIC.relations);

    test('the effect sits on the spine', () => {
        expect(layout.effect.y).toBe(spineY());
    });

    test('the effect is to the right of every category station', () => {
        const count = topology.categories.length;
        for (const category of topology.categories) {
            expect(layout.effect.x).toBeGreaterThan(stationX(category.order, count));
        }
    });

    test('the effect is to the right of every other node', () => {
        for (const node of CLASSIC.nodes) {
            if (node.stableid !== 'effect') {
                expect(layout.effect.x).toBeGreaterThan(layout[node.stableid].x);
            }
        }
    });

    test('category stations are strictly ordered left to right', () => {
        const count = topology.categories.length;
        const xs = topology.categories.map(c => stationX(c.order, count));
        for (let i = 1; i < xs.length; i++) {
            expect(xs[i]).toBeGreaterThan(xs[i - 1]);
        }
    });
});

describe('fishbone layout: branches', () => {
    const layout = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);
    const topology = fishboneTopology(CLASSIC.nodes, CLASSIC.relations);

    test('categories alternate around the spine', () => {
        const signs = topology.categories.map(c => Math.sign(layout[c.id].y - spineY()));
        for (let i = 1; i < signs.length; i++) {
            expect(signs[i]).not.toBe(signs[i - 1]);
        }
    });

    test('no category sits on the spine itself', () => {
        for (const category of topology.categories) {
            expect(Math.abs(layout[category.id].y - spineY())).toBeGreaterThan(50);
        }
    });

    test('causes stay on the same side as their category', () => {
        for (const category of topology.categories) {
            const side = Math.sign(layout[category.id].y - spineY());
            for (const ancestor of category.ancestors) {
                expect(Math.sign(layout[ancestor.id].y - spineY())).toBe(side);
            }
        }
    });

    test('a deeper cause sits further from the spine than its parent', () => {
        // deep > m1 > method: each level moves further out along the bone.
        const dist = (id: string): number => Math.abs(layout[id].y - spineY());
        expect(dist('deep')).toBeGreaterThan(dist('m1'));
        expect(dist('m1')).toBeGreaterThan(dist('method'));
    });

    test('causes do not converge on the head: each is left of its station', () => {
        const count = topology.categories.length;
        for (const category of topology.categories) {
            const station = stationX(category.order, count);
            for (const ancestor of category.ancestors) {
                expect(layout[ancestor.id].x).toBeLessThan(station);
            }
        }
    });
});

describe('fishbone layout: separation', () => {
    const layout = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);

    test('no two nodes land on the same spot', () => {
        const seen = new Set<string>();
        for (const node of CLASSIC.nodes) {
            const key = `${layout[node.stableid].x},${layout[node.stableid].y}`;
            expect(seen.has(key)).toBe(false);
            seen.add(key);
        }
    });

    test('node boxes do not overlap', () => {
        // Treat each node as a generous box; branches must clear each other.
        const w = 150;
        const h = 60;
        const ids = CLASSIC.nodes.map(n => n.stableid);
        for (let i = 0; i < ids.length; i++) {
            for (let j = i + 1; j < ids.length; j++) {
                const a = layout[ids[i]];
                const b = layout[ids[j]];
                const overlaps = Math.abs(a.x - b.x) < w && Math.abs(a.y - b.y) < h;
                expect(overlaps).toBe(false);
            }
        }
    });
});

describe('fishbone layout: determinism', () => {
    test('the same graph always produces the same positions', () => {
        const a = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);
        const b = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);
        expect(a).toEqual(b);
    });

    test('arranging twice is idempotent', () => {
        const once = fishboneLayout(CLASSIC.nodes, CLASSIC.relations);
        const twice = fishboneLayout(CLASSIC.nodes, CLASSIC.relations, once);
        expect(twice).toEqual(once);
    });

    test('scrambled starting positions still yield a fishbone', () => {
        const scrambled: LayoutMap = {};
        CLASSIC.nodes.forEach((n, i) => {
            scrambled[n.stableid] = {x: 1000 + (i * 137) % 400, y: 500 + (i * 89) % 300};
        });
        const layout = fishboneLayout(CLASSIC.nodes, CLASSIC.relations, scrambled);

        // The effect still ends up rightmost and on the spine.
        expect(layout.effect.y).toBe(spineY());
        for (const node of CLASSIC.nodes) {
            if (node.stableid !== 'effect') {
                expect(layout.effect.x).toBeGreaterThan(layout[node.stableid].x);
            }
        }
    });
});

describe('fishbone layout: degenerate maps', () => {
    test('an empty map yields an empty layout', () => {
        expect(fishboneLayout([], [])).toEqual({});
    });

    test('every node gets a position, even with no relations', () => {
        const only = nodes('a', 'b', 'c');
        const layout = fishboneLayout(only, []);
        for (const node of only) {
            expect(layout[node.stableid]).toBeDefined();
        }
    });

    test('a single category is centred on the spine run', () => {
        const layout = fishboneLayout(nodes('effect', 'only'), rels('only>effect'));
        expect(layout.only.x).toBeLessThan(layout.effect.x);
        expect(layout.only.y).not.toBe(spineY());
    });
});
