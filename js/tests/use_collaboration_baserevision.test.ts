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
 * Regression test for the container-drift-on-(re)load bug.
 *
 * The poll loop must start its cursor at the revision the initial workspace load
 * already applied. If it starts at 0, the first fetch pulls the whole op-log and
 * re-applies every historical container move/resize, so containers visibly
 * wander through their edit history on every (re)load.
 *
 * @module     mod_vimipad/tests/use_collaboration_baserevision
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {ApiClient} from '../src/api/service';
import {useCollaboration} from '../src/collab/use_collaboration';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

describe('useCollaboration base revision', () => {
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

    test('initialises the poll cursor at the loaded revision, before starting', () => {
        const calls: string[] = [];
        let setRevisionArg = -1;
        const poll = {
            setRevision: (r: number): void => { setRevisionArg = r; calls.push('setRevision'); },
            start: (): void => { calls.push('start'); },
            stop: (): void => { /* no-op */ },
            pollOnce: async (): Promise<void> => { /* no-op */ },
        };
        const lock = {heartbeat: async (): Promise<void> => { /* no-op */ }};
        const api = {
            createPollClient: (): unknown => poll,
            createLockClient: (): unknown => lock,
        } as unknown as ApiClient;

        function Harness(): React.ReactElement {
            useCollaboration(
                api,
                7,
                1,
                undefined,
                () => { /* onOperations */ },
                undefined,
                undefined,
                undefined,
                42
            );
            return React.createElement('div');
        }

        act(() => { root.render(React.createElement(Harness)); });

        // The cursor is set to the loaded revision (42), not 0, and it is set
        // BEFORE the loop starts — so the first fetch asks only for newer ops.
        expect(setRevisionArg).toBe(42);
        expect(calls).toEqual(['setRevision', 'start']);
    });
});
