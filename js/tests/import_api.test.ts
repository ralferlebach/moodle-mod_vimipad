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
 * Tests for the importMap method of the API client.
 *
 * @module     mod_vimipad/tests/import_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient importMap', () => {
    test('sends the JSON to the import function with cmid and workspace', async () => {
        const calls: {method: string; args: Record<string, unknown>}[] = [];
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, args});
            return {nodes: 2, relations: 1, revision: 3};
        };
        const api = new ApiClient(transport, 42);

        const res = await api.importMap(7, '{"generator":"mod_vimipad"}');

        expect(calls[0].method).toBe('mod_vimipad_import_map');
        expect(calls[0].args).toEqual({
            cmid: 42,
            workspaceid: 7,
            json: '{"generator":"mod_vimipad"}',
            mode: 'append',
        });
        expect(res.nodes).toBe(2);
        expect(res.relations).toBe(1);
    });

    test('passes replace mode through', async () => {
        const calls: {method: string; args: Record<string, unknown>}[] = [];
        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, args});
            return {nodes: 0, relations: 0, revision: 5};
        };
        const api = new ApiClient(transport, 42);

        await api.importMap(7, '{}', 'replace');

        expect(calls[0].args.mode).toBe('replace');
    });
});
