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
 * Replays the development of a map as a "film": it steps through the revisions
 * from 1 to a target revision, reconstructing and rendering each state on the
 * read-only canvas. A play/pause control and a scrubber let the viewer run the
 * animation or jump to any step.
 *
 * @module     mod_vimipad/components/RevisionPlayer
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {ApiClient} from '../api/service';
import {CanvasView} from './CanvasView';
import {computeLayout} from '../graph/autolayout';
import {EditorState} from '../store/reducer';

interface Props {
    api: ApiClient;
    workspaceid: number;
    /** The highest revision to play up to (usually the current revision). */
    maxRevision: number;
    t: (key: string) => string;
}

const EMPTY: EditorState = {
    workspaceid: 0, revision: 0, locked: 1, profile: 'conceptmap', layoutjson: '', nodes: [], relations: [],
};

const noop = (): void => {
    // Read-only playback: element mutations are inert.
};

/** Milliseconds each revision is shown before advancing during playback. */
const STEP_MS = 900;

/**
 * The number of graph elements (nodes + relations + containers) in a state.
 *
 * @param s The state to count.
 * @returns The element count.
 */
export function elementCount(s: EditorState): number {
    return (s.nodes?.length ?? 0) + (s.relations?.length ?? 0) + (s.containers?.length ?? 0);
}

/**
 * Whether the op-log history is incomplete for this map: the live current map
 * has more elements than the replay reconstructs at its final revision, which
 * happens when elements were created before the op-log existed (or imported by
 * a legacy path). Only compared when both refer to the same workspace.
 *
 * @param live The live current state (from get_workspace).
 * @param reconstructed The reconstruction at the final revision.
 * @returns True if the replay cannot faithfully show the map's development.
 */
export function isHistoryIncomplete(live: EditorState, reconstructed: EditorState): boolean {
    if (live.workspaceid !== reconstructed.workspaceid) {
        return false;
    }
    return elementCount(live) > elementCount(reconstructed);
}

/**
 * Render the revision player.
 *
 * @param props Component props.
 * @returns The player.
 */
export function RevisionPlayer(props: Props): React.ReactElement {
    const {api, workspaceid, maxRevision, t} = props;
    const total = Math.max(1, maxRevision);
    const [current, setCurrent] = useState(total);
    const [state, setState] = useState<EditorState>(EMPTY);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [playing, setPlaying] = useState(false);
    // Fallback for maps whose element create-operations predate the op-log:
    // the replay reconstructs from the op-log, so such elements never appear.
    // When the live map has more elements than the reconstruction at the final
    // revision, the op-history is incomplete — we then show the live current
    // state with a hint instead of an unfaithful, partial animation.
    const [historyIncomplete, setHistoryIncomplete] = useState(false);
    const liveState = useRef<EditorState | null>(null);

    // Cache reconstructed states so scrubbing back and forth is instant and the
    // animation does not re-hit the service for a revision already seen.
    const cache = useRef<Map<number, EditorState>>(new Map());

    const loadRevision = useCallback(async (rev: number): Promise<void> => {
        const cached = cache.current.get(rev);
        if (cached) {
            setState(cached);
            setLoading(false);
            return;
        }
        setLoading(true);
        try {
            const ws = await api.getRevisionState(workspaceid, rev) as EditorState;
            cache.current.set(rev, ws);
            setState(ws);
            setError(null);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }, [api, workspaceid]);

    // Load whenever the current step changes.
    useEffect(() => {
        void loadRevision(current);
    }, [current, loadRevision]);

    // On mount, detect whether the op-history can faithfully replay this map.
    // Fetch the live current state and the reconstruction at the final revision
    // and compare; if the live map has more elements, mark history incomplete.
    useEffect(() => {
        let cancelled = false;
        (async () => {
            try {
                const [live, reconstructed] = await Promise.all([
                    api.getWorkspace() as Promise<EditorState>,
                    api.getRevisionState(workspaceid, total) as Promise<EditorState>,
                ]);
                if (cancelled) {
                    return;
                }
                cache.current.set(total, reconstructed);
                if (isHistoryIncomplete(live, reconstructed)) {
                    liveState.current = live;
                    setHistoryIncomplete(true);
                }
            } catch (e) {
                // A failed completeness check is non-fatal: fall back to the
                // normal replay, which still works for complete histories.
                if (!cancelled) {
                    setHistoryIncomplete(false);
                }
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [api, workspaceid, total]);

    // Playback timer: advance one revision per tick, stop at the end.
    useEffect(() => {
        if (!playing) {
            return undefined;
        }
        if (current >= total) {
            setPlaying(false);
            return undefined;
        }
        const timer = window.setTimeout(() => setCurrent(c => Math.min(total, c + 1)), STEP_MS);
        return () => window.clearTimeout(timer);
    }, [playing, current, total]);

    // When the history is incomplete, show the live current map (a faithful
    // static view) instead of an unfaithful partial animation.
    const shownState = historyIncomplete && liveState.current ? liveState.current : state;
    const shownLayout = useMemo(
        () => computeLayout(shownState.nodes, {}, shownState.relations, shownState.profile),
        [shownState.nodes, shownState.relations, shownState.profile]
    );

    const togglePlay = useCallback(() => {
        // Restart from the beginning if we are at the end.
        if (!playing && current >= total) {
            setCurrent(1);
        }
        setPlaying(p => !p);
    }, [playing, current, total]);

    return (
        <div className="vimipad-revision-player">
            {historyIncomplete ? (
                <div className="alert alert-info vimipad-revision-incomplete" role="status">
                    {t('revision:historyincomplete')}
                </div>
            ) : (
                <div className="vimipad-revision-player-controls">
                    <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={togglePlay}
                        aria-label={playing ? t('revision:pause') : t('revision:play')}
                    >
                        <i className={`fa-solid ${playing ? 'fa-pause' : 'fa-play'}`} aria-hidden="true" />{' '}
                        {playing ? t('revision:pause') : t('revision:play')}
                    </button>
                    <input
                        type="range"
                        className="vimipad-revision-scrubber form-range"
                        min={1}
                        max={total}
                        value={current}
                        aria-label={t('revision:scrubber')}
                        onChange={(e) => {
                            setPlaying(false);
                            setCurrent(Number(e.target.value));
                        }}
                    />
                    <span className="vimipad-revision-counter text-muted small">
                        {t('journal:revisiontitle')} {current} / {total}
                    </span>
                </div>
            )}

            {error && <div className="alert alert-danger" role="alert">{error}</div>}

            <div className="vimipad-revision-player-stage" aria-busy={loading}>
                <CanvasView
                    state={shownState}
                    layout={shownLayout}
                    profile={shownState.profile}
                    formconfig={shownState.formconfig}
                    sizes={{}}
                    disabled={true}
                    onNodeMoved={noop}
                    t={t}
                />
            </div>
        </div>
    );
}
