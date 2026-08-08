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
 * Tests for the redesigned lock menu (reclamations R1/R2): the normal element
 * menu carries a lock button that opens a submenu of struck-through per-group
 * toggles, rather than the top-right button switching the whole menu.
 *
 * @module     mod_vimipad/tests/lock_submenu
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {NodeFormatToolbar} from '../src/components/NodeFormatToolbar';
import {RelationMenu} from '../src/components/RelationMenu';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;
const noop = (): void => undefined;

/** Find a button by its aria-label. */
function button(container: HTMLElement, arialabel: string): HTMLButtonElement | null {
    return Array.from(container.querySelectorAll('button'))
        .find(b => b.getAttribute('aria-label') === arialabel) ?? null;
}

describe('Node lock submenu (R1/R2)', () => {
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

    const renderNode = (metadatajson: string | undefined, onToggle?: (g: string) => void): void => {
        act(() => {
            root.render(React.createElement(NodeFormatToolbar, {
                target: {metadatajson}, profile: 'conceptmap', disabled: false,
                onChangeStyle: noop, onDelete: noop, onEditText: noop,
                lockGroups: onToggle ? ['move', 'color', 'text'] : undefined,
                onToggleLockGroup: onToggle as ((g: 'move' | 'color' | 'text') => void) | undefined,
                t,
            }));
        });
    };

    test('a lock button is shown when the user may lock, and hidden otherwise', () => {
        renderNode(undefined);
        expect(button(container, 'editor:templatelocks')).toBeNull();
        renderNode(undefined, noop);
        expect(button(container, 'editor:templatelocks')).not.toBeNull();
    });

    test('the lock submenu is closed until the lock button is clicked', () => {
        renderNode(undefined, noop);
        // Submenu (labelled editor:templatelocks group) not present yet.
        expect(container.querySelector('.vimipad-lock-submenu')).toBeNull();
        act(() => { button(container, 'editor:templatelocks')!.click(); });
        expect(container.querySelector('.vimipad-lock-submenu')).not.toBeNull();
        // The submenu offers the three struck-through group toggles.
        expect(container.querySelectorAll('.vimipad-lock-submenu .vimipad-struck').length).toBe(3);
    });

    test('clicking a group toggle calls onToggleLockGroup with that group', () => {
        const toggled: string[] = [];
        renderNode(undefined, g => toggled.push(g));
        act(() => { button(container, 'editor:templatelocks')!.click(); });
        const moveToggle = Array.from(container.querySelectorAll('.vimipad-lock-submenu button'))
            .find(b => (b.getAttribute('aria-label') ?? '').startsWith('editor:lockgroup_move')) as HTMLButtonElement;
        act(() => { moveToggle.click(); });
        expect(toggled).toEqual(['move']);
    });

    test('a locked group shows its toggle in the active (pressed) state', () => {
        renderNode(JSON.stringify({locked: true, locks: {move: true, color: false, text: false}}), noop);
        act(() => { button(container, 'editor:templatelocks')!.click(); });
        const moveToggle = Array.from(container.querySelectorAll('.vimipad-lock-submenu button'))
            .find(b => (b.getAttribute('aria-label') ?? '').startsWith('editor:lockgroup_move')) as HTMLButtonElement;
        expect(moveToggle.getAttribute('aria-pressed')).toBe('true');
    });
});

describe('Relation lock submenu (R2, no colour group)', () => {
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

    test('the relation lock submenu offers only move and text (no colour)', () => {
        act(() => {
            root.render(React.createElement(RelationMenu, {
                stableid: 'r1', direction: 1, metadatajson: undefined, disabled: false,
                canLock: true, onEditText: noop, onChangeDirection: noop, onDelete: noop,
                onToggleLockGroup: noop, t,
            }));
        });
        act(() => { button(container, 'editor:templatelocks')!.click(); });
        const toggles = Array.from(container.querySelectorAll('.vimipad-lock-submenu button'))
            .map(b => (b.getAttribute('aria-label') ?? '').split(' — ')[0]);
        expect(toggles).toEqual(['editor:lockgroup_move', 'editor:lockgroup_text']);
    });
});
