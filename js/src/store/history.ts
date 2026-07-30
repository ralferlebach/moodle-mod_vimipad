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
 * Undo/redo history for the editor.
 *
 * In a server-authoritative, collaborative editor an undo cannot roll back local
 * state: it must apply the inverse operation through the server so collaborators
 * see it and the revision advances. Each entry therefore carries the operation
 * sequence to run on undo and on redo (a sequence, so a node deletion can be
 * undone by recreating the node and its relations in order). This module is the
 * pure stack; the executor that runs the operations lives in the editor.
 *
 * @module     mod_vimipad/store/history
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** A single operation to send to the server during undo or redo. */
export interface OpSpec {
    /** The operation type, e.g. 'node_delete'. */
    type: string;
    /** The operation payload. */
    payload: Record<string, unknown>;
}

/** One undoable change: the operations to run on undo and on redo. */
export interface HistoryEntry {
    /** Operations (in order) that reverse the change. */
    undo: OpSpec[];
    /** Operations (in order) that re-apply the change. */
    redo: OpSpec[];
}

/**
 * A bounded undo/redo stack.
 *
 * Pushing a new entry clears the redo stack (standard linear-history
 * semantics). The stack is capped so long sessions do not grow without bound.
 */
export class History {
    /** @type {HistoryEntry[]} Entries available to undo (most recent last). */
    private undoStack: HistoryEntry[] = [];

    /** @type {HistoryEntry[]} Entries available to redo (most recent last). */
    private redoStack: HistoryEntry[] = [];

    /** @type {number} Maximum number of undo entries kept. */
    private readonly limit: number;

    /**
     * @param limit Maximum undo depth (default 50).
     */
    public constructor(limit: number = 50) {
        this.limit = Math.max(1, limit);
    }

    /**
     * Record a new change, clearing any pending redo.
     *
     * @param entry The change to record.
     * @returns void
     */
    public push(entry: HistoryEntry): void {
        this.undoStack.push(entry);
        if (this.undoStack.length > this.limit) {
            this.undoStack.shift();
        }
        this.redoStack = [];
    }

    /**
     * Whether an undo is available.
     *
     * @returns True if the undo stack is non-empty.
     */
    public canUndo(): boolean {
        return this.undoStack.length > 0;
    }

    /**
     * Whether a redo is available.
     *
     * @returns True if the redo stack is non-empty.
     */
    public canRedo(): boolean {
        return this.redoStack.length > 0;
    }

    /**
     * Take the most recent change off the undo stack and move it to redo.
     *
     * @returns The entry to undo, or null if none.
     */
    public takeUndo(): HistoryEntry | null {
        const entry = this.undoStack.pop();
        if (entry === undefined) {
            return null;
        }
        this.redoStack.push(entry);
        return entry;
    }

    /**
     * Take the most recent change off the redo stack and move it to undo.
     *
     * @returns The entry to redo, or null if none.
     */
    public takeRedo(): HistoryEntry | null {
        const entry = this.redoStack.pop();
        if (entry === undefined) {
            return null;
        }
        this.undoStack.push(entry);
        return entry;
    }

    /**
     * Empty both stacks (e.g. after a full reload).
     *
     * @returns void
     */
    public clear(): void {
        this.undoStack = [];
        this.redoStack = [];
    }
}
