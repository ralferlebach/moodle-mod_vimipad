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
 * Tests for the RevisionPlayer animated-replay component.
 *
 * @module     mod_vimipad/tests/revision_player
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {RevisionPlayer, elementCount, isHistoryIncomplete} from '../src/components/RevisionPlayer';
import {ApiClient} from '../src/api/service';
import {reconstructAt} from '../src/graph/reconstruct';
import {EditorState} from '../src/store/reducer';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

/**
 * A transport that serves the op-log via get_operations (the player now
 * reconstructs frames on the client) and records each method call so tests can
 * assert the single-fetch behaviour.
 */
function makeApi(calls: string[], ops: Array<[string, Record<string, unknown>]> = []): ApiClient {
    const operations = ops.map(([operationtype, payload], i) => ({
        revision: i + 1, operationtype, payloadjson: JSON.stringify(payload),
    }));
    // The live state is the reconstruction at the final revision, so the
    // stable-id sets match and history is (correctly) complete.
    const finalState = reconstructAt(operations, operations.length);
    const transport = async (method: string): Promise<unknown> => {
        calls.push(method);
        if (method === 'mod_vimipad_get_operations') {
            return {workspaceid: 1, fromrevision: 1, torevision: operations.length,
                operations, hasmore: false, nextrevision: 0};
        }
        if (method === 'mod_vimipad_get_workspace') {
            return {
                workspaceid: 1, revision: operations.length, locked: 1, profile: 'conceptmap',
                layoutjson: '', nodes: finalState.nodes, relations: finalState.relations,
                containers: finalState.containers,
            };
        }
        return {};
    };
    return new ApiClient(transport, 42, true);
}

/** Flush several rounds of microtasks so the async mount chain settles. */
async function flush(rounds = 8): Promise<void> {
    await act(async () => {
        for (let i = 0; i < rounds; i++) {
            // eslint-disable-next-line no-await-in-loop
            await Promise.resolve();
        }
    });
}

describe('RevisionPlayer', () => {
    let container!: HTMLDivElement;
    let root!: Root;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
        root = createRoot(container);
        jest.useFakeTimers();
    });

    afterEach(() => {
        act(() => root.unmount());
        container.remove();
        jest.useRealTimers();
    });

    test('fetches the op-log once and reconstructs on the client', async () => {
        const calls: string[] = [];
        const api = makeApi(calls, [
            ['node_create', {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A'}],
            ['node_create', {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B'}],
            ['relation_create', {stableid: 'rel_aaaaaaaaaaaa', sourceid: 'node_aaaaaaaaaaaa',
                targetid: 'node_bbbbbbbbbbbb', type: 'link', label: 'r', direction: 1}],
        ]);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 3, t}));
        });
        await flush();

        // Exactly one op-log fetch, not one reconstruction per revision.
        const opcalls = calls.filter(c => c === 'mod_vimipad_get_operations');
        expect(opcalls.length).toBe(1);
        expect(calls).not.toContain('mod_vimipad_get_revision_state');
        expect(container.querySelector('.vimipad-revision-player')).not.toBeNull();
    });

    test('the scrubber jumps to a chosen revision', async () => {
        const calls: string[] = [];
        const api = makeApi(calls, [
            ['node_create', {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A'}],
            ['node_create', {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B'}],
        ]);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 2, t}));
        });
        await flush();

        const scrubber = container.querySelector('.vimipad-revision-scrubber') as HTMLInputElement;
        expect(scrubber).not.toBeNull();

        // At revision 1 only the first node exists; jumping shows frame 1.
        await act(async () => {
            const setter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype, 'value'
            )?.set;
            setter?.call(scrubber, '1');
            scrubber.dispatchEvent(new Event('input', {bubbles: true}));
        });
        await flush();

        expect(container.querySelectorAll('.vimipad-canvas-node').length).toBe(1);
    });

    test('play advances the revision over time and stops at the end', async () => {
        const calls: string[] = [];
        const api = makeApi(calls, [
            ['node_create', {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A'}],
            ['node_create', {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B'}],
            ['node_create', {stableid: 'node_cccccccccccc', type: 'concept', label: 'C'}],
        ]);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 3, t}));
        });
        await flush();

        // Move to the start, then play.
        const scrubber = container.querySelector('.vimipad-revision-scrubber') as HTMLInputElement;
        const setValue = (v: string): void => {
            const setter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype, 'value'
            )?.set;
            setter?.call(scrubber, v);
            scrubber.dispatchEvent(new Event('input', {bubbles: true}));
        };
        await act(async () => { setValue('1'); });
        await flush();
        // At revision 1 only the first node exists.
        expect(container.querySelectorAll('.vimipad-canvas-node').length).toBe(1);

        const playBtn = container.querySelector('button') as HTMLButtonElement;
        await act(async () => {
            playBtn.click();
        });
        // Drive the playback timer to the end; flush between ticks so each
        // cached frame renders.
        for (let i = 0; i < 4; i++) {
            await act(async () => {
                jest.advanceTimersByTime(1000);
                await Promise.resolve();
            });
        }

        // Playback reached the final revision: all three nodes are shown.
        expect(container.querySelectorAll('.vimipad-canvas-node').length).toBe(3);
    });

    test('renders nodes, relations and containers from the reconstructed state', async () => {
        // The op-log builds 2 nodes, 1 relation, 1 container; the live state
        // matches, so history is complete and the frames render fully.
        const built = {
            nodes: [
                {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A', content: '',
                    contentformat: 0, metadatajson: ''},
                {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B', content: '',
                    contentformat: 0, metadatajson: ''},
            ],
            relations: [
                {stableid: 'rel_aaaaaaaaaaaa', sourceid: 'node_aaaaaaaaaaaa',
                    targetid: 'node_bbbbbbbbbbbb', type: 'link', label: 'r', direction: 1, metadatajson: ''},
            ],
            containers: [
                {stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                    geometryjson: '{"x":10,"y":10,"w":100,"h":100}', metadatajson: ''},
            ],
        };
        const operations = ([
            ['node_create', {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A'}],
            ['node_create', {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B'}],
            ['relation_create', {stableid: 'rel_aaaaaaaaaaaa', sourceid: 'node_aaaaaaaaaaaa',
                targetid: 'node_bbbbbbbbbbbb', type: 'link', label: 'r', direction: 1}],
            ['container_create', {stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                geometryjson: '{"x":10,"y":10,"w":100,"h":100}'}],
        ] as Array<[string, Record<string, unknown>]>).map(([operationtype, payload], i) => ({
            revision: i + 1, operationtype, payloadjson: JSON.stringify(payload),
        }));
        const transport = async (method: string): Promise<unknown> => {
            if (method === 'mod_vimipad_get_operations') {
                return {workspaceid: 1, fromrevision: 1, torevision: 4, operations, hasmore: false, nextrevision: 0};
            }
            if (method === 'mod_vimipad_get_workspace') {
                return {workspaceid: 1, revision: 4, locked: 1, profile: 'conceptmap', layoutjson: '', ...built};
            }
            return {};
        };
        const api = new ApiClient(transport, 42, true);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 4, t}));
        });
        await flush();

        const nodes = container.querySelectorAll('.vimipad-canvas-node');
        const relations = container.querySelectorAll('.vimipad-canvas-relation');
        const containers = container.querySelectorAll('.vimipad-canvas-container');

        expect(nodes.length).toBe(2);
        expect(relations.length).toBeGreaterThanOrEqual(1);
        expect(containers.length).toBe(1);
    });

    test('elementCount sums nodes, relations and containers', () => {
        const s = {
            workspaceid: 1, revision: 1, locked: 1, profile: 'conceptmap', layoutjson: '',
            nodes: [{stableid: 'n1'}, {stableid: 'n2'}],
            relations: [{stableid: 'r1'}],
            containers: [{stableid: 'c1'}],
        } as unknown as EditorState;
        expect(elementCount(s)).toBe(4);
    });

    test('isHistoryIncomplete compares stable-id sets, not just counts', () => {
        const live = {
            workspaceid: 5,
            nodes: [{stableid: 'node_a'}, {stableid: 'node_b'}],
            relations: [{stableid: 'rel_a'}], containers: [{stableid: 'cont_a'}],
        } as unknown as EditorState;
        const full = {
            workspaceid: 5,
            nodes: [{stableid: 'node_b'}, {stableid: 'node_a'}],
            relations: [{stableid: 'rel_a'}], containers: [{stableid: 'cont_a'}],
        } as unknown as EditorState;
        const partial = {
            workspaceid: 5, nodes: [], relations: [], containers: [{stableid: 'cont_a'}],
        } as unknown as EditorState;
        // Same id sets (order-independent): complete.
        expect(isHistoryIncomplete(live, full)).toBe(false);
        // Reconstruction has fewer: incomplete.
        expect(isHistoryIncomplete(live, partial)).toBe(true);
        // Same COUNT but different ids (A+B vs A+C): still incomplete.
        const sameCountDifferentIds = {
            workspaceid: 5,
            nodes: [{stableid: 'node_a'}, {stableid: 'node_c'}],
            relations: [{stableid: 'rel_a'}], containers: [{stableid: 'cont_a'}],
        } as unknown as EditorState;
        expect(isHistoryIncomplete(live, sameCountDifferentIds)).toBe(true);

        // Same IDS but different CONTENT (a rename the replay is missing):
        // detected via the content fingerprint.
        const liveContent = {
            workspaceid: 5,
            nodes: [{stableid: 'node_a', type: 'concept', label: 'Neu'}],
            relations: [], containers: [],
        } as unknown as EditorState;
        const replayContent = {
            workspaceid: 5,
            nodes: [{stableid: 'node_a', type: 'concept', label: 'Alt'}],
            relations: [], containers: [],
        } as unknown as EditorState;
        expect(isHistoryIncomplete(liveContent, replayContent)).toBe(true);
        // Identical content: complete.
        expect(isHistoryIncomplete(liveContent, {...liveContent} as EditorState)).toBe(false);

        // Different workspace: never compared.
        expect(isHistoryIncomplete(live, {...partial, workspaceid: 9} as EditorState)).toBe(false);
    });

    test('truncated history: slider is clamped and a warning is shown', async () => {
        // A transport that always reports more pages, so the player's loop guard
        // truncates loading. Each page returns one node_create at an increasing
        // revision; loading stops well before `maxRevision`.
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            if (method === 'mod_vimipad_get_operations') {
                const from = (args.fromrevision as number) ?? 1;
                return {
                    workspaceid: 1, fromrevision: from, torevision: 100000,
                    operations: [{
                        revision: from, operationtype: 'node_create',
                        payloadjson: JSON.stringify({
                            stableid: 'node_' + String(from).padStart(12, '0'), type: 'concept', label: 'N' + from,
                        }),
                    }],
                    hasmore: true, nextrevision: from + 1,
                };
            }
            if (method === 'mod_vimipad_get_workspace') {
                // Live state differs (a full map); irrelevant once truncated.
                return {
                    workspaceid: 1, revision: 100000, locked: 1, profile: 'conceptmap',
                    layoutjson: '', nodes: [], relations: [], containers: [],
                };
            }
            return {};
        };
        const api = new ApiClient(transport, 42, true);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 100000, t}));
        });
        await flush(12);

        // The truncation warning is shown.
        expect(container.textContent).toContain('revision:historytruncated');
        // The scrubber max is clamped to the highest loaded revision, far below
        // the workspace's real maxRevision.
        const scrubber = container.querySelector('.vimipad-revision-scrubber') as HTMLInputElement;
        expect(scrubber).not.toBeNull();
        expect(Number(scrubber.max)).toBeLessThan(100000);
        expect(Number(scrubber.max)).toBeGreaterThan(0);
        // It never claims the (unfaithful) history is complete.
        expect(container.textContent).not.toContain('revision:historyincomplete');
    });

    test('falls back to the live state with a hint when history is incomplete', async () => {
        // Reconstruction yields only a container; the live map also has nodes
        // and a relation — the exact "old data" case.
        const transport = async (method: string): Promise<unknown> => {
            if (method === 'mod_vimipad_get_operations') {
                // Only a container-create is logged: reconstruction yields just
                // the container, while the live map (below) has more elements.
                return {
                    workspaceid: 1, fromrevision: 1, torevision: 1, hasmore: false, nextrevision: 0, operations: [{
                        revision: 1, operationtype: 'container_create',
                        payloadjson: JSON.stringify({stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                            geometryjson: '{"x":10,"y":10,"w":100,"h":100}'}),
                    }],
                };
            }
            if (method === 'mod_vimipad_get_workspace') {
                return {
                    workspaceid: 1, revision: 4, locked: 1, profile: 'conceptmap', layoutjson: '',
                    nodes: [
                        {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'A',
                            content: '', contentformat: 0, metadatajson: ''},
                        {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'B',
                            content: '', contentformat: 0, metadatajson: ''},
                    ],
                    relations: [
                        {stableid: 'rel_aaaaaaaaaaaa', sourceid: 'node_aaaaaaaaaaaa',
                            targetid: 'node_bbbbbbbbbbbb', type: 'link', label: 'r',
                            direction: 1, metadatajson: ''},
                    ],
                    containers: [{stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                        geometryjson: '{"x":10,"y":10,"w":100,"h":100}', metadatajson: ''}],
                };
            }
            return {};
        };
        const api = new ApiClient(transport, 42, true);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 4, t}));
        });
        await flush();

        // The hint is shown and the playback controls are hidden.
        expect(container.textContent).toContain('revision:historyincomplete');
        expect(container.querySelector('.vimipad-revision-scrubber')).toBeNull();
        // The live map is shown in full: 2 nodes + 1 container.
        expect(container.querySelectorAll('.vimipad-canvas-node').length).toBe(2);
        expect(container.querySelectorAll('.vimipad-canvas-container').length).toBe(1);
    });
});
