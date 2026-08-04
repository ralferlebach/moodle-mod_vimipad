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
 * Tests that the lock-mode preview toggle is threaded to the server on every
 * mutating call. The API client carries an `enforceLocks` flag (kept in sync
 * with the editor's lock-mode button); when on, apply-operation and
 * save-layout must send `enforcelocks: true` so the server binds a managing
 * user to the template locks a learner would see.
 *
 * @module     mod_vimipad/tests/enforce_locks_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient lock enforcement flag', () => {
    const makeTransport = () => {
        const args: Record<string, unknown>[] = [];
        const transport = async (method: string, params: Record<string, unknown>): Promise<unknown> => {
            args.push({method, ...params});
            return {revision: 1, stableid: 'node_x', status: true};
        };
        return {transport, args};
    };

    test('defaults to not enforcing', async () => {
        const {transport, args} = makeTransport();
        const api = new ApiClient(transport, 7);
        await api.applyOperation(1, 0, 'node_update', {stableid: 'a'});
        await api.saveLayout(1, '{"v":1,"pos":{}}');
        expect(args[0].enforcelocks).toBe(false);
        expect(args[1].enforcelocks).toBe(false);
    });

    test('setEnforceLocks(true) sends enforcelocks on operation and layout calls', async () => {
        const {transport, args} = makeTransport();
        const api = new ApiClient(transport, 7);
        api.setEnforceLocks(true);
        await api.applyOperation(1, 0, 'node_update', {stableid: 'a'});
        await api.saveLayout(1, '{"v":1,"pos":{}}', '', 'merge');
        expect(args[0].method).toBe('mod_vimipad_apply_operation');
        expect(args[0].enforcelocks).toBe(true);
        expect(args[1].method).toBe('mod_vimipad_save_layout');
        expect(args[1].enforcelocks).toBe(true);
    });

    test('the flag can be toggled back off', async () => {
        const {transport, args} = makeTransport();
        const api = new ApiClient(transport, 7);
        api.setEnforceLocks(true);
        api.setEnforceLocks(false);
        await api.saveLayout(1, '{"v":1,"pos":{}}');
        expect(args[0].enforcelocks).toBe(false);
    });
});
