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
 * Tests for the drag-arming sequence, pinning the "sticky node" race.
 *
 * @module     mod_vimipad/tests/drag_arm
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    abortDrag, armDrag, DragArmHost, initialDrag, pointerMove, pointerUp,
} from '../src/canvas/drag_arm';

/** A host with a lock whose resolution we control. */
function makeHost(): {host: DragArmHost; grant: (ok: boolean) => void; log: string[]} {
    const log: string[] = [];
    let resolve: (ok: boolean) => void = () => undefined;
    const host: DragArmHost = {
        capture: () => log.push('capture'),
        acquire: () => new Promise<boolean>(res => { resolve = res; }),
        release: () => log.push('release'),
    };
    return {host, grant: ok => resolve(ok), log};
}

describe('drag arming order', () => {
    test('capture and id are set synchronously, before the lock resolves', () => {
        const state = initialDrag();
        const {host} = makeHost();
        armDrag(state, 'node_a', host);
        // Immediately, without awaiting the lock:
        expect(state.id).toBe('node_a');
        expect(state.down).toBe(true);
    });

    test('the sticky-node race: pointerup during the lock still disarms', async () => {
        const state = initialDrag();
        const {host, grant, log} = makeHost();
        const armed = armDrag(state, 'node_a', host);

        // Pointer released while the lock is still in flight (the reported gap).
        const committed = pointerUp(state, host);
        expect(committed).toBe(false); // it was a click, not a move
        expect(state.id).toBeNull(); // <- the fix: not left latched

        grant(true);
        await armed;
        // A later bare move must NOT re-arm or move anything.
        pointerMove(state);
        expect(state.id).toBeNull();
        expect(log).toContain('capture');
        expect(log).toContain('release');
    });

    test('a granted lock with a real move commits and releases', async () => {
        const state = initialDrag();
        const {host, grant} = makeHost();
        const armed = armDrag(state, 'node_a', host);
        grant(true);
        await armed;
        pointerMove(state);
        expect(pointerUp(state, host)).toBe(true);
        expect(state.id).toBeNull();
    });

    test('a refused lock cancels an armed-but-unmoved drag', async () => {
        const state = initialDrag();
        const {host, grant} = makeHost();
        const armed = armDrag(state, 'node_a', host);
        grant(false);
        await armed;
        expect(state.id).toBeNull();
    });

    test('a refused lock does not yank a drag that already became a move', async () => {
        const state = initialDrag();
        const {host, grant} = makeHost();
        const armed = armDrag(state, 'node_a', host);
        pointerMove(state); // user already dragging
        grant(false);
        await armed;
        // Still dragging locally; the refusal must not strand the pointer.
        expect(state.id).toBe('node_a');
    });

    test('lost capture / cancel always disarms and releases', () => {
        const state = initialDrag();
        const {host, log} = makeHost();
        armDrag(state, 'node_a', host);
        abortDrag(state, host);
        expect(state.id).toBeNull();
        expect(state.down).toBe(false);
        expect(log).toContain('release');
    });
});
