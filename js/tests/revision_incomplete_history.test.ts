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
 * The single-revision journal viewer must not show an empty canvas when the
 * op-log is incomplete (elements predate the log). It then falls back to the
 * live map with a notice — like the player — while still showing a faithful
 * reconstruction when the history is complete.
 *
 * @module     mod_vimipad/tests/revision_incomplete_history
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {RevisionViewer} from '../src/components/RevisionViewer';
import {ApiClient} from '../src/api/service';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;
const t = (k: string): string => k;

const N1 = 'node_00000000000a';
const N2 = 'node_00000000000b';

const liveNodes = [
    {stableid: N1, type: 'concept', label: 'Alpha', content: '', contentformat: 1, metadatajson: ''},
    {stableid: N2, type: 'concept', label: 'Beta', content: '', contentformat: 1, metadatajson: ''},
];
const live = {
    workspaceid: 1, revision: 5, locked: 0, profile: 'conceptmap', formconfig: undefined,
    layoutjson: JSON.stringify({v: 1, pos: {[N1]: {x: 300, y: 300}, [N2]: {x: 500, y: 320}}}),
    nodes: liveNodes, relations: [], containers: [], collab: {},
};

async function mountViewer(getRevisionState: (rev: number) => unknown): Promise<HTMLElement> {
    const api = new ApiClient(async (m: string, args: Record<string, unknown>) => {
        if (m === 'mod_vimipad_get_workspace') {
            return live;
        }
        if (m === 'mod_vimipad_get_revision_state') {
            return getRevisionState((args.revision as number) ?? 0);
        }
        return {};
    }, 7, true);
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root: Root = createRoot(container);
    await act(async () => {
        root.render(React.createElement(RevisionViewer, {api, workspaceid: 1, revision: 3, t}));
    });
    for (let i = 0; i < 6; i++) {
        await act(async () => { await Promise.resolve(); });
    }
    (container as unknown as {__root: Root}).__root = root;
    return container;
}

describe('RevisionViewer incomplete-history fallback', () => {
    test('incomplete op-log: falls back to the live map with a notice', async () => {
        // Reconstruction yields NO nodes (create-ops predate the log), while the
        // live map has two. The viewer must show the live nodes, not an empty canvas.
        const container = await mountViewer(() => ({
            ...live, nodes: [], relations: [], containers: [],
        }));
        const nodeGroups = container.querySelectorAll('g.vimipad-canvas-node');
        const notice = container.querySelector('.vimipad-revision-incomplete');
        expect(nodeGroups.length).toBe(2);
        expect(notice).not.toBeNull();
        act(() => (container as unknown as {__root: Root}).__root.unmount());
        container.remove();
    });

    test('complete op-log: shows the faithful reconstruction with no notice', async () => {
        // Reconstruction reproduces the live map (fingerprint matches) -> complete.
        const container = await mountViewer(() => ({...live}));
        const nodeGroups = container.querySelectorAll('g.vimipad-canvas-node');
        const notice = container.querySelector('.vimipad-revision-incomplete');
        expect(nodeGroups.length).toBe(2);
        expect(notice).toBeNull();
        act(() => (container as unknown as {__root: Root}).__root.unmount());
        container.remove();
    });
});
