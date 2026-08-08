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
 * Tests that the RevisionViewer renders a past revision with its recorded node
 * topology (R10) rather than a recomputed auto-layout.
 *
 * @module     mod_vimipad/tests/revision_topology
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {RevisionViewer} from '../src/components/RevisionViewer';
import {ApiClient} from '../src/api/service';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

/** A fake transport returning a revision state with a recorded layout. */
function makeApi(layoutjson: string): ApiClient {
    const transport = async (method: string): Promise<unknown> => {
        if (method === 'mod_vimipad_get_revision_state') {
            return {
                workspaceid: 1, revision: 3, locked: 1, profile: 'conceptmap', formconfig: undefined,
                layoutjson,
                nodes: [
                    {stableid: 'a', type: 'concept', label: 'A'},
                    {stableid: 'b', type: 'concept', label: 'B'},
                ],
                relations: [],
                containers: [],
                collab: {},
            };
        }
        return {};
    };
    return new ApiClient(transport, 7, true);
}

/** Read a node group's translate() position from the rendered SVG. */
function nodePos(container: HTMLElement, label: string): {x: number; y: number} | null {
    const groups = Array.from(container.querySelectorAll('g.vimipad-canvas-node'));
    for (const g of groups) {
        if ((g.textContent ?? '').includes(label)) {
            const tr = g.getAttribute('transform') ?? '';
            const m = tr.match(/translate\(([-\d.]+),\s*([-\d.]+)\)/);
            if (m) {
                return {x: parseFloat(m[1]), y: parseFloat(m[2])};
            }
        }
    }
    return null;
}

describe('RevisionViewer topology (R10)', () => {
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

    test('renders nodes at their recorded historical positions', async () => {
        const layoutjson = JSON.stringify({v: 1, pos: {a: {x: 111, y: 222}, b: {x: 333, y: 444}}});
        const api = makeApi(layoutjson);
        await act(async () => {
            root.render(React.createElement(RevisionViewer, {api, workspaceid: 1, revision: 3, t}));
        });
        // Let the async state load settle.
        await act(async () => { await Promise.resolve(); });

        const posA = nodePos(container, 'A');
        const posB = nodePos(container, 'B');
        expect(posA).toEqual({x: 111, y: 222});
        expect(posB).toEqual({x: 333, y: 444});
    });

    test('falls back to auto-layout when no historical layout was recorded', async () => {
        const api = makeApi('');
        await act(async () => {
            root.render(React.createElement(RevisionViewer, {api, workspaceid: 1, revision: 3, t}));
        });
        await act(async () => { await Promise.resolve(); });

        // With no recorded layout, nodes are still placed (auto-layout), not at
        // the historical coordinates above.
        const posA = nodePos(container, 'A');
        expect(posA).not.toBeNull();
        expect(posA).not.toEqual({x: 111, y: 222});
    });
});
