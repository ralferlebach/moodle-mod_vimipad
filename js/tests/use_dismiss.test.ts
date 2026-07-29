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
 * Tests for the useDismiss hook (outside-click / Escape dismissal).
 *
 * @module     mod_vimipad/tests/use_dismiss
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useRef} from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {useDismiss} from '../src/hooks/use_dismiss';

// Mark the environment so React's effects flush synchronously under act().
(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

/** A harness that wires useDismiss to an inner element. */
function Harness(props: {active: boolean; onDismiss: () => void}): React.ReactElement {
    const ref = useRef<HTMLDivElement>(null);
    useDismiss(ref, props.active, props.onDismiss);
    return React.createElement('div', {ref, id: 'menu'}, 'menu');
}

describe('useDismiss', () => {
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

    const render = (active: boolean, onDismiss: () => void): void => {
        act(() => {
            root.render(React.createElement(Harness, {active, onDismiss}));
        });
    };

    test('dismisses on an outside mousedown when active', () => {
        const onDismiss = jest.fn();
        render(true, onDismiss);
        act(() => {
            document.body.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
        });
        expect(onDismiss).toHaveBeenCalledTimes(1);
    });

    test('does not dismiss on a click inside the element', () => {
        const onDismiss = jest.fn();
        render(true, onDismiss);
        const menu = container.querySelector('#menu') as HTMLElement;
        act(() => {
            menu.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
        });
        expect(onDismiss).not.toHaveBeenCalled();
    });

    test('dismisses on Escape when active', () => {
        const onDismiss = jest.fn();
        render(true, onDismiss);
        act(() => {
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'}));
        });
        expect(onDismiss).toHaveBeenCalledTimes(1);
    });

    test('does nothing when inactive', () => {
        const onDismiss = jest.fn();
        render(false, onDismiss);
        act(() => {
            document.body.dispatchEvent(new MouseEvent('mousedown', {bubbles: true}));
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'}));
        });
        expect(onDismiss).not.toHaveBeenCalled();
    });
});
