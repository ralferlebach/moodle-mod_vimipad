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
 * Unit tests for the backend form-config bridge.
 *
 * @module     mod_vimipad/tests/form_config
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    formClampShape,
    formDefaultShape,
    formLine,
    formShapes,
    formShared,
} from '../src/canvas/form_config';
import {FormConfig} from '../src/types';

const treeConfig: FormConfig = {
    profile: 'tree',
    name: 'Tree',
    allowedshapes: ['rect', 'roundrect'],
    defaultshape: 'rect',
    line: 'orthogonal',
    bifurcation: 'shared',
};

describe('form_config', () => {
    test('prefers backend shapes and default when present', () => {
        expect(formShapes(treeConfig, 'tree')).toEqual(['rect', 'roundrect']);
        expect(formDefaultShape(treeConfig, 'tree')).toBe('rect');
    });

    test('falls back to the built-in table when config is absent', () => {
        // mindmap's built-in default is ellipse.
        expect(formDefaultShape(undefined, 'mindmap')).toBe('ellipse');
        expect(formShapes(undefined, 'tree')).toContain('rect');
    });

    test('ignores invalid backend shapes and uses the built-in set', () => {
        const bad: FormConfig = {...treeConfig, allowedshapes: ['bogus']};
        expect(formShapes(bad, 'tree')).toContain('rect');
    });

    test('clamps a stored shape to the allowed set', () => {
        // ellipse is not allowed by treeConfig, so it clamps to the default.
        expect(formClampShape(treeConfig, 'tree', 'ellipse')).toBe('rect');
        expect(formClampShape(treeConfig, 'tree', 'roundrect')).toBe('roundrect');
    });

    test('prefers the backend line, else the fallback', () => {
        expect(formLine(treeConfig, 'straight')).toBe('orthogonal');
        expect(formLine(undefined, 'curved')).toBe('curved');
        const bad: FormConfig = {...treeConfig, line: 'zigzag'};
        expect(formLine(bad, 'straight')).toBe('straight');
    });

    test('reports shared bifurcation from the backend, else the fallback', () => {
        expect(formShared(treeConfig, false)).toBe(true);
        const individual: FormConfig = {...treeConfig, bifurcation: 'individual'};
        expect(formShared(individual, true)).toBe(false);
        expect(formShared(undefined, true)).toBe(true);
    });
});
