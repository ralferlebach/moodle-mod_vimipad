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
 * Tests for the System Dynamics role carried on a node.
 *
 * @module mod_vimipad/tests/stockflow_node_types
 */

import {parseNodeStyle, serialiseNodeStyle, withNodeStyle} from '../src/canvas/node_style';
import {relationTypeStyle} from '../src/relation_types';
import {STOCKFLOW_PALETTE} from '../src/components/NodeFormatToolbar';
import {ALL_SHAPES} from '../src/canvas/shape_catalog';

describe('stockflow node role', () => {
    test('a role survives parse and serialise', () => {
        const json = JSON.stringify({systemtype: 'stock', shape: 'stock'});
        const style = parseNodeStyle(json);
        expect(style.systemtype).toBe('stock');
        expect(JSON.parse(serialiseNodeStyle(style)).systemtype).toBe('stock');
    });

    test('changing the colour does not wipe the role', () => {
        // This is the failure that matters in practice: a teacher recolours a
        // stock and it quietly stops being a stock.
        const before = JSON.stringify({systemtype: 'valve', shape: 'valve'});
        const after = withNodeStyle(before, {fill: '#ffeeaa'});
        const parsed = JSON.parse(after);
        expect(parsed.systemtype).toBe('valve');
        expect(parsed.shape).toBe('valve');
        expect(parsed.fill).toBe('#ffeeaa');
    });

    test('changing the text style does not wipe the role', () => {
        const before = JSON.stringify({systemtype: 'delay'});
        const after = withNodeStyle(before, {text: {bold: true}});
        expect(JSON.parse(after).systemtype).toBe('delay');
    });

    test('a role can be set alongside a shape', () => {
        const json = withNodeStyle(undefined, {shape: 'stock', systemtype: 'stock'});
        const parsed = JSON.parse(json);
        expect(parsed).toMatchObject({shape: 'stock', systemtype: 'stock'});
    });

    test('the role is independent of the shape', () => {
        // A stock drawn as a plain rounded box is still a stock.
        const json = withNodeStyle(undefined, {shape: 'roundrect', systemtype: 'stock'});
        expect(JSON.parse(json)).toMatchObject({shape: 'roundrect', systemtype: 'stock'});
    });

    test('an unrecognised role is kept rather than dropped', () => {
        // The server validates the vocabulary. Dropping it here would downgrade
        // a node written by a newer version on the next style change.
        const after = withNodeStyle(JSON.stringify({systemtype: 'converter'}), {fill: '#ffffff'});
        expect(JSON.parse(after).systemtype).toBe('converter');
    });

    test('a node without a role stays without one', () => {
        const json = withNodeStyle(JSON.stringify({shape: 'rect'}), {fill: '#ffffff'});
        expect(JSON.parse(json).systemtype).toBeUndefined();
    });
});

describe('stockflow relation styling', () => {
    test('a flow is drawn more strongly than an influence', () => {
        const flow = relationTypeStyle('flow');
        const influence = relationTypeStyle('influence');
        expect(flow?.weight ?? 1).toBeGreaterThan(influence?.weight ?? 1);
    });

    test('flow and influence are visually distinguishable', () => {
        expect(relationTypeStyle('flow')?.color)
            .not.toBe(relationTypeStyle('influence')?.color);
    });

    test('no relation type carries a polarity property', () => {
        // The issue rules polarity out: meaning lives in the relation's label.
        for (const type of ['flow', 'influence', 'relation']) {
            expect(relationTypeStyle(type) ?? {}).not.toHaveProperty('polarity');
        }
    });

    test('existing relation types keep their default weight', () => {
        for (const type of ['support', 'attack', 'positive', 'negative', 'isa']) {
            expect(relationTypeStyle(type)?.weight).toBeUndefined();
        }
    });

    test('a neutral relation has no special styling', () => {
        expect(relationTypeStyle('relation')).toBeNull();
    });
});

describe('stockflow role palette', () => {
    test('the palette offers exactly the eight roles the notation defines', () => {
        expect(STOCKFLOW_PALETTE.map(e => e.role)).toEqual([
            'element', 'stock', 'source', 'sink', 'valve', 'delay', 'auxiliary', 'parameter',
        ]);
    });

    test('source and sink share a symbol but stay distinct roles', () => {
        const source = STOCKFLOW_PALETTE.find(e => e.role === 'source');
        const sink = STOCKFLOW_PALETTE.find(e => e.role === 'sink');
        expect(source?.shape).toBe('cloud');
        expect(sink?.shape).toBe('cloud');
        expect(source?.role).not.toBe(sink?.role);
        // A shape-only picker could not express both, which is why the palette
        // is keyed by role.
        expect(source?.label).not.toBe(sink?.label);
    });

    test('every palette entry maps to a real shape and its own label', () => {
        const labels = STOCKFLOW_PALETTE.map(e => e.label);
        expect(new Set(labels).size).toBe(STOCKFLOW_PALETTE.length);
        for (const entry of STOCKFLOW_PALETTE) {
            expect(ALL_SHAPES).toContain(entry.shape);
            expect(entry.label.startsWith('editor:sys_')).toBe(true);
        }
    });

    test('picking a palette entry records both the geometry and the role', () => {
        const entry = STOCKFLOW_PALETTE.find(e => e.role === 'sink')!;
        const json = withNodeStyle(undefined, {shape: entry.shape, systemtype: entry.role});
        expect(JSON.parse(json)).toMatchObject({shape: 'cloud', systemtype: 'sink'});
    });
});
