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
 * The ordering rule for arming a node drag on pointer-down.
 *
 * A node drag is armed synchronously on pointer-down; the collaboration lock is
 * an async round-trip that must NOT precede arming. Invariant: arm pointer tracking synchronously before awaiting the lease,
 * so pointer-up can always clear local drag state.
 */

/** The mutable drag state a host keeps. */
export interface DragState {
    /** The armed node id, or null when no drag is armed. */
    id: string | null;
    /** Whether the pointer has moved since arming (a real move, not a click). */
    moved: boolean;
    /** Whether the pointer is still down. */
    down: boolean;
}

/** Side effects the sequence performs on the host. */
export interface DragArmHost {
    /** Capture the pointer to the node element (synchronous). */
    capture: () => void;
    /** Acquire the collaboration lock; resolves granted/refused. */
    acquire: () => Promise<boolean>;
    /** Release the collaboration lock. */
    release: () => void;
}

/** A fresh, unarmed drag state. */
export function initialDrag(): DragState {
    return {id: null, moved: false, down: false};
}

/**
 * Arm a drag on pointer-down: capture and set the id synchronously, then acquire
 * the lock in the background and cancel the drag if it is refused (and the drag
 * has not already become a move).
 *
 * @param state The drag state (mutated in place).
 * @param id The node id.
 * @param host The host side effects.
 * @returns A promise that settles once the lock resolves.
 */
export function armDrag(state: DragState, id: string, host: DragArmHost): Promise<void> {
    host.capture();
    state.id = id;
    state.moved = false;
    state.down = true;
    return host.acquire().then(granted => {
        if (!granted && state.id === id && !state.moved) {
            state.id = null;
        }
    });
}

/**
 * Record pointer movement while a drag is armed.
 *
 * @param state The drag state (mutated in place).
 */
export function pointerMove(state: DragState): void {
    if (state.id !== null) {
        state.moved = true;
    }
}

/**
 * Handle pointer-up: commit a move if one happened, then disarm and release.
 *
 * @param state The drag state (mutated in place).
 * @param host The host side effects.
 * @returns True if an actual move was committed.
 */
export function pointerUp(state: DragState, host: DragArmHost): boolean {
    state.down = false;
    const committed = state.id !== null && state.moved;
    if (state.id !== null) {
        host.release();
    }
    state.id = null;
    state.moved = false;
    return committed;
}

/**
 * Handle a lost capture or cancelled gesture: disarm and release unconditionally.
 *
 * @param state The drag state (mutated in place).
 * @param host The host side effects.
 */
export function abortDrag(state: DragState, host: DragArmHost): void {
    if (state.id !== null) {
        host.release();
    }
    state.id = null;
    state.moved = false;
    state.down = false;
}
