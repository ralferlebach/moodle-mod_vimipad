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
import {ReplayEngine, Operation} from '../graph/reconstruct';
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

/** A deterministic per-element fingerprint over the fields that affect rendering. */
function stateFingerprint(s: EditorState): string {
    const parts: string[] = [];
    for (const n of s.nodes ?? []) {
        parts.push('n|' + n.stableid + '|' + n.type + '|' + n.label + '|'
            + (n.content ?? '') + '|' + (n.metadatajson ?? ''));
    }
    for (const r of s.relations ?? []) {
        parts.push('r|' + r.stableid + '|' + r.type + '|' + r.sourceid + '|' + r.targetid
            + '|' + String(r.direction) + '|' + r.label + '|' + (r.metadatajson ?? ''));
    }
    for (const c of s.containers ?? []) {
        parts.push('c|' + c.stableid + '|' + c.type + '|' + c.label + '|'
            + (c.geometryjson ?? '') + '|' + (c.metadatajson ?? ''));
    }
    parts.sort();
    return parts.join('\n');
}

/**
 * Whether the op-log history is incomplete for this map: the live current map
 * is not an exact reproduction of what the replay reconstructs at its final
 * revision. This happens when elements were created before the op-log existed
 * (or imported by a legacy path). Only compared when both refer to the same
 * workspace.
 *
 * The comparison is a content fingerprint over every element's rendering fields
 * (type, label, content, endpoints, direction, geometry, metadata), not merely
 * the stable-id sets, so a mismatch that keeps the same ids but changes content
 * (e.g. a rename or retarget the replay is missing) is still detected.
 *
 * @param live The live current state (from get_workspace).
 * @param reconstructed The reconstruction at the final revision.
 * @returns True if the replay cannot faithfully show the map's development.
 */
export function isHistoryIncomplete(live: EditorState, reconstructed: EditorState): boolean {
    if (live.workspaceid !== reconstructed.workspaceid) {
        return false;
    }
    return stateFingerprint(live) !== stateFingerprint(reconstructed);
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
    // The highest revision the replay can faithfully show. Equals `total` when
    // the whole op-log loaded; lower when a protection limit truncated loading.
    const [effectiveMax, setEffectiveMax] = useState(total);
    // True when the op-log was only partially loaded (history truncated).
    const [truncated, setTruncated] = useState(false);
    const liveState = useRef<EditorState | null>(null);
    // Held across the on-demand frame construction so shown states carry the
    // right profile/form config without re-reading the live state each time.
    const profileRef = useRef<string>('conceptmap');
    const formconfigRef = useRef<unknown>(undefined);

    // A bounded, checkpoint-based replay engine reconstructs frames on demand
    // from the operation log — no full frame is retained per revision, so peak
    // memory does not grow with history length times map size.
    const engine = useRef<ReplayEngine | null>(null);

    const showAt = useCallback((rev: number): void => {
        const eng = engine.current;
        if (eng) {
            const frame = eng.stateAt(rev);
            setState({
                workspaceid, revision: rev, locked: 1,
                profile: profileRef.current, formconfig: formconfigRef.current, layoutjson: '',
                nodes: frame.nodes, relations: frame.relations, containers: frame.containers,
            } as EditorState);
        }
        setLoading(false);
    }, [workspaceid]);

    // Show whenever the current step changes (on-demand reconstruction).
    useEffect(() => {
        showAt(current);
    }, [current, showAt]);

    // On mount: fetch the whole op-log once (paginated to bound each request)
    // and the live current state for the completeness check, then build the
    // replay engine.
    useEffect(() => {
        let cancelled = false;
        (async () => {
            setLoading(true);
            try {
                const live = await api.getWorkspace() as EditorState;
                if (cancelled) {
                    return;
                }
                profileRef.current = live.profile;
                formconfigRef.current = (live as {formconfig?: unknown}).formconfig;

                // Page through the operation log so no single request is
                // unbounded; stop at a safe hard ceiling on total operations.
                // If we stop before the end, the history is only partially
                // loaded and MUST NOT be presented as complete up to `total`.
                const operations: Operation[] = [];
                let from = 1;
                let guard = 0;
                let truncated = false;
                let highestLoaded = 0;
                const MAX_TOTAL_OPS = 20000;
                for (;;) {
                    const batch = await api.getOperations(workspaceid, total, from);
                    if (cancelled) {
                        return;
                    }
                    operations.push(...batch.operations);
                    if (batch.operations.length > 0) {
                        highestLoaded = batch.operations[batch.operations.length - 1].revision;
                    }
                    if (!batch.hasmore || batch.nextrevision <= 0) {
                        // Reached the end of the log: everything up to `total` is
                        // loaded (any trailing revisions with no ops are empty).
                        highestLoaded = total;
                        break;
                    }
                    if (operations.length >= MAX_TOTAL_OPS || ++guard > 200) {
                        // Stopped early by a protection limit: the history is
                        // incomplete beyond highestLoaded.
                        truncated = true;
                        break;
                    }
                    from = batch.nextrevision;
                }

                // Build the engine only up to the highest revision we actually
                // loaded, so it never treats unloaded history as empty.
                const cap = truncated ? highestLoaded : total;
                engine.current = new ReplayEngine(operations, cap);
                setEffectiveMax(cap);
                if (truncated) {
                    // Partial history: clamp playback and warn explicitly. We do
                    // NOT claim completeness against the live state.
                    liveState.current = live;
                    setTruncated(true);
                    setCurrent(cap);
                } else {
                    // Full history loaded: verify it faithfully reproduces the
                    // live map (content fingerprint, not just id sets).
                    const reconstructed = {
                        workspaceid, revision: total, locked: 1, profile: live.profile,
                        ...engine.current.stateAt(total),
                    } as EditorState;
                    if (isHistoryIncomplete(live, reconstructed)) {
                        liveState.current = live;
                        setHistoryIncomplete(true);
                    }
                }
                showAt(Math.min(current, cap));
                setError(null);
            } catch (e) {
                if (!cancelled) {
                    setError((e as Error).message);
                    setHistoryIncomplete(false);
                    setLoading(false);
                }
            }
        })();
        return () => {
            cancelled = true;
        };
        // Build once for the given workspace/bound; `current` is intentionally
        // excluded so refetching does not happen on every scrub.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [api, workspaceid, total]);

    // Playback timer: advance one revision per tick, stop at the end.
    useEffect(() => {
        if (!playing) {
            return undefined;
        }
        if (current >= effectiveMax) {
            setPlaying(false);
            return undefined;
        }
        const timer = window.setTimeout(() => setCurrent(c => Math.min(effectiveMax, c + 1)), STEP_MS);
        return () => window.clearTimeout(timer);
    }, [playing, current, effectiveMax]);

    // When the history is incomplete, show the live current map (a faithful
    // static view) instead of an unfaithful partial animation.
    const shownState = historyIncomplete && liveState.current ? liveState.current : state;
    const shownLayout = useMemo(
        () => computeLayout(shownState.nodes, {}, shownState.relations, shownState.profile),
        [shownState.nodes, shownState.relations, shownState.profile]
    );

    const togglePlay = useCallback(() => {
        // Restart from the beginning if we are at the end.
        if (!playing && current >= effectiveMax) {
            setCurrent(1);
        }
        setPlaying(p => !p);
    }, [playing, current, effectiveMax]);

    return (
        <div className="vimipad-revision-player">
            {truncated && (
                <div className="alert alert-warning vimipad-revision-truncated" role="status">
                    {t('revision:historytruncated')}
                </div>
            )}
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
                        max={effectiveMax}
                        value={current}
                        aria-label={t('revision:scrubber')}
                        onChange={(e) => {
                            setPlaying(false);
                            setCurrent(Number(e.target.value));
                        }}
                    />
                    <span className="vimipad-revision-counter text-muted small">
                        {t('journal:revisiontitle')} {current} / {effectiveMax}
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
