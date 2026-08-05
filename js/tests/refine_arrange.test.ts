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
 * The arrange adapter that drives the refiner from editor state: the per-profile
 * resolver picks sensible axes, membership is read from the current geometry,
 * pinned nodes are frozen, and a clean layout is preserved.
 *
 * @module     mod_vimipad/tests/refine_arrange
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineArrangement, refineOptionsForProfile} from '../src/graph/refine/refine_arrange';
import {VimiNode, VimiRelation, VimiContainer, LayoutMap, SizeMap} from '../src/types';

function node(stableid: string, label = 'N'): VimiNode {
    return {stableid, type: 'concept', label, content: '', contentformat: 1, metadatajson: ''} as VimiNode;
}
function rel(stableid: string, sourceid: string, targetid: string): VimiRelation {
    return {stableid, sourceid, targetid, type: 'link', direction: 1, metadatajson: ''} as VimiRelation;
}

describe('refineOptionsForProfile', () => {
    test('hierarchical profiles flow down and keep sibling order', () => {
        expect(refineOptionsForProfile('tree')).toEqual({
            preferredDir: {x: 0, y: 1}, directed: true, orderAxis: {x: 1, y: 0},
        });
        expect(refineOptionsForProfile('conceptmap').directed).toBe(true);
    });
    test('radial and unknown profiles are free-form', () => {
        expect(refineOptionsForProfile('mindmap').orderAxis).toBeNull();
        expect(refineOptionsForProfile('semanticnetwork').preferredDir).toBeNull();
    });
});

describe('refineArrangement', () => {
    test('preserves a clean layout (small movement)', () => {
        const nodes = [node('a'), node('b'), node('c')];
        const relations = [rel('r1', 'a', 'b'), rel('r2', 'b', 'c')];
        const positions: LayoutMap = {a: {x: 100, y: 200}, b: {x: 250, y: 200}, c: {x: 400, y: 200}};
        const sizes: SizeMap = {a: {w: 60, h: 40}, b: {w: 60, h: 40}, c: {w: 60, h: 40}};
        const res = refineArrangement({
            nodes, relations, containers: [], profile: 'semanticnetwork', positions, sizes,
            overrides: {stabilityScale: 2},
        });
        for (const id of ['a', 'b', 'c']) {
            const moved = Math.hypot(res.positions[id].x - positions[id].x, res.positions[id].y - positions[id].y);
            expect(moved).toBeLessThan(12);
        }
    });

    test('freezes a pinned node exactly', () => {
        const nodes = [node('a'), node('b')];
        const positions: LayoutMap = {a: {x: 200, y: 200}, b: {x: 214, y: 206}}; // overlapping
        const sizes: SizeMap = {a: {w: 80, h: 40}, b: {w: 80, h: 40}};
        const res = refineArrangement({
            nodes, relations: [], containers: [], profile: 'semanticnetwork', positions, sizes,
            pinned: new Set(['a']),
        });
        expect(res.positions.a).toEqual({x: 200, y: 200});
        // b was free to move away from the overlap.
        const bMoved = Math.hypot(res.positions.b.x - 214, res.positions.b.y - 206);
        expect(bMoved).toBeGreaterThan(0);
    });

    test('reads container membership from geometry and keeps a member inside', () => {
        const nodes = [node('m'), node('anchor')];
        const relations = [rel('r', 'anchor', 'm')];
        // 'm' starts just outside the box; 'anchor' inside. Both count as members
        // only if their centre is inside — here anchor is inside, m is outside, so
        // m is NOT a member and must not be dragged in. Use a member that starts
        // inside to test confinement.
        const positions: LayoutMap = {m: {x: 300, y: 300}, anchor: {x: 340, y: 320}};
        const sizes: SizeMap = {m: {w: 50, h: 40}, anchor: {w: 50, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'Box',
                geometryjson: JSON.stringify({x: 250, y: 250, w: 200, h: 160})} as VimiContainer,
        ];
        const res = refineArrangement({
            nodes, relations, containers, profile: 'conceptmap', positions, sizes,
            overrides: {stabilityScale: 1, containerIn: 2},
        });
        // Both centres started inside the box; they should remain inside it.
        const box = {x: 250, y: 250, w: 200, h: 160};
        for (const id of ['m', 'anchor']) {
            const p = res.positions[id];
            expect(p.x).toBeGreaterThanOrEqual(box.x);
            expect(p.x).toBeLessThanOrEqual(box.x + box.w);
            expect(p.y).toBeGreaterThanOrEqual(box.y);
            expect(p.y).toBeLessThanOrEqual(box.y + box.h);
        }
    });

    test('returns integer positions and unchanged container geometry', () => {
        const nodes = [node('a')];
        const positions: LayoutMap = {a: {x: 123, y: 321}};
        const sizes: SizeMap = {a: {w: 50, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B',
                geometryjson: JSON.stringify({x: 100, y: 100, w: 300, h: 300})} as VimiContainer,
        ];
        const res = refineArrangement({nodes, relations: [], containers, profile: 'tree', positions, sizes});
        expect(Number.isInteger(res.positions.a.x)).toBe(true);
        expect(Number.isInteger(res.positions.a.y)).toBe(true);
        // Boxes are not resized by the arrange (fixed): geometry is unchanged.
        expect(res.containers.c).toEqual({x: 100, y: 100, w: 300, h: 300});
    });
});
