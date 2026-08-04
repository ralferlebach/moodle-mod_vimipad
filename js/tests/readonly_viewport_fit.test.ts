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
 * Regression test for the journal/revision state replay: in read-only views the
 * canvas must fit its viewport to the actual content, so nodes placed anywhere
 * on the large canvas by the saved layout are visible (not clipped) and
 * containers appear at their true position — rather than being cut off by the
 * fixed, canvas-centred starting viewport.
 *
 * @module     mod_vimipad/tests/readonly_viewport_fit
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {RevisionViewer} from '../src/components/RevisionViewer';
import {RevisionPlayer} from '../src/components/RevisionPlayer';
import {ApiClient} from '../src/api/service';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (k: string): string => k;
const N1 = 'node_00000000000a';
const N2 = 'node_00000000000b';
const C1 = 'cont_00000000000c';

/** Read the rendered SVG viewBox as [x, y, w, h]. */
function viewBox(container: HTMLElement): [number, number, number, number] {
    const raw = container.querySelector('svg')?.getAttribute('viewBox') ?? '';
    const [x, y, w, h] = raw.split(' ').map(Number);
    return [x, y, w, h];
}

/** Whether a canvas point lies within the current viewBox. */
function inside(vb: [number, number, number, number], x: number, y: number): boolean {
    return x >= vb[0] && x <= vb[0] + vb[2] && y >= vb[1] && y <= vb[1] + vb[3];
}

describe('read-only canvas fits the viewport to content (journal replay)', () => {
    test('RevisionViewer frames nodes placed off the default window', async () => {
        // Nodes recorded far from the canvas centre (which is 1200,800). With the
        // old fixed viewport these would be clipped.
        const layoutjson = JSON.stringify({v: 1, pos: {[N1]: {x: 300, y: 250}, [N2]: {x: 520, y: 380}}});
        const payload = {
            workspaceid: 1, revision: 3, locked: 1, profile: 'conceptmap', formconfig: undefined,
            layoutjson,
            nodes: [
                {stableid: N1, type: 'concept', label: 'Alpha'},
                {stableid: N2, type: 'concept', label: 'Beta'},
            ],
            relations: [],
            containers: [
                {stableid: C1, type: 'group', label: 'Box',
                    geometryjson: JSON.stringify({x: 250, y: 200, w: 400, h: 300})},
            ],
            collab: {},
        };
        const api = new ApiClient(async (m: string) =>
            m === 'mod_vimipad_get_revision_state' ? payload : {}, 7, true);

        const container = document.createElement('div');
        document.body.appendChild(container);
        const root: Root = createRoot(container);
        await act(async () => {
            root.render(React.createElement(RevisionViewer, {api, workspaceid: 1, revision: 3, t}));
        });
        await act(async () => { await Promise.resolve(); });

        const vb = viewBox(container);
        expect(inside(vb, 300, 250)).toBe(true); // Alpha
        expect(inside(vb, 520, 380)).toBe(true); // Beta
        expect(inside(vb, 450, 350)).toBe(true); // container centre

        act(() => root.unmount());
        container.remove();
    });

    test('RevisionPlayer frames the reconstructed content at the shown revision', async () => {
        const op = (rev: number, type: string, payload: object) =>
            ({revision: rev, operationtype: type, payloadjson: JSON.stringify(payload)});
        const ops = [
            op(1, 'node_create', {stableid: N1, type: 'concept', label: 'Alpha'}),
            op(2, 'node_create', {stableid: N2, type: 'concept', label: 'Beta'}),
            op(3, 'container_create', {stableid: C1, label: 'Box',
                geometryjson: JSON.stringify({x: 300, y: 200, w: 250, h: 180})}),
            op(4, 'container_update', {stableid: C1,
                geometryjson: JSON.stringify({x: 800, y: 600, w: 250, h: 180})}),
        ];
        const live = {
            workspaceid: 1, revision: 4, locked: 0, profile: 'conceptmap', formconfig: undefined,
            layoutjson: '',
            nodes: [{stableid: N1, type: 'concept', label: 'Alpha'}, {stableid: N2, type: 'concept', label: 'Beta'}],
            relations: [],
            containers: [{stableid: C1, type: 'group', label: 'Box',
                geometryjson: JSON.stringify({x: 800, y: 600, w: 250, h: 180})}],
            collab: {},
        };
        const layoutHist = {history: [
            {revision: 4, layoutjson: JSON.stringify({v: 1, pos: {[N1]: {x: 450, y: 350}, [N2]: {x: 950, y: 450}}})},
        ]};
        const api = new ApiClient(async (m: string, args: Record<string, unknown>) => {
            if (m === 'mod_vimipad_get_workspace') {
                return live;
            }
            if (m === 'mod_vimipad_get_layout_history') {
                return layoutHist;
            }
            if (m === 'mod_vimipad_get_operations') {
                const since = (args.sincerevision as number) ?? 0;
                return {operations: ops.filter(o => o.revision > since), hasmore: false, nextrevision: 0};
            }
            return {};
        }, 7, true);

        const container = document.createElement('div');
        document.body.appendChild(container);
        const root: Root = createRoot(container);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 4, t}));
        });
        for (let i = 0; i < 6; i++) {
            await act(async () => { await Promise.resolve(); });
        }

        const vb = viewBox(container);
        expect(inside(vb, 450, 350)).toBe(true); // Alpha at its recorded position
        expect(inside(vb, 950, 450)).toBe(true); // Beta
        expect(inside(vb, 925, 690)).toBe(true); // moved container centre

        act(() => root.unmount());
        container.remove();
    });
});
