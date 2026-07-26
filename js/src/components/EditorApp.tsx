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
 * The editor application shell.
 *
 * M3 scope: a graphical canvas (draggable nodes, auto-layout, persisted
 * positions) and an equal-rights relation list view with retarget-by-dropdown
 * and drag-and-drop. Both views share one optimistic state reconciled against
 * the server revision; layout is persisted separately (non-revisioned).
 *
 * @module     mod_vimipad/components/EditorApp
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useEffect, useMemo, useReducer, useState} from 'react';
import {ApiClient} from '../api/service';
import {computeLayout} from '../graph/autolayout';
import {EditorState, reduce} from '../store/reducer';
import {CanvasView} from './CanvasView';
import {RelationListView} from './RelationListView';
import {LayoutMap, Point, PolledOperation} from '../types';
import {useCollaboration} from '../collab/use_collaboration';
import {operationToAction} from '../collab/apply_remote';

interface Props {
    api: ApiClient;
    t: (key: string) => string;
}

type ViewMode = 'canvas' | 'list';

const EMPTY: EditorState = {
    workspaceid: 0, revision: 0, locked: 0, profile: 'conceptmap', layoutjson: '', nodes: [], relations: [],
};

/**
 * Parse a stored layout JSON string into a position map.
 *
 * @param json The layout JSON string.
 * @returns The parsed position map, or empty on failure.
 */
function parseLayout(json: string): LayoutMap {
    if (!json) {
        return {};
    }
    try {
        const parsed = JSON.parse(json);
        return (parsed && typeof parsed === 'object') ? parsed as LayoutMap : {};
    } catch {
        return {};
    }
}

/**
 * The editor root component.
 *
 * @param props Component props.
 * @returns The rendered editor.
 */
export function EditorApp(props: Props): React.ReactElement {
    const {api, t} = props;
    const [state, dispatch] = useReducer(reduce, EMPTY);
    const [view, setView] = useState<ViewMode>('canvas');
    const [stored, setStored] = useState<LayoutMap>({});
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [nodeLabel, setNodeLabel] = useState('');
    const [relSource, setRelSource] = useState('');
    const [relTarget, setRelTarget] = useState('');
    const [relLabel, setRelLabel] = useState('');

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const ws = await api.getWorkspace();
            dispatch({kind: 'load', state: ws});
            setStored(parseLayout(ws.layoutjson));
            setError(null);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }, [api]);

    useEffect(() => {
        load();
    }, [load]);

    // Feed operations polled from collaborators into the local state. Layout
    // changes travel on the separate layout channel and are reconciled below.
    const applyRemoteOperations = useCallback((operations: PolledOperation[]) => {
        operations.forEach((op) => {
            const action = operationToAction(op);
            if (action) {
                dispatch(action);
            }
        });
    }, []);

    const currentUserId = useMemo(() => {
        const cfg = (window as unknown as {M?: {cfg?: {userId?: number}}}).M?.cfg;
        return cfg?.userId ?? 0;
    }, []);

    const collab = useCollaboration(
        api,
        state.workspaceid,
        currentUserId,
        state.collab,
        applyRemoteOperations,
        (e) => setError(e.message)
    );

    const layout = useMemo(() => computeLayout(state.nodes, stored), [state.nodes, stored]);
    const disabled = busy || loading || state.locked === 1;

    const runOperation = useCallback(async (
        type: string,
        payload: Record<string, unknown>,
        optimistic: () => void
    ): Promise<{revision: number; stableid: string} | null> => {
        setBusy(true);
        try {
            const res = await api.applyOperation(state.workspaceid, state.revision, type, payload);
            optimistic();
            dispatch({kind: 'setRevision', revision: res.revision});
            setError(null);
            return res;
        } catch (e) {
            setError((e as Error).message);
            await load();
            return null;
        } finally {
            setBusy(false);
        }
    }, [api, state.workspaceid, state.revision, load]);

    const addNode = useCallback(async () => {
        const label = nodeLabel.trim();
        if (!label) {
            return;
        }
        const res = await runOperation('node_create', {type: 'concept', label}, () => undefined);
        if (res) {
            dispatch({kind: 'addNode', node: {stableid: res.stableid, type: 'concept', label}});
            setNodeLabel('');
        }
    }, [runOperation, nodeLabel]);

    const addRelation = useCallback(async () => {
        if (!relSource || !relTarget || relSource === relTarget) {
            return;
        }
        const label = relLabel.trim();
        const res = await runOperation('relation_create',
            {sourceid: relSource, targetid: relTarget, type: 'related', label}, () => undefined);
        if (res) {
            dispatch({
                kind: 'addRelation',
                relation: {
                    stableid: res.stableid, sourceid: relSource, targetid: relTarget,
                    type: 'related', label, direction: 1,
                },
            });
            setRelLabel('');
        }
    }, [runOperation, relSource, relTarget, relLabel]);

    const deleteRelation = useCallback(async (stableid: string) => {
        await runOperation('relation_delete', {stableid},
            () => dispatch({kind: 'deleteRelation', stableid}));
    }, [runOperation]);

    const deleteNode = useCallback(async (stableid: string) => {
        await runOperation('node_delete', {stableid},
            () => dispatch({kind: 'deleteNode', stableid}));
    }, [runOperation]);

    const renameNode = useCallback(async (stableid: string, label: string) => {
        const trimmed = label.trim();
        if (!trimmed) {
            return;
        }
        await runOperation('node_update', {stableid, label: trimmed},
            () => dispatch({kind: 'updateNode', stableid, label: trimmed}));
    }, [runOperation]);

    const renameRelation = useCallback(async (stableid: string, label: string) => {
        await runOperation('relation_update', {stableid, label: label.trim()},
            () => dispatch({kind: 'updateRelation', stableid, label: label.trim()}));
    }, [runOperation]);

    const retarget = useCallback(async (stableid: string, change: {sourceid?: string; targetid?: string}) => {
        const payload: Record<string, unknown> = {stableid};
        if (change.sourceid) {
            payload.newsource = change.sourceid;
        }
        if (change.targetid) {
            payload.newtarget = change.targetid;
        }
        await runOperation('relation_retarget', payload,
            () => dispatch({kind: 'retargetRelation', stableid, ...change}));
    }, [runOperation]);

    const onNodeMoved = useCallback(async (stableid: string, point: Point) => {
        const next = {...stored, [stableid]: point};
        setStored(next);
        try {
            await api.saveLayout(state.workspaceid, JSON.stringify(next));
        } catch (e) {
            setError((e as Error).message);
        }
    }, [api, state.workspaceid, stored]);

    const submit = useCallback(async () => {
        // eslint-disable-next-line no-alert
        if (!window.confirm(t('editor:submitconfirm'))) {
            return;
        }
        setBusy(true);
        try {
            await api.createSnapshot(state.workspaceid);
            dispatch({kind: 'load', state: {...state, locked: 1}});
            setError(null);
        } catch (e) {
            setError((e as Error).message);
            await load();
        } finally {
            setBusy(false);
        }
    }, [api, state, load, t]);

    if (loading) {
        return <div className="vimipad-editor-loading">{t('editor:loading')}</div>;
    }

    return (
        <div className="vimipad-editor">
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
            {state.locked === 1 && (
                <div className="alert alert-warning" role="status">{t('editor:locked')}</div>
            )}

            <ul className="nav nav-tabs mb-3" role="tablist">
                <li className="nav-item" role="presentation">
                    <button
                        type="button"
                        className={`nav-link ${view === 'canvas' ? 'active' : ''}`}
                        aria-selected={view === 'canvas'}
                        role="tab"
                        onClick={() => setView('canvas')}
                    >
                        {t('editor:canvasview')}
                    </button>
                </li>
                <li className="nav-item" role="presentation">
                    <button
                        type="button"
                        className={`nav-link ${view === 'list' ? 'active' : ''}`}
                        aria-selected={view === 'list'}
                        role="tab"
                        onClick={() => setView('list')}
                    >
                        {t('editor:listview')}
                    </button>
                </li>
            </ul>

            <fieldset disabled={disabled} className="mb-3">
                <legend className="h6">{t('editor:addnode')}</legend>
                <div className="form-inline">
                    <label className="sr-only" htmlFor="vimipad-node-label">{t('editor:nodelabel')}</label>
                    <input
                        id="vimipad-node-label"
                        type="text"
                        className="form-control mr-2"
                        value={nodeLabel}
                        placeholder={t('editor:nodelabel')}
                        onChange={e => setNodeLabel(e.target.value)}
                    />
                    <button type="button" className="btn btn-primary" onClick={addNode}>
                        {t('editor:add')}
                    </button>
                </div>
            </fieldset>

            <fieldset disabled={disabled || state.nodes.length < 2} className="mb-3">
                <legend className="h6">{t('editor:addrelation')}</legend>
                <div className="form-inline">
                    <label className="sr-only" htmlFor="vimipad-rel-source">{t('editor:subject')}</label>
                    <select
                        id="vimipad-rel-source"
                        className="form-control mr-2"
                        value={relSource}
                        onChange={e => setRelSource(e.target.value)}
                    >
                        <option value="">{t('editor:subject')}</option>
                        {state.nodes.map(n => <option key={n.stableid} value={n.stableid}>{n.label}</option>)}
                    </select>
                    <input
                        type="text"
                        className="form-control mr-2"
                        value={relLabel}
                        placeholder={t('editor:relation')}
                        onChange={e => setRelLabel(e.target.value)}
                    />
                    <label className="sr-only" htmlFor="vimipad-rel-target">{t('editor:object')}</label>
                    <select
                        id="vimipad-rel-target"
                        className="form-control mr-2"
                        value={relTarget}
                        onChange={e => setRelTarget(e.target.value)}
                    >
                        <option value="">{t('editor:object')}</option>
                        {state.nodes.map(n => <option key={n.stableid} value={n.stableid}>{n.label}</option>)}
                    </select>
                    <button type="button" className="btn btn-primary" onClick={addRelation}>
                        {t('editor:add')}
                    </button>
                </div>
            </fieldset>

            {view === 'canvas' ? (
                <CanvasView
                    state={state}
                    layout={layout}
                    disabled={disabled}
                    onNodeMoved={onNodeMoved}
                    onDeleteNode={deleteNode}
                    onDeleteRelation={deleteRelation}
                    onRenameNode={renameNode}
                    onRenameRelation={renameRelation}
                    t={t}
                    isLockedByOther={collab.isLockedByOther}
                    beginEdit={collab.beginEdit}
                    endEdit={collab.endEdit}
                />
            ) : (
                <RelationListView
                    state={state}
                    disabled={disabled}
                    onDeleteRelation={deleteRelation}
                    onRetarget={retarget}
                    t={t}
                />
            )}

            <p className="text-muted small mt-2">{t('editor:revision')}: {state.revision}</p>

            {state.locked !== 1 && (
                <div className="vimipad-submit-bar mt-3">
                    <button
                        type="button"
                        className="btn btn-success"
                        disabled={busy || loading || state.nodes.length === 0}
                        onClick={submit}
                    >
                        {t('editor:submit')}
                    </button>
                </div>
            )}
        </div>
    );
}
