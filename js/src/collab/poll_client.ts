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
 * Collaboration poll client.
 *
 * Drives the polling loop: it asks the server for changes since the revision it
 * last saw, forwards new operations and presence (leases) to callbacks, and
 * adapts the interval from measured round-trip time and whether anything
 * changed. Timers and transport are injected so the loop is unit testable; the
 * pure interval maths live in ./adaptive.
 *
 * @module     mod_vimipad/collab/poll_client
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {AdaptiveConfig, nextInterval} from './adaptive';
import {Lease, PolledOperation, PollResult, ServiceTransport} from '../types';

/** Options for constructing a PollClient. */
export interface PollClientOptions {
    cmid: number;
    workspaceid: number;
    transport: ServiceTransport;
    adaptive: AdaptiveConfig;
    onOperations?: (operations: PolledOperation[]) => void;
    onPresence?: (leases: Lease[]) => void;
    onError?: (error: Error) => void;
    /** Clock, injectable for tests. Defaults to Date.now. */
    now?: () => number;
    /** Timer scheduler, injectable for tests. Defaults to setTimeout. */
    schedule?: (fn: () => void, ms: number) => number;
    /** Timer canceller, injectable for tests. Defaults to clearTimeout. */
    cancel?: (handle: number) => void;
}

/**
 * Polls poll_changes on an adaptive interval and dispatches results.
 */
export class PollClient {
    /** @type {PollClientOptions} */
    private readonly opts: PollClientOptions;

    /** @type {number} The highest revision the client has applied. */
    private revision = 0;

    /** @type {number} The current polling interval in milliseconds. */
    private interval: number;

    /** @type {boolean} Whether the loop is running. */
    private running = false;

    /** @type {number|null} The pending timer handle. */
    private handle: number | null = null;

    /**
     * @param options The client options.
     */
    constructor(options: PollClientOptions) {
        this.opts = options;
        this.interval = options.adaptive.base;
    }

    /**
     * Set the known revision (e.g. after the initial workspace load).
     *
     * @param revision The revision.
     * @returns void
     */
    public setRevision(revision: number): void {
        this.revision = revision;
    }

    /**
     * The highest revision applied so far.
     *
     * @returns The revision.
     */
    public getRevision(): number {
        return this.revision;
    }

    /**
     * The current polling interval in milliseconds.
     *
     * @returns The interval.
     */
    public getInterval(): number {
        return this.interval;
    }

    /**
     * Override the current interval (used by tests and manual control).
     *
     * @param ms The interval in milliseconds.
     * @returns void
     */
    public setInterval(ms: number): void {
        this.interval = ms;
    }

    /**
     * Perform a single poll: fetch, dispatch, and adapt the interval.
     *
     * Never throws; transport failures go to onError so the loop survives.
     *
     * @returns A promise that resolves when the poll completes.
     */
    public async pollOnce(): Promise<void> {
        const clock = this.opts.now ?? Date.now;
        const started = clock();
        try {
            const raw = await this.opts.transport('mod_vimipad_poll_changes', {
                cmid: this.opts.cmid,
                workspaceid: this.opts.workspaceid,
                sincerevision: this.revision,
            });
            const poll = raw as PollResult;
            const rttMs = clock() - started;

            const hasOperations = poll.operations.length > 0;
            if (hasOperations && this.opts.onOperations) {
                this.opts.onOperations(poll.operations);
            }
            if (this.opts.onPresence) {
                this.opts.onPresence(poll.leases);
            }

            // Advance the revision monotonically.
            if (poll.revision > this.revision) {
                this.revision = poll.revision;
            }

            const changed = hasOperations || poll.leases.length > 0;
            this.interval = nextInterval(this.opts.adaptive, this.interval, {changed, rttMs});
        } catch (e) {
            if (this.opts.onError) {
                this.opts.onError(e as Error);
            }
            // Back off on error the same way as a slow poll.
            this.interval = nextInterval(this.opts.adaptive, this.interval, {changed: false, rttMs: this.interval});
        }
    }

    /**
     * Start the polling loop.
     *
     * @returns void
     */
    public start(): void {
        if (this.running) {
            return;
        }
        this.running = true;
        this.loop();
    }

    /**
     * Stop the polling loop.
     *
     * @returns void
     */
    public stop(): void {
        this.running = false;
        if (this.handle !== null) {
            (this.opts.cancel ?? clearTimeout)(this.handle);
            this.handle = null;
        }
    }

    /**
     * Internal loop step: poll, then schedule the next poll after the interval.
     *
     * @returns void
     */
    private loop(): void {
        if (!this.running) {
            return;
        }
        void this.pollOnce().then(() => {
            if (!this.running) {
                return;
            }
            const schedule = this.opts.schedule ?? ((fn, ms) => window.setTimeout(fn, ms));
            this.handle = schedule(() => this.loop(), this.interval);
        });
    }
}
