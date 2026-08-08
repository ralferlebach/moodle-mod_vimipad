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
 * Tests for the RelationListView, focused on the merged rendering of a
 * double-arrow relation (its label and action buttons appear once, spanning
 * both connected rows, rather than being duplicated).
 *
 * @module     mod_vimipad/tests/relation_list_view
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {RelationListView} from '../src/components/RelationListView';
import {EditorState} from '../src/store/reducer';
import {VimiNode, VimiRelation} from '../src/types';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;
const noop = (): void => undefined;

const nodes: VimiNode[] = [
    {stableid: 'a', type: 'concept', label: 'A'},
    {stableid: 'b', type: 'concept', label: 'B'},
];

function stateWith(relations: VimiRelation[]): EditorState {
    return {
        workspaceid: 1, revision: 1, locked: 0, profile: 'conceptmap', layoutjson: '',
        nodes, relations,
    };
}

function render(root: Root, state: EditorState, enforced = true): void {
    act(() => {
        root.render(React.createElement(RelationListView, {
            state, disabled: false, enforced, onDeleteRelation: noop, onRetarget: noop,
            onRenameRelation: noop, t,
        }));
    });
}

/** Find an action button by its (lock-independent) aria-label. */
function button(container: HTMLElement, arialabel: string): HTMLButtonElement | null {
    return Array.from(container.querySelectorAll('button'))
        .find(b => b.getAttribute('aria-label') === arialabel) ?? null;
}

describe('RelationListView double-arrow merging', () => {
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

    test('a single-direction relation renders one action cell', () => {
        render(root, stateWith([
            {stableid: 'r1', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1},
        ]));
        const actionCells = container.querySelectorAll('.vimipad-relation-actions');
        expect(actionCells.length).toBe(1);
        // One row for a single-direction relation.
        expect(container.querySelectorAll('tbody tr').length).toBe(1);
    });

    test('a double-arrow relation renders two rows but only one action cell', () => {
        render(root, stateWith([
            {stableid: 'r2', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 2},
        ]));
        // Two connected rows (A->B and B->A).
        expect(container.querySelectorAll('tbody tr').length).toBe(2);
        // The action buttons appear once, spanning both rows.
        const actionCells = container.querySelectorAll('.vimipad-relation-actions');
        expect(actionCells.length).toBe(1);
        expect((actionCells[0] as HTMLTableCellElement).rowSpan).toBe(2);
        // The relation label cell also appears once and spans both rows.
        const spanned = Array.from(container.querySelectorAll('td[rowspan="2"]'));
        expect(spanned.length).toBe(2); // label cell + actions cell
    });

    test('a move-locked relation disables reverse and delete but not rename', () => {
        render(root, stateWith([
            {
                stableid: 'r3', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true, locks: {move: true, color: false, text: false}}),
            },
        ]));
        // Controls are shown but disabled, so the editor can see the lock.
        expect(button(container, 'editor:reverse')?.disabled).toBe(true);
        expect(button(container, 'editor:deleterelation')?.disabled).toBe(true);
        // Rename (edit) stays enabled because text is not locked.
        expect(button(container, 'editor:reledit')?.disabled).toBe(false);
        // A lock badge is shown for the row.
        expect(container.querySelector('.fa-lock')).not.toBeNull();
    });

    test('a text-locked relation disables rename but not reverse', () => {
        render(root, stateWith([
            {
                stableid: 'r4', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true, locks: {move: false, color: false, text: true}}),
            },
        ]));
        expect(button(container, 'editor:reledit')?.disabled).toBe(true);
        expect(button(container, 'editor:reverse')?.disabled).toBe(false);
    });

    test('a legacy globally-locked relation disables every action button', () => {
        render(root, stateWith([
            {
                stableid: 'r5', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true}),
            },
        ]));
        expect(button(container, 'editor:reledit')?.disabled).toBe(true);
        expect(button(container, 'editor:reverse')?.disabled).toBe(true);
        expect(button(container, 'editor:deleterelation')?.disabled).toBe(true);
    });

    test('with enforcement off (manager authoring) a locked relation stays fully editable', () => {
        render(root, stateWith([
            {
                stableid: 'r6', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true}),
            },
        ]), false);
        // No lock binds when enforcement is off, so every control is enabled.
        expect(button(container, 'editor:reledit')?.disabled).toBe(false);
        expect(button(container, 'editor:reverse')?.disabled).toBe(false);
        expect(button(container, 'editor:deleterelation')?.disabled).toBe(false);
        expect(container.querySelector('.fa-lock')).toBeNull();
    });
});
