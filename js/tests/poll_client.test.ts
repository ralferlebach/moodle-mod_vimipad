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

import {PollClient} from '../src/collab/poll_client';
import {PollResult} from '../src/types';

/** Build a poll result with sensible defaults. */
const result = (over: Partial<PollResult> = {}): PollResult => ({
    revision: 0,
    locked: 0,
    profile: 'conceptmap',
    operations: [],
    layoutjson: '',
    leases: [],
    ...over,
});

describe('PollClient', () => {
    test('calls poll_changes with the current known revision', async () => {
        const calls: Array<Record<string, unknown>> = [];
        const transport = jest.fn(async (_m: string, args: Record<string, unknown>) => {
            calls.push(args);
            return result({revision: 3});
        });
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
        });
        client.setRevision(3);
        await client.pollOnce();
        expect(transport).toHaveBeenCalledWith('mod_vimipad_poll_changes',
            {cmid: 1, workspaceid: 2, sincerevision: 3, layoutsince: 0});
    });

    test('emits operations from the poll to the onOperations callback', async () => {
        const ops = [{revision: 4, operationtype: 'node_create', payloadjson: '{}', userid: 9}];
        const transport = jest.fn(async () => result({revision: 4, operations: ops}));
        const received: unknown[] = [];
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onOperations: (o) => received.push(...o),
        });
        await client.pollOnce();
        expect(received).toHaveLength(1);
        expect(client.getRevision()).toBe(4);
    });

    test('with a batch backlog, advances only to the last op and polls at min', async () => {
        const ops = [
            {revision: 11, operationtype: 'node_create', payloadjson: '{}', userid: 9},
            {revision: 12, operationtype: 'node_create', payloadjson: '{}', userid: 9},
        ];
        // The workspace is at revision 30 but only ops up to 12 were returned.
        const transport = jest.fn(async () => result({revision: 30, operations: ops, hasmore: true}));
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 500, max: 10000, base: 1000, adaptive: true},
        });
        client.setRevision(10);
        await client.pollOnce();
        // Must not skip ahead to 30; the next poll continues from the last op.
        expect(client.getRevision()).toBe(12);
        expect(client.getInterval()).toBe(500);
    });

    test('emits presence (leases) to the onPresence callback', async () => {
        const leases = [{targettype: 'node', targetstableid: 'n1', userid: 7, timeexpires: 999}];
        const transport = jest.fn(async () => result({leases}));
        let seen: unknown[] = [];
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onPresence: (l) => { seen = l; },
        });
        await client.pollOnce();
        expect(seen).toEqual(leases);
    });

    test('emits workspace state (lock/profile) to onWorkspaceState', async () => {
        const transport = jest.fn(async () => result({locked: 1, profile: 'mindmap'}));
        let seen: {locked: number; profile: string} | null = null;
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onWorkspaceState: (s) => { seen = s; },
        });
        await client.pollOnce();
        expect(seen).toEqual({locked: 1, profile: 'mindmap'});
    });

    test('emits the layout json to the onLayout callback', async () => {
        const layoutjson = JSON.stringify({v: 1, pos: {n1: {x: 5, y: 6}}, size: {n1: {w: 90, h: 40}}});
        const transport = jest.fn(async () => result({layoutjson}));
        let seen = '';
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onLayout: (l) => { seen = l; },
        });
        await client.pollOnce();
        expect(seen).toBe(layoutjson);
    });

    test('skips an unchanged layout and passes layoutsince back', async () => {
        let call = 0;
        const transport = jest.fn(async (_m: string, _args: Record<string, unknown>) => {
            call++;
            return call === 1
                ? result({layoutjson: '{"v":1}', layouttime: 555})
                : result({layoutjson: '', layouttime: 555});
        });
        const layouts: string[] = [];
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onLayout: (l) => layouts.push(l),
        });
        await client.pollOnce();
        await client.pollOnce();
        // Only the first (changed) layout is applied; the second poll sends the
        // known layout time so the server can skip re-sending it.
        expect(layouts).toEqual(['{"v":1}']);
        expect(transport).toHaveBeenLastCalledWith('mod_vimipad_poll_changes',
            {cmid: 1, workspaceid: 2, sincerevision: 0, layoutsince: 555});
    });

    test('lengthens the interval after an empty poll', async () => {
        const transport = jest.fn(async () => result());
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
        });
        const before = client.getInterval();
        await client.pollOnce();
        expect(client.getInterval()).toBeGreaterThan(before);
    });

    test('shortens the interval after a poll with changes', async () => {
        const transport = jest.fn(async () => result({operations: [
            {revision: 1, operationtype: 'node_create', payloadjson: '{}', userid: 1},
        ]}));
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 4000, adaptive: true},
        });
        client.setInterval(4000);
        await client.pollOnce();
        expect(client.getInterval()).toBeLessThan(4000);
    });

    test('does not advance revision when the poll returns an older one', async () => {
        const transport = jest.fn(async () => result({revision: 2}));
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
        });
        client.setRevision(5);
        await client.pollOnce();
        expect(client.getRevision()).toBe(5);
    });

    test('swallows transport errors so the loop can continue', async () => {
        const transport = jest.fn(async () => { throw new Error('network'); });
        let errored = false;
        const client = new PollClient({
            cmid: 1, workspaceid: 2, transport,
            adaptive: {min: 1000, max: 10000, base: 1000, adaptive: true},
            onError: () => { errored = true; },
        });
        await expect(client.pollOnce()).resolves.toBeUndefined();
        expect(errored).toBe(true);
    });
});
