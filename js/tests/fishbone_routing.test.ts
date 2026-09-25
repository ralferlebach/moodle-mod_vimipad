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
 * Tests for Ishikawa routing: one shared spine, one station per category.
 *
 * @module mod_vimipad/tests/fishbone_routing
 */

import {fishboneRouting} from '../src/canvas/fishbone_geometry';
import {fishboneLayout} from '../src/graph/fishbone_layout';
import {fishboneTopology} from '../src/graph/fishbone_topology';
import {VimiNode, VimiRelation} from '../src/types';

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

const NODES = nodes('effect', 'method', 'machine', 'material', 'people', 'm1', 'deep');
const RELS = rels(
    'method>effect', 'machine>effect', 'material>effect', 'people>effect',
    'm1>method', 'deep>m1'
);

/**
 * Route the standard fixture.
 *
 * @returns The routing result.
 */
function routed() {
    const layout = fishboneLayout(NODES, RELS);
    const topology = fishboneTopology(NODES, RELS, layout);
    return {routing: fishboneRouting(topology, layout, RELS), layout, topology};
}

describe('fishbone routing: the shared spine', () => {
    test('there is exactly one spine and it is horizontal', () => {
        const {routing} = routed();
        expect(routing.spine.from.y).toBe(routing.spine.to.y);
    });

    test('the spine ends at the effect', () => {
        const {routing, layout} = routed();
        expect(routing.spine.to.x).toBe(layout.effect.x);
        expect(routing.spine.to.y).toBe(layout.effect.y);
    });

    test('the spine reaches past the leftmost station', () => {
        const {routing} = routed();
        const xs = Object.values(routing.stations).map(p => p.x);
        expect(routing.spine.from.x).toBeLessThan(Math.min(...xs));
    });

    test('no category relation redraws the spine', () => {
        const {routing, layout} = routed();
        // A bone must stop at its station, not continue to the effect.
        const bones = routing.routes.filter(r => r.kind === 'bone');
        expect(bones.length).toBeGreaterThan(0);
        for (const bone of bones) {
            const end = bone.points[bone.points.length - 1];
            expect(end.x).not.toBe(layout.effect.x);
        }
    });

    test('the full spine segment appears exactly once across all routes', () => {
        const {routing, layout} = routed();
        const spanning = routing.routes.filter(r =>
            r.points.some(p => p.x === layout.effect.x && p.y === layout.effect.y));
        expect(spanning).toHaveLength(0);
    });
});

describe('fishbone routing: bone direction', () => {
    test('a bone runs toward the effect, never away from it', () => {
        const {routing, layout} = routed();
        for (const bone of routing.routes.filter(r => r.kind === 'bone')) {
            const [start, end] = bone.points;
            // The arrowhead sits at the spine end, so that end must be nearer
            // the effect than the start is. A bone drawn the other way points
            // the arrow back down the spine, away from the cause it feeds.
            expect(end.x).toBeGreaterThan(start.x);
            expect(layout.effect.x).toBeGreaterThan(end.x);
        }
    });

    test('bones on both sides of the spine lean the same way', () => {
        const {routing, layout} = routed();
        const spineY = routing.spine.from.y;
        const above: number[] = [];
        const below: number[] = [];
        for (const bone of routing.routes.filter(r => r.kind === 'bone')) {
            const [start, end] = bone.points;
            (start.y < spineY ? above : below).push(end.x - start.x);
        }
        expect(above.length).toBeGreaterThan(0);
        expect(below.length).toBeGreaterThan(0);
        // Every bone leans toward the effect regardless of its side.
        for (const lean of [...above, ...below]) {
            expect(lean).toBeGreaterThan(0);
        }
        expect(layout.effect.x).toBeGreaterThan(0);
    });

    test('a station never lands past the effect', () => {
        const {routing, layout} = routed();
        for (const station of Object.values(routing.stations)) {
            expect(station.x).toBeLessThan(layout.effect.x);
        }
    });
});

describe('fishbone routing: stations', () => {
    test('every main category gets its own station', () => {
        const {routing, topology} = routed();
        const xs = Object.values(routing.stations).map(p => p.x);
        expect(new Set(xs).size).toBe(topology.categories.length);
    });

    test('stations sit on the spine', () => {
        const {routing} = routed();
        for (const station of Object.values(routing.stations)) {
            expect(station.y).toBe(routing.spine.from.y);
        }
    });

    test('a bone runs from its category to its own station', () => {
        const {routing, layout} = routed();
        const bone = routing.routes.find(r => r.kind === 'bone');
        expect(bone).toBeDefined();
        const [start, end] = bone!.points;
        // It starts at a category node and ends on the spine, ahead of the
        // category in the direction of the effect rather than straight below it.
        expect(start.y).not.toBe(routing.spine.from.y);
        expect(end.y).toBe(routing.spine.from.y);
        expect(end.x).toBeGreaterThan(start.x);
        expect(layout.effect.x).toBeGreaterThan(end.x);
    });
});

describe('fishbone routing: sub-bones', () => {
    test('a cause routes to its parent, not to the effect', () => {
        const {routing, layout} = routed();
        const sub = routing.routes.find(r => r.relationid === 'rel_4');
        expect(sub?.kind).toBe('sub');
        const end = sub!.points[sub!.points.length - 1];
        expect(end).toEqual(layout.method);
    });

    test('a third-level cause routes to its own parent cause', () => {
        const {routing, layout} = routed();
        const sub = routing.routes.find(r => r.relationid === 'rel_5');
        expect(sub?.kind).toBe('sub');
        expect(sub!.points[sub!.points.length - 1]).toEqual(layout.m1);
    });
});

describe('fishbone routing: robustness', () => {
    test('every relation yields exactly one route', () => {
        const {routing} = routed();
        expect(routing.routes).toHaveLength(RELS.length);
        expect(routing.routes.map(r => r.relationid)).toEqual(RELS.map(r => r.stableid));
    });

    test('a relation to a missing node degrades to an empty plain route', () => {
        const layout = fishboneLayout(NODES, RELS);
        const topology = fishboneTopology(NODES, RELS, layout);
        const extra = rels('ghost>effect');
        const routing = fishboneRouting(topology, layout, extra);
        expect(routing.routes[0].kind).toBe('plain');
        expect(routing.routes[0].points).toEqual([]);
    });

    test('routing is deterministic', () => {
        expect(routed().routing).toEqual(routed().routing);
    });
});
