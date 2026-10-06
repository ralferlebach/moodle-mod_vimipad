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
 * Arrange keeps every node on the canvas when nothing has been stored yet.
 *
 * A map built through the form - the usual case in a database field - can have
 * no stored positions; the canvas shows positions it computes itself. Arrange
 * used to start from the stored layout alone, so every such node began at the
 * canvas origin and was spread into negative coordinates. The read-only view
 * keeps its frame on the canvas, so the whole map vanished and could not be
 * reached again.
 *
 * @module mod_vimipad/tests/arrange_without_layout
 */

import {refineArrangement} from '../src/graph/refine/refine_arrange';
import {computeLayout} from '../src/graph/autolayout';
import {CANVAS_HEIGHT, CANVAS_WIDTH} from '../src/graph/canvas_size';
import {LayoutMap, Size, SizeMap, VimiNode, VimiRelation} from '../src/types';

const NODES: VimiNode[] = ['water', 'ice', 'steam', 'cloud', 'rain'].map(
    id => ({stableid: id, label: id, metadatajson: '{}'} as VimiNode));
const RELS: VimiRelation[] = [['water', 'ice'], ['water', 'steam'], ['steam', 'cloud'], ['cloud', 'rain']]
    .map(([s, t], i) => ({stableid: `r${i}`, sourceid: s, targetid: t, type: '', label: '', direction: 1} as VimiRelation));

/**
 * Sizes as the canvas would measure them.
 *
 * @returns A size per node.
 */
function sizes(): SizeMap {
    const out: SizeMap = {};
    NODES.forEach(n => { out[n.stableid] = {w: 140, h: 50} as Size; });
    return out;
}

/**
 * Every node lies on the canvas, with room for its box.
 *
 * @param layout The positions to check.
 */
function expectOnCanvas(layout: LayoutMap): void {
    for (const node of NODES) {
        const p = layout[node.stableid];
        expect(p).toBeDefined();
        expect([node.stableid, p.x >= 0 && p.x <= CANVAS_WIDTH, p.y >= 0 && p.y <= CANVAS_HEIGHT])
            .toEqual([node.stableid, true, true]);
    }
}

/**
 * Run Arrange the way the editor does.
 *
 * @param seed The starting positions.
 * @returns The arranged positions.
 */
function arrange(seed: LayoutMap): LayoutMap {
    return refineArrangement({
        nodes: NODES, relations: RELS, containers: [], profile: 'conceptmap', positions: seed,
        sizes: sizes(), pinned: new Set<string>(), lockedContainers: new Set<string>(), maxIterations: 400,
    }).positions;
}

describe('arrange without a stored layout', () => {
    test('the refiner keeps unpositioned nodes on the canvas', () => {
        // Defence in depth: even a caller that hands over no positions at all
        // must not push the map off the canvas.
        expectOnCanvas(arrange({}));
    });

    test('arranging what the canvas shows keeps the map on the canvas', () => {
        // The editor seeds Arrange from the displayed layout, which fills in a
        // position for every node the stored layout lacks.
        const displayed = computeLayout(NODES, {}, RELS, 'conceptmap');
        expectOnCanvas(displayed);
        expectOnCanvas(arrange(displayed));
    });

    test('arranging stays near what was on screen', () => {
        const displayed = computeLayout(NODES, {}, RELS, 'conceptmap');
        const arranged = arrange(displayed);
        // Preservation-first: Arrange tidies the map where it is rather than
        // moving it to another part of the canvas.
        const centre = (l: LayoutMap) => NODES.reduce(
            (acc, n) => ({x: acc.x + l[n.stableid].x / NODES.length, y: acc.y + l[n.stableid].y / NODES.length}),
            {x: 0, y: 0});
        const a = centre(displayed);
        const b = centre(arranged);
        expect(Math.hypot(a.x - b.x, a.y - b.y)).toBeLessThan(400);
    });
});

describe('a map already pushed off the canvas', () => {
    /** Positions as the faulty Arrange stored them: spread around the origin. */
    const damaged: LayoutMap = {
        water: {x: -49, y: -61}, ice: {x: 87, y: -26}, steam: {x: -45, y: 11},
        cloud: {x: 7, y: 77}, rain: {x: 130, y: 140},
    };

    test('is drawn on the canvas again', () => {
        expectOnCanvas(computeLayout(NODES, damaged, RELS, 'conceptmap'));
    });

    test('keeps its arrangement - it is moved, not scrambled', () => {
        const shown = computeLayout(NODES, damaged, RELS, 'conceptmap');
        const dx = shown.water.x - damaged.water.x;
        const dy = shown.water.y - damaged.water.y;
        for (const node of NODES) {
            expect(shown[node.stableid].x - damaged[node.stableid].x).toBeCloseTo(dx, 6);
            expect(shown[node.stableid].y - damaged[node.stableid].y).toBeCloseTo(dy, 6);
        }
    });

    test('a healthy map is left exactly as stored', () => {
        const healthy: LayoutMap = {
            water: {x: 400, y: 300}, ice: {x: 700, y: 300}, steam: {x: 550, y: 500},
            cloud: {x: 900, y: 600}, rain: {x: 1100, y: 700},
        };
        expect(computeLayout(NODES, healthy, RELS, 'conceptmap')).toEqual(healthy);
    });
});
