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
 * Tests for the optional Mercure push client: it wakes a poll on each new
 * revision event and is a safe no-op when push is not configured.
 *
 * @module     mod_vimipad/tests/push_client
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {PushClient, pushAvailable, EventSourceLike} from '../src/collab/push_client';

class FakeES implements EventSourceLike {
    public onmessage: ((ev: {data: string}) => void) | null = null;
    public onerror: ((ev: unknown) => void) | null = null;
    public closed = false;
    public constructor(public readonly url: string) {}
    public emit(data: string): void {
        this.onmessage?.({data});
    }
    public close(): void {
        this.closed = true;
    }
}

const full = {pushenabled: 1, pushendpoint: 'https://hub.example/.well-known/mercure',
    pushtopic: 'vimipad/workspace/7', pushtoken: 'tok'};

describe('pushAvailable', () => {
    test('requires enabled + endpoint + topic + token', () => {
        expect(pushAvailable(full)).toBe(true);
        expect(pushAvailable({...full, pushenabled: 0})).toBe(false);
        expect(pushAvailable({...full, pushendpoint: ''})).toBe(false);
        expect(pushAvailable({...full, pushtopic: ''})).toBe(false);
        expect(pushAvailable({...full, pushtoken: ''})).toBe(false);
        expect(pushAvailable(undefined)).toBe(false);
    });
});

describe('PushClient', () => {
    test('subscribes to the topic, sets the auth cookie, and wakes on new revisions', () => {
        let made: FakeES | null = null;
        const cookies: string[] = [];
        const woken: number[] = [];
        const client = new PushClient(
            full,
            (r) => woken.push(r),
            (url) => { made = new FakeES(url); return made; },
            (v) => cookies.push(v)
        );
        client.start();

        expect(made).not.toBeNull();
        const es = made as unknown as FakeES;
        expect(es.url).toContain('topic=vimipad%2Fworkspace%2F7');
        expect(cookies[0]).toContain('mercureAuthorization=tok');

        es.emit(JSON.stringify({revision: 5}));
        es.emit(JSON.stringify({revision: 5})); // not newer -> ignored
        es.emit(JSON.stringify({revision: 8}));
        es.emit(JSON.stringify({revision: 3})); // stale -> ignored
        expect(woken).toEqual([5, 8]);

        client.stop();
        expect(es.closed).toBe(true);
    });

    test('is a no-op when push is unavailable (factory never called)', () => {
        let called = false;
        const client = new PushClient(
            {...full, pushenabled: 0},
            () => undefined,
            () => { called = true; return new FakeES('x'); },
            () => undefined
        );
        client.start();
        expect(called).toBe(false);
    });

    test('malformed event data does not wake or throw', () => {
        let made: FakeES | null = null;
        const woken: number[] = [];
        const client = new PushClient(full, (r) => woken.push(r),
            (url) => { made = new FakeES(url); return made; }, () => undefined);
        client.start();
        (made as unknown as FakeES).emit('not json');
        expect(woken).toEqual([]);
    });
});
