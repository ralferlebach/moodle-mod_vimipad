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
 * The add-concept and add-relation form appears only where editing is possible.
 *
 * The form is the keyboard route to building a map without drawing. In a view
 * that can never edit - the gallery, a teacher looking at a learner's map - it
 * was still rendered, greyed out, as a bar of dead controls. Mounted through
 * mountValue, the path the gallery itself uses.
 *
 * @module mod_vimipad/tests/readonly_controls
 */

import {mountValue} from '../src/mount';

const MAP = JSON.stringify({
    profile: 'conceptmap',
    layoutjson: '{"node_aaaaaaaaaaaa":{"x":400,"y":300},"node_bbbbbbbbbbbb":{"x":700,"y":300}}',
    revision: 1,
    nodes: [
        {stableid: 'node_aaaaaaaaaaaa', type: 'concept', label: 'Water', metadatajson: '{}'},
        {stableid: 'node_bbbbbbbbbbbb', type: 'concept', label: 'Ice', metadatajson: '{}'},
    ],
    relations: [],
    containers: [],
});

/**
 * Mount the editor on the map and wait until it has rendered.
 *
 * @param readonly Whether the view is read-only.
 * @returns The host element.
 */
async function mounted(readonly: boolean): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.appendChild(host);
    mountValue(host, {value: MAP, readonly, profile: 'conceptmap', onChange: () => undefined});
    // The editor loads its workspace asynchronously; let the promises settle.
    for (let i = 0; i < 20 && !host.querySelector('.vimipad-editor'); i++) {
        await new Promise(resolve => setTimeout(resolve, 10));
    }
    return host;
}

describe('add form visibility', () => {
    test('an editable map offers the add form', async () => {
        const host = await mounted(false);
        expect(host.querySelector('.vimipad-editor')).not.toBeNull();
        expect(host.querySelector('.vimipad-controls-row')).not.toBeNull();
    });

    test('a read-only view does not render the add form at all', async () => {
        const host = await mounted(true);
        expect(host.querySelector('.vimipad-editor')).not.toBeNull();
        expect(host.querySelector('.vimipad-controls-row')).toBeNull();
        expect(host.querySelector('#vimipad-node-label')).toBeNull();
    });
});
