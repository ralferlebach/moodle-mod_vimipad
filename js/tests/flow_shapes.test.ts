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
 * Tests for the flowchart node shapes and their connector anchoring.
 *
 * @module mod_vimipad/tests/flow_shapes
 */

import {shapeElement} from '../src/canvas/shapes';
import {edgePointForShape} from '../src/canvas/node_geometry';
import {ALL_SHAPES, GENERIC_SHAPES, allowedShapes, defaultShape, clampShape} from '../src/canvas/shape_catalog';
import {formShapes} from '../src/canvas/form_config';

describe('flowchart shape vocabulary', () => {
    test('the catalogue knows the flowchart symbols', () => {
        expect(ALL_SHAPES).toEqual(
            expect.arrayContaining(['terminator', 'diamond', 'parallelogram'])
        );
    });

    test('generic profiles do not gain flowchart symbols', () => {
        for (const profile of ['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap']) {
            expect(allowedShapes(profile)).toEqual(GENERIC_SHAPES);
        }
    });

    test('flow offers the flowchart symbols and defaults to process', () => {
        expect(allowedShapes('flow')).toEqual(['rect', 'terminator', 'diamond', 'parallelogram']);
        expect(defaultShape('flow')).toBe('rect');
    });

    test('a shape the profile forbids is clamped to its default', () => {
        // An ellipse drawn under conceptmap becomes a process box under flow.
        expect(clampShape('flow', 'ellipse')).toBe('rect');
        // And a diamond is not smuggled into a concept map.
        expect(clampShape('conceptmap', 'diamond')).toBe('roundrect');
    });

    test('flow shapes survive the server form config filter', () => {
        const config = {
            profile: 'flow',
            allowedshapes: ['rect', 'terminator', 'diamond', 'parallelogram'],
            defaultshape: 'rect',
        } as never;
        expect(formShapes(config, 'flow')).toEqual(
            ['rect', 'terminator', 'diamond', 'parallelogram']
        );
    });
});

describe('flowchart SVG geometry', () => {
    const el = (shape: Parameters<typeof shapeElement>[0]) => shapeElement(shape, 100, 60, {});
    const corners = (points: string): string[] => points.trim().split(/\s+/);

    test('a decision is a four-point polygon', () => {
        const decision = el('diamond');
        expect(decision.type).toBe('polygon');
        expect(corners(decision.props.points)).toHaveLength(4);
    });

    test('an input/output is a slanted four-point polygon', () => {
        const io = el('parallelogram');
        expect(io.type).toBe('polygon');
        const pts = corners(io.props.points);
        expect(pts).toHaveLength(4);
        // The top and bottom edges are offset against each other, which is what
        // makes it a parallelogram rather than a rectangle.
        const topLeftX = Number(pts[0].split(',')[0]);
        const bottomLeftX = Number(pts[3].split(',')[0]);
        expect(topLeftX).toBeGreaterThan(bottomLeftX);
    });

    test('a start/end is a capsule', () => {
        const term = el('terminator');
        expect(term.type).toBe('rect');
        // The corner radius is half the height, so the short sides are semicircles.
        expect(term.props.rx).toBe(30);
        expect(term.props.ry).toBe(30);
    });

    test('the generic shapes are unchanged', () => {
        expect(el('ellipse').type).toBe('ellipse');
        expect(el('rect').props.rx).toBe(0);
        expect(el('roundrect').props.rx).toBe(10);
    });

    test('no shape rasterises: every symbol stays native SVG', () => {
        for (const shape of ALL_SHAPES) {
            expect(['rect', 'ellipse', 'polygon', 'path']).toContain(el(shape).type);
        }
    });
});

describe('shape-aware connector anchoring', () => {
    const center = {x: 0, y: 0};
    const size = {w: 100, h: 60};

    test('a diamond anchor sits on its edge, not on the bounding box', () => {
        // Heading right along the axis, the diamond's vertex is the boundary.
        const p = edgePointForShape(center, size, {x: 500, y: 0}, 'diamond');
        expect(p.x).toBeCloseTo(52, 0);
        expect(p.y).toBeCloseTo(0, 5);

        // Diagonally, the diamond boundary is strictly inside the box corner.
        const diag = edgePointForShape(center, size, {x: 500, y: 500}, 'diamond');
        const box = edgePointForShape(center, size, {x: 500, y: 500}, 'rect');
        expect(Math.hypot(diag.x, diag.y)).toBeLessThan(Math.hypot(box.x, box.y));
    });

    test('an ellipse anchor lies on the ellipse', () => {
        const p = edgePointForShape(center, size, {x: 300, y: 300}, 'ellipse');
        const hw = size.w / 2 + 2;
        const hh = size.h / 2 + 2;
        expect((p.x / hw) ** 2 + (p.y / hh) ** 2).toBeCloseTo(1, 5);
    });

    test('a terminator anchor lies on the capsule outline', () => {
        // Straight up leaves through the flat top edge.
        const up = edgePointForShape(center, size, {x: 0, y: -300}, 'terminator');
        expect(up.y).toBeCloseTo(-32, 0);

        // Straight right leaves through the cap, at the node's half width.
        const right = edgePointForShape(center, size, {x: 300, y: 0}, 'terminator');
        expect(right.x).toBeCloseTo(52, 0);
    });

    test('a parallelogram anchor lies on the sheared outline', () => {
        // Straight up still leaves through the flat top edge.
        const up = edgePointForShape(center, size, {x: 0, y: -300}, 'parallelogram');
        expect(up.y).toBeCloseTo(-32, 0);

        // A shallow direction leaves through a slanted side. The point must sit
        // on that side: sheared back, it lies on the upright rectangle's edge.
        const hh = size.h / 2 + 2;
        const hw = size.w / 2 + 2;
        const slant = Math.min(hw / 2, hh * 0.6);
        const k = slant / (2 * hh);
        const halfwidth = hw - slant / 2;

        const right = edgePointForShape(center, size, {x: 300, y: -60}, 'parallelogram');
        expect(Math.abs(right.x + k * right.y)).toBeCloseTo(halfwidth, 5);

        // The outline is sheared, so the two shallow side anchors are not mirrored.
        const left = edgePointForShape(center, size, {x: -300, y: -60}, 'parallelogram');
        expect(Math.abs(left.x)).not.toBeCloseTo(Math.abs(right.x), 1);
    });

    test('a rectangle still uses the bounding box', () => {
        const p = edgePointForShape(center, size, {x: 300, y: 0}, 'rect');
        expect(p.x).toBeCloseTo(52, 5);
    });

    test('a zero-length direction returns the centre', () => {
        expect(edgePointForShape(center, size, center, 'diamond')).toEqual(center);
    });
});
