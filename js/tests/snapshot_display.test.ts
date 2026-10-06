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
 * A submitted snapshot is drawn as the author drew it.
 *
 * The gallery hands a snapshot to the editor through mountValue. The snapshot
 * keeps its positions decoded under "layout" and its relation fields as the
 * database returns them. The editor once read neither: it invented a layout and
 * dropped every arrowhead, so the gallery showed a topologically different map.
 * This mounts exactly that shape and checks what is drawn.
 *
 * @module mod_vimipad/tests/snapshot_display
 */

import {mountValue} from '../src/mount';

const IDS = {pump: 'node_aaaaaaaaaaa1', valve: 'node_aaaaaaaaaaa2', tank: 'node_aaaaaaaaaaa3'};

/** The map as the gallery's display fixture stores it. */
const SNAPSHOT = JSON.stringify({
    profile: 'conceptmap',
    revision: '3',
    nodes: [
        {stableid: IDS.pump, type: 'concept', label: 'Pump', metadatajson: '{}'},
        {stableid: IDS.valve, type: 'concept', label: 'Valve', metadatajson: '{}'},
        {stableid: IDS.tank, type: 'concept', label: 'Tank', metadatajson: '{}'},
    ],
    relations: [
        {stableid: 'rel_aaaaaaaaaaa1', sourceid: IDS.pump, targetid: IDS.valve,
            type: '', label: 'feeds', direction: '1', metadatajson: '{}'},
        {stableid: 'rel_aaaaaaaaaaa2', sourceid: IDS.valve, targetid: IDS.tank,
            type: '', label: 'fills', direction: '1', metadatajson: '{}'},
    ],
    containers: [],
    layout: {v: 1, pos: {
        [IDS.pump]: {x: 700, y: 800}, [IDS.valve]: {x: 1200, y: 800}, [IDS.tank]: {x: 1700, y: 800},
    }, size: {}},
});

/**
 * Mount the snapshot read-only, as the gallery does, and wait for it to draw.
 *
 * @returns The host element.
 */
async function mounted(): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.appendChild(host);
    mountValue(host, {value: SNAPSHOT, readonly: true, profile: 'conceptmap', onChange: () => undefined});
    for (let i = 0; i < 40 && !host.querySelector('svg.vimipad-canvas g[transform^="translate"]'); i++) {
        await new Promise(r => setTimeout(r, 20));
    }
    return host;
}

/**
 * The drawn centre of each node, by the translate of its group.
 *
 * @param host The host element.
 * @returns Centres in drawing order.
 */
function centres(host: HTMLElement): {x: number; y: number}[] {
    return [...host.querySelectorAll('svg.vimipad-canvas g[transform^="translate"]')].map(g => {
        const m = /translate\(\s*(-?[\d.]+)[,\s]+(-?[\d.]+)\s*\)/.exec(g.getAttribute('transform') ?? '');
        return {x: Number(m?.[1]), y: Number(m?.[2])};
    }).slice(0, 3);
}

describe('a snapshot shown through mountValue', () => {
    test('the nodes stand where the author put them', async () => {
        const host = await mounted();
        expect(centres(host)).toEqual([{x: 700, y: 800}, {x: 1200, y: 800}, {x: 1700, y: 800}]);
    });

    test('every directed relation keeps its arrowhead', async () => {
        const host = await mounted();
        // Direction arrives as the string "1"; it must still count as directed.
        expect(host.querySelectorAll('svg.vimipad-canvas [marker-end]').length).toBe(2);
    });

    test('a viewer gets no editing tools', async () => {
        const host = await mounted();
        expect(host.querySelector('#vimipad-node-label')).toBeNull();
    });
});
