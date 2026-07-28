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
 * Unit tests for the SVG export content-bounds computation.
 *
 * @module     mod_vimipad/tests/svg_export
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {computeContentBounds, buildImagePdf, Bounds} from '../src/canvas/svg_export';
import {VimiNode} from '../src/types';

const node = (id: string): VimiNode => ({stableid: id, type: 'concept', label: id});
const fallback: Bounds = {x: 0, y: 0, w: 800, h: 520};

describe('computeContentBounds', () => {
    test('returns the fallback when no node is placed', () => {
        const bounds = computeContentBounds([node('a')], {}, {}, 60, fallback);
        expect(bounds).toEqual(fallback);
    });

    test('wraps placed nodes with padding and their sizes', () => {
        const nodes = [node('a'), node('b')];
        const layout = {a: {x: 100, y: 100}, b: {x: 300, y: 200}};
        const sizes = {a: {w: 120, h: 40}, b: {w: 120, h: 40}};
        const bounds = computeContentBounds(nodes, layout, sizes, 60, fallback);
        // a spans x[40..160] y[80..120]; b spans x[240..360] y[180..220].
        // min corner (40,80) minus pad 60 → (-20, 20); width 320+120=... check.
        expect(bounds.x).toBe(-20);
        expect(bounds.y).toBe(20);
        expect(bounds.w).toBe(320 + 120); // (360-40) + 2*60
        expect(bounds.h).toBe(140 + 120); // (220-80) + 2*60
    });

    test('ignores unplaced nodes but still frames placed ones', () => {
        const nodes = [node('a'), node('ghost')];
        const layout = {a: {x: 0, y: 0}};
        const bounds = computeContentBounds(nodes, layout, {}, 10, fallback);
        expect(bounds).not.toEqual(fallback);
        // Default node size 120x44 → half 60x22; with pad 10 → x -70, y -32.
        expect(bounds.x).toBe(-70);
        expect(bounds.y).toBe(-32);
    });
});

describe('buildImagePdf', () => {
    const decode = (bytes: Uint8Array): string =>
        Array.from(bytes).map((b) => String.fromCharCode(b)).join('');

    test('produces a structurally valid single-image PDF', () => {
        const jpeg = new Uint8Array([0xff, 0xd8, 0xff, 0xd9]); // minimal JPEG-ish bytes
        const pdf = buildImagePdf(jpeg, 200, 100, 200, 100);
        const text = decode(pdf);

        expect(text.startsWith('%PDF-1.3')).toBe(true);
        expect(text.trimEnd().endsWith('%%EOF')).toBe(true);
        expect(text).toContain('/Type /Catalog');
        expect(text).toContain('/Filter /DCTDecode');
        expect(text).toContain('/MediaBox [0 0 200 100]');
        expect(text).toContain('startxref');
        // The xref table declares six entries (free + five objects).
        expect(text).toContain('xref\n0 6');
        // The embedded JPEG length is declared correctly.
        expect(text).toContain(`/Length ${jpeg.length}`);
    });
});
