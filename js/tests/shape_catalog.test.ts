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
 * Unit tests for the profile-dependent shape catalogue.
 *
 * @module     mod_vimipad/tests/shape_catalog
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    allowedShapes,
    clampShape,
    defaultShape,
    isShapeAllowed,
} from '../src/canvas/shape_catalog';

describe('shape_catalog', () => {
    it('gives each MVP profile a shape-appropriate default', () => {
        expect(defaultShape('conceptmap')).toBe('roundrect');
        expect(defaultShape('tree')).toBe('rect');
        expect(defaultShape('mindmap')).toBe('ellipse');
        expect(defaultShape('bubblemap')).toBe('ellipse');
        expect(defaultShape('semanticnetwork')).toBe('ellipse');
    });

    it('falls back for an unknown profile', () => {
        expect(defaultShape('does-not-exist')).toBe('roundrect');
        expect(allowedShapes('does-not-exist')).toEqual(['roundrect', 'rect', 'ellipse']);
    });

    it('permits the three universal shapes for MVP profiles', () => {
        expect(isShapeAllowed('conceptmap', 'ellipse')).toBe(true);
        expect(isShapeAllowed('tree', 'roundrect')).toBe(true);
    });

    it('keeps a permitted stored shape unchanged (conversion)', () => {
        expect(clampShape('conceptmap', 'ellipse')).toBe('ellipse');
    });

    it('converts an absent shape to the profile default', () => {
        expect(clampShape('tree', undefined)).toBe('rect');
        expect(clampShape('mindmap', undefined)).toBe('ellipse');
    });
});
