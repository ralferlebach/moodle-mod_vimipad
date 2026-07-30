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
 * Hook that fetches the non-blocking constraint status after edits, debounced.
 *
 * It coalesces rapid changes (a burst of operations advances the revision many
 * times) into a single request, and applies a latest-request-wins guard so a
 * slow earlier response cannot overwrite a newer one. Fetching is skipped while
 * disabled (read-only, submitted, or no workspace yet), and a failed fetch is
 * swallowed — hints are advisory and must never disrupt editing.
 *
 * @module     mod_vimipad/hooks/use_constraint_hints
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from 'react';
import {ApiClient} from '../api/service';
import {ConstraintStatus} from '../types';

/** Milliseconds to wait after the last change before fetching. */
export const HINT_DEBOUNCE_MS = 600;

/**
 * @param api The API client.
 * @param workspaceid The workspace id (0 before load).
 * @param revision The current revision; a change re-triggers a (debounced) fetch.
 * @param enabled Whether hints should be fetched at all.
 * @returns The latest constraint status, or null before the first result.
 */
export function useConstraintHints(
    api: ApiClient,
    workspaceid: number,
    revision: number,
    enabled: boolean
): ConstraintStatus | null {
    const [status, setStatus] = useState<ConstraintStatus | null>(null);
    // Monotonic request id so a stale response cannot clobber a newer one.
    const seq = useRef(0);

    useEffect(() => {
        if (!enabled || workspaceid <= 0) {
            return undefined;
        }
        const requestId = ++seq.current;
        const timer = setTimeout(() => {
            api.getConstraintStatus(workspaceid)
                .then((result) => {
                    if (requestId === seq.current) {
                        setStatus(result);
                    }
                })
                .catch(() => {
                    // Advisory only: ignore failures.
                });
        }, HINT_DEBOUNCE_MS);
        return () => clearTimeout(timer);
    }, [api, workspaceid, revision, enabled]);

    return status;
}
