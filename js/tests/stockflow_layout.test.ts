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
 * Arrange behaviour for Stock-and-Flow maps.
 *
 * The issue asks for a readable material chain rather than one rigid canonical
 * layout, so these tests assert relative properties — a flow chain stays
 * tighter than the influences hanging off it — rather than fixed coordinates.
 *
 * @module mod_vimipad/tests/stockflow_layout
 */

import {refineArrangement} from '../src/graph/refine/refine_arrange';
import {LayoutMap, Size, SizeMap, VimiNode, VimiRelation} from '../src/types';

/**
 * A node carrying a System Dynamics role.
 *
 * @param id The stable id.
 * @param role The role.
 * @returns A minimal node.
 */
function sysNode(id: string, role: string): VimiNode {
    return {
        stableid: id,
        label: id,
        metadatajson: JSON.stringify({systemtype: role}),
    } as VimiNode;
}

/**
 * A typed relation.
 *
 * @param i Index, for the stable id.
 * @param from Source id.
 * @param to Target id.
 * @param type Relation type.
 * @param label Relation label.
 * @returns A minimal relation.
 */
function typedRel(i: number, from: string, to: string, type: string, label = ''): VimiRelation {
    return {
        stableid: `rel_${i}`, sourceid: from, targetid: to, type, label, direction: 1,
    } as VimiRelation;
}

/** A material chain with information influencing one of its rates. */
const NODES: VimiNode[] = [
    sysNode('supply', 'source'),
    sysNode('prodrate', 'valve'),
    sysNode('inventory', 'stock'),
    sysNode('shiprate', 'valve'),
    sysNode('customers', 'sink'),
    sysNode('demand', 'auxiliary'),
    sysNode('capacity', 'parameter'),
];

const RELATIONS: VimiRelation[] = [
    typedRel(0, 'supply', 'prodrate', 'flow'),
    typedRel(1, 'prodrate', 'inventory', 'flow'),
    typedRel(2, 'inventory', 'shiprate', 'flow'),
    typedRel(3, 'shiprate', 'customers', 'flow'),
    typedRel(4, 'demand', 'shiprate', 'influence', 'increases'),
    typedRel(5, 'capacity', 'prodrate', 'influence', 'limits'),
];

/**
 * Run Arrange from a given starting layout.
 *
 * @param positions The starting positions.
 * @returns The arranged positions.
 */
function arrange(positions: LayoutMap): LayoutMap {
    const sizes: SizeMap = {};
    for (const node of NODES) {
        sizes[node.stableid] = {w: 140, h: 60} as Size;
    }
    return refineArrangement({
        nodes: NODES,
        relations: RELATIONS,
        containers: [],
        profile: 'stockflow',
        positions,
        sizes,
        pinned: new Set<string>(),
        lockedContainers: new Set<string>(),
        maxIterations: 400,
    }).positions;
}

/**
 * A spread-out starting layout, so Arrange has work to do.
 *
 * @returns Starting positions.
 */
function scattered(): LayoutMap {
    const out: LayoutMap = {};
    NODES.forEach((n, i) => {
        out[n.stableid] = {x: 900 + ((i * 211) % 500), y: 700 + ((i * 137) % 400)};
    });
    return out;
}

/**
 * The distance between two nodes.
 *
 * @param layout The layout.
 * @param a First node id.
 * @param b Second node id.
 * @returns The distance.
 */
function dist(layout: LayoutMap, a: string, b: string): number {
    return Math.hypot(layout[a].x - layout[b].x, layout[a].y - layout[b].y);
}

describe('stockflow arrange', () => {
    test('every node keeps a position', () => {
        const layout = arrange(scattered());
        for (const node of NODES) {
            expect(layout[node.stableid]).toBeDefined();
            expect(Number.isFinite(layout[node.stableid].x)).toBe(true);
            expect(Number.isFinite(layout[node.stableid].y)).toBe(true);
        }
    });

    test('the material chain is tighter than the influences hanging off it', () => {
        const layout = arrange(scattered());

        const chain = [
            dist(layout, 'supply', 'prodrate'),
            dist(layout, 'prodrate', 'inventory'),
            dist(layout, 'inventory', 'shiprate'),
            dist(layout, 'shiprate', 'customers'),
        ];
        const influences = [
            dist(layout, 'demand', 'shiprate'),
            dist(layout, 'capacity', 'prodrate'),
        ];

        const meanChain = chain.reduce((a, b) => a + b, 0) / chain.length;
        const meanInfluence = influences.reduce((a, b) => a + b, 0) / influences.length;

        // Flow carries more structural weight than influence, so the chain must
        // end up the tighter of the two.
        expect(meanChain).toBeLessThan(meanInfluence);
    });

    test('a valve stays between the nodes it connects', () => {
        const layout = arrange(scattered());
        // The production rate sits between supply and inventory rather than
        // being pushed out by the parameter influencing it.
        const throughValve = dist(layout, 'supply', 'prodrate') + dist(layout, 'prodrate', 'inventory');
        expect(throughValve).toBeLessThan(dist(layout, 'supply', 'inventory') * 2.2);
    });

    test('auxiliaries and parameters do not collapse onto the chain', () => {
        const layout = arrange(scattered());
        // They influence a rate but must not sit on top of it.
        expect(dist(layout, 'demand', 'shiprate')).toBeGreaterThan(60);
        expect(dist(layout, 'capacity', 'prodrate')).toBeGreaterThan(60);
    });

    test('no two nodes end up on the same spot', () => {
        const layout = arrange(scattered());
        const seen = new Set<string>();
        for (const node of NODES) {
            const key = `${Math.round(layout[node.stableid].x)},${Math.round(layout[node.stableid].y)}`;
            expect(seen.has(key)).toBe(false);
            seen.add(key);
        }
    });

    test('arrange is deterministic for the same input', () => {
        const start = scattered();
        expect(arrange(start)).toEqual(arrange(start));
    });

    test('repeated arrange calls converge', () => {
        const once = arrange(scattered());
        const twice = arrange(once);
        // Preservation-first refinement: a second pass must not keep drifting.
        for (const node of NODES) {
            expect(dist({a: once[node.stableid], b: twice[node.stableid]} as LayoutMap, 'a', 'b'))
                .toBeLessThan(60);
        }
    });

    test('no loop analysis is performed: a cycle arranges without hanging', () => {
        // The issue puts loop detection explicitly out of scope; a feedback
        // cycle must simply lay out like any other graph.
        const cyclic = [...RELATIONS, typedRel(6, 'inventory', 'demand', 'influence', 'reduces')];
        const sizes: SizeMap = {};
        for (const node of NODES) {
            sizes[node.stableid] = {w: 140, h: 60} as Size;
        }
        const result = refineArrangement({
            nodes: NODES, relations: cyclic, containers: [], profile: 'stockflow',
            positions: scattered(), sizes, pinned: new Set<string>(),
            lockedContainers: new Set<string>(), maxIterations: 400,
        });
        expect(Object.keys(result.positions)).toHaveLength(NODES.length);
    });
});
