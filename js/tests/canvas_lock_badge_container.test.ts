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
 * Tests for the canvas lock-badge overlay (R6) and the container label/title-bar
 * behaviour (R9).
 *
 * @module     mod_vimipad/tests/canvas_lock_badge_container
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {CanvasView} from '../src/components/CanvasView';
import {EditorState} from '../src/store/reducer';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;
const noop = (): void => undefined;

function box(x: number, y: number, w: number, h: number): string {
    return JSON.stringify({x, y, w, h});
}

function baseState(over: Partial<EditorState>): EditorState {
    return {
        workspaceid: 1, revision: 1, locked: 0, profile: 'conceptmap', layoutjson: '',
        nodes: [], relations: [], containers: [], canmanage: true, ...over,
    } as EditorState;
}

function render(root: Root, state: EditorState): void {
    act(() => {
        root.render(React.createElement(CanvasView, {
            state,
            layout: {n1: {x: 300, y: 300}},
            profile: 'conceptmap',
            sizes: {},
            disabled: false,
            onNodeMoved: noop,
            t,
        }));
    });
}

describe('Canvas lock badge (R6)', () => {
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

    test('a locked node shows exactly one lock badge, in the dedicated overlay layer', () => {
        render(root, baseState({
            nodes: [{
                stableid: 'n1', type: 'concept', label: 'A',
                metadatajson: JSON.stringify({locked: true, locks: {move: true, color: false, text: false}}),
            }],
        }));
        const layer = container.querySelector('.vimipad-lock-badges');
        expect(layer).not.toBeNull();
        expect(layer!.querySelectorAll('.vimipad-lock-badge').length).toBe(1);
    });

    test('an unlocked node shows no lock badge', () => {
        render(root, baseState({
            nodes: [{stableid: 'n1', type: 'concept', label: 'A'}],
        }));
        expect(container.querySelectorAll('.vimipad-lock-badge').length).toBe(0);
    });

    test('the badge layer paints after (above) the node layer', () => {
        render(root, baseState({
            nodes: [{stableid: 'n1', type: 'concept', label: 'A', metadatajson: JSON.stringify({locked: true})}],
        }));
        const svg = container.querySelector('svg')!;
        const html = svg.innerHTML;
        const nodePos = html.indexOf('vimipad-canvas-node');
        const badgePos = html.indexOf('vimipad-lock-badges');
        expect(nodePos).toBeGreaterThan(-1);
        expect(badgePos).toBeGreaterThan(-1);
        // The badge layer appears later in document order, so it paints on top.
        expect(badgePos).toBeGreaterThan(nodePos);
    });
});

describe('Container label and title bar (R9)', () => {
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

    test('an empty-label container hides the grey title bar (transparent fill) and shows no fallback text', () => {
        render(root, baseState({
            containers: [{stableid: 'c1', type: 'group', label: '', geometryjson: box(100, 100, 200, 150)}],
        }));
        const titleBar = container.querySelector('.vimipad-container-title') as SVGRectElement;
        expect(titleBar.getAttribute('fill')).toBe('transparent');
        // No "Containers" fallback text is rendered.
        const label = container.querySelector('.vimipad-container-label');
        expect((label?.textContent ?? '')).toBe('');
    });

    test('a labelled container shows the grey title bar and its label', () => {
        render(root, baseState({
            containers: [{stableid: 'c1', type: 'group', label: 'Group X', geometryjson: box(100, 100, 200, 150)}],
        }));
        const titleBar = container.querySelector('.vimipad-container-title') as SVGRectElement;
        expect(titleBar.getAttribute('fill')).not.toBe('transparent');
        const label = container.querySelector('.vimipad-container-label');
        expect(label?.textContent).toBe('Group X');
    });

    test('a locked container shows a lock badge in the overlay layer', () => {
        render(root, baseState({
            containers: [{
                stableid: 'c1', type: 'group', label: 'G', geometryjson: box(100, 100, 200, 150),
                metadatajson: JSON.stringify({locked: true}),
            }],
        }));
        const layer = container.querySelector('.vimipad-lock-badges')!;
        expect(layer.querySelectorAll('.vimipad-lock-badge').length).toBe(1);
    });
});
