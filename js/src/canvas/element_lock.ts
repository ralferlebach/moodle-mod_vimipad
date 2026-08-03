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
 * Pure helpers for the template lock carried in an element's metadata.
 *
 * A locked element stores `{"locked": true}` (optionally with an `editable`
 * whitelist) in its metadata JSON; the server enforces this (since 0.6.4). These
 * helpers read and write that flag while preserving any other metadata keys
 * (shape, fill, ...), so setting a lock never clobbers styling.
 *
 * @module     mod_vimipad/canvas/element_lock
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The lock state read from an element's metadata. */
export interface LockState {
    locked: boolean;
    /** Field names a learner may still change while locked (e.g. ['label']). */
    editable: string[];
}

/** The three independent lock groups. */
export type LockGroup = 'move' | 'color' | 'text';

/** All lock groups, in menu order. */
export const LOCK_GROUPS: LockGroup[] = ['move', 'color', 'text'];

/** Per-group lock flags carried in an element's metadata. */
export interface GroupLocks {
    move: boolean;
    color: boolean;
    text: boolean;
}

/**
 * Parse the raw metadata JSON into a plain object (or empty on failure).
 *
 * @param metadatajson The element's metadata JSON.
 * @returns The parsed object, or an empty object.
 */
function parseMeta(metadatajson?: string): Record<string, unknown> {
    if (!metadatajson) {
        return {};
    }
    try {
        const raw = JSON.parse(metadatajson);
        return raw && typeof raw === 'object' ? raw as Record<string, unknown> : {};
    } catch {
        return {};
    }
}

/**
 * Read the lock state from an element's metadata.
 *
 * @param metadatajson The element's metadata JSON.
 * @returns The lock state.
 */
export function readLock(metadatajson?: string): LockState {
    const meta = parseMeta(metadatajson);
    const editable = Array.isArray(meta.editable)
        ? meta.editable.filter((v): v is string => typeof v === 'string')
        : [];
    return {locked: Boolean(meta.locked), editable};
}

/**
 * Whether an element is locked.
 *
 * @param metadatajson The element's metadata JSON.
 * @returns True if locked.
 */
export function isLocked(metadatajson?: string): boolean {
    return Boolean(parseMeta(metadatajson).locked);
}

/**
 * Write a lock state into metadata JSON, preserving all other keys.
 *
 * Unlocking removes the `locked` and `editable` keys entirely, so an unlocked
 * element carries no lock cruft. Locking sets `locked: true` and, when the
 * whitelist is non-empty, an `editable` array.
 *
 * @param metadatajson The existing metadata JSON (may be empty).
 * @param state The desired lock state.
 * @returns The updated metadata JSON.
 */
export function writeLock(metadatajson: string | undefined, state: LockState): string {
    const meta = parseMeta(metadatajson);
    if (state.locked) {
        meta.locked = true;
        if (state.editable.length > 0) {
            meta.editable = [...state.editable];
        } else {
            delete meta.editable;
        }
    } else {
        delete meta.locked;
        delete meta.editable;
    }
    return JSON.stringify(meta);
}

/**
 * Read the per-group lock flags from an element's metadata.
 *
 * A locked element with no `locks` map is a legacy/global lock: every group is
 * locked. An unlocked element locks nothing. This mirrors the server's
 * \mod_vimipad\local\lock\element_lock so the UI and the server agree.
 *
 * @param metadatajson The element's metadata JSON.
 * @returns The per-group lock flags.
 */
export function readGroupLocks(metadatajson?: string): GroupLocks {
    const meta = parseMeta(metadatajson);
    if (!meta.locked) {
        return {move: false, color: false, text: false};
    }
    const locks = meta.locks;
    if (!locks || typeof locks !== 'object') {
        // Legacy global lock: everything locked.
        return {move: true, color: true, text: true};
    }
    const l = locks as Record<string, unknown>;
    return {
        move: Boolean(l.move),
        color: Boolean(l.color),
        text: Boolean(l.text),
    };
}

/**
 * Whether a specific group is locked on the element.
 *
 * @param metadatajson The element's metadata JSON.
 * @param group The lock group.
 * @returns True if that group is locked.
 */
export function isGroupLocked(metadatajson: string | undefined, group: LockGroup): boolean {
    return readGroupLocks(metadatajson)[group];
}

/**
 * Whether the element has any group locked at all.
 *
 * @param metadatajson The element's metadata JSON.
 * @returns True if any group is locked.
 */
export function isAnyLocked(metadatajson?: string): boolean {
    const g = readGroupLocks(metadatajson);
    return g.move || g.color || g.text;
}

/**
 * Write per-group lock flags into metadata JSON, preserving all other keys.
 *
 * If every group is false the element is fully unlocked (`locked`/`locks`
 * removed). Otherwise `locked: true` is set together with the explicit `locks`
 * map, so partial locks are represented precisely.
 *
 * @param metadatajson The existing metadata JSON (may be empty).
 * @param groups The desired per-group lock flags.
 * @returns The updated metadata JSON.
 */
export function writeGroupLocks(metadatajson: string | undefined, groups: GroupLocks): string {
    const meta = parseMeta(metadatajson);
    const anyLocked = groups.move || groups.color || groups.text;
    if (!anyLocked) {
        delete meta.locked;
        delete meta.locks;
        delete meta.editable;
    } else {
        meta.locked = true;
        meta.locks = {move: groups.move, color: groups.color, text: groups.text};
        delete meta.editable;
    }
    return JSON.stringify(meta);
}
