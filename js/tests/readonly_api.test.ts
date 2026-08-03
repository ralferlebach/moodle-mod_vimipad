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
 * Tests for the read-only mode of the API client (foreign map viewing).
 *
 * @module     mod_vimipad/tests/readonly_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient read-only mode', () => {
    const makeTransport = () => {
        const calls: string[] = [];
        const transport = async (method: string): Promise<unknown> => {
            calls.push(method);
            if (method === 'mod_vimipad_get_workspace') {
                return {workspaceid: 1, revision: 0, locked: 0, profile: 'conceptmap', layoutjson: '', nodes: [], relations: []};
            }
            return {revision: 1, stableid: 'node_x'};
        };
        return {transport, calls};
    };

    test('reads pass through', async () => {
        const {transport, calls} = makeTransport();
        const api = new ApiClient(transport, 7, true);
        expect(api.isReadonly()).toBe(true);
        await api.getWorkspace(0);
        expect(calls).toContain('mod_vimipad_get_workspace');
    });

    test('writes are blocked without reaching the transport', async () => {
        const {transport, calls} = makeTransport();
        const api = new ApiClient(transport, 7, true);
        await expect(api.applyOperation(1, 0, 'node_create', {type: 'concept'})).rejects.toThrow();
        await expect(api.createSnapshot(1)).rejects.toThrow();
        await expect(api.addJournalEntry(1, 'hi', false)).rejects.toThrow();
        expect(calls).not.toContain('mod_vimipad_apply_operation');
        expect(calls).not.toContain('mod_vimipad_create_snapshot');
        expect(calls).not.toContain('mod_vimipad_add_journal_entry');
    });

    test('a normal client allows writes', async () => {
        const {transport, calls} = makeTransport();
        const api = new ApiClient(transport, 7, false);
        expect(api.isReadonly()).toBe(false);
        await api.applyOperation(1, 0, 'node_create', {type: 'concept'});
        expect(calls).toContain('mod_vimipad_apply_operation');
    });
});
