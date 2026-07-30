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
 * Tests for the getConstraintStatus API client method.
 *
 * @module     mod_vimipad/tests/constraint_status_api
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient.getConstraintStatus', () => {
    test('calls the read endpoint with cmid and workspaceid and returns the status', async () => {
        const args: Array<Record<string, unknown>> = [];
        const transport = async (method: string, a: Record<string, unknown>): Promise<unknown> => {
            args.push({method, ...a});
            return {
                configured: true,
                satisfied: false,
                messages: ['Missing required concept(s): mitochondria'],
                requiredmissing: ['mitochondria'],
                forbiddenpresent: [],
                typeviolations: [],
            };
        };
        const api = new ApiClient(transport, 7);
        const status = await api.getConstraintStatus(42);

        expect(args[0].method).toBe('mod_vimipad_get_constraint_status');
        expect(args[0].cmid).toBe(7);
        expect(args[0].workspaceid).toBe(42);
        expect(status.configured).toBe(true);
        expect(status.satisfied).toBe(false);
        expect(status.requiredmissing).toEqual(['mitochondria']);
    });

    test('is a read: allowed even in read-only mode', async () => {
        const transport = async (): Promise<unknown> => ({
            configured: false, satisfied: true, messages: [],
            requiredmissing: [], forbiddenpresent: [], typeviolations: [],
        });
        const api = new ApiClient(transport, 7, true);
        const status = await api.getConstraintStatus(1);
        expect(status.satisfied).toBe(true);
    });
});
