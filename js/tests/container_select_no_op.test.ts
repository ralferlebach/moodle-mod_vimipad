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
 * Regression test for the container format menu "spinning" bug.
 *
 * Selecting a container starts a zero-distance move drag through its title bar.
 * Committing that as a container_update on pointer-up bumped the workspace
 * revision on every select-click; the next edit (e.g. picking a shape in the
 * format menu) then ran against a stale revision, was rejected, and forced a
 * full reload that dropped the selection — the menu appeared to jump around and
 * shapes never stuck. A select-click that does not move the box must therefore
 * emit no operation; only a real move or resize may.
 *
 * @module     mod_vimipad/tests/container_select_no_op
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

/** Dispatch a pointer event of the given type at client coordinates. */
function pointer(el: Element, type: string, clientX: number, clientY: number): void {
    act(() => {
        el.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, clientX, clientY}));
    });
}

describe('Container select-click emits no operation (menu "spinning" fix)', () => {
    let host: HTMLDivElement;
    let root: Root;
    let updates: Array<{stableid: string; geometryjson: string}>;

    beforeEach(() => {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
        updates = [];
        // jsdom returns an all-zero rect by default, which makes the client->SVG
        // mapping degenerate; give the SVG a finite, non-degenerate rect so the
        // drag delta is well defined.
        (Element.prototype as unknown as {getBoundingClientRect: () => DOMRect}).getBoundingClientRect =
            () => ({left: 0, top: 0, right: 800, bottom: 600, width: 800, height: 600, x: 0, y: 0} as DOMRect);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    function render(): void {
        act(() => {
            root.render(React.createElement(CanvasView, {
                state: baseState({
                    containers: [{
                        stableid: 'c1', type: 'container', label: 'Container A',
                        geometryjson: box(100, 100, 300, 200), metadatajson: '',
                    }],
                }),
                layout: {},
                profile: 'conceptmap',
                sizes: {},
                disabled: false,
                onNodeMoved: noop,
                onUpdateContainer: (stableid: string, geometryjson: string) => {
                    updates.push({stableid, geometryjson});
                },
                t,
            }));
        });
    }

    function titleBar(): Element {
        const el = host.querySelector('.vimipad-container-title');
        if (!el) {
            throw new Error('container title bar not found');
        }
        return el;
    }

    test('a pointer down+up with no movement commits nothing', () => {
        render();
        const title = titleBar();
        pointer(title, 'pointerdown', 200, 110);
        pointer(title, 'pointerup', 200, 110);
        expect(updates).toHaveLength(0);
    });

    test('a pointer down+move+up commits exactly one geometry update', () => {
        render();
        const title = titleBar();
        pointer(title, 'pointerdown', 200, 110);
        pointer(title, 'pointermove', 260, 160);
        pointer(title, 'pointerup', 260, 160);
        expect(updates).toHaveLength(1);
        expect(updates[0].stableid).toBe('c1');
    });
});
