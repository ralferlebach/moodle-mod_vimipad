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
 * Tests for the shared relation-type style map used by the menu, list and canvas.
 *
 * @module     mod_vimipad/tests/relation_types
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {relationTypeStyle} from '../src/relation_types';

describe('relationTypeStyle', () => {
    test('untyped / unknown / undefined relations have no style', () => {
        expect(relationTypeStyle(undefined)).toBeNull();
        expect(relationTypeStyle('link')).toBeNull();
        expect(relationTypeStyle('mystery')).toBeNull();
    });

    test('argument types: support solid, attack dashed', () => {
        expect(relationTypeStyle('support')!.dash).toBeUndefined();
        expect(relationTypeStyle('support')!.color).toContain('support');
        expect(relationTypeStyle('attack')!.dash).toBeTruthy();
        expect(relationTypeStyle('attack')!.color).toContain('attack');
    });

    test('causal polarity: positive and negative are distinct, negative dashed', () => {
        const pos = relationTypeStyle('positive')!;
        const neg = relationTypeStyle('negative')!;
        expect(pos.color).not.toBe(neg.color);
        expect(pos.dash).toBeUndefined();
        expect(neg.dash).toBeTruthy();
    });

    test('flow branches: yes solid, no dashed; sequence is neutral (no style)', () => {
        expect(relationTypeStyle('yes')!.dash).toBeUndefined();
        expect(relationTypeStyle('no')!.dash).toBeTruthy();
        expect(relationTypeStyle('sequence')).toBeNull();
    });

    test('semantic-network links instanceof/hasproperty are styled and distinct', () => {
        expect(relationTypeStyle('instanceof')!.color).toContain('instanceof');
        expect(relationTypeStyle('hasproperty')!.color).toContain('hasproperty');
        expect(relationTypeStyle('instanceof')!.color).not.toBe(relationTypeStyle('hasproperty')!.color);
    });

    test('every styled type uses a CSS variable with a literal fallback (export-safe)', () => {
        for (const key of ['support', 'attack', 'positive', 'negative', 'isa', 'partof',
            'associated', 'instanceof', 'hasproperty', 'yes', 'no']) {
            expect(relationTypeStyle(key)!.color).toMatch(/^var\(--vimipad-relation-.+, #[0-9a-f]{6}\)$/);
        }
    });
});
