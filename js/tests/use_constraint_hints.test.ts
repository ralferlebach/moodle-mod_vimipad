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
 * Tests for the useConstraintHints hook (debounced, latest-wins, gated).
 *
 * @module     mod_vimipad/tests/use_constraint_hints
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {ApiClient} from '../src/api/service';
import {ConstraintStatus} from '../src/types';
import {HINT_DEBOUNCE_MS, useConstraintHints} from '../src/hooks/use_constraint_hints';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

function Harness(props: {api: ApiClient; ws: number; rev: number; enabled: boolean}): React.ReactElement {
    const status = useConstraintHints(props.api, props.ws, props.rev, props.enabled);
    return React.createElement('div', {id: 'out'}, status ? (status.satisfied ? 'ok' : 'violated') : 'none');
}

function fakeApi(fn: jest.Mock): ApiClient {
    return {getConstraintStatus: fn} as unknown as ApiClient;
}

const violated: ConstraintStatus = {
    configured: true, satisfied: false, messages: ['x'],
    requiredmissing: ['x'], forbiddenpresent: [], typeviolations: [],
};

describe('useConstraintHints', () => {
    let container: HTMLDivElement;
    let root: Root;

    beforeEach(() => {
        jest.useFakeTimers();
        container = document.createElement('div');
        document.body.appendChild(container);
        root = createRoot(container);
    });

    afterEach(() => {
        act(() => root.unmount());
        container.remove();
        jest.useRealTimers();
    });

    const render = (api: ApiClient, ws: number, rev: number, enabled: boolean): void => {
        act(() => {
            root.render(React.createElement(Harness, {api, ws, rev, enabled}));
        });
    };

    test('fetches once after the debounce and surfaces the status', async () => {
        const fn = jest.fn().mockResolvedValue(violated);
        const api = fakeApi(fn);

        render(api, 5, 1, true);
        expect(fn).not.toHaveBeenCalled();

        await act(async () => {
            jest.advanceTimersByTime(HINT_DEBOUNCE_MS);
        });
        expect(fn).toHaveBeenCalledTimes(1);
        expect(container.querySelector('#out')?.textContent).toBe('violated');
    });

    test('coalesces a burst of revision changes into a single fetch', async () => {
        const fn = jest.fn().mockResolvedValue(violated);
        const api = fakeApi(fn);

        render(api, 5, 1, true);
        render(api, 5, 2, true);
        render(api, 5, 3, true);
        await act(async () => {
            jest.advanceTimersByTime(HINT_DEBOUNCE_MS);
        });
        expect(fn).toHaveBeenCalledTimes(1);
    });

    test('does not fetch while disabled or before a workspace exists', async () => {
        const fn = jest.fn().mockResolvedValue(violated);
        const api = fakeApi(fn);

        render(api, 5, 1, false);
        render(api, 0, 1, true);
        await act(async () => {
            jest.advanceTimersByTime(HINT_DEBOUNCE_MS * 3);
        });
        expect(fn).not.toHaveBeenCalled();
    });
});
