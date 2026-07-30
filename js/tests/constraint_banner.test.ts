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
 * Tests for the ConstraintBanner presentational component.
 *
 * @module     mod_vimipad/tests/constraint_banner
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {ConstraintBanner} from '../src/components/ConstraintBanner';
import {ConstraintStatus} from '../src/types';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

describe('ConstraintBanner', () => {
    let container: HTMLDivElement;
    let root: Root;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
        root = createRoot(container);
    });

    afterEach(() => {
        act(() => root.unmount());
        container.remove();
    });

    const render = (status: ConstraintStatus | null): void => {
        act(() => {
            root.render(React.createElement(ConstraintBanner, {status, t}));
        });
    };

    test('renders nothing when there is no status', () => {
        render(null);
        expect(container.querySelector('.vimipad-constraint-hints')).toBeNull();
    });

    test('renders nothing when satisfied or unconfigured', () => {
        render({configured: true, satisfied: true, messages: [], requiredmissing: [], forbiddenpresent: [], typeviolations: []});
        expect(container.querySelector('.vimipad-constraint-hints')).toBeNull();

        render({configured: false, satisfied: false, messages: ['x'], requiredmissing: [], forbiddenpresent: [], typeviolations: []});
        expect(container.querySelector('.vimipad-constraint-hints')).toBeNull();
    });

    test('renders each message when the map is configured but not satisfied', () => {
        render({
            configured: true, satisfied: false,
            messages: ['Missing: mitochondria', 'Too few relations'],
            requiredmissing: ['mitochondria'], forbiddenpresent: [], typeviolations: [],
        });
        const banner = container.querySelector('.vimipad-constraint-hints');
        expect(banner).not.toBeNull();
        const items = container.querySelectorAll('.vimipad-constraint-hints li');
        expect(items.length).toBe(2);
        expect(items[0].textContent).toContain('mitochondria');
    });
});
