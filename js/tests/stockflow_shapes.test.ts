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
 * Tests for the Stock-and-Flow symbols and their connector anchoring.
 *
 * @module mod_vimipad/tests/stockflow_shapes
 */

import {shapeElement} from '../src/canvas/shapes';
import {edgePointForShape} from '../src/canvas/node_geometry';
import {
    ALL_SHAPES, GENERIC_SHAPES, allowedShapes, clampShape, defaultShape,
} from '../src/canvas/shape_catalog';
import {formShapes} from '../src/canvas/form_config';

/** The symbols a stock-and-flow map uses. */
const SYSTEM_SHAPES = ['stock', 'cloud', 'cloudsink', 'valve', 'delay', 'parameter'] as const;

describe('stockflow vocabulary', () => {
    test('the catalogue knows every system symbol', () => {
        expect(ALL_SHAPES).toEqual(expect.arrayContaining([...SYSTEM_SHAPES]));
    });

    test('the profile offers the system symbols and a generic default', () => {
        const allowed = allowedShapes('stockflow');
        for (const shape of SYSTEM_SHAPES) {
            expect(allowed).toContain(shape);
        }
        // Adding a node must not assert a system role the author did not choose.
        expect(defaultShape('stockflow')).toBe('roundrect');
    });

    test('generic profiles do not gain system symbols', () => {
        for (const profile of ['conceptmap', 'mindmap', 'tree', 'semanticnetwork', 'bubblemap']) {
            expect(allowedShapes(profile)).toEqual(GENERIC_SHAPES);
        }
    });

    test('the causal profile is untouched', () => {
        // Causal loop diagrams keep the generic shapes; stockflow is additive.
        expect(allowedShapes('causal')).toEqual(GENERIC_SHAPES);
    });

    test('a stock is clamped away under a profile that forbids it', () => {
        expect(clampShape('conceptmap', 'stock')).toBe('roundrect');
        expect(clampShape('stockflow', 'stock')).toBe('stock');
    });

    test('system symbols survive the server form config filter', () => {
        const config = {
            profile: 'stockflow',
            allowedshapes: [...SYSTEM_SHAPES, 'ellipse', 'roundrect'],
            defaultshape: 'roundrect',
        } as never;
        expect(formShapes(config, 'stockflow')).toEqual(
            expect.arrayContaining([...SYSTEM_SHAPES])
        );
    });
});

describe('stockflow SVG geometry', () => {
    const el = (shape: Parameters<typeof shapeElement>[0]) => shapeElement(shape, 120, 70, {});

    test('every system symbol is native SVG, never a raster or emoji', () => {
        for (const shape of SYSTEM_SHAPES) {
            const node = el(shape);
            expect(['rect', 'ellipse', 'polygon', 'path']).toContain(node.type);
        }
    });

    test('a stock is a box with a heavier outline', () => {
        const stock = el('stock');
        expect(stock.type).toBe('rect');
        expect(stock.props.strokeWidth).toBe(3);
    });

    test('a cloud is a closed path built from arcs', () => {
        const cloud = el('cloud');
        expect(cloud.type).toBe('path');
        expect(cloud.props.d).toContain('A');
        expect(cloud.props.d.trim().endsWith('Z')).toBe(true);
    });

    test('the sink cloud is the source cloud flipped', () => {
        const source = el('cloud').props.d as string;
        const sink = el('cloudsink').props.d as string;
        expect(sink).not.toBe(source);

        // The source keeps a flat base: its first segment runs along the bottom
        // edge. The sink keeps a flat roof, so that segment sits on top.
        const firstY = (d: string): number => Number(/^M [^,]+,(-?[\d.]+)/.exec(d)![1]);
        expect(firstY(source)).toBeGreaterThan(0);
        expect(firstY(sink)).toBeLessThan(0);
        // Mirroring keeps the same number of arcs, so both read as clouds.
        expect((sink.match(/A /g) ?? []).length).toBe((source.match(/A /g) ?? []).length);
    });

    test('a valve is a closed bow tie', () => {
        const valve = el('valve');
        expect(valve.type).toBe('path');
        expect(valve.props.d.trim().endsWith('Z')).toBe(true);
    });

    test('a delay is an hourglass, i.e. the bow tie on its side', () => {
        const valve = el('valve').props.d as string;
        const delay = el('delay').props.d as string;
        expect(delay).not.toBe(valve);
        expect(delay.trim().endsWith('Z')).toBe(true);
    });

    test('a parameter is an ellipse plus a marker, so it differs from auxiliary', () => {
        const parameter = el('parameter');
        expect(parameter.type).toBe('path');
        // Two subpaths: the ellipse, then the distinguishing bar.
        expect((parameter.props.d.match(/M /g) ?? []).length).toBe(2);
        expect(el('ellipse').type).toBe('ellipse');
    });

    test('symbols scale with the node rather than using fixed coordinates', () => {
        const small = shapeElement('cloud', 60, 40, {}).props.d as string;
        const large = shapeElement('cloud', 240, 160, {}).props.d as string;
        expect(small).not.toBe(large);
    });
});

describe('stockflow connector anchoring', () => {
    const center = {x: 0, y: 0};
    const size = {w: 120, h: 70};

    test('every system symbol anchors inside its bounding box', () => {
        for (const shape of SYSTEM_SHAPES) {
            const p = edgePointForShape(center, size, {x: 400, y: 200}, shape);
            expect(Math.abs(p.x)).toBeLessThanOrEqual(size.w / 2 + 2.001);
            expect(Math.abs(p.y)).toBeLessThanOrEqual(size.h / 2 + 2.001);
        }
    });

    test('a bow tie anchors tighter than a plain box', () => {
        // The valve pinches toward its centre, so a connector must stop short of
        // the box corner rather than floating beside the symbol.
        const valve = edgePointForShape(center, size, {x: 400, y: 400}, 'valve');
        const box = edgePointForShape(center, size, {x: 400, y: 400}, 'rect');
        expect(Math.hypot(valve.x, valve.y)).toBeLessThanOrEqual(Math.hypot(box.x, box.y));
    });

    test('both clouds anchor on an ellipse-like outline', () => {
        const sinkPoint = edgePointForShape(center, size, {x: 300, y: 300}, 'cloudsink');
        expect(Number.isFinite(sinkPoint.x)).toBe(true);
        const p = edgePointForShape(center, size, {x: 300, y: 300}, 'cloud');
        const hw = (size.w / 2 + 2) * 0.95;
        const hh = (size.h / 2 + 2) * 0.9;
        expect((p.x / hw) ** 2 + (p.y / hh) ** 2).toBeCloseTo(1, 5);
    });

    test('a stock anchors on its box, like a rectangle', () => {
        const stock = edgePointForShape(center, size, {x: 300, y: 0}, 'stock');
        const rect = edgePointForShape(center, size, {x: 300, y: 0}, 'rect');
        expect(stock).toEqual(rect);
    });

    test('a zero-length direction returns the centre for every symbol', () => {
        for (const shape of SYSTEM_SHAPES) {
            expect(edgePointForShape(center, size, center, shape)).toEqual(center);
        }
    });
});
