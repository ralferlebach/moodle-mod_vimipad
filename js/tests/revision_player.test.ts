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
import {EditorState} from '../src/store/reducer';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

const t = (key: string): string => key;

/** A transport that records which revisions were requested. */
function makeApi(requested: number[]): ApiClient {
    const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
        if (method === 'mod_vimipad_get_revision_state') {
            requested.push(args.revision as number);
            return {
                workspaceid: 1, revision: args.revision, locked: 1, profile: 'conceptmap',
                layoutjson: '', nodes: [], relations: [],
            };
        }
        if (method === 'mod_vimipad_get_workspace') {
            // Live state matches the (empty) reconstruction: history complete.
            return {
                workspaceid: 1, revision: 3, locked: 1, profile: 'conceptmap',
                layoutjson: '', nodes: [], relations: [], containers: [],
            };
        }
        return {};
    };
    return new ApiClient(transport, 42, true);
}

describe('RevisionPlayer', () => {
    let container: HTMLDivElement;
    let root: Root;

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

    test('loads the target (max) revision on mount', async () => {
        const requested: number[] = [];
        const api = makeApi(requested);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 5, t}));
        });
        // Starts at the end of the timeline.
        expect(requested).toContain(5);
        expect(container.querySelector('.vimipad-revision-player')).not.toBeNull();
    });

    test('the scrubber jumps to a chosen revision', async () => {
        const requested: number[] = [];
        const api = makeApi(requested);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 5, t}));
        });

        const scrubber = container.querySelector('.vimipad-revision-scrubber') as HTMLInputElement;
        expect(scrubber).not.toBeNull();

        await act(async () => {
            // Simulate moving the scrubber to revision 2.
            const setter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype, 'value'
            )?.set;
            setter?.call(scrubber, '2');
            scrubber.dispatchEvent(new Event('input', {bubbles: true}));
        });

        expect(requested).toContain(2);
    });

    test('play advances the revision over time and stops at the end', async () => {
        const requested: number[] = [];
        const api = makeApi(requested);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 3, t}));
        });

        // Move to the start, then play.
        const scrubber = container.querySelector('.vimipad-revision-scrubber') as HTMLInputElement;
        await act(async () => {
            const setter = Object.getOwnPropertyDescriptor(
                window.HTMLInputElement.prototype, 'value'
            )?.set;
            setter?.call(scrubber, '1');
            scrubber.dispatchEvent(new Event('input', {bubbles: true}));
        });

        const playBtn = container.querySelector('button') as HTMLButtonElement;
        await act(async () => {
            playBtn.click();
        });

        // Advance the animation timers; each tick moves one revision forward.
        await act(async () => {
            jest.advanceTimersByTime(3000);
        });

        // The playback reached the final revision.
        expect(requested).toContain(3);
    });

    test('renders nodes, relations and containers from the reconstructed state', async () => {
        // A transport returning a populated state (2 nodes, 1 relation, 1
        // container) — the exact case the journal replay must show fully.
        const transport = async (method: string): Promise<unknown> => {
            if (method === 'mod_vimipad_get_revision_state') {
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
                    containers: [
                        {stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                            geometryjson: '{"x":10,"y":10,"w":100,"h":100}', metadatajson: ''},
                    ],
                };
            }
            return {};
        };
        const api = new ApiClient(transport, 42, true);
        await act(async () => {
            root.render(React.createElement(RevisionPlayer, {api, workspaceid: 1, maxRevision: 4, t}));
        });
        await act(async () => { await Promise.resolve(); });

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

    test('isHistoryIncomplete is true only when the live map has more elements', () => {
        const live = {
            workspaceid: 5, nodes: [{}, {}], relations: [{}], containers: [{}],
        } as unknown as EditorState;
        const full = {
            workspaceid: 5, nodes: [{}, {}], relations: [{}], containers: [{}],
        } as unknown as EditorState;
        const partial = {
            workspaceid: 5, nodes: [], relations: [], containers: [{}],
        } as unknown as EditorState;
        // Same counts: complete.
        expect(isHistoryIncomplete(live, full)).toBe(false);
        // Reconstruction has fewer: incomplete.
        expect(isHistoryIncomplete(live, partial)).toBe(true);
        // Different workspace: never compared.
        expect(isHistoryIncomplete(live, {...partial, workspaceid: 9} as EditorState)).toBe(false);
    });

    test('falls back to the live state with a hint when history is incomplete', async () => {
        // Reconstruction yields only a container; the live map also has nodes
        // and a relation — the exact "old data" case.
        const transport = async (method: string): Promise<unknown> => {
            if (method === 'mod_vimipad_get_revision_state') {
                return {
                    workspaceid: 1, revision: 4, locked: 1, profile: 'conceptmap', layoutjson: '',
                    nodes: [], relations: [],
                    containers: [{stableid: 'cont_aaaaaaaaaaaa', type: 'group', label: 'C',
                        geometryjson: '{"x":10,"y":10,"w":100,"h":100}', metadatajson: ''}],
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
        await act(async () => { await Promise.resolve(); await Promise.resolve(); });

        // The hint is shown and the playback controls are hidden.
        expect(container.textContent).toContain('revision:historyincomplete');
        expect(container.querySelector('.vimipad-revision-scrubber')).toBeNull();
        // The live map is shown in full: 2 nodes + 1 container.
        expect(container.querySelectorAll('.vimipad-canvas-node').length).toBe(2);
        expect(container.querySelectorAll('.vimipad-canvas-container').length).toBe(1);
    });
});
