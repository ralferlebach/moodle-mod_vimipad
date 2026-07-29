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
 * Tests for the revision-state API call used by the read-only viewer.
 *
 * @module     mod_vimipad/tests/revision_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient.getRevisionState', () => {
    test('calls the reconstruction service with cmid, workspace and revision', async () => {
        const calls: Array<{method: string; args: Record<string, unknown>}> = [];
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, args});
            return {
                workspaceid: 5, revision: 3, locked: 1, profile: 'conceptmap',
                layoutjson: '', nodes: [], relations: [],
            };
        };
        const api = new ApiClient(transport, 9);

        await api.getRevisionState(5, 3);

        expect(calls).toHaveLength(1);
        expect(calls[0].method).toBe('mod_vimipad_get_revision_state');
        expect(calls[0].args).toMatchObject({cmid: 9, workspaceid: 5, revision: 3});
    });

    test('is allowed in read-only mode (it is a read)', async () => {
        const transport = async (): Promise<unknown> => ({
            workspaceid: 5, revision: 3, locked: 1, profile: 'conceptmap',
            layoutjson: '', nodes: [], relations: [],
        });
        const api = new ApiClient(transport, 9, true);

        await expect(api.getRevisionState(5, 3)).resolves.toMatchObject({revision: 3});
    });
});
