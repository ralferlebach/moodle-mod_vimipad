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

import {resolveProfileRefine, refineOptionsForProfile, fishboneEdgeDirs} from '../src/graph/refine/refine_arrange';
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
        expect(r).toEqual({directed: true, preferredDir: {x: 0, y: 1}, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null, attackRepel: false, rankLayered: false, clustered: false, fishbone: false, relationLayout: []});
    });

    test('an order-only PHP layout (conceptmap-like) drops direction to null', () => {
        const r = resolveProfileRefine('conceptmap', cfg({
            profile: 'conceptmap',
            layout: {directed: false, orderaxis: {x: 1, y: 0}},
        }));
        expect(r).toEqual({directed: false, preferredDir: null, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null, attackRepel: false, rankLayered: false, clustered: false, fishbone: false, relationLayout: []});
    });

    test('a free PHP layout (radial) yields no direction and no order', () => {
        const r = resolveProfileRefine('mindmap', cfg({
            profile: 'mindmap', layout: {directed: false},
        }));
        expect(r).toEqual({directed: false, preferredDir: null, orderAxis: null, cyclicOrder: false, lineAxis: null, attackRepel: false, rankLayered: false, clustered: false, fishbone: false, relationLayout: []});
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

    test('an argument form (relationtypes incl. attack) enables attack repulsion', () => {
        const r = resolveProfileRefine('argument', cfg({
            profile: 'argument',
            relationtypes: ['support', 'attack'],
            layout: {directed: true, direction: {x: 0, y: -1}, orderaxis: {x: 1, y: 0}},
        }));
        expect(r.directed).toBe(true);
        expect(r.preferredDir).toEqual({x: 0, y: -1});
        expect(r.attackRepel).toBe(true);
    });

    test('a form without an attack relation type does not repel', () => {
        const r = resolveProfileRefine('tree', cfg({
            profile: 'tree', relationtypes: ['link'],
            layout: {directed: true, direction: {x: 0, y: 1}, orderaxis: {x: 1, y: 0}},
        }));
        expect(r.attackRepel).toBe(false);
    });

    test('the built-in argument default flows up with attack repulsion', () => {
        const a = refineOptionsForProfile('argument');
        expect(a.directed).toBe(true);
        expect(a.preferredDir).toEqual({x: 0, y: -1});
        expect(a.attackRepel).toBe(true);
    });

    test('a flow PHP layout (ranklayered) enables rank layering downward', () => {
        const r = resolveProfileRefine('flow', cfg({
            profile: 'flow',
            layout: {directed: true, direction: {x: 0, y: 1}, orderaxis: {x: 1, y: 0}, ranklayered: true},
        }));
        expect(r.directed).toBe(true);
        expect(r.preferredDir).toEqual({x: 0, y: 1});
        expect(r.rankLayered).toBe(true);
    });

    test('the built-in flow default is directed with rank layering', () => {
        const f = refineOptionsForProfile('flow');
        expect(f.directed).toBe(true);
        expect(f.rankLayered).toBe(true);
        expect(f.preferredDir).toEqual({x: 0, y: 1});
    });

    test('an affinity PHP layout (clustered) enables cluster cohesion', () => {
        const r = resolveProfileRefine('affinity', cfg({
            profile: 'affinity', layout: {directed: false, clustered: true},
        }));
        expect(r.directed).toBe(false);
        expect(r.clustered).toBe(true);
    });

    test('the built-in affinity default is undirected and clustered', () => {
        const a = refineOptionsForProfile('affinity');
        expect(a.clustered).toBe(true);
        expect(a.directed).toBe(false);
        expect(a.orderAxis).toBeNull();
    });

    test('the built-in fishbone default is a directed spine with per-branch bones', () => {
        const f = refineOptionsForProfile('fishbone');
        expect(f.directed).toBe(true);
        expect(f.fishbone).toBe(true);
        expect(f.preferredDir).toEqual({x: 1, y: 0});
    });

    test('fishboneEdgeDirs alternates the main bones and inherits down the branch', () => {
        // Head H with two main bones A, B (A has a sub-cause A1).
        const rels = [
            {stableid: 'r1', sourceid: 'A', targetid: 'H', type: 'link', label: '', direction: 1},
            {stableid: 'r2', sourceid: 'B', targetid: 'H', type: 'link', label: '', direction: 1},
            {stableid: 'r3', sourceid: 'A1', targetid: 'A', type: 'link', label: '', direction: 1},
        ];
        const dirs = fishboneEdgeDirs(rels);
        const dA = dirs.get('A\u0000H');
        const dB = dirs.get('B\u0000H');
        const dA1 = dirs.get('A1\u0000A');
        // Both main bones point forward (+x) but to opposite sides in y.
        expect(dA!.x).toBe(1);
        expect(dB!.x).toBe(1);
        expect(Math.sign(dA!.y)).toBe(-Math.sign(dB!.y));
        // The sub-cause inherits its branch's side (same y sign as A's bone).
        expect(Math.sign(dA1!.y)).toBe(Math.sign(dA!.y));
    });

    test('the built-in venn default reuses cluster cohesion; causal is free', () => {
        const v = refineOptionsForProfile('venn');
        expect(v.clustered).toBe(true);
        expect(v.directed).toBe(false);
        const c = refineOptionsForProfile('causal');
        expect(c.clustered).toBe(false);
        expect(c.directed).toBe(false);
        expect(c.preferredDir).toBeNull();
    });
});
