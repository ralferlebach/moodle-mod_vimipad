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
 * Tests for the LockPanel authoring component.
 *
 * @module     mod_vimipad/tests/lock_panel
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {LockKind, LockPanel} from '../src/components/LockPanel';
import {VimiContainer, VimiNode, VimiRelation} from '../src/types';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

describe('LockPanel', () => {
    let container: HTMLDivElement;
    let root: Root;
    let calls: Array<{kind: LockKind; stableid: string; metadatajson: string}>;

    const nodes: VimiNode[] = [{stableid: 'node_a', type: 'concept', label: 'Cell'}];
    const relations: VimiRelation[] = [
        {stableid: 'rel_a', sourceid: 'node_a', targetid: 'node_a', type: 'link', label: 'has', direction: 1},
    ];
    const containers: VimiContainer[] = [
        {stableid: 'container_a', type: 'group', label: 'Section', geometryjson: '{"x":0,"y":0,"w":100,"h":100}'},
    ];

    const render = (n: VimiNode[], r: VimiRelation[], c: VimiContainer[] = []): void => {
        act(() => {
            root.render(React.createElement(LockPanel, {
                nodes: n, relations: r, containers: c, disabled: false, t,
                onSetLock: (kind, stableid, metadatajson) => calls.push({kind, stableid, metadatajson}),
            }));
        });
    };

    beforeEach(() => {
        calls = [];
        container = document.createElement('div');
        document.body.appendChild(container);
        root = createRoot(container);
    });

    afterEach(() => {
        act(() => root.unmount());
        container.remove();
    });

    test('renders a row per node, relation and container', () => {
        render(nodes, relations, containers);
        expect(container.querySelectorAll('.vimipad-lock-row').length).toBe(3);
    });

    test('locking a container writes locked metadata for it', () => {
        render([], [], containers);
        const cb = container.querySelector('.vimipad-lock-row input[type=checkbox]') as HTMLInputElement;
        act(() => {
            cb.click();
        });
        expect(calls).toHaveLength(1);
        expect(calls[0].kind).toBe('container');
        expect(calls[0].stableid).toBe('container_a');
        expect(JSON.parse(calls[0].metadatajson).locked).toBe(true);
    });

    test('renders nothing when there is nothing to lock', () => {
        render([], []);
        expect(container.querySelector('.vimipad-lock-panel')).toBeNull();
    });

    test('toggling the lock writes locked metadata for that element', () => {
        render(nodes, relations);
        const firstCheckbox = container.querySelector('.vimipad-lock-row input[type=checkbox]') as HTMLInputElement;
        act(() => {
            firstCheckbox.click();
        });
        expect(calls).toHaveLength(1);
        expect(calls[0].kind).toBe('node');
        expect(calls[0].stableid).toBe('node_a');
        expect(JSON.parse(calls[0].metadatajson).locked).toBe(true);
    });

    test('an already-locked element shows the allow-renaming sub-toggle', () => {
        const locked: VimiNode[] = [{...nodes[0], metadatajson: '{"locked":true}'}];
        render(locked, []);
        const suboption = container.querySelector('.vimipad-lock-suboption');
        expect(suboption).not.toBeNull();
        const sub = suboption?.querySelector('input[type=checkbox]') as HTMLInputElement;
        act(() => {
            sub.click();
        });
        expect(JSON.parse(calls[0].metadatajson).editable).toEqual(['label']);
    });
});
