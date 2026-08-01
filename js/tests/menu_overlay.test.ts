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
 * Tests for the MenuOverlay canvas menu wrapper.
 *
 * These guard the click-through invariant that has repeatedly regressed: the
 * overlay box must never steal pointer events from the graph beneath it, and a
 * pointer-down on the toolbar must not reach the canvas.
 *
 * @module     mod_vimipad/tests/menu_overlay
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {MenuOverlay} from '../src/components/MenuOverlay';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

describe('MenuOverlay', () => {
    let host: HTMLDivElement;
    let root: Root;

    const render = (children: React.ReactNode): void => {
        act(() => {
            // A foreignObject must live inside an <svg>, so render into one.
            root.render(React.createElement('svg', null,
                React.createElement(MenuOverlay,
                    {x: 10, y: 20, width: 300, height: 320}, children)));
        });
    };

    beforeEach(() => {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    test('the foreignObject is click-through (pointer-events none)', () => {
        render(React.createElement('div', {className: 'vimipad-node-dock'}, 'menu'));
        const fo = host.querySelector('foreignObject') as SVGForeignObjectElement;
        expect(fo).not.toBeNull();
        // The box itself must not capture pointer events.
        expect(fo.getAttribute('pointer-events')).toBe('none');
    });

    test('the overlay box is positioned and sized as given', () => {
        render(React.createElement('div', {className: 'vimipad-node-dock'}, 'menu'));
        const fo = host.querySelector('foreignObject') as SVGForeignObjectElement;
        expect(fo.getAttribute('x')).toBe('10');
        expect(fo.getAttribute('y')).toBe('20');
        expect(fo.getAttribute('width')).toBe('300');
        expect(fo.getAttribute('height')).toBe('320');
    });

    test('the wrapper carries the click-through class, not an auto override', () => {
        render(React.createElement('div', {className: 'vimipad-node-dock'}, 'menu'));
        const wrapper = host.querySelector('.vimipad-node-dock-fo') as HTMLElement;
        expect(wrapper).not.toBeNull();
        // The wrapper must rely on the CSS class (pointer-events: none) and must
        // NOT set an inline pointer-events:auto that would make the whole box
        // clickable — the exact regression this component prevents.
        expect(wrapper.style.pointerEvents).not.toBe('auto');
    });

    test('a pointer-down on the menu does not propagate to the canvas', () => {
        let reachedCanvas = false;
        act(() => {
            root.render(React.createElement('svg',
                {onPointerDown: () => { reachedCanvas = true; }},
                React.createElement(MenuOverlay, {x: 0, y: 0, width: 300, height: 320},
                    React.createElement('button',
                        {className: 'vimipad-node-dock', type: 'button'}, 'X'))));
        });
        const button = host.querySelector('button') as HTMLButtonElement;
        act(() => {
            button.dispatchEvent(new Event('pointerdown', {bubbles: true, cancelable: true}));
        });
        // stopPropagation in the overlay must keep the event from bubbling up to
        // the SVG canvas, so clicking a menu button never pans or deselects.
        expect(reachedCanvas).toBe(false);
    });

    test('the menu content is rendered inside the wrapper', () => {
        render(React.createElement('div', {className: 'vimipad-node-dock'}, 'toolbar-content'));
        const wrapper = host.querySelector('.vimipad-node-dock-fo') as HTMLElement;
        expect(wrapper.textContent).toContain('toolbar-content');
        expect(wrapper.querySelector('.vimipad-node-dock')).not.toBeNull();
    });
});
