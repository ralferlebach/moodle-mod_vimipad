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

import {LockClient} from '../src/collab/lock_client';

describe('LockClient', () => {
    test('acquire calls the acquire_lock function and reports success', async () => {
        const transport = jest.fn(async () => ({acquired: true, userid: 1, timeexpires: 100}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        const ok = await client.acquire('node', 'n1');
        expect(ok).toBe(true);
        expect(transport).toHaveBeenCalledWith('mod_vimipad_acquire_lock',
            {cmid: 1, workspaceid: 2, targettype: 'node', targetstableid: 'n1'});
    });

    test('acquire reports failure when the element is held by someone else', async () => {
        const transport = jest.fn(async () => ({acquired: false, userid: 99, timeexpires: 100}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        expect(await client.acquire('node', 'n1')).toBe(false);
    });

    test('release calls the release_lock function', async () => {
        const transport = jest.fn(async () => ({status: true}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        await client.release('node', 'n1');
        expect(transport).toHaveBeenCalledWith('mod_vimipad_release_lock',
            {cmid: 1, workspaceid: 2, targettype: 'node', targetstableid: 'n1'});
    });

    test('renew calls the renew_lock function', async () => {
        const transport = jest.fn(async () => ({acquired: true, userid: 1, timeexpires: 200}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        expect(await client.renew('node', 'n1')).toBe(true);
        expect(transport).toHaveBeenCalledWith('mod_vimipad_renew_lock',
            {cmid: 1, workspaceid: 2, targettype: 'node', targetstableid: 'n1'});
    });

    test('a held lock is renewed by the heartbeat tick', async () => {
        const transport = jest.fn(async () => ({acquired: true, userid: 1, timeexpires: 200}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        await client.acquire('node', 'n1');
        transport.mockClear();
        await client.heartbeat();
        expect(transport).toHaveBeenCalledWith('mod_vimipad_renew_lock',
            {cmid: 1, workspaceid: 2, targettype: 'node', targetstableid: 'n1'});
    });

    test('after release the heartbeat no longer renews', async () => {
        const transport = jest.fn(async () => ({acquired: true, userid: 1, timeexpires: 200, status: true}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        await client.acquire('node', 'n1');
        await client.release('node', 'n1');
        transport.mockClear();
        await client.heartbeat();
        expect(transport).not.toHaveBeenCalled();
    });

    test('tracks whether the client currently holds a given element', async () => {
        const transport = jest.fn(async () => ({acquired: true, userid: 1, timeexpires: 200}));
        const client = new LockClient({cmid: 1, workspaceid: 2, transport});
        expect(client.holds('node', 'n1')).toBe(false);
        await client.acquire('node', 'n1');
        expect(client.holds('node', 'n1')).toBe(true);
    });

    test('swallows errors and treats acquire failure as not held', async () => {
        const transport = jest.fn(async () => { throw new Error('network'); });
        let errored = false;
        const client = new LockClient({
            cmid: 1, workspaceid: 2, transport,
            onError: () => { errored = true; },
        });
        expect(await client.acquire('node', 'n1')).toBe(false);
        expect(errored).toBe(true);
        expect(client.holds('node', 'n1')).toBe(false);
    });
});
