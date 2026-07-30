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
 * Tests for the colour field, in particular the reset action that replaced the
 * former intermediate sub-menu.
 *
 * @module     mod_vimipad/tests/color_field
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {ColorField} from '../src/components/ColorField';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

describe('ColorField', () => {
    let host: HTMLDivElement;
    let root: Root;

    const render = (onReset?: () => void, onChange: (c: string) => void = () => undefined): void => {
        act(() => {
            root.render(React.createElement(ColorField, {
                value: '#eef2ff', fallback: '#ffffff', disabled: false,
                icon: 'fa-solid fa-palette', label: 'Fill', onChange, onReset, t,
            }));
        });
    };

    const openPopover = (): void => {
        const trigger = host.querySelector('.vimipad-colorfield > button') as HTMLButtonElement;
        act(() => {
            trigger.click();
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

    test('the trigger opens the picker directly, with no intermediate menu', () => {
        render();
        expect(host.querySelector('.vimipad-color-popover')).toBeNull();
        openPopover();
        expect(host.querySelector('.vimipad-color-popover')).not.toBeNull();
        expect(host.querySelector('.vimipad-node-dock-panel')).toBeNull();
    });

    test('without onReset the picker offers exactly cancel and confirm', () => {
        render();
        openPopover();
        const actions = host.querySelectorAll('.vimipad-color-actions button');
        expect(actions.length).toBe(2);
    });

    test('with onReset the picker offers a third action that resets and closes', () => {
        const calls: string[] = [];
        render(() => calls.push('reset'));
        openPopover();
        const actions = host.querySelectorAll('.vimipad-color-actions button');
        expect(actions.length).toBe(3);

        act(() => {
            (actions[0] as HTMLButtonElement).click();
        });
        expect(calls).toEqual(['reset']);
        expect(host.querySelector('.vimipad-color-popover')).toBeNull();
    });

    test('confirm commits the draft colour and carries the confirm class', () => {
        const committed: string[] = [];
        render(undefined, c => committed.push(c));
        openPopover();
        const confirm = host.querySelector('.vimipad-dock-confirm') as HTMLButtonElement;
        expect(confirm).not.toBeNull();
        act(() => {
            confirm.click();
        });
        expect(committed).toEqual(['#eef2ff']);
    });
});
