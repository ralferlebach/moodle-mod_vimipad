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
 * Editing a map that lives in a value - a database field or a quiz answer.
 *
 * The value transport stands in for the server there. It did not mint stable
 * ids for created elements and it answered every lock request with an empty
 * object. Together that meant: the first node showed only as an optimistic
 * preview with an empty id and never reached the value, so the form saved an
 * empty map; a second node shared the empty id and never appeared; and every
 * drag was refused before the pointer had moved.
 *
 * @module mod_vimipad/tests/value_editing
 */

import {createValueTransport, withMintedStableId} from '../src/value_transport';
import {mountValue} from '../src/mount';

const settle = (ms = 25): Promise<void> => new Promise(r => setTimeout(r, ms));

describe('stable ids for created elements', () => {
    test.each([
        ['node_create', /^node_[0-9a-f]{12}$/],
        ['relation_create', /^rel_[0-9a-f]{12}$/],
        ['container_create', /^cont_[0-9a-f]{12}$/],
    ])('%s without an id gets one in the server format', (type, pattern) => {
        const payload = JSON.parse(withMintedStableId(type, '{"label":"x"}'));
        expect(payload.stableid).toMatch(pattern);
    });

    test('an id the caller supplied is kept', () => {
        const payload = JSON.parse(withMintedStableId('node_create', '{"stableid":"node_aaaaaaaaaaaa"}'));
        expect(payload.stableid).toBe('node_aaaaaaaaaaaa');
    });

    test('ids do not collide', () => {
        const ids = new Set(Array.from({length: 200},
            () => JSON.parse(withMintedStableId('node_create', '{}')).stableid));
        expect(ids.size).toBe(200);
    });

    test('a create without an id reaches the value and returns its id', async () => {
        const changes: string[] = [];
        const {transport, getValue} = createValueTransport('', {onChange: v => changes.push(v)});
        const ws = await transport('mod_vimipad_get_workspace', {}) as {revision: number};
        const first = await transport('mod_vimipad_apply_operation', {
            revision: ws.revision, operationtype: 'node_create',
            payloadjson: JSON.stringify({type: 'concept', label: 'First'}),
        }) as {revision: number; stableid: string};
        const second = await transport('mod_vimipad_apply_operation', {
            revision: first.revision, operationtype: 'node_create',
            payloadjson: JSON.stringify({type: 'concept', label: 'Second'}),
        }) as {stableid: string};

        expect(first.stableid).toMatch(/^node_[0-9a-f]{12}$/);
        expect(second.stableid).not.toBe(first.stableid);
        expect(changes.length).toBe(2);
        const value = JSON.parse(getValue());
        expect(value.nodes.map((n: {label: string}) => n.label)).toEqual(['First', 'Second']);
    });
});

describe('locks in a single-author value', () => {
    test.each(['mod_vimipad_acquire_lock', 'mod_vimipad_renew_lock'])('%s is granted when editable', async (fn) => {
        const {transport} = createValueTransport('');
        const res = await transport(fn, {targettype: 'node', targetstableid: 'node_aaaaaaaaaaaa'}) as {acquired: boolean};
        expect(res.acquired).toBe(true);
    });

    test('a read-only value grants no lock', async () => {
        const {transport} = createValueTransport('', {readonly: true});
        const res = await transport('mod_vimipad_acquire_lock', {}) as {acquired: boolean};
        expect(res.acquired).toBe(false);
    });
});

describe('building a map through the form, as a learner does', () => {
    /**
     * Type into a React-controlled input.
     *
     * @param el The input.
     * @param text The text.
     */
    function typeInto(el: HTMLInputElement, text: string): void {
        const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
        setter.call(el, text);
        el.dispatchEvent(new Event('input', {bubbles: true}));
    }

    test('two concepts both appear and both are saved', async () => {
        let value = '';
        const host = document.createElement('div');
        document.body.appendChild(host);
        mountValue(host, {value: '', readonly: false, profile: 'conceptmap', onChange: (v: string) => { value = v; }});
        for (let i = 0; i < 40 && !host.querySelector('#vimipad-node-label'); i++) {
            await settle();
        }
        const input = host.querySelector('#vimipad-node-label') as HTMLInputElement;
        const add = input.closest('fieldset')!.querySelector('button') as HTMLButtonElement;

        for (const label of ['First', 'Second']) {
            typeInto(input, label);
            await settle();
            add.click();
            for (let i = 0; i < 20; i++) {
                await settle();
            }
        }

        const drawn = [...host.querySelectorAll('svg.vimipad-canvas foreignObject, svg.vimipad-canvas text')]
            .map(t => t.textContent?.trim()).filter(Boolean);
        expect(drawn).toEqual(expect.arrayContaining(['First', 'Second']));
        const saved = JSON.parse(value);
        expect(saved.nodes.map((n: {label: string}) => n.label)).toEqual(['First', 'Second']);
        // Every saved node carries a real id, so it can be moved and linked.
        for (const node of saved.nodes) {
            expect(node.stableid).toMatch(/^node_[0-9a-f]{12}$/);
        }
    });
});
