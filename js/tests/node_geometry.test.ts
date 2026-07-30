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
 * Tests for the pure canvas geometry helpers.
 *
 * @module     mod_vimipad/tests/node_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    clampSize, clampView, edgePoint, MAX_H, MAX_W, MIN_H, MIN_W,
    nodeHeight, nodeWidth, profileLine, relLinePath, treeBusPath,
} from '../src/canvas/node_geometry';

describe('node geometry helpers', () => {
    test('nodeWidth grows with the longest line but is bounded', () => {
        expect(nodeWidth('a')).toBeGreaterThanOrEqual(70);
        expect(nodeWidth('x'.repeat(500))).toBeLessThanOrEqual(MAX_W);
        expect(nodeWidth('short\nmuch longer line here')).toBeGreaterThan(nodeWidth('short'));
    });

    test('nodeHeight grows with line count, bounded by MAX_H', () => {
        const single = nodeHeight('one', 120);
        const many = nodeHeight('a\nb\nc\nd\ne\nf', 120);
        expect(many).toBeGreaterThan(single);
        expect(nodeHeight('x'.repeat(5000), 120)).toBeLessThanOrEqual(MAX_H);
    });

    test('clampSize respects the min/max bounds and rounds', () => {
        expect(clampSize(5, 5)).toEqual({w: MIN_W, h: MIN_H});
        expect(clampSize(9999, 9999)).toEqual({w: MAX_W, h: MAX_H});
        expect(clampSize(100.4, 80.6)).toEqual({w: 100, h: 81});
    });

    test('clampView keeps the viewport within the canvas', () => {
        const v = clampView({x: -50, y: -50, w: 200, h: 100});
        expect(v.x).toBe(0);
        expect(v.y).toBe(0);
        expect(v.w).toBe(200);
    });

    test('profileLine maps display types to line styles', () => {
        expect(profileLine('tree')).toBe('orthogonal');
        expect(profileLine('mindmap')).toBe('curved');
        expect(profileLine('bubblemap')).toBe('curved');
        expect(profileLine('conceptmap')).toBe('straight');
    });

    test('edgePoint returns the centre when source and target coincide', () => {
        const c = {x: 10, y: 10};
        expect(edgePoint(c, {w: 40, h: 20}, c)).toEqual(c);
    });

    test('edgePoint lands on the box boundary towards the target', () => {
        const p = edgePoint({x: 0, y: 0}, {w: 40, h: 20}, {x: 100, y: 0});
        // Horizontal target: x reaches half-width (+2 margin), y stays 0.
        expect(p.x).toBeCloseTo(22);
        expect(p.y).toBeCloseTo(0);
    });

    test('relLinePath returns null for straight, a path otherwise', () => {
        expect(relLinePath({x: 0, y: 0}, {x: 10, y: 10}, 'straight')).toBeNull();
        expect(relLinePath({x: 0, y: 0}, {x: 10, y: 10}, 'curved')).toMatch(/^M .* C /);
        expect(relLinePath({x: 0, y: 0}, {x: 10, y: 10}, 'orthogonal')).toMatch(/^M .* L /);
    });

    test('treeBusPath produces a four-segment org-chart route', () => {
        const d = treeBusPath({x: 0, y: 0}, {w: 40, h: 20}, {x: 60, y: 200}, {w: 40, h: 20});
        expect(d.startsWith('M ')).toBe(true);
        expect((d.match(/ L /g) ?? []).length).toBe(3);
    });
});
