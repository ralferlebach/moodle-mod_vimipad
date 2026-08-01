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

function render(root: Root, state: EditorState): void {
    act(() => {
        root.render(React.createElement(RelationListView, {
            state, disabled: false, onDeleteRelation: noop, onRetarget: noop,
            onRenameRelation: noop, t,
        }));
    });
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

    test('a move-locked relation offers no reverse button and no retarget', () => {
        render(root, stateWith([
            {
                stableid: 'r3', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true, locks: {move: true, color: false, text: false}}),
            },
        ]));
        // Reverse button (title "editor:reverse") must be gone.
        const titles = Array.from(container.querySelectorAll('button')).map(b => b.getAttribute('title'));
        expect(titles).not.toContain('editor:reverse');
        // Rename (edit) is still offered because text is not locked.
        expect(titles).toContain('editor:reledit');
        // Delete is gone because the relation carries a lock.
        expect(titles).not.toContain('editor:deleterelation');
    });

    test('a text-locked relation offers no rename', () => {
        render(root, stateWith([
            {
                stableid: 'r4', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true, locks: {move: false, color: false, text: true}}),
            },
        ]));
        const titles = Array.from(container.querySelectorAll('button')).map(b => b.getAttribute('title'));
        // No rename (edit) button.
        expect(titles).not.toContain('editor:reledit');
        // Reverse is still available because move is not locked.
        expect(titles).toContain('editor:reverse');
    });

    test('a legacy globally-locked relation offers no action buttons at all', () => {
        render(root, stateWith([
            {
                stableid: 'r5', sourceid: 'a', targetid: 'b', type: 'link', label: 'rel', direction: 1,
                metadatajson: JSON.stringify({locked: true}),
            },
        ]));
        const titles = Array.from(container.querySelectorAll('button')).map(b => b.getAttribute('title'));
        expect(titles).not.toContain('editor:reledit');
        expect(titles).not.toContain('editor:reverse');
        expect(titles).not.toContain('editor:deleterelation');
    });
});
