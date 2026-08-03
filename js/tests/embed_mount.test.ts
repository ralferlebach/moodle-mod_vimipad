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
 * Embeddability contract test: the editor mounts through the public mount()
 * entrypoint with a fully caller-supplied transport (the swappable persistence
 * adapter), using no Moodle globals and no network fetch. This is the surface a
 * dependent plugin (question type, database field, standalone host) relies on.
 *
 * @module     mod_vimipad/tests/embed_mount
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act} from 'react';
import editor from '../src/mount';
import {ServiceTransport} from '../src/types';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

/**
 * A self-contained in-memory transport: it answers get_workspace from a local
 * object and accepts operations, standing in for a custom persistence adapter.
 * No fetch, no Moodle service.php.
 */
function memoryTransport(): {transport: ServiceTransport; calls: string[]} {
    const calls: string[] = [];
    const workspace = {
        workspaceid: 101, revision: 1, locked: 0, profile: 'conceptmap', formconfig: undefined,
        layoutjson: '', nodes: [
            {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'Embedded', content: '',
                contentformat: 0, metadatajson: ''},
        ],
        relations: [], containers: [],
    };
    const transport: ServiceTransport = async (method: string): Promise<unknown> => {
        calls.push(method);
        if (method === 'mod_vimipad_get_workspace') {
            return workspace;
        }
        if (method === 'mod_vimipad_get_constraint_status') {
            return {configured: false, satisfied: true, messages: []};
        }
        if (method === 'mod_vimipad_get_journal_entries') {
            return {entries: []};
        }
        // Any other call (locks, operations) succeeds trivially for this test.
        return {};
    };
    return {transport, calls};
}

describe('embeddable editor mount()', () => {
    let host: HTMLDivElement;

    beforeEach(() => {
        host = document.createElement('div');
        document.body.appendChild(host);
    });

    afterEach(() => {
        host.remove();
    });

    test('mounts with a caller-supplied transport and renders workspace content', async () => {
        const {transport, calls} = memoryTransport();

        await act(async () => {
            editor.mount(host, {
                cmid: 1,
                callService: transport,
                // A trivial i18n getter, proving the host controls strings too.
                getString: (key: string): string => key,
            });
        });
        // Let the initial load() promise chain settle.
        await act(async () => { await Promise.resolve(); await Promise.resolve(); });

        // The editor talked to OUR transport, not the network.
        expect(calls).toContain('mod_vimipad_get_workspace');
        // The workspace content from our adapter is on screen.
        expect(host.textContent).toContain('Embedded');
        // Something actually rendered into the host.
        expect(host.querySelector('svg, .vimipad-editor, .vimipad-canvas-node')).not.toBeNull();
    });

    test('the mount entrypoint exposes the documented surface', () => {
        // The public contract: a default export with mount/mountRevision/mountPlayer.
        expect(typeof editor.mount).toBe('function');
        expect(typeof editor.mountRevision).toBe('function');
        expect(typeof editor.mountPlayer).toBe('function');
    });
});
