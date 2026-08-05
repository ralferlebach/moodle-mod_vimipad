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

import React, {useCallback, useEffect, useMemo, useReducer, useRef, useState} from 'react';
import {ApiClient} from '../api/service';
import {CANVAS_HEIGHT, CANVAS_WIDTH, clampToCanvas, computeLayout} from '../graph/autolayout';
import {refineArrangement} from '../graph/refine/refine_arrange';
import {
    ContainerBox, parseGeometry, serializeGeometry, isNodePinnedForRearrange,
} from '../canvas/container_geometry';
import {computeContentBounds, downloadCanvasPdf, downloadCanvasPng, downloadCanvasSvg, extractMapData} from '../canvas/svg_export';
import {EditorState, reduce} from '../store/reducer';
import {History, HistoryEntry, OpSpec} from '../store/history';
import {CanvasView} from './CanvasView';
import {JournalPanel} from './JournalPanel';
import {RelationListView} from './RelationListView';
import {FA, Icon} from '../canvas/icons';
import {LayoutMap, Point, PolledOperation, Size, SizeMap, VimiNode, VimiRelation} from '../types';
import {decodeLayout, encodeLayout} from '../canvas/layout_codec';
import {isGroupLocked} from '../canvas/element_lock';
import {useCollaboration} from '../collab/use_collaboration';
import {useConstraintHints} from '../hooks/use_constraint_hints';
import {ConstraintBanner} from './ConstraintBanner';
import {LockKind} from './LockPanel';
import {operationToAction} from '../collab/apply_remote';

/**
 * Operation that recreates a node (used to undo a deletion / redo a creation).
 *
 * @param node The node to reconstruct.
 * @returns The node_create op spec.
 */
function nodeCreateSpec(node: VimiNode): OpSpec {
    const payload: Record<string, unknown> = {
        stableid: node.stableid, type: node.type, label: node.label,
    };
    if (node.content !== undefined) {
        payload.content = node.content;
    }
    if (node.metadatajson !== undefined) {
        payload.metadatajson = node.metadatajson;
    }
    return {type: 'node_create', payload};
}

/**
 * Operation that recreates a relation (used to undo a deletion).
 *
 * @param rel The relation to reconstruct.
 * @returns The relation_create op spec.
 */
function relationCreateSpec(rel: VimiRelation): OpSpec {
    return {
        type: 'relation_create',
        payload: {
            stableid: rel.stableid, sourceid: rel.sourceid, targetid: rel.targetid,
            type: rel.type, label: rel.label, direction: rel.direction,
        },
    };
}

interface Props {
    api: ApiClient;
    t: (key: string) => string;
    /** Active group id for group collaboration mode (0 = auto-select). */
    groupid?: number;
    /** Which view opens first (set by the surrounding Moodle tab). */
    initialView?: ViewMode;
    /** Owner user to view read-only (0 = self). */
    targetUserid?: number;
    /** Site-configured solver iteration ceiling for the Arrange action. */
    arrangeIterations?: number;
}

type ViewMode = 'canvas' | 'list' | 'tools';

const EMPTY: EditorState = {
    workspaceid: 0, revision: 0, locked: 0, profile: 'conceptmap', layoutjson: '', nodes: [], relations: [],
};



/**
 * The editor root component.
 *
 * @param props Component props.
 * @returns The rendered editor.
 */
export function EditorApp(props: Props): React.ReactElement {
    const {api, t, groupid = 0, initialView = 'canvas', targetUserid = 0, arrangeIterations} = props;
    const [state, dispatch] = useReducer(reduce, EMPTY);
    const view = initialView;
    const [stored, setStored] = useState<LayoutMap>({});
    const [sizes, setSizes] = useState<SizeMap>({});
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [nodeLabel, setNodeLabel] = useState('');
    const [relSource, setRelSource] = useState('');
    const [relTarget, setRelTarget] = useState('');
    const [relLabel, setRelLabel] = useState('');
    const [drawingContainer, setDrawingContainer] = useState(false);
    const [lockMode, setLockMode] = useState(false);

    // Keep the API client's lock-enforcement flag in sync with the lock-mode
    // toggle, so every mutating call (operation and layout) tells the server
    // whether to enforce template locks against this caller's own edits.
    useEffect(() => {
        api.setEnforceLocks(lockMode);
    }, [api, lockMode]);

    // Undo/redo. In a server-authoritative editor an undo is the inverse
    // operation sent to the server, not a local rollback (see store/history).
    const rootRef = useRef<HTMLDivElement>(null);
    // Absolute export endpoint URL. A relative "export.php" can resolve wrongly
    // depending on the Moodle base URL / subdirectory, so anchor it at wwwroot.
    const exportBase = useMemo(() => {
        const cfg = (window as unknown as {M?: {cfg?: {wwwroot?: string}}}).M?.cfg;
        return `${cfg?.wwwroot ?? ''}/mod/vimipad/export.php`;
    }, []);
    const historyRef = useRef(new History());
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);
    // Polite screen-reader announcements for actions with little visual feedback.
    const [status, setStatus] = useState('');
    const statusTick = useRef(false);
    // Guards Arrange against re-entry: the action persists a layout save and a
    // sequence of container_update operations against the current revision, so a
    // second press while the first is still awaiting would interleave op-batches
    // on a stale revision and corrupt container membership. One press at a time.
    const arrangingRef = useRef(false);
    const [arranging, setArranging] = useState(false);
    const announce = useCallback((text: string) => {
        // Toggle a trailing non-breaking space so repeating the same action still
        // changes the node text and is re-announced by assistive technology.
        statusTick.current = !statusTick.current;
        setStatus(statusTick.current ? text : `${text}\u00a0`);
    }, []);
    const syncHistory = useCallback(() => {
        setCanUndo(historyRef.current.canUndo());
        setCanRedo(historyRef.current.canRedo());
    }, []);
    const pushHistory = useCallback((entry: HistoryEntry) => {
        historyRef.current.push(entry);
        syncHistory();
    }, [syncHistory]);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const ws = await api.getWorkspace(groupid, targetUserid);
            dispatch({kind: 'load', state: ws});
            const decoded = decodeLayout(ws.layoutjson);
            setStored(decoded.positions);
            setSizes(decoded.sizes);
            historyRef.current.clear();
            syncHistory();
            setError(null);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setLoading(false);
        }
    }, [api, groupid, targetUserid, syncHistory]);

    useEffect(() => {
        load();
    }, [load]);

    const importInputRef = useRef<HTMLInputElement>(null);
    const [importReplace, setImportReplace] = useState(false);

    const onImportFile = useCallback(async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        try {
            let text = await file.text();
            // An exported SVG carries the map JSON in its metadata; extract it.
            if (file.name.toLowerCase().endsWith('.svg')) {
                const embedded = extractMapData(text);
                if (!embedded) {
                    setError(t('editor:importnovimidata'));
                    return;
                }
                text = embedded;
            }
            await api.importMap(stateRef.current.workspaceid, text, importReplace ? 'replace' : 'append');
            await load();
        } catch (e) {
            setError((e as Error).message);
        }
    }, [api, load, importReplace, t]);

    // Feed operations polled from collaborators into the local state. Layout
    // changes travel on the separate layout channel and are reconciled below.
    const applyRemoteOperations = useCallback((operations: PolledOperation[]) => {
        let maxRevision = 0;
        operations.forEach((op) => {
            const action = operationToAction(op);
            if (action) {
                dispatch(action);
            }
            if (op.revision > maxRevision) {
                maxRevision = op.revision;
            }
        });
        // Keep our base revision in step with what we have applied, so the next
        // local edit does not send a stale base revision and hit a needless
        // revision conflict (which would force a full reload).
        if (maxRevision > 0) {
            dispatch({kind: 'setRevision', revision: maxRevision});
        }
    }, []);

    // Reconcile remote layout (positions + sizes) live. Merge rather than
    // replace so a node the remote map does not mention keeps its local value,
    // and so an in-progress local drag/resize (which CanvasView renders from its
    // own gesture state) is not disturbed.
    const onRemoteLayout = useCallback((layoutjson: string) => {
        const decoded = decodeLayout(layoutjson);
        setStored(prev => ({...prev, ...decoded.positions}));
        setSizes(prev => ({...prev, ...decoded.sizes}));
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
        onRemoteLayout,
        (s) => {
            dispatch({kind: 'setLocked', locked: s.locked});
            dispatch({kind: 'setProfile', profile: s.profile});
        },
        (e) => setError(e.message)
    );

    const layout = useMemo(
        () => computeLayout(state.nodes, stored, state.relations, state.profile),
        [state.nodes, stored, state.relations, state.profile]
    );
    const readonly = api.isReadonly();
    const disabled = busy || loading || state.locked === 1 || readonly;

    // Soft, non-blocking constraint hints, refreshed (debounced) as the map
    // changes. Only for the editing owner of an open map.
    const constraintStatus = useConstraintHints(
        api,
        state.workspaceid,
        state.revision,
        !readonly && state.locked !== 1
    );

    // Latest state/revision, so callbacks can read the current values without
    // widening their dependency lists (and without capturing a stale revision).
    const stateRef = useRef(state);
    stateRef.current = state;
    const revisionRef = useRef(state.revision);
    revisionRef.current = state.revision;

    const runOperation = useCallback(async (
        type: string,
        payload: Record<string, unknown>,
        optimistic: () => void
    ): Promise<{revision: number; stableid: string} | null> => {
        setBusy(true);
        try {
            // Read the revision from the ref, not the render-time closure: two
            // edits fired in quick succession (e.g. a container select-drag
            // immediately followed by a shape pick) would otherwise send the
            // pre-first-edit revision on the second call, get rejected on a
            // revision mismatch, and trigger a full reload that drops the
            // selection. The ref always holds the latest acknowledged revision.
            const res = await api.applyOperation(
                stateRef.current.workspaceid, revisionRef.current, type, payload);
            optimistic();
            dispatch({kind: 'setRevision', revision: res.revision});
            revisionRef.current = res.revision;
            setError(null);
            return res;
        } catch (e) {
            setError((e as Error).message);
            await load();
            return null;
        } finally {
            setBusy(false);
        }
    }, [api, load]);

    // Apply a sequence of operations to the server and locally (used by undo and
    // redo). Not recorded in history; the stack is managed by undo()/redo().
    const runOps = useCallback(async (specs: OpSpec[]): Promise<void> => {
        setBusy(true);
        let revision = revisionRef.current;
        try {
            for (const spec of specs) {
                if (spec.type === '__layout') {
                    // Non-revisioned layout change (node move/resize/re-arrange):
                    // restore positions/sizes and persist on the layout channel.
                    const positions = spec.payload.positions as LayoutMap;
                    const layoutSizes = spec.payload.sizes as SizeMap;
                    setStored(positions);
                    setSizes(layoutSizes);
                    await api.saveLayout(stateRef.current.workspaceid, encodeLayout(positions, layoutSizes));
                    continue;
                }
                const res = await api.applyOperation(
                    stateRef.current.workspaceid, revision, spec.type, spec.payload
                );
                revision = res.revision;
                const action = operationToAction({
                    operationtype: spec.type,
                    payloadjson: JSON.stringify(spec.payload),
                    revision: res.revision,
                    userid: currentUserId,
                });
                if (action) {
                    dispatch(action);
                }
            }
            revisionRef.current = revision;
            dispatch({kind: 'setRevision', revision});
            setError(null);
        } catch (e) {
            setError((e as Error).message);
            await load();
        } finally {
            setBusy(false);
        }
    }, [api, currentUserId, load]);

    const undo = useCallback(async () => {
        const entry = historyRef.current.takeUndo();
        syncHistory();
        if (entry) {
            await runOps(entry.undo);
            announce(t('editor:undo'));
        }
    }, [runOps, syncHistory, announce, t]);

    const redo = useCallback(async () => {
        const entry = historyRef.current.takeRedo();
        syncHistory();
        if (entry) {
            await runOps(entry.redo);
            announce(t('editor:redo'));
        }
    }, [runOps, syncHistory, announce, t]);

    // Keyboard: Ctrl/Cmd+Z undo, Ctrl/Cmd+Shift+Z or Ctrl/Cmd+Y redo. Skipped
    // while a text field or contentEditable has focus so native text undo keeps
    // working during label/relation editing.
    useEffect(() => {
        const onKey = (event: KeyboardEvent): void => {
            if (!(event.ctrlKey || event.metaKey)) {
                return;
            }
            const target = document.activeElement as HTMLElement | null;
            if (target && (target.isContentEditable
                || target.tagName === 'INPUT'
                || target.tagName === 'TEXTAREA'
                || target.tagName === 'SELECT')) {
                return;
            }
            if (stateRef.current.locked === 1) {
                return;
            }
            const key = event.key.toLowerCase();
            if (key === 'z' && !event.shiftKey) {
                event.preventDefault();
                void undo();
            } else if ((key === 'z' && event.shiftKey) || key === 'y') {
                event.preventDefault();
                void redo();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [undo, redo]);

    // Export the current map as a standalone SVG file (client-side).
    const exportSvg = useCallback(async () => {
        const svg = rootRef.current?.querySelector('svg.vimipad-canvas') as SVGSVGElement | null;
        if (!svg) {
            return;
        }
        const bounds = computeContentBounds(state.nodes, stored, sizes, 60, {
            x: 0, y: 0, w: CANVAS_WIDTH, h: CANVAS_HEIGHT,
        }, state.containers ?? []);
        // Embed the semantic map JSON so the SVG can be re-imported later.
        let embedJson: string | undefined;
        try {
            const url = `${exportBase}?cmid=${api.getCmid()}&workspaceid=${state.workspaceid}&format=json`;
            const response = await fetch(url, {credentials: 'same-origin'});
            if (response.ok) {
                embedJson = await response.text();
            }
        } catch {
            embedJson = undefined;
        }
        downloadCanvasSvg(svg, bounds, `vimipad-${state.profile}.svg`, embedJson);
    }, [state.nodes, state.profile, state.containers, state.workspaceid, stored, sizes, exportBase, api]);

    // Export the current map as a rasterized PNG file (client-side).
    const exportPng = useCallback(() => {
        const svg = rootRef.current?.querySelector('svg.vimipad-canvas') as SVGSVGElement | null;
        if (!svg) {
            return;
        }
        const bounds = computeContentBounds(state.nodes, stored, sizes, 60, {
            x: 0, y: 0, w: CANVAS_WIDTH, h: CANVAS_HEIGHT,
        }, state.containers ?? []);
        downloadCanvasPng(svg, bounds, `vimipad-${state.profile}.png`);
    }, [state.nodes, state.profile, state.containers, stored, sizes]);

    // Export the current map as a single-page PDF (client-side).
    const exportPdf = useCallback(() => {
        const svg = rootRef.current?.querySelector('svg.vimipad-canvas') as SVGSVGElement | null;
        if (!svg) {
            return;
        }
        const bounds = computeContentBounds(state.nodes, stored, sizes, 60, {
            x: 0, y: 0, w: CANVAS_WIDTH, h: CANVAS_HEIGHT,
        }, state.containers ?? []);
        downloadCanvasPdf(svg, bounds, `vimipad-${state.profile}.pdf`);
    }, [state.nodes, state.profile, state.containers, stored, sizes]);

    const addNode = useCallback(async () => {
        const label = nodeLabel.trim();
        if (!label) {
            return;
        }
        const res = await runOperation('node_create', {type: 'concept', label}, () => undefined);
        if (res) {
            dispatch({kind: 'addNode', node: {stableid: res.stableid, type: 'concept', label}});
            pushHistory({
                undo: [{type: 'node_delete', payload: {stableid: res.stableid}}],
                redo: [{type: 'node_create', payload: {stableid: res.stableid, type: 'concept', label}}],
            });
            setNodeLabel('');
        }
    }, [runOperation, nodeLabel, pushHistory]);

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
            pushHistory({
                undo: [{type: 'relation_delete', payload: {stableid: res.stableid}}],
                redo: [relationCreateSpec({
                    stableid: res.stableid, sourceid: relSource, targetid: relTarget,
                    type: 'related', label, direction: 1,
                })],
            });
            setRelLabel('');
        }
    }, [runOperation, relSource, relTarget, relLabel, pushHistory]);

    const createRelation = useCallback(async (sourceid: string, targetid: string) => {
        if (sourceid === targetid) {
            return;
        }
        const res = await runOperation('relation_create',
            {sourceid, targetid, type: 'related', label: ''}, () => undefined);
        if (res) {
            dispatch({
                kind: 'addRelation',
                relation: {
                    stableid: res.stableid, sourceid, targetid, type: 'related', label: '', direction: 1,
                },
            });
            pushHistory({
                undo: [{type: 'relation_delete', payload: {stableid: res.stableid}}],
                redo: [relationCreateSpec({
                    stableid: res.stableid, sourceid, targetid, type: 'related', label: '', direction: 1,
                })],
            });
        }
    }, [runOperation, pushHistory]);

    const deleteRelation = useCallback(async (stableid: string) => {
        const rel = stateRef.current.relations.find(r => r.stableid === stableid);
        const res = await runOperation('relation_delete', {stableid},
            () => dispatch({kind: 'deleteRelation', stableid}));
        if (res && rel) {
            pushHistory({
                undo: [relationCreateSpec(rel)],
                redo: [{type: 'relation_delete', payload: {stableid}}],
            });
        }
    }, [runOperation, pushHistory]);

    const deleteNode = useCallback(async (stableid: string) => {
        const node = stateRef.current.nodes.find(n => n.stableid === stableid);
        const attached = stateRef.current.relations.filter(
            r => r.sourceid === stableid || r.targetid === stableid
        );
        const res = await runOperation('node_delete', {stableid},
            () => dispatch({kind: 'deleteNode', stableid}));
        if (res && node) {
            // Undo recreates the node first, then its relations (endpoints must
            // exist); redo deletes the node (which cascades its relations).
            pushHistory({
                undo: [nodeCreateSpec(node), ...attached.map(relationCreateSpec)],
                redo: [{type: 'node_delete', payload: {stableid}}],
            });
        }
    }, [runOperation, pushHistory]);

    const createContainer = useCallback(async (geometryjson: string) => {
        const label = t('editor:newcontainer');
        const res = await runOperation('container_create', {type: 'group', label, geometryjson}, () => undefined);
        if (res) {
            dispatch({kind: 'addContainer', container: {
                stableid: res.stableid, type: 'group', label, geometryjson,
            }});
            pushHistory({
                undo: [{type: 'container_delete', payload: {stableid: res.stableid}}],
                redo: [{type: 'container_create',
                    payload: {stableid: res.stableid, type: 'group', label, geometryjson}}],
            });
        }
    }, [runOperation, pushHistory, t]);

    const deleteContainer = useCallback(async (stableid: string) => {
        const existing = (stateRef.current.containers ?? []).find(c => c.stableid === stableid);
        const res = await runOperation('container_delete', {stableid},
            () => dispatch({kind: 'deleteContainer', stableid}));
        if (res && existing) {
            pushHistory({
                undo: [{type: 'container_create', payload: {
                    stableid, type: existing.type, geometryjson: existing.geometryjson ?? '',
                }}],
                redo: [{type: 'container_delete', payload: {stableid}}],
            });
        }
    }, [runOperation, pushHistory]);

    const updateContainerGeometry = useCallback(async (stableid: string, geometryjson: string) => {
        const prev = (stateRef.current.containers ?? []).find(c => c.stableid === stableid)?.geometryjson;
        const res = await runOperation('container_update', {stableid, geometryjson}, () => {
            dispatch({kind: 'updateContainer', stableid, geometryjson});
        });
        if (res) {
            pushHistory({
                undo: [{type: 'container_update', payload: {stableid, geometryjson: prev ?? ''}}],
                redo: [{type: 'container_update', payload: {stableid, geometryjson}}],
            });
        }
    }, [runOperation, pushHistory]);

    const renameContainer = useCallback(async (stableid: string, label: string) => {
        const prev = (stateRef.current.containers ?? []).find(c => c.stableid === stableid)?.label ?? '';
        if (label === prev) {
            return;
        }
        const res = await runOperation('container_update', {stableid, label}, () => {
            dispatch({kind: 'updateContainer', stableid, label});
        });
        if (res) {
            pushHistory({
                undo: [{type: 'container_update', payload: {stableid, label: prev}}],
                redo: [{type: 'container_update', payload: {stableid, label}}],
            });
        }
    }, [runOperation, pushHistory]);

    const updateContainerStyle = useCallback(async (stableid: string, metadatajson: string) => {
        const prev = (stateRef.current.containers ?? []).find(c => c.stableid === stableid)?.metadatajson ?? '';
        if (metadatajson === prev) {
            return;
        }
        const res = await runOperation('container_update', {stableid, metadatajson}, () => {
            dispatch({kind: 'updateContainer', stableid, metadatajson});
        });
        if (res) {
            pushHistory({
                undo: [{type: 'container_update', payload: {stableid, metadatajson: prev}}],
                redo: [{type: 'container_update', payload: {stableid, metadatajson}}],
            });
        }
    }, [runOperation, pushHistory]);

    const setElementLock = useCallback(async (kind: LockKind, stableid: string, metadatajson: string) => {
        const type = kind === 'node' ? 'node_update' : (kind === 'relation' ? 'relation_update' : 'container_update');
        const findPrev = (): string | undefined => {
            if (kind === 'node') {
                return stateRef.current.nodes.find(n => n.stableid === stableid)?.metadatajson;
            }
            if (kind === 'relation') {
                return stateRef.current.relations.find(r => r.stableid === stableid)?.metadatajson;
            }
            return (stateRef.current.containers ?? []).find(c => c.stableid === stableid)?.metadatajson;
        };
        const prev = findPrev();
        const res = await runOperation(type, {stableid, metadatajson}, () => {
            if (kind === 'node') {
                dispatch({kind: 'updateNode', stableid, metadatajson});
            } else if (kind === 'relation') {
                dispatch({kind: 'updateRelation', stableid, metadatajson});
            } else {
                dispatch({kind: 'updateContainer', stableid, metadatajson});
            }
        });
        if (res) {
            pushHistory({
                undo: [{type, payload: {stableid, metadatajson: prev ?? ''}}],
                redo: [{type, payload: {stableid, metadatajson}}],
            });
        }
    }, [runOperation, pushHistory]);

    const renameNode = useCallback(async (stableid: string, label: string) => {
        const trimmed = label.trim();
        if (!trimmed) {
            return;
        }
        const prev = stateRef.current.nodes.find(n => n.stableid === stableid)?.label ?? '';
        const res = await runOperation('node_update', {stableid, label: trimmed},
            () => dispatch({kind: 'updateNode', stableid, label: trimmed}));
        if (res && prev !== trimmed) {
            pushHistory({
                undo: [{type: 'node_update', payload: {stableid, label: prev}}],
                redo: [{type: 'node_update', payload: {stableid, label: trimmed}}],
            });
        }
    }, [runOperation, pushHistory]);

    const changeNodeStyle = useCallback(async (stableid: string, metadatajson: string) => {
        const prev = stateRef.current.nodes.find(n => n.stableid === stableid)?.metadatajson ?? '';
        const res = await runOperation('node_update', {stableid, metadatajson},
            () => dispatch({kind: 'updateNode', stableid, metadatajson}));
        if (res) {
            pushHistory({
                undo: [{type: 'node_update', payload: {stableid, metadatajson: prev}}],
                redo: [{type: 'node_update', payload: {stableid, metadatajson}}],
            });
        }
    }, [runOperation, pushHistory]);

    const renameRelation = useCallback(async (stableid: string, label: string) => {
        const trimmed = label.trim();
        const prev = stateRef.current.relations.find(r => r.stableid === stableid)?.label ?? '';
        const res = await runOperation('relation_update', {stableid, label: trimmed},
            () => dispatch({kind: 'updateRelation', stableid, label: trimmed}));
        if (res && prev !== trimmed) {
            pushHistory({
                undo: [{type: 'relation_update', payload: {stableid, label: prev}}],
                redo: [{type: 'relation_update', payload: {stableid, label: trimmed}}],
            });
        }
    }, [runOperation, pushHistory]);

    const changeDirection = useCallback(async (stableid: string, direction: number) => {
        const prev = stateRef.current.relations.find(r => r.stableid === stableid)?.direction ?? 1;
        const res = await runOperation('relation_update', {stableid, direction},
            () => dispatch({kind: 'updateRelation', stableid, direction}));
        if (res && prev !== direction) {
            pushHistory({
                undo: [{type: 'relation_update', payload: {stableid, direction: prev}}],
                redo: [{type: 'relation_update', payload: {stableid, direction}}],
            });
        }
    }, [runOperation, pushHistory]);

    const retarget = useCallback(async (stableid: string, change: {sourceid?: string; targetid?: string}) => {
        const prev = stateRef.current.relations.find(r => r.stableid === stableid);
        const payload: Record<string, unknown> = {stableid};
        if (change.sourceid) {
            payload.newsource = change.sourceid;
        }
        if (change.targetid) {
            payload.newtarget = change.targetid;
        }
        const res = await runOperation('relation_retarget', payload,
            () => dispatch({kind: 'retargetRelation', stableid, ...change}));
        if (res && prev) {
            const undoPayload: Record<string, unknown> = {stableid};
            if (change.sourceid) {
                undoPayload.newsource = prev.sourceid;
            }
            if (change.targetid) {
                undoPayload.newtarget = prev.targetid;
            }
            pushHistory({
                undo: [{type: 'relation_retarget', payload: undoPayload}],
                redo: [{type: 'relation_retarget', payload}],
            });
        }
    }, [runOperation, pushHistory]);

    const onNodeMoved = useCallback(async (stableid: string, point: Point) => {
        const prevPos = stored;
        const nextPos = {...stored, [stableid]: point};
        setStored(nextPos);
        try {
            // Send only the moved node so concurrent moves of other nodes are not
            // clobbered; the server merges this patch into the stored layout.
            await api.saveLayout(state.workspaceid, encodeLayout({[stableid]: point}, {}), '', 'merge');
            pushHistory({
                undo: [{type: '__layout', payload: {positions: prevPos, sizes}}],
                redo: [{type: '__layout', payload: {positions: nextPos, sizes}}],
            });
        } catch (e) {
            setError((e as Error).message);
        }
    }, [api, state.workspaceid, stored, sizes, pushHistory]);

    // Discard the stored positions for the active profile and re-apply the
    // automatic layout (tidy tree for the tree profile, circle otherwise),
    // persisting the result so collaborators receive it too.
    const reArrangeLayout = useCallback(async () => {
        if (arrangingRef.current) {
            return;
        }
        arrangingRef.current = true;
        setArranging(true);
        const prevPos = stored;
        const containers = state.containers ?? [];

        // Lock handling for re-arrange:
        //  - a move-locked node keeps its current position (never repositioned);
        //  - a node inside a move-locked container is pinned to its current
        //    position too, so it cannot be pushed out of the locked container.
        //
        // Enforcement follows the same rule as drag/resize: a non-manager is
        // always bound, a manager only while the lock-mode preview is on. So a
        // teacher authoring a template (lock-mode off) can still reflow locked
        // elements with re-arrange; a learner (or a previewing teacher) cannot.
        const enforcementActive = state.canmanage !== true || lockMode;
        const moveLockedContainerBoxes: ContainerBox[] = [];
        const lockedContainers = new Set<string>();
        if (enforcementActive) {
            for (const c of containers) {
                if (isGroupLocked(c.metadatajson, 'move')) {
                    lockedContainers.add(c.stableid);
                    const b = parseGeometry(c.geometryjson);
                    if (b) {
                        moveLockedContainerBoxes.push(b);
                    }
                }
            }
        }
        const isPinned = (n: {stableid: string; metadatajson?: string}): boolean =>
            enforcementActive && isNodePinnedForRearrange(
                n.metadatajson,
                stored[n.stableid],
                moveLockedContainerBoxes,
                (m) => isGroupLocked(m, 'move')
            );
        const pinned = new Set(state.nodes.filter(isPinned).map(n => n.stableid));

        // Preservation-first refinement: gently improve the existing (human)
        // layout in place instead of re-seeding it. Container membership is read
        // from the current geometry and kept by the interior/exterior potentials.
        // Boxes may grow to keep their members enclosed (never shrink, so the
        // human's chosen size is preserved); move-locked boxes are left untouched.
        const arranged = refineArrangement({
            nodes: state.nodes, relations: state.relations, containers,
            profile: state.profile, positions: stored, sizes, pinned, lockedContainers,
            maxIterations: arrangeIterations, formconfig: state.formconfig,
        });
        const auto: LayoutMap = {...arranged.positions};
        for (const n of state.nodes) {
            if (pinned.has(n.stableid) && stored[n.stableid]) {
                auto[n.stableid] = stored[n.stableid];
            }
        }

        // Emit a revisioned container_update for every box whose geometry the
        // refiner grew. Move-locked boxes are excluded (their geometry change
        // would be rejected server-side anyway).
        const refits: Array<{stableid: string; oldgeom: string; newgeom: string}> = [];
        for (const c of containers) {
            if (lockedContainers.has(c.stableid)) {
                continue;
            }
            const g = arranged.containers[c.stableid];
            if (!g) {
                continue;
            }
            const old = parseGeometry(c.geometryjson);
            if (!old || old.x !== g.x || old.y !== g.y || old.w !== g.w || old.h !== g.h) {
                refits.push({
                    stableid: c.stableid,
                    oldgeom: c.geometryjson ?? '',
                    newgeom: serializeGeometry(g),
                });
            }
        }

        setStored(auto);
        refits.forEach(u => dispatch({kind: 'updateContainer', stableid: u.stableid, geometryjson: u.newgeom}));
        try {
            await api.saveLayout(state.workspaceid, encodeLayout(auto, sizes));
            let revision = revisionRef.current;
            for (const u of refits) {
                const res = await api.applyOperation(
                    stateRef.current.workspaceid, revision, 'container_update',
                    {stableid: u.stableid, geometryjson: u.newgeom}
                );
                revision = res.revision;
            }
            if (refits.length > 0) {
                revisionRef.current = revision;
                dispatch({kind: 'setRevision', revision});
            }
            pushHistory({
                undo: [
                    {type: '__layout', payload: {positions: prevPos, sizes}},
                    ...refits.map(u => ({
                        type: 'container_update', payload: {stableid: u.stableid, geometryjson: u.oldgeom},
                    })),
                ],
                redo: [
                    {type: '__layout', payload: {positions: auto, sizes}},
                    ...refits.map(u => ({
                        type: 'container_update', payload: {stableid: u.stableid, geometryjson: u.newgeom},
                    })),
                ],
            });
            announce(t('editor:rearrange'));
        } catch (e) {
            setError((e as Error).message);
            await load();
        } finally {
            arrangingRef.current = false;
            setArranging(false);
        }
    }, [api, state.workspaceid, state.nodes, state.relations, state.profile, state.containers,
        state.canmanage, lockMode, stored, sizes, pushHistory, announce, t, load, arrangeIterations]);

    const onNodeResized = useCallback(async (stableid: string, size: Size) => {
        const prevSizes = sizes;
        const nextSizes = {...sizes, [stableid]: size};
        setSizes(nextSizes);
        try {
            await api.saveLayout(state.workspaceid, encodeLayout(stored, nextSizes));
            pushHistory({
                undo: [{type: '__layout', payload: {positions: stored, sizes: prevSizes}}],
                redo: [{type: '__layout', payload: {positions: stored, sizes: nextSizes}}],
            });
        } catch (e) {
            setError((e as Error).message);
        }
    }, [api, state.workspaceid, stored, sizes, pushHistory]);

    const duplicateNode = useCallback(async (stableid: string) => {
        const src = state.nodes.find(n => n.stableid === stableid);
        if (!src) {
            return;
        }
        const res = await runOperation('node_create',
            {type: src.type, label: src.label, metadatajson: src.metadatajson ?? ''},
            () => undefined);
        if (!res) {
            return;
        }
        dispatch({kind: 'addNode', node: {
            stableid: res.stableid, type: src.type, label: src.label, metadatajson: src.metadatajson,
        }});
        // Offset the copy so it does not sit exactly on the original.
        const base = layout[stableid] ?? {x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT / 2};
        const pos = clampToCanvas({x: base.x + 40, y: base.y + 40});
        const next = {...stored, [res.stableid]: pos};
        const srcSize = sizes[stableid];
        const nextSizes = srcSize ? {...sizes, [res.stableid]: srcSize} : sizes;
        setStored(next);
        if (srcSize) {
            setSizes(nextSizes);
        }
        try {
            await api.saveLayout(state.workspaceid, encodeLayout(next, nextSizes));
        } catch (e) {
            setError((e as Error).message);
        }
    }, [runOperation, state.nodes, state.workspaceid, api, layout, stored, sizes]);

    if (loading) {
        return <div className="vimipad-editor-loading">{t('editor:loading')}</div>;
    }

    const addNodeControls = (
        <fieldset disabled={disabled} className="vimipad-control">
            <legend className="h6">{t('editor:addnode')}</legend>
            <div className="vimipad-control-line">
                <label className="sr-only" htmlFor="vimipad-node-label">{t('editor:nodelabel')}</label>
                <input
                    id="vimipad-node-label"
                    type="text"
                    className="form-control"
                    value={nodeLabel}
                    placeholder={t('editor:nodelabel')}
                    onChange={e => setNodeLabel(e.target.value)}
                />
                <button type="button" className="btn btn-primary" onClick={addNode}>
                    <Icon name={FA.addNode} /> {t('editor:add')}
                </button>
            </div>
        </fieldset>
    );

    const addRelationControls = (
        <fieldset disabled={disabled || state.nodes.length < 2} className="vimipad-control">
            <legend className="h6">{t('editor:addrelation')}</legend>
            <div className="vimipad-control-line">
                <label className="sr-only" htmlFor="vimipad-rel-source">{t('editor:subject')}</label>
                <select
                    id="vimipad-rel-source"
                    className="form-control"
                    value={relSource}
                    onChange={e => setRelSource(e.target.value)}
                >
                    <option value="">{t('editor:subject')}</option>
                    {state.nodes.map(n => <option key={n.stableid} value={n.stableid}>{n.label}</option>)}
                </select>
                <input
                    type="text"
                    className="form-control"
                    value={relLabel}
                    placeholder={t('editor:relation')}
                    onChange={e => setRelLabel(e.target.value)}
                />
                <label className="sr-only" htmlFor="vimipad-rel-target">{t('editor:object')}</label>
                <select
                    id="vimipad-rel-target"
                    className="form-control"
                    value={relTarget}
                    onChange={e => setRelTarget(e.target.value)}
                >
                    <option value="">{t('editor:object')}</option>
                    {state.nodes.map(n => <option key={n.stableid} value={n.stableid}>{n.label}</option>)}
                </select>
                <button type="button" className="btn btn-primary" onClick={addRelation}>
                    <Icon name={FA.addRelation} /> {t('editor:add')}
                </button>
            </div>
        </fieldset>
    );


    return (
        <div className="vimipad-editor" ref={rootRef}>
            <div className="vimipad-sr-only" role="status" aria-live="polite">{status}</div>
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
            {readonly && (
                <div className="alert alert-info" role="status">{t('editor:readonly')}</div>
            )}
            {state.locked === 1 && (
                <div className="alert alert-warning" role="status">{t('editor:locked')}</div>
            )}
            <ConstraintBanner status={constraintStatus} t={t} />

            <div className="vimipad-viewpanel">
            {view === 'tools' ? null : view === 'canvas' ? (
                <>
                    <CanvasView
                        state={state}
                        layout={layout}
                        profile={state.profile}
                        formconfig={state.formconfig}
                        sizes={sizes}
                        disabled={disabled}
                        onNodeMoved={onNodeMoved}
                        onNodeResized={onNodeResized}
                        onChangeStyle={changeNodeStyle}
                        onDuplicateNode={duplicateNode}
                        onCreateRelation={createRelation}
                        onChangeDirection={changeDirection}
                        onDeleteNode={deleteNode}
                        onDeleteRelation={deleteRelation}
                        onRenameNode={renameNode}
                        onRenameRelation={renameRelation}
                        onUndo={undo}
                        onRedo={redo}
                        canUndo={canUndo}
                        canRedo={canRedo}
                        onReArrange={reArrangeLayout}
                        arrangeBusy={arranging}
                        onExportSvg={exportSvg}
                        onExportPng={exportPng}
                        onExportPdf={exportPdf}
                        t={t}
                        isLockedByOther={collab.isLockedByOther}
                        beginEdit={collab.beginEdit}
                        endEdit={collab.endEdit}
                        drawingContainer={drawingContainer}
                        onCreateContainer={createContainer}
                        onDeleteContainer={deleteContainer}
                        onFinishDrawContainer={() => setDrawingContainer(false)}
                        onUpdateContainer={updateContainerGeometry}
                        onRenameContainer={renameContainer}
                        onUpdateContainerStyle={updateContainerStyle}
                        canManage={state.canmanage === true}
                        canLock={state.canmanage === true || state.lockmodeforlearners === true}
                        onToggleDrawContainer={() => setDrawingContainer(v => !v)}
                        lockMode={lockMode}
                        onToggleLockMode={() => setLockMode(v => !v)}
                        onSetElementLock={setElementLock}
                    />
                    <div className="vimipad-controls-row">
                        {addNodeControls}
                        {addRelationControls}
                    </div>
                    <JournalPanel
                        api={api}
                        workspaceid={state.workspaceid}
                        allowPrivate={state.journalallowprivate === true}
                        revision={state.revision}
                        t={t}
                    />
                </>
            ) : (
                <>
                    <div className="vimipad-controls-row">
                        {addNodeControls}
                        {addRelationControls}
                    </div>
                    <RelationListView
                        state={state}
                        disabled={disabled}
                        enforced={state.canmanage !== true || lockMode}
                        onDeleteRelation={deleteRelation}
                        onRetarget={retarget}
                        onRenameRelation={renameRelation}
                        t={t}
                    />
                    <JournalPanel
                        api={api}
                        workspaceid={state.workspaceid}
                        allowPrivate={state.journalallowprivate === true}
                        revision={state.revision}
                        t={t}
                    />
                </>
            )}
            </div>

            {view === 'tools' && (
                <div className="vimipad-tools-panel">
                    <fieldset className="vimipad-control">
                        <legend className="h6">{t('editor:exportdataheading')}</legend>
                        <div className="vimipad-tools mt-2">
                            <a
                                className="btn btn-outline-secondary btn-sm"
                                href={`${exportBase}?cmid=${api.getCmid()}&workspaceid=${state.workspaceid}&format=json`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >{t('editor:exportjson')}</a>
                            <a
                                className="btn btn-outline-secondary btn-sm"
                                href={`${exportBase}?cmid=${api.getCmid()}&workspaceid=${state.workspaceid}&format=xml`}
                                target="_blank"
                                rel="noopener noreferrer"
                            >{t('editor:exportxml')}</a>
                        </div>
                        <p className="text-muted small mt-1">{t('editor:exportdatahint')}</p>
                    </fieldset>
                    <fieldset className="vimipad-control">
                        <legend className="h6">{t('editor:importheading')}</legend>
                        <div className="vimipad-tools mt-2">
                            <input
                                ref={importInputRef}
                                type="file"
                                accept="application/json,application/xml,text/xml,image/svg+xml,.json,.xml,.svg"
                                className="vimipad-hidden-input"
                                onChange={(e) => void onImportFile(e)}
                            />
                            <button
                                type="button"
                                className="btn btn-outline-secondary btn-sm"
                                onClick={() => importInputRef.current?.click()}
                                disabled={state.locked === 1 || readonly}
                            >
                                {t('editor:import')}
                            </button>
                            <label className="vimipad-import-replace">
                                <input
                                    type="checkbox"
                                    checked={importReplace}
                                    onChange={(e) => setImportReplace(e.target.checked)}
                                    disabled={state.locked === 1 || readonly}
                                />{' '}
                                {t('editor:importreplace')}
                            </label>
                        </div>
                        <p className="text-muted small mt-1">{t('editor:importhint')}</p>
                    </fieldset>
                </div>
            )}
        </div>
    );
}
