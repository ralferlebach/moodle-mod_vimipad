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
 * Tests for resolveProfileRefine: the arrange refiner now takes its per-profile
 * layout behaviour from the form's PHP subplugin (transported in the form
 * config), falling back to the built-in switch only when no config is present.
 *
 * @module     mod_vimipad/tests/refine_profile_resolver
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {resolveProfileRefine, refineOptionsForProfile} from '../src/graph/refine/refine_arrange';
import {FormConfig} from '../src/types';

function cfg(over: Partial<FormConfig>): FormConfig {
    return {
        profile: 'x', name: 'X', allowedshapes: ['roundrect'], defaultshape: 'roundrect',
        line: 'straight', bifurcation: 'individual', ...over,
    };
}

describe('resolveProfileRefine', () => {
    test('with no config, falls back to the built-in profile defaults', () => {
        expect(resolveProfileRefine('tree')).toEqual(refineOptionsForProfile('tree'));
        expect(resolveProfileRefine('mindmap')).toEqual(refineOptionsForProfile('mindmap'));
    });

    test('with a config lacking layout, still falls back', () => {
        expect(resolveProfileRefine('tree', cfg({profile: 'tree'}))).toEqual(refineOptionsForProfile('tree'));
    });

    test('a directed, ordered PHP layout (tree-like) is used verbatim', () => {
        const r = resolveProfileRefine('tree', cfg({
            profile: 'tree',
            layout: {directed: true, direction: {x: 0, y: 1}, orderaxis: {x: 1, y: 0}},
        }));
        expect(r).toEqual({directed: true, preferredDir: {x: 0, y: 1}, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null});
    });

    test('an order-only PHP layout (conceptmap-like) drops direction to null', () => {
        const r = resolveProfileRefine('conceptmap', cfg({
            profile: 'conceptmap',
            layout: {directed: false, orderaxis: {x: 1, y: 0}},
        }));
        expect(r).toEqual({directed: false, preferredDir: null, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null});
    });

    test('a free PHP layout (radial) yields no direction and no order', () => {
        const r = resolveProfileRefine('mindmap', cfg({
            profile: 'mindmap', layout: {directed: false},
        }));
        expect(r).toEqual({directed: false, preferredDir: null, orderAxis: null, cyclicOrder: false, lineAxis: null});
    });

    test('a line-confine PHP layout (timeline-like) sets lineAxis', () => {
        const r = resolveProfileRefine('timeline', cfg({
            profile: 'timeline',
            layout: {directed: true, direction: {x: 1, y: 0}, lineaxis: {x: 1, y: 0}},
        }));
        expect(r.directed).toBe(true);
        expect(r.preferredDir).toEqual({x: 1, y: 0});
        expect(r.lineAxis).toEqual({x: 1, y: 0});
    });

    test('the built-in timeline default is directed with a line axis', () => {
        const t = refineOptionsForProfile('timeline');
        expect(t.directed).toBe(true);
        expect(t.lineAxis).toEqual({x: 1, y: 0});
        expect(t.preferredDir).toEqual({x: 1, y: 0});
    });
});
