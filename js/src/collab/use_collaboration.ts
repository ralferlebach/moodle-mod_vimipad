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
 * React hook wiring the collaboration clients into the editor.
 *
 * Starts the poll loop, feeds server operations back into the editor via a
 * callback, exposes presence (which elements other users hold) for the UI to
 * mark as locked, and drives the lock heartbeat. The pure logic lives in
 * ./poll_client, ./lock_client, ./adaptive and ./tween; this hook is the thin
 * React glue around them.
 *
 * @module     mod_vimipad/collab/use_collaboration
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {ApiClient} from '../api/service';
import {AdaptiveConfig} from './adaptive';
import {LockClient} from './lock_client';
import {PollClient} from './poll_client';
import {CollabConfig, Lease, PolledOperation} from '../types';

/** A map from "type:stableid" to the user id holding it. */
export type PresenceMap = Record<string, number>;

/** What the hook returns to the editor. */
export interface Collaboration {
    /** Presence: which elements are currently held, and by whom. */
    presence: PresenceMap;
    /** True if the given element is held by someone other than us. */
    isLockedByOther: (targettype: string, stableid: string) => boolean;
    /** Attempt to take a lock on drag-start; returns whether we got it. */
    beginEdit: (targettype: string, stableid: string) => Promise<boolean>;
    /** Release a lock on drag-end. */
    endEdit: (targettype: string, stableid: string) => Promise<void>;
}

/** Build the composite presence key. */
const keyOf = (targettype: string, stableid: string): string => `${targettype}:${stableid}`;

/**
 * Derive an adaptive config from the server-provided collaboration settings.
 *
 * @param collab The collaboration settings, if present.
 * @returns An adaptive configuration with sane fallbacks.
 */
function toAdaptive(collab?: CollabConfig): AdaptiveConfig {
    const base = collab?.pollinterval ?? 1000;
    return {
        base,
        min: collab?.pollmin ?? 1000,
        max: collab?.pollmax ?? 10000,
        adaptive: (collab?.polladaptive ?? 1) === 1,
    };
}

/**
 * Wire collaboration into the editor.
 *
 * @param api The API client.
 * @param workspaceid The workspace id (0 disables collaboration).
 * @param currentUserId The current user's id, to distinguish own vs others' locks.
 * @param collab The collaboration settings from the workspace load.
 * @param onOperations Called with operations polled from the server.
 * @param onError Called on transport errors.
 * @returns The collaboration handle.
 */
export function useCollaboration(
    api: ApiClient,
    workspaceid: number,
    currentUserId: number,
    collab: CollabConfig | undefined,
    onOperations: (operations: PolledOperation[]) => void,
    onError?: (error: Error) => void
): Collaboration {
    const [presence, setPresence] = useState<PresenceMap>({});
    const pollRef = useRef<PollClient | null>(null);
    const lockRef = useRef<LockClient | null>(null);

    // Keep the latest operations handler without restarting the loop.
    const opsHandler = useRef(onOperations);
    opsHandler.current = onOperations;

    const adaptive = useMemo(() => toAdaptive(collab), [collab]);

    useEffect(() => {
        if (!workspaceid) {
            return undefined;
        }

        const lock = api.createLockClient(workspaceid, onError);
        lockRef.current = lock;

        // Under Behat acceptance testing, skip the continuous background poll
        // loop. The scenarios are single-user and do not exercise live
        // collaboration; continuous fetches would only add flakiness and load,
        // and could interfere with Behat's page-stability detection. Locking
        // (beginEdit/endEdit) stays available for any interaction under test.
        const behat = Boolean(
            (window as unknown as {M?: {cfg?: {behatsiterunning?: boolean}}}).M?.cfg?.behatsiterunning
        );
        if (behat) {
            return () => {
                lockRef.current = null;
            };
        }

        const poll = api.createPollClient(workspaceid, adaptive, {
            onOperations: (ops) => opsHandler.current(ops),
            onPresence: (leases: Lease[]) => {
                const next: PresenceMap = {};
                leases.forEach((l) => {
                    next[keyOf(l.targettype, l.targetstableid)] = l.userid;
                });
                setPresence(next);
                // Renew our own held leases on the same tick (heartbeat).
                void lock.heartbeat();
            },
            onError,
        });
        pollRef.current = poll;
        poll.start();

        return () => {
            poll.stop();
            pollRef.current = null;
            lockRef.current = null;
        };
    }, [api, workspaceid, adaptive, onError]);

    const isLockedByOther = useCallback((targettype: string, stableid: string): boolean => {
        const holder = presence[keyOf(targettype, stableid)];
        return holder !== undefined && holder !== currentUserId;
    }, [presence, currentUserId]);

    const beginEdit = useCallback(async (targettype: string, stableid: string): Promise<boolean> => {
        const lock = lockRef.current;
        if (!lock) {
            return true;
        }
        return lock.acquire(targettype, stableid);
    }, []);

    const endEdit = useCallback(async (targettype: string, stableid: string): Promise<void> => {
        const lock = lockRef.current;
        if (lock) {
            await lock.release(targettype, stableid);
        }
    }, []);

    return {presence, isLockedByOther, beginEdit, endEdit};
}
