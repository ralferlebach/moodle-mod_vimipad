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
 * Typed client for the mod_vimipad external functions.
 *
 * The transport is injected so the same client works whether it is driven by
 * Moodle's core/ajax (production) or a stub (tests). This mirrors the
 * swappable-persistence-adapter decision that lets satellite plugins reuse the
 * editor.
 *
 * @module     mod_vimipad/api/service
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ConstraintStatus, JournalEntry, Lease, PolledOperation, ServiceTransport, WorkspaceState} from '../types';
import {AdaptiveConfig} from '../collab/adaptive';
import {PollClient} from '../collab/poll_client';
import {LockClient} from '../collab/lock_client';

export interface ApplyResult {
    revision: number;
    stableid: string;
}

/** Web-service methods that mutate state; blocked in read-only mode. */
const WRITE_METHODS = new Set<string>([
    'mod_vimipad_apply_operation',
    'mod_vimipad_save_layout',
    'mod_vimipad_create_snapshot',
    'mod_vimipad_import_map',
    'mod_vimipad_add_journal_entry',
    'mod_vimipad_acquire_lock',
    'mod_vimipad_renew_lock',
    'mod_vimipad_release_lock',
]);

/**
 * Wrap a transport so every state-mutating call is rejected. Reads and polling
 * pass through unchanged, so a foreign map can still be viewed live.
 *
 * @param transport The underlying transport.
 * @returns A transport that blocks writes.
 */
function readonlyGuard(transport: ServiceTransport): ServiceTransport {
    return (methodname: string, args: Record<string, unknown>): Promise<unknown> => {
        if (WRITE_METHODS.has(methodname)) {
            return Promise.reject(new Error('vimipad:readonly'));
        }
        return transport(methodname, args);
    };
}

export class ApiClient {
    private readonly transport: ServiceTransport;
    private readonly cmid: number;
    private readonly readonly: boolean;

    /**
     * @param transport The call transport.
     * @param cmid The course module id.
     * @param readonly If true, all mutating calls are blocked (foreign view).
     */
    constructor(transport: ServiceTransport, cmid: number, readonly = false) {
        this.readonly = readonly;
        this.transport = readonly ? readonlyGuard(transport) : transport;
        this.cmid = cmid;
    }

    /**
     * Whether this client is in read-only mode (viewing a foreign map).
     *
     * @returns True if writes are blocked.
     */
    isReadonly(): boolean {
        return this.readonly;
    }

    /**
     * Resolve and load a workspace state.
     *
     * @param groupid Optional group id (group mode).
     * @param targetuserid Optional owner user to view read-only (0 = self).
     */
    async getWorkspace(groupid = 0, targetuserid = 0): Promise<WorkspaceState> {
        const result = await this.transport('mod_vimipad_get_workspace', {
            cmid: this.cmid,
            groupid,
            targetuserid,
        });
        return result as WorkspaceState;
    }

    /**
     * Reconstruct a workspace state at a past revision (read-only).
     *
     * @param workspaceid The workspace id.
     * @param revision The revision to reconstruct.
     */
    async getRevisionState(workspaceid: number, revision: number): Promise<WorkspaceState> {
        const result = await this.transport('mod_vimipad_get_revision_state', {
            cmid: this.cmid,
            workspaceid,
            revision,
        });
        return result as WorkspaceState;
    }

    /**
     * Fetch the current map's non-blocking constraint status for edit-time
     * hints. A read; available even in read-only mode.
     *
     * @param workspaceid The workspace id.
     * @returns The constraint status (configured/satisfied + messages).
     */
    async getConstraintStatus(workspaceid: number): Promise<ConstraintStatus> {
        const result = await this.transport('mod_vimipad_get_constraint_status', {
            cmid: this.cmid,
            workspaceid,
        });
        return result as ConstraintStatus;
    }

    /**
     * Apply a single operation against a known base revision.
     *
     * @param workspaceid The workspace id.
     * @param baserevision The revision the client is based on.
     * @param operationtype The operation type.
     * @param payload The operation payload.
     */
    async applyOperation(
        workspaceid: number,
        baserevision: number,
        operationtype: string,
        payload: Record<string, unknown>
    ): Promise<ApplyResult> {
        const result = await this.transport('mod_vimipad_apply_operation', {
            cmid: this.cmid,
            workspaceid,
            baserevision,
            operationtype,
            payloadjson: JSON.stringify(payload),
        });
        return result as ApplyResult;
    }

    /**
     * Persist the (non-revisioned) layout of the workspace.
     *
     * @param workspaceid The workspace id.
     * @param layoutjson The layout JSON payload.
     * @param viewportjson Optional viewport JSON payload.
     */
    async saveLayout(
        workspaceid: number,
        layoutjson: string,
        viewportjson = '',
        mode: 'replace' | 'merge' = 'replace'
    ): Promise<void> {
        await this.transport('mod_vimipad_save_layout', {
            cmid: this.cmid,
            workspaceid,
            layoutjson,
            viewportjson,
            mode,
        });
    }

    /**
     * Submit the workspace as an immutable snapshot for grading.
     *
     * @param workspaceid The workspace id.
     * @returns The created snapshot id and its status.
     */
    async createSnapshot(workspaceid: number): Promise<{snapshotid: number; status: number; pending: number}> {
        const result = await this.transport('mod_vimipad_create_snapshot', {
            cmid: this.cmid,
            workspaceid,
        });
        return result as {snapshotid: number; status: number; pending: number};
    }

    /**
     * Import a JSON export document into a workspace.
     *
     * @param workspaceid The workspace id.
     * @param json The JSON export document.
     * @returns The imported element counts and the new revision.
     */
    async importMap(
        workspaceid: number,
        json: string,
        mode: 'append' | 'replace' = 'append'
    ): Promise<{nodes: number; relations: number; revision: number}> {
        const result = await this.transport('mod_vimipad_import_map', {
            cmid: this.cmid,
            workspaceid,
            json,
            mode,
        });
        return result as {nodes: number; relations: number; revision: number};
    }

    /**
     * Fetch the current user's own journal entries for a workspace.
     *
     * @param workspaceid The workspace id.
     * @returns The entries, newest first.
     */
    async getJournalEntries(workspaceid: number): Promise<{entries: JournalEntry[]}> {
        const result = await this.transport('mod_vimipad_get_journal_entries', {
            cmid: this.cmid,
            workspaceid,
        });
        return result as {entries: JournalEntry[]};
    }

    /**
     * Add a journal entry to the current user's own journal.
     *
     * @param workspaceid The workspace id.
     * @param entrytext The entry text.
     * @param visibility 0 private, 1 teacher-visible.
     * @returns The new entry id.
     */
    async addJournalEntry(
        workspaceid: number,
        entrytext: string,
        priv: boolean
    ): Promise<{id: number}> {
        const result = await this.transport('mod_vimipad_add_journal_entry', {
            cmid: this.cmid,
            workspaceid,
            entrytext,
            private: priv ? 1 : 0,
        });
        return result as {id: number};
    }

    /**
     * The course module id this client is bound to.
     *
     * @returns The cmid.
     */
    getCmid(): number {
        return this.cmid;
    }

    /**
     * Create a poll client bound to this client's transport and cmid.
     *
     * @param workspaceid The workspace id.
     * @param adaptive The adaptive interval configuration.
     * @param handlers Operation/presence/error callbacks.
     * @returns A configured PollClient.
     */
    createPollClient(
        workspaceid: number,
        adaptive: AdaptiveConfig,
        handlers: {
            onOperations?: (operations: PolledOperation[]) => void;
            onPresence?: (leases: Lease[]) => void;
            onLayout?: (layoutjson: string) => void;
            onWorkspaceState?: (state: {locked: number; profile: string}) => void;
            onError?: (error: Error) => void;
        }
    ): PollClient {
        return new PollClient({
            cmid: this.cmid,
            workspaceid,
            transport: this.transport,
            adaptive,
            ...handlers,
        });
    }

    /**
     * Create a lock client bound to this client's transport and cmid.
     *
     * @param workspaceid The workspace id.
     * @param onError Optional error callback.
     * @returns A configured LockClient.
     */
    createLockClient(workspaceid: number, onError?: (error: Error) => void): LockClient {
        return new LockClient({
            cmid: this.cmid,
            workspaceid,
            transport: this.transport,
            onError,
        });
    }
}

/**
 * Build the default fetch-based transport that speaks Moodle's AJAX protocol.
 *
 * Used when the host page does not inject core/ajax. Reads wwwroot and sesskey
 * from the global M.cfg object.
 *
 * @returns A ServiceTransport bound to Moodle's service.php endpoint.
 */
export function createFetchTransport(): ServiceTransport {
    return async (methodname: string, args: Record<string, unknown>): Promise<unknown> => {
        const cfg = (window as unknown as {M?: {cfg?: {wwwroot: string; sesskey: string}}}).M?.cfg;
        if (!cfg) {
            throw new Error('Moodle configuration is not available');
        }
        const url = `${cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(cfg.sesskey)}`
            + `&info=${encodeURIComponent(methodname)}`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{index: 0, methodname, args}]),
        });
        const payload = await response.json();
        const first = Array.isArray(payload) ? payload[0] : payload;
        if (!first || first.error) {
            throw new Error(first && first.exception ? first.exception.message : 'Request failed');
        }
        return first.data;
    };
}
