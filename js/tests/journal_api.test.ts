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
 * Tests for the journal methods of the API client.
 *
 * @module     mod_vimipad/tests/journal_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

interface Call {
    method: string;
    args: Record<string, unknown>;
}

describe('ApiClient journal methods', () => {
    test('getJournalEntries requests the right function with cmid and workspace', async () => {
        const calls: Call[] = [];
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, args});
            return {entries: [{id: 1, entrytext: 'hi', visibility: 0, timecreated: 100}]};
        };
        const api = new ApiClient(transport, 42);

        const res = await api.getJournalEntries(7);

        expect(calls[0].method).toBe('mod_vimipad_get_journal_entries');
        expect(calls[0].args).toEqual({cmid: 42, workspaceid: 7});
        expect(res.entries).toHaveLength(1);
        expect(res.entries[0].entrytext).toBe('hi');
    });

    test('addJournalEntry sends the text and private flag', async () => {
        const calls: Call[] = [];
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, args});
            return {id: 99};
        };
        const api = new ApiClient(transport, 42);

        const res = await api.addJournalEntry(7, 'my entry', true);

        expect(calls[0].method).toBe('mod_vimipad_add_journal_entry');
        expect(calls[0].args).toEqual({cmid: 42, workspaceid: 7, entrytext: 'my entry', private: 1});
        expect(res.id).toBe(99);
    });
});
