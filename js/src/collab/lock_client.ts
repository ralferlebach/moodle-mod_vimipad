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
 * Collaboration lock client.
 *
 * Wraps the acquire/renew/release external functions and tracks which elements
 * this client currently holds, so a heartbeat can renew them while the user is
 * still editing. A lock is taken on drag-start and released on drag-end; if the
 * client disconnects, the server-side lease simply expires.
 *
 * @module     mod_vimipad/collab/lock_client
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {ServiceTransport} from '../types';

/** Options for constructing a LockClient. */
export interface LockClientOptions {
    cmid: number;
    workspaceid: number;
    transport: ServiceTransport;
    onError?: (error: Error) => void;
}

/** Shape of an acquire/renew response. */
interface LockResponse {
    acquired: boolean;
    userid: number;
    timeexpires: number;
}

/**
 * Manages this client's editing leases.
 */
export class LockClient {
    /** @type {LockClientOptions} */
    private readonly opts: LockClientOptions;

    /** @type {Set<string>} Keys ("type:stableid") the client currently holds. */
    private readonly held = new Set<string>();

    /**
     * @param options The client options.
     */
    constructor(options: LockClientOptions) {
        this.opts = options;
    }

    /**
     * Build the map key for an element.
     *
     * @param targettype The element type.
     * @param stableid The element stable id.
     * @returns The composite key.
     */
    private key(targettype: string, stableid: string): string {
        return `${targettype}:${stableid}`;
    }

    /**
     * Whether the client currently holds a lease on the given element.
     *
     * @param targettype The element type.
     * @param stableid The element stable id.
     * @returns True if held.
     */
    public holds(targettype: string, stableid: string): boolean {
        return this.held.has(this.key(targettype, stableid));
    }

    /**
     * Try to acquire a lease (typically on drag-start).
     *
     * @param targettype The element type.
     * @param stableid The element stable id.
     * @returns True if the lease is now held by this client.
     */
    public async acquire(targettype: string, stableid: string): Promise<boolean> {
        try {
            const res = await this.opts.transport('mod_vimipad_acquire_lock', {
                cmid: this.opts.cmid,
                workspaceid: this.opts.workspaceid,
                targettype,
                targetstableid: stableid,
            }) as LockResponse;
            if (res.acquired) {
                this.held.add(this.key(targettype, stableid));
            }
            return res.acquired;
        } catch (e) {
            if (this.opts.onError) {
                this.opts.onError(e as Error);
            }
            return false;
        }
    }

    /**
     * Renew a single lease.
     *
     * @param targettype The element type.
     * @param stableid The element stable id.
     * @returns True if still held after renewal.
     */
    public async renew(targettype: string, stableid: string): Promise<boolean> {
        try {
            const res = await this.opts.transport('mod_vimipad_renew_lock', {
                cmid: this.opts.cmid,
                workspaceid: this.opts.workspaceid,
                targettype,
                targetstableid: stableid,
            }) as LockResponse;
            if (!res.acquired) {
                this.held.delete(this.key(targettype, stableid));
            }
            return res.acquired;
        } catch (e) {
            if (this.opts.onError) {
                this.opts.onError(e as Error);
            }
            return false;
        }
    }

    /**
     * Release a lease (typically on drag-end).
     *
     * @param targettype The element type.
     * @param stableid The element stable id.
     * @returns A promise that resolves when released.
     */
    public async release(targettype: string, stableid: string): Promise<void> {
        this.held.delete(this.key(targettype, stableid));
        try {
            await this.opts.transport('mod_vimipad_release_lock', {
                cmid: this.opts.cmid,
                workspaceid: this.opts.workspaceid,
                targettype,
                targetstableid: stableid,
            });
        } catch (e) {
            if (this.opts.onError) {
                this.opts.onError(e as Error);
            }
        }
    }

    /**
     * Renew every currently held lease. Call on the poll tick as a heartbeat.
     *
     * @returns A promise that resolves when all renewals complete.
     */
    public async heartbeat(): Promise<void> {
        const keys = Array.from(this.held);
        await Promise.all(keys.map((key) => {
            const [targettype, stableid] = key.split(':');
            return this.renew(targettype, stableid);
        }));
    }
}
