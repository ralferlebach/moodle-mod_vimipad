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
import {EditorState} from '../store/reducer';

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
 * The state comes from the get_revision_state web service (op-log replay) and
 * carries no stored positions, so the graph is laid out automatically.
 *
 * Unlike RevisionPlayer, this viewer shows one specific historical revision on
 * purpose, so it does NOT fall back to the live current state when the op-log
 * is incomplete: substituting today's map would misrepresent the moment the
 * journal entry was written. It faithfully shows whatever that revision
 * reconstructs to.
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
    const [view, setView] = useState<ViewMode>('canvas');

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        api.getRevisionState(workspaceid, revision)
            .then((ws) => {
                if (!cancelled) {
                    setState(ws as EditorState);
                    setError(null);
                }
            })
            .catch((e) => {
                if (!cancelled) {
                    setError((e as Error).message);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
    }, [api, workspaceid, revision]);

    const layout = useMemo(
        () => computeLayout(state.nodes, {}, state.relations, state.profile),
        [state.nodes, state.relations, state.profile]
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
                    onDeleteRelation={noop}
                    onRetarget={noop}
                    onRenameRelation={noop}
                    t={t}
                />
            )}
        </div>
    );
}
