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
 * Tests that ApiClient.getWorkspace loads a large map by paging elements and
 * reassembling the full WorkspaceState, so the editor sees the same shape.
 *
 * @module     mod_vimipad/tests/get_workspace_pagination
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ApiClient} from '../src/api/service';

describe('ApiClient.getWorkspace pagination', () => {
    test('pages nodes across multiple requests and reassembles them in order', async () => {
        const total = 600;
        const allNodes = Array.from({length: total}, (_, i) => ({
            stableid: `n${i}`, type: 'concept', label: `N${i}`,
            content: '', contentformat: 1, metadatajson: '',
        }));
        const calls: Array<Record<string, unknown>> = [];

        const transport = async (method: string, args: Record<string, unknown>): Promise<unknown> => {
            calls.push({method, ...args});
            if (method === 'mod_vimipad_get_workspace') {
                expect(args.includeelements).toBe(false);
                return {
                    workspaceid: 42, revision: 5, locked: 0, profile: 'conceptmap', layoutjson: '',
                    nodes: [], relations: [], containers: [],
                    counts: {nodes: total, relations: 0, containers: 0},
                };
            }
            if (method === 'mod_vimipad_get_workspace_elements') {
                const kind = args.kind as string;
                const offset = args.offset as number;
                const limit = args.limit as number;
                if (kind !== 'nodes') {
                    return {kind, offset, limit, total: 0, hasmore: false, [kind]: []};
                }
                const slice = allNodes.slice(offset, offset + limit);
                return {
                    kind: 'nodes', offset, limit, total,
                    hasmore: offset + slice.length < total, nodes: slice,
                };
            }
            throw new Error(`unexpected method ${method}`);
        };

        const api = new ApiClient(transport, 7);
        const ws = await api.getWorkspace();

        expect(ws.workspaceid).toBe(42);
        expect(ws.revision).toBe(5);
        expect(ws.nodes).toHaveLength(total);
        expect(ws.nodes[0].stableid).toBe('n0');
        expect(ws.nodes[total - 1].stableid).toBe('n599');
        // 600 nodes over a 500 page size → two node pages were fetched.
        const nodePages = calls.filter(c => c.method === 'mod_vimipad_get_workspace_elements' && c.kind === 'nodes');
        expect(nodePages).toHaveLength(2);
    });

    test('falls back to the inline arrays when the backend returns no counts', async () => {
        const transport = async (method: string): Promise<unknown> => {
            if (method === 'mod_vimipad_get_workspace') {
                return {
                    workspaceid: 0, revision: 0, locked: 0, profile: 'conceptmap', layoutjson: '',
                    nodes: [], relations: [], containers: [],
                };
            }
            throw new Error(`should not page without counts, got ${method}`);
        };
        const api = new ApiClient(transport, 7);
        const ws = await api.getWorkspace();
        expect(ws.workspaceid).toBe(0);
        expect(ws.nodes).toEqual([]);
    });
});
