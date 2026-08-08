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

import React, {useEffect, useMemo, useState} from 'react';
import {ApiClient} from '../api/service';
import {CanvasView} from './CanvasView';
import {RelationListView} from './RelationListView';
import {computeLayout} from '../graph/autolayout';
import {decodeLayout} from '../canvas/layout_codec';
import {EditorState} from '../store/reducer';
import {isHistoryIncomplete} from './RevisionPlayer';

interface Props {
    api: ApiClient;
    workspaceid: number;
    revision: number;
    t: (key: string) => string;
}

type ViewMode = 'canvas' | 'list';

const EMPTY: EditorState = {
    workspaceid: 0, revision: 0, locked: 1, profile: 'conceptmap', layoutjson: '', nodes: [], relations: [],
};

const noop = (): void => {
    // Read-only: element mutations are intentionally inert.
};

/**
 * Render a reconstructed, read-only snapshot of a map at a past revision.
 *
 * The state comes from the get_revision_state web service (op-log replay). When
 * the op-log is complete this viewer shows exactly the requested historical
 * revision — even an empty one — so it faithfully represents the moment a
 * journal entry was written.
 *
 * When the op-log is incomplete, however (elements created before the op-log
 * existed, or imported by a legacy path, have no create-operations to replay),
 * a faithful past state is impossible and the reconstruction would be missing
 * those elements entirely. In that case — detected exactly as RevisionPlayer
 * does, by fingerprinting the full replay against the live map — the viewer
 * shows the current map with a clear notice instead of an empty canvas.
 *
 * @param props Component props.
 * @returns The rendered viewer.
 *
 * @module mod_vimipad/components/RevisionViewer
 */
export function RevisionViewer(props: Props): React.ReactElement {
    const {api, workspaceid, revision, t} = props;
    const [state, setState] = useState<EditorState>(EMPTY);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [incomplete, setIncomplete] = useState(false);
    const [view, setView] = useState<ViewMode>('canvas');

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        (async () => {
            try {
                // Detect an incomplete op-log the same way the player does: does
                // replaying the whole log reproduce the live map? Compare the live
                // state to the reconstruction at the latest revision.
                const live = await api.getWorkspace() as EditorState;
                const latest = live.revision ?? revision;
                const reconLatest = await api.getRevisionState(workspaceid, latest) as EditorState;
                if (cancelled) {
                    return;
                }
                if (isHistoryIncomplete(live, reconLatest)) {
                    setIncomplete(true);
                    setState(live);
                    setError(null);
                    return;
                }
                // History is complete: show the requested revision faithfully.
                const reconR = latest === revision
                    ? reconLatest
                    : await api.getRevisionState(workspaceid, revision) as EditorState;
                if (cancelled) {
                    return;
                }
                setIncomplete(false);
                setState(reconR);
                setError(null);
            } catch (e) {
                if (!cancelled) {
                    setError((e as Error).message);
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        })();
        return () => {
            cancelled = true;
        };
    }, [api, workspaceid, revision]);

    const layout = useMemo(
        () => {
            // Use the historical node layout recorded for this revision, so the
            // past map renders with the topology it actually had. Nodes without a
            // recorded position (e.g. from before layout history existed) fall
            // back to the deterministic auto-layout.
            const stored = decodeLayout(state.layoutjson ?? '').positions;
            return computeLayout(state.nodes, stored, state.relations, state.profile);
        },
        [state.nodes, state.relations, state.profile, state.layoutjson]
    );

    if (loading) {
        return <div className="vimipad-editor-loading">{t('editor:loading')}</div>;
    }
    if (error) {
        return <div className="alert alert-danger" role="alert">{error}</div>;
    }

    return (
        <div className="vimipad-revision-viewer">
            <div className="alert alert-info" role="status">
                {t('journal:revisiontitle')} {revision}
            </div>
            {incomplete && (
                <div className="alert alert-info vimipad-revision-incomplete" role="status">
                    {t('revision:historyincomplete')}
                </div>
            )}
            <ul className="nav nav-tabs mb-2" role="tablist">
                <li className="nav-item" role="presentation">
                    <button
                        type="button"
                        className={`nav-link ${view === 'canvas' ? 'active' : ''}`}
                        onClick={() => setView('canvas')}
                    >
                        {t('editor:canvasview')}
                    </button>
                </li>
                <li className="nav-item" role="presentation">
                    <button
                        type="button"
                        className={`nav-link ${view === 'list' ? 'active' : ''}`}
                        onClick={() => setView('list')}
                    >
                        {t('editor:listview')}
                    </button>
                </li>
            </ul>
            {view === 'canvas' ? (
                <CanvasView
                    state={state}
                    layout={layout}
                    profile={state.profile}
                    formconfig={state.formconfig}
                    sizes={{}}
                    disabled={true}
                    onNodeMoved={noop}
                    t={t}
                />
            ) : (
                <RelationListView
                    state={state}
                    disabled={true}
                    enforced={true}
                    onDeleteRelation={noop}
                    onRetarget={noop}
                    onRenameRelation={noop}
                    t={t}
                />
            )}
        </div>
    );
}
