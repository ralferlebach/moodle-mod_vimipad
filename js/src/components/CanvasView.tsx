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
 * The graphical canvas view.
 *
 * Renders nodes as shaped boxes (rounded rectangle, rectangle or ellipse,
 * according to the node's stored style clamped to the active profile) and
 * relations as connectors on an SVG surface. A selected node shows a dashed
 * "marching ants" outline (move affordance) and four corner handles (resize
 * affordance). Nodes can be dragged to move and their corners dragged to resize
 * (both non-revisioned layout operations, committed on drop). A click selects an
 * element; ESC clears the selection; Del removes the selected element; a
 * double-click on a node's text opens inline editing (Enter commits,
 * Shift+Enter inserts a newline). The pure interaction rules live in
 * ../canvas/interaction; shape and style parsing live in ../canvas/*.
 *
 * @module     mod_vimipad/components/CanvasView
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useEffect, useMemo, useReducer, useRef, useState} from 'react';
import {CANVAS_HEIGHT, CANVAS_WIDTH, clampToCanvas} from '../graph/autolayout';
import {EditorState} from '../store/reducer';
import {LayoutMap, Point, Size, SizeMap, FormConfig} from '../types';
import {formClampShape, formLine, formShared, LineStyle} from '../canvas/form_config';
import {
    clampSize, clampView, edgePoint, nodeHeight, nodeWidth, profileLine, relLinePath, treeBusPath,
} from '../canvas/node_geometry';
import {labelBox, shapeElement} from '../canvas/shapes';
import {useDismiss} from '../hooks/use_dismiss';
import {parseNodeStyle} from '../canvas/node_style';
import {BoxCorner, boxFromDrag, ContainerBox, isDrawable, moveBox, parseGeometry, resizeBoxCorner, serializeGeometry} from '../canvas/container_geometry';
import {isLocked, readGroupLocks, writeGroupLocks, LockGroup, isGroupLocked} from '../canvas/element_lock';
import {screenToViewBox} from '../canvas/viewport';
import {computeContentBounds} from '../canvas/svg_export';
import {editableToText} from '../canvas/editable_text';
import {freeConnectorPath, labelPoint, offsetAnchors, siblingOffsets} from '../canvas/connection_geometry';
import {NodeFormatToolbar} from './NodeFormatToolbar';
import {RelationMenu} from './RelationMenu';
import {MenuOverlay} from './MenuOverlay';
import {TextEditMenu} from './TextEditMenu';
import {FA, Icon, SvgLockBadge} from '../canvas/icons';
import {
    deletableTarget,
    initialInteraction,
    interactionReduce,
    isEditing,
    isSelected,
    Target,
} from '../canvas/interaction';
import {vdbg} from '../debug';

interface Props {
    state: EditorState;
    layout: LayoutMap;
    /** Active diagram profile; decides the default and allowed node shapes. */
    profile: string;
    /** Backend form config (shapes, line, bifurcation); preferred over built-ins. */
    formconfig?: FormConfig;
    /** Stored manual node sizes; nodes without one use a label-derived size. */
    sizes: SizeMap;
    disabled: boolean;
    onNodeMoved: (stableid: string, point: Point) => void;
    /** Commit a manual resize (layout channel, like move). */
    onNodeResized?: (stableid: string, size: Size) => void;
    onDeleteNode?: (stableid: string) => void;
    onDeleteRelation?: (stableid: string) => void;
    onRenameNode?: (stableid: string, label: string) => void;
    onRenameRelation?: (stableid: string, label: string) => void;
    /** Notify the host when the selected element changes (for the format bar). */
    onSelectionChange?: (target: Target | null) => void;
    /** Persist a new full metadatajson for a node (from the dock). */
    onChangeStyle?: (stableid: string, metadatajson: string) => void;
    /** Duplicate a node (without its relations). */
    onDuplicateNode?: (stableid: string) => void;
    /** Create a relation by dragging from a connector dock to another node. */
    onCreateRelation?: (sourceid: string, targetid: string) => void;
    /** Set a relation's arrow direction (0 none, 1 forward, -1 reverse, 2 both). */
    onChangeDirection?: (stableid: string, direction: number) => void;
    t: (key: string) => string;
    /** True if a node is held by another collaborator (renders as locked). */
    isLockedByOther?: (targettype: string, stableid: string) => boolean;
    /** Take an editing lock on drag-start; resolves to whether we may drag. */
    beginEdit?: (targettype: string, stableid: string) => Promise<boolean>;
    /** Release the editing lock on drag-end. */
    endEdit?: (targettype: string, stableid: string) => Promise<void>;
    /** Undo/redo, re-arrange and export actions rendered as a top-left overlay. */
    onUndo?: () => void;
    onRedo?: () => void;
    canUndo?: boolean;
    canRedo?: boolean;
    onReArrange?: () => void;
    onExportSvg?: () => void;
    onExportPng?: () => void;
    onExportPdf?: () => void;
    /** True while the "draw container" tool is active. */
    drawingContainer?: boolean;
    /** Create a container from a drawn box (geometry JSON). */
    onCreateContainer?: (geometryjson: string) => void;
    /** Delete a container by stable id. */
    onDeleteContainer?: (stableid: string) => void;
    /** Called when a draw gesture completes, so the host can exit draw mode. */
    onFinishDrawContainer?: () => void;
    /** Commit a container's new geometry (move/resize). */
    onUpdateContainer?: (stableid: string, geometryjson: string) => void;
    /** Persist a new style (metadatajson) for a container: shape, fill, text. */
    onUpdateContainerStyle?: (stableid: string, metadatajson: string) => void;
    /** Rename a container. */
    onRenameContainer?: (stableid: string, label: string) => void;
    /** Whether the viewer may author/manage the template (container drawing). */
    canManage?: boolean;
    /** Whether the viewer may use lock mode (teachers, or learners if enabled). */
    canLock?: boolean;
    /** Toggle container drawing mode (author tool, lives in the canvas toolbar). */
    onToggleDrawContainer?: () => void;
    /** Whether lock mode is armed (element docks then offer a lock toggle). */
    lockMode?: boolean;
    /** Toggle lock mode. */
    onToggleLockMode?: () => void;
    /** Persist a new metadata JSON (used by the dock's lock toggle). */
    onSetElementLock?: (kind: 'node' | 'relation' | 'container', stableid: string, metadatajson: string) => void;
}

/** Size of a corner resize handle, in canvas units. */
const HANDLE = 9;
/** Finger-friendly hit area around each corner handle (touch targets). */
const HANDLE_HIT = 26;
// Pan/zoom viewport limits: view width can shrink to a 4x zoom-in, grow to full canvas.
/**
 * Straight run at each connector end, in canvas units. Covers the arrow marker
 * (markerWidth 7 scaled by the stroke width) so the head sits on a straight
 * piece and therefore points the way the connection really goes.
 */
const ARROW_STUB = 12;

/** Perpendicular spacing between parallel connections of the same node pair. */
const SIBLING_SPACING = 16;

const MIN_VIEW_W = CANVAS_WIDTH * 0.25;
const MAX_VIEW_W = CANVAS_WIDTH;
const VIEW_ASPECT = CANVAS_HEIGHT / CANVAS_WIDTH;
// Open on a comfortable window centred on the canvas middle (where new content is
// placed) rather than the whole large canvas, which would appear zoomed far out.
const INITIAL_VIEW_W = Math.min(CANVAS_WIDTH, 1100);

/**
 * Render the SVG canvas.
 *
 * @param props Component props.
 * @returns The rendered canvas.
 */
export function CanvasView(props: Props): React.ReactElement {
    const {
        state, layout, profile, formconfig, sizes, disabled, onNodeMoved, onNodeResized,
        onDeleteNode, onDeleteRelation, onRenameNode, onRenameRelation, t,
        isLockedByOther, beginEdit, endEdit, onSelectionChange, onChangeStyle, onDuplicateNode,
        onCreateRelation, onChangeDirection,
        onUndo, onRedo, canUndo, canRedo, onReArrange,
        onExportSvg, onExportPng, onExportPdf,
    } = props;

    // Toggle a single lock group on an element and persist the new metadata via
    // the element-lock service call. Shared by nodes, relations and containers.
    const toggleLockGroup = (
        kind: 'node' | 'relation' | 'container',
        stableid: string,
        metadatajson: string | undefined,
        group: LockGroup
    ): void => {
        const current = readGroupLocks(metadatajson);
        const next = {...current, [group]: !current[group]};
        props.onSetElementLock?.(kind, stableid, writeGroupLocks(metadatajson, next));
    };
    // Rendering rules for the active display type: prefer the backend form config,
    // fall back to the built-in profile defaults when it is absent.
    const relLine: LineStyle = formLine(formconfig, profileLine(profile));
    const sharedBifurcation = formShared(formconfig, profile === 'tree');
    const svgRef = useRef<SVGSVGElement>(null);
    // Manual double-click detection: pointer capture on nodes swallows native dblclick.
    const lastNodeClick = useRef<{id: string; t: number}>({id: '', t: 0});
    // Pan/zoom viewport (SVG viewBox) plus a ref mirror for use inside gesture handlers.
    const [view, setView] = useState({
        x: (CANVAS_WIDTH - INITIAL_VIEW_W) / 2,
        y: (CANVAS_HEIGHT - INITIAL_VIEW_W * VIEW_ASPECT) / 2,
        w: INITIAL_VIEW_W,
        h: INITIAL_VIEW_W * VIEW_ASPECT,
    });
    const viewRef = useRef(view);
    useEffect(() => { viewRef.current = view; }, [view]);

    // In read-only views (revision viewer, journal replay, player) the node
    // positions come from the saved layout and can sit anywhere on the large
    // canvas, so the fixed, canvas-centred starting viewport would clip content
    // that lies outside it — nodes appear to be missing and containers look
    // misplaced. Fit the viewport to the actual content instead, until the user
    // pans or zooms (then their framing is respected).
    const userPannedRef = useRef(false);
    useEffect(() => {
        if (!disabled || userPannedRef.current) {
            return;
        }
        const hasContent = state.nodes.length > 0 || (state.containers?.length ?? 0) > 0;
        if (!hasContent) {
            return;
        }
        const bounds = computeContentBounds(
            state.nodes, layout, sizes, 120, view, state.containers ?? []
        );
        // Grow the content box to the viewport aspect ratio, keep it centred, and
        // clamp its size and position to the canvas.
        let w = Math.max(bounds.w, bounds.h / VIEW_ASPECT);
        w = Math.min(MAX_VIEW_W, Math.max(MIN_VIEW_W, w));
        let h = w * VIEW_ASPECT;
        if (h < bounds.h) {
            h = bounds.h;
            w = h / VIEW_ASPECT;
        }
        const cx = bounds.x + bounds.w / 2;
        const cy = bounds.y + bounds.h / 2;
        setView(clampView({x: cx - w / 2, y: cy - h / 2, w, h}));
        // Intentionally not depending on `view`: this fits on content changes
        // (e.g. scrubbing the player to a new revision), not on its own updates.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [disabled, state.nodes, state.containers, layout, sizes]);

    // Full-page canvas view. Native Fullscreen API is used when available (robust
    // against ancestors with transform/overflow that break position:fixed); a
    // fixed-overlay fallback covers the rare case where it is unavailable.
    const wrapRef = useRef<HTMLDivElement>(null);
    const [nativeFs, setNativeFs] = useState(false);
    const [fallbackFs, setFallbackFs] = useState(false);
    const expanded = nativeFs || fallbackFs;

    // Export dropdown (controlled; robust across browsers, unlike <details>).
    const [exportOpen, setExportOpen] = useState(false);
    const exportRef = useRef<HTMLDivElement>(null);
    const closeExport = useCallback(() => setExportOpen(false), []);
    useDismiss(exportRef, exportOpen, closeExport);

    useEffect(() => {
        const onChange = (): void => setNativeFs(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onChange);
        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    const toggleFullview = useCallback(async () => {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
            return;
        }
        if (fallbackFs) {
            setFallbackFs(false);
            return;
        }
        const el = wrapRef.current;
        if (el && el.requestFullscreen) {
            try {
                await el.requestFullscreen();
                return;
            } catch (e) {
                // Native request refused; use the CSS overlay fallback instead.
            }
        }
        setFallbackFs(true);
    }, [fallbackFs]);

    // Escape leaves the fallback overlay (native fullscreen handles Escape itself).
    useEffect(() => {
        if (!fallbackFs) {
            return undefined;
        }
        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setFallbackFs(false);
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [fallbackFs]);
    // Active pointers on the background, for one-finger pan and two-finger pinch.
    const pointers = useRef<Map<number, {x: number; y: number}>>(new Map());
    const panStart = useRef<{cx: number; cy: number; vx: number; vy: number} | null>(null);
    const pinchDist = useRef<number | null>(null);
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragPos, setDragPos] = useState<Point | null>(null);
    const [moved, setMoved] = useState(false);
    const [resizeId, setResizeId] = useState<string | null>(null);
    const [resizeSize, setResizeSize] = useState<Size | null>(null);
    // Refs mirror the drag state so the async lock callback and the
    // lost-capture/cancel handlers read live values rather than a stale closure.
    const dragIdRef = useRef<string | null>(null);
    const movedRef = useRef(false);
    const resizeIdRef = useRef<string | null>(null);
    useEffect(() => { dragIdRef.current = dragId; }, [dragId]);
    useEffect(() => { movedRef.current = moved; }, [moved]);
    useEffect(() => { resizeIdRef.current = resizeId; }, [resizeId]);
    const [interaction, dispatchInteraction] = useReducer(interactionReduce, initialInteraction);
    const [editValue, setEditValue] = useState('');
    const [hoveredId, setHoveredId] = useState<string | null>(null);
    // Live mirror of the edit value, kept in sync synchronously (never via a
    // post-render effect) so the callback ref below always seeds the editor with
    // THIS element's text, not the previously edited one.
    const editValueRef = useRef('');
    const setEditRef = useCallback((el: HTMLDivElement | null) => {
        if (!el) {
            return;
        }
        el.textContent = editValueRef.current;
        const focusEnd = (): void => {
            el.focus();
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            const sel = window.getSelection();
            sel?.removeAllRanges();
            sel?.addRange(range);
            vdbg('node-editor focus-applied');
        };
        focusEnd();
        // Re-grab focus on the next frame in case a default action stole it.
        requestAnimationFrame(focusEnd);
    }, []);
    // Dragging a new connection out of a node's connector dock.
    const [connectFrom, setConnectFrom] = useState<string | null>(null);
    const [connectTo, setConnectTo] = useState<Point | null>(null);

    // Surface the current selection so the host can show a format bar for it.
    useEffect(() => {
        if (onSelectionChange) {
            onSelectionChange(interaction.selected);
        }
    }, [interaction.selected, onSelectionChange]);

    const lockedByOther = useCallback((stableid: string): boolean =>
        isLockedByOther ? isLockedByOther('node', stableid) : false, [isLockedByOther]);

    // Whether template locks are enforced against the current user right now.
    // A non-manager is always bound; a manager is bound only while the lock-mode
    // preview toggle is on. This mirrors the server's bypass rule exactly, so
    // the UI never lets a user start an edit the server would then reject.
    const enforcementActive = props.canManage !== true || props.lockMode === true;

    // Per-stableid metadata lookups for nodes and containers, so the drag/resize
    // handlers can consult an element's lock groups in O(1).
    const nodeMetaById = useMemo(() => {
        const map = new Map<string, string | undefined>();
        for (const node of state.nodes) {
            map.set(node.stableid, node.metadatajson);
        }
        return map;
    }, [state.nodes]);
    const containerMetaById = useMemo(() => {
        const map = new Map<string, string | undefined>();
        for (const container of state.containers ?? []) {
            map.set(container.stableid, container.metadatajson);
        }
        return map;
    }, [state.containers]);

    // Whether a node's move group is locked *for the current user* — i.e. the
    // move lock is set and enforcement is active. Move-locked nodes must not be
    // draggable or resizable (their position/size live on the layout channel,
    // which the server also pins, so this is the matching client affordance).
    const nodeMoveLockedForMe = useCallback((stableid: string): boolean =>
        enforcementActive && isGroupLocked(nodeMetaById.get(stableid), 'move'),
        [enforcementActive, nodeMetaById]);
    const containerMoveLockedForMe = useCallback((stableid: string): boolean =>
        enforcementActive && isGroupLocked(containerMetaById.get(stableid), 'move'),
        [enforcementActive, containerMetaById]);

    const positionOf = useCallback((stableid: string): Point => {
        if (dragId === stableid && dragPos) {
            return dragPos;
        }
        return layout[stableid] ?? {x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT / 2};
    }, [dragId, dragPos, layout]);

    const sizeOf = useCallback((stableid: string, label: string): Size => {
        if (resizeId === stableid && resizeSize) {
            return resizeSize;
        }
        const width = nodeWidth(label);
        return sizes[stableid] ?? {w: width, h: nodeHeight(label, width)};
    }, [resizeId, resizeSize, sizes]);

    // First node whose box contains the given canvas point, if any.
    const nodeAt = useCallback((point: Point): string | null => {
        for (const node of state.nodes) {
            const p = positionOf(node.stableid);
            const s = sizeOf(node.stableid, node.label);
            if (Math.abs(point.x - p.x) <= s.w / 2 && Math.abs(point.y - p.y) <= s.h / 2) {
                return node.stableid;
            }
        }
        return null;
    }, [state.nodes, positionOf, sizeOf]);

    const toSvgPoint = useCallback((clientX: number, clientY: number): Point => {
        const svg = svgRef.current;
        if (!svg) {
            return {x: 0, y: 0};
        }
        // Prefer the browser's own matrix: it accounts for the viewBox, for
        // preserveAspectRatio letterboxing and for any CSS transform on an
        // ancestor (e.g. the full-view wrapper).
        const ctm = typeof svg.getScreenCTM === 'function' ? svg.getScreenCTM() : null;
        if (ctm) {
            const pt = svg.createSVGPoint();
            pt.x = clientX;
            pt.y = clientY;
            const local = pt.matrixTransform(ctm.inverse());
            return {x: local.x, y: local.y};
        }
        // Fallback (no CTM available, e.g. in jsdom): replicate "xMidYMid meet".
        const rect = svg.getBoundingClientRect();
        return screenToViewBox({x: clientX, y: clientY}, rect, viewRef.current);
    }, []);

    // Relations sharing a node pair are drawn as parallel lines rather than on
    // top of each other; this maps each relation to its slot in its group.
    const siblingSlots = useMemo(() => {
        const groups = new Map<string, string[]>();
        for (const rel of state.relations) {
            const key = [rel.sourceid, rel.targetid].slice().sort().join('|');
            const list = groups.get(key) ?? [];
            list.push(rel.stableid);
            groups.set(key, list);
        }
        const slots = new Map<string, {index: number; count: number}>();
        groups.forEach(ids => ids.forEach((id, index) => slots.set(id, {index, count: ids.length})));
        return slots;
    }, [state.relations]);

    // Container drawing (isolated from the node/connect pointer state machine:
    // a dedicated overlay captures these events only while the tool is active).
    const [drawStart, setDrawStart] = useState<Point | null>(null);
    const [drawBox, setDrawBox] = useState<ContainerBox | null>(null);

    const onDrawDown = useCallback((event: React.PointerEvent) => {
        if (!props.drawingContainer) {
            return;
        }
        event.stopPropagation();
        (event.target as Element).setPointerCapture?.(event.pointerId);
        const p = toSvgPoint(event.clientX, event.clientY);
        setDrawStart(p);
        setDrawBox({x: p.x, y: p.y, w: 0, h: 0});
    }, [props.drawingContainer, toSvgPoint]);

    const onDrawMove = useCallback((event: React.PointerEvent) => {
        if (!props.drawingContainer || !drawStart) {
            return;
        }
        event.stopPropagation();
        setDrawBox(boxFromDrag(drawStart, toSvgPoint(event.clientX, event.clientY)));
    }, [props.drawingContainer, drawStart, toSvgPoint]);

    const onDrawUp = useCallback((event: React.PointerEvent) => {
        if (!props.drawingContainer || !drawStart) {
            return;
        }
        event.stopPropagation();
        const box = drawBox;
        setDrawStart(null);
        setDrawBox(null);
        if (box && isDrawable(box) && props.onCreateContainer) {
            props.onCreateContainer(serializeGeometry(box));
        }
        props.onFinishDrawContainer?.();
    }, [props.drawingContainer, drawStart, drawBox, props.onCreateContainer, props.onFinishDrawContainer]);

    // Container move/resize via title-bar and corner-handle drags (pointer
    // capture keeps this out of the node/connect pointer state machine).
    const [containerDrag, setContainerDrag] = useState<
        {mode: 'move' | 'resize'; stableid: string; start: Point; startBox: ContainerBox; corner?: BoxCorner} | null
    >(null);
    const [containerPreview, setContainerPreview] = useState<ContainerBox | null>(null);
    const [renamingContainer, setRenamingContainer] = useState<string | null>(null);
    const [containerName, setContainerName] = useState('');

    const beginContainerDrag = useCallback((
        event: React.PointerEvent, mode: 'move' | 'resize', stableid: string, box: ContainerBox,
        corner?: BoxCorner
    ) => {
        if (disabled || containerMoveLockedForMe(stableid)) {
            return;
        }
        event.stopPropagation();
        (event.target as Element).setPointerCapture?.(event.pointerId);
        setContainerDrag({
            mode, stableid, start: toSvgPoint(event.clientX, event.clientY), startBox: box, corner,
        });
        setContainerPreview(box);
    }, [disabled, containerMoveLockedForMe, toSvgPoint]);

    const onContainerDragMove = useCallback((event: React.PointerEvent) => {
        if (!containerDrag) {
            return;
        }
        event.stopPropagation();
        const p = toSvgPoint(event.clientX, event.clientY);
        const dx = p.x - containerDrag.start.x;
        const dy = p.y - containerDrag.start.y;
        setContainerPreview(containerDrag.mode === 'move'
            ? moveBox(containerDrag.startBox, dx, dy)
            : resizeBoxCorner(containerDrag.startBox, containerDrag.corner ?? 'se', dx, dy));
    }, [containerDrag, toSvgPoint]);

    const onContainerDragUp = useCallback((event: React.PointerEvent) => {
        if (!containerDrag) {
            return;
        }
        event.stopPropagation();
        const box = containerPreview;
        const dragged = containerDrag.stableid;
        setContainerDrag(null);
        setContainerPreview(null);
        if (box && props.onUpdateContainer) {
            props.onUpdateContainer(dragged, serializeGeometry(box));
        }
    }, [containerDrag, containerPreview, props.onUpdateContainer]);

    const startRenameContainer = useCallback((stableid: string, current: string) => {
        if (disabled) {
            return;
        }
        setContainerName(current);
        setRenamingContainer(stableid);
    }, [disabled]);

    const commitRenameContainer = useCallback((stableid: string) => {
        setRenamingContainer(null);
        props.onRenameContainer?.(stableid, containerName.trim());
    }, [containerName, props.onRenameContainer]);

    const onNodePointerDown = useCallback(async (event: React.PointerEvent, stableid: string, label: string) => {
        // Manual double-click: two quick clicks on the same node open the text editor.
        const now = Date.now();
        const prev = lastNodeClick.current;
        const isDouble = prev.id === stableid && now - prev.t < 350;
        lastNodeClick.current = {id: stableid, t: now};
        vdbg('node-pointerdown', stableid, 'isDouble=' + isDouble, 'dt=' + (now - prev.t));
        if (isDouble && !disabled) {
            lastNodeClick.current = {id: '', t: 0};
            event.stopPropagation();
            // Stop the browser's default focus action (it would focus the SVG and
            // blur our editor a few ms after it mounts — the diagnosed race).
            event.preventDefault();
            vdbg('node-startEditing', stableid);
            setDragId(null);
            setDragPos(null);
            setMoved(false);
            editValueRef.current = label;
            setEditValue(label);
            dispatchInteraction({kind: 'startEditing', target: {kind: 'node', id: stableid}});
            return;
        }
        // A click always selects the node (and reveals its affordances).
        dispatchInteraction({kind: 'select', target: {kind: 'node', id: stableid}});
        if (disabled || lockedByOther(stableid) || nodeMoveLockedForMe(stableid)) {
            return;
        }
        // Start the drag synchronously: capture the pointer and arm the drag in
        // the same event turn, BEFORE any await. Doing the collaboration lock
        // first (an async round-trip) meant setDragId ran a tick later — if the
        // pointer was already released in that gap, onPointerUp ran while dragId
        // was still null and never cleared it, so the node stayed armed and then
        // "moved" on a later bare pointermove. The lock is acquired optimistically
        // in the background; if it is refused, we cancel the in-flight drag.
        event.preventDefault();
        (event.target as Element).setPointerCapture(event.pointerId);
        setDragId(stableid);
        setDragPos(clampToCanvas(positionOf(stableid)));
        setMoved(false);
        if (beginEdit) {
            const dragToken = stableid;
            void beginEdit('node', stableid).then(granted => {
                if (!granted) {
                    // Lock refused: drop the drag if it is still the same one and
                    // has not become an actual move.
                    setDragId(cur => (cur === dragToken && !movedRef.current ? null : cur));
                }
            });
        }
    }, [disabled, lockedByOther, nodeMoveLockedForMe, beginEdit, positionOf]);

    const onHandlePointerDown = useCallback(async (
        event: React.PointerEvent, stableid: string, label: string
    ) => {
        event.stopPropagation();
        if (disabled || lockedByOther(stableid) || nodeMoveLockedForMe(stableid) || !onNodeResized) {
            return;
        }
        // Arm the resize synchronously (same reasoning as onNodePointerDown): the
        // lock is acquired in the background, never before capture/arming.
        event.preventDefault();
        (event.target as Element).setPointerCapture(event.pointerId);
        setResizeId(stableid);
        setResizeSize(sizeOf(stableid, label));
        if (beginEdit) {
            const token = stableid;
            void beginEdit('node', stableid).then(granted => {
                if (!granted) {
                    setResizeId(cur => (cur === token ? null : cur));
                }
            });
        }
    }, [disabled, lockedByOther, nodeMoveLockedForMe, onNodeResized, beginEdit, sizeOf]);

    const onPointerMove = useCallback((event: React.PointerEvent) => {
        // Background gesture: one-finger pan, two-finger pinch-zoom.
        if (pointers.current.has(event.pointerId)) {
            pointers.current.set(event.pointerId, {x: event.clientX, y: event.clientY});
            const svg = svgRef.current;
            if (pointers.current.size >= 2 && svg) {
                const pts = [...pointers.current.values()];
                const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
                const midX = (pts[0].x + pts[1].x) / 2;
                const midY = (pts[0].y + pts[1].y) / 2;
                if (pinchDist.current && dist > 0) {
                    const ratio = pinchDist.current / dist;
                    const rect = svg.getBoundingClientRect();
                    userPannedRef.current = true;
                    setView(v => {
                        const fx = (midX - rect.left) / rect.width;
                        const fy = (midY - rect.top) / rect.height;
                        const cx = v.x + fx * v.w;
                        const cy = v.y + fy * v.h;
                        const nw = Math.min(MAX_VIEW_W, Math.max(MIN_VIEW_W, v.w * ratio));
                        const nh = nw * VIEW_ASPECT;
                        return clampView({x: cx - fx * nw, y: cy - fy * nh, w: nw, h: nh});
                    });
                }
                pinchDist.current = dist;
                return;
            }
            if (panStart.current && svg) {
                const rect = svg.getBoundingClientRect();
                const v = viewRef.current;
                const dx = (event.clientX - panStart.current.cx) / rect.width * v.w;
                const dy = (event.clientY - panStart.current.cy) / rect.height * v.h;
                const start = panStart.current;
                userPannedRef.current = true;
                setView(cur => clampView({...cur, x: start.vx - dx, y: start.vy - dy}));
                return;
            }
        }
        if (connectFrom !== null) {
            setConnectTo(toSvgPoint(event.clientX, event.clientY));
            return;
        }
        if (resizeId !== null) {
            // Centre-anchored resize: the box grows symmetrically about its
            // position, so a drag from any corner behaves the same.
            const centre = positionOf(resizeId);
            const p = toSvgPoint(event.clientX, event.clientY);
            setResizeSize(clampSize(2 * Math.abs(p.x - centre.x), 2 * Math.abs(p.y - centre.y)));
            return;
        }
        if (dragId === null) {
            return;
        }
        setMoved(true);
        setDragPos(clampToCanvas(toSvgPoint(event.clientX, event.clientY)));
    }, [connectFrom, resizeId, dragId, positionOf, toSvgPoint]);

    const onPointerUp = useCallback((event: React.PointerEvent) => {
        // Release a background gesture pointer.
        if (pointers.current.has(event.pointerId)) {
            pointers.current.delete(event.pointerId);
            if (pointers.current.size < 2) {
                pinchDist.current = null;
            }
            if (pointers.current.size === 0) {
                panStart.current = null;
            }
            return;
        }
        if (connectFrom !== null) {
            const target = connectTo ? nodeAt(connectTo) : null;
            if (target && target !== connectFrom && onCreateRelation) {
                onCreateRelation(connectFrom, target);
            }
            setConnectFrom(null);
            setConnectTo(null);
            return;
        }
        if (resizeId !== null) {
            if (resizeSize && onNodeResized) {
                onNodeResized(resizeId, resizeSize);
            }
            if (endEdit) {
                void endEdit('node', resizeId);
            }
            setResizeId(null);
            setResizeSize(null);
            return;
        }
        if (dragId !== null && dragPos && moved) {
            onNodeMoved(dragId, dragPos);
        }
        if (dragId !== null && endEdit) {
            void endEdit('node', dragId);
        }
        setDragId(null);
        setDragPos(null);
        setMoved(false);
    }, [connectFrom, connectTo, nodeAt, onCreateRelation,
        resizeId, resizeSize, onNodeResized, dragId, dragPos, moved, onNodeMoved, endEdit]);

    // Safety net: if the pointer capture is lost or the gesture is cancelled
    // (pointercancel, e.g. a context menu or an interrupted touch), a drag that
    // was armed but never received its pointerup would otherwise stay latched and
    // the node would follow the bare cursor. Clear it and release any lock.
    const abortDrag = useCallback(() => {
        if (dragIdRef.current !== null) {
            if (endEdit) {
                void endEdit('node', dragIdRef.current);
            }
            setDragId(null);
            setDragPos(null);
            setMoved(false);
        }
        if (resizeIdRef.current !== null) {
            if (endEdit) {
                void endEdit('node', resizeIdRef.current);
            }
            setResizeId(null);
            setResizeSize(null);
        }
    }, [endEdit]);

    // Begin dragging a new connection out of a node's connector dock.
    const startConnect = useCallback((event: React.PointerEvent, stableid: string) => {
        event.stopPropagation();
        if (disabled || lockedByOther(stableid) || !onCreateRelation) {
            return;
        }
        event.preventDefault();
        (event.target as Element).setPointerCapture(event.pointerId);
        setConnectFrom(stableid);
        setConnectTo(toSvgPoint(event.clientX, event.clientY));
    }, [disabled, lockedByOther, onCreateRelation, toSvgPoint]);

    // Pointer-down on empty canvas: clear selection and start a pan (or pinch if two fingers).
    const onBackgroundPointerDown = useCallback((event: React.PointerEvent) => {
        dispatchInteraction({kind: 'clear'});
        const svg = svgRef.current;
        if (!svg) {
            return;
        }
        svg.setPointerCapture(event.pointerId);
        pointers.current.set(event.pointerId, {x: event.clientX, y: event.clientY});
        if (pointers.current.size >= 2) {
            panStart.current = null;
            const pts = [...pointers.current.values()];
            pinchDist.current = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
        } else {
            panStart.current = {cx: event.clientX, cy: event.clientY, vx: viewRef.current.x, vy: viewRef.current.y};
        }
    }, []);

    // Wheel: zoom around the cursor. Registered natively so preventDefault is honoured.
    useEffect(() => {
        const svg = svgRef.current;
        if (!svg) {
            return undefined;
        }
        const onWheel = (event: WheelEvent): void => {
            event.preventDefault();
            const rect = svg.getBoundingClientRect();
            const factor = event.deltaY > 0 ? 1.1 : 1 / 1.1;
            userPannedRef.current = true;
            setView(v => {
                const fx = (event.clientX - rect.left) / rect.width;
                const fy = (event.clientY - rect.top) / rect.height;
                const cx = v.x + fx * v.w;
                const cy = v.y + fy * v.h;
                const nw = Math.min(MAX_VIEW_W, Math.max(MIN_VIEW_W, v.w * factor));
                const nh = nw * VIEW_ASPECT;
                return clampView({x: cx - fx * nw, y: cy - fy * nh, w: nw, h: nh});
            });
        };
        svg.addEventListener('wheel', onWheel, {passive: false});
        return () => svg.removeEventListener('wheel', onWheel);
    }, []);

    // Begin inline editing of a node's label (double-click on its text).
    const startNodeEdit = useCallback((stableid: string, label: string) => {
        if (disabled || lockedByOther(stableid)) {
            return;
        }
        editValueRef.current = label;
        setEditValue(label);
        dispatchInteraction({kind: 'startEditing', target: {kind: 'node', id: stableid}});
    }, [disabled, lockedByOther]);

    // Begin inline editing of a relation's label (double-click / text button).
    const startRelationEdit = useCallback((stableid: string, label: string) => {
        if (disabled) {
            return;
        }
        editValueRef.current = label;
        setEditValue(label);
        dispatchInteraction({kind: 'startEditing', target: {kind: 'relation', id: stableid}});
    }, [disabled]);

    // Commit the current inline edit to the parent.
    const commitEdit = useCallback(() => {
        const target = interaction.editing;
        vdbg('commitEdit', target, JSON.stringify(editValue));
        if (target) {
            if (target.kind === 'node' && onRenameNode) {
                onRenameNode(target.id, editValue);
            } else if (target.kind === 'relation' && onRenameRelation) {
                onRenameRelation(target.id, editValue);
            }
        }
        dispatchInteraction({kind: 'stopEditing'});
    }, [interaction.editing, editValue, onRenameNode, onRenameRelation]);

    // Commit the inline editor when the user clicks outside it (safety zone: the
    // field itself and the dock are excluded). Replaces onBlur, which was racing
    // with the browser focusing the SVG on click and closing the editor instantly.
    useEffect(() => {
        if (!interaction.editing) {
            return undefined;
        }
        vdbg('editing-active', interaction.editing);
        const onDocDown = (event: PointerEvent): void => {
            const el = event.target as Element | null;
            if (el && el.closest && el.closest(
                '.vimipad-canvas-edit, .vimipad-canvas-relation-edit, .vimipad-node-dock, .vimipad-text-menu'
            )) {
                return;
            }
            vdbg('outside-pointerdown -> commit');
            commitEdit();
        };
        document.addEventListener('pointerdown', onDocDown, true);
        return () => document.removeEventListener('pointerdown', onDocDown, true);
    }, [interaction.editing, commitEdit]);

    // Canvas-level keyboard: ESC clears, Del removes the selected element.
    const onKeyDown = useCallback((event: React.KeyboardEvent) => {
        if (interaction.editing) {
            return;
        }
        if (event.key === 'Escape') {
            dispatchInteraction({kind: 'clear'});
            return;
        }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            const target = deletableTarget(interaction);
            if (!target || disabled) {
                return;
            }
            event.preventDefault();
            if (target.kind === 'node' && onDeleteNode) {
                onDeleteNode(target.id);
            } else if (target.kind === 'relation' && onDeleteRelation) {
                onDeleteRelation(target.id);
            }
            dispatchInteraction({kind: 'clear'});
        }
    }, [interaction, disabled, onDeleteNode, onDeleteRelation]);

    // Text keys while editing: Enter commits, Shift+Enter inserts a newline.
    const onEditKeyDown = useCallback((event: React.KeyboardEvent<HTMLElement>) => {
        event.stopPropagation();
        // Enter now inserts a newline (multi-line labels); commit is via the
        // green confirm button or a click outside. Escape cancels without saving.
        if (event.key === 'Escape') {
            event.preventDefault();
            vdbg('edit-escape-cancel');
            dispatchInteraction({kind: 'clear'});
        }
    }, []);

    // Escape during a move/resize cancels it and restores the original state
    // (we simply drop the in-progress drag without committing it).
    useEffect(() => {
        if (dragId === null && resizeId === null) {
            return undefined;
        }
        const onKey = (event: KeyboardEvent): void => {
            if (event.key !== 'Escape') {
                return;
            }
            vdbg('operation-escape-cancel', {dragId, resizeId});
            if (resizeId !== null) {
                if (endEdit) {
                    void endEdit('node', resizeId);
                }
                setResizeId(null);
                setResizeSize(null);
            }
            if (dragId !== null) {
                if (endEdit) {
                    void endEdit('node', dragId);
                }
                setDragId(null);
                setDragPos(null);
                setMoved(false);
            }
        };
        document.addEventListener('keydown', onKey, true);
        return () => document.removeEventListener('keydown', onKey, true);
    }, [dragId, resizeId, endEdit]);

    const selectRelation = useCallback((event: React.PointerEvent, stableid: string) => {
        event.stopPropagation();
        dispatchInteraction({kind: 'select', target: {kind: 'relation', id: stableid}});
    }, []);

    const selColor = 'var(--vimipad-selected, #2563eb)';

    return (
        <div
            ref={wrapRef}
            className={`vimipad-canvas-wrap${fallbackFs ? ' vimipad-canvas-wrap--expanded' : ''}`}
        >
            <div className="vimipad-canvas-actions" role="toolbar" aria-label={t('editor:actions')}>
                <button
                    type="button"
                    className="btn btn-light vimipad-canvas-action"
                    onClick={() => onUndo?.()}
                    disabled={disabled || !canUndo}
                    title={t('editor:undo')}
                    aria-label={t('editor:undo')}
                >
                    <Icon name={FA.undo} />
                </button>
                <button
                    type="button"
                    className="btn btn-light vimipad-canvas-action"
                    onClick={() => onRedo?.()}
                    disabled={disabled || !canRedo}
                    title={t('editor:redo')}
                    aria-label={t('editor:redo')}
                >
                    <Icon name={FA.redo} />
                </button>
                <button
                    type="button"
                    className="btn btn-light vimipad-canvas-action"
                    onClick={() => onReArrange?.()}
                    disabled={disabled}
                    title={t('editor:rearrange')}
                    aria-label={t('editor:rearrange')}
                >
                    <Icon name={FA.reArrange} />
                </button>
                {props.canManage && props.onToggleDrawContainer && (
                    <button
                        type="button"
                        className={`btn btn-light vimipad-canvas-action${props.drawingContainer ? ' active' : ''}`}
                        onClick={() => props.onToggleDrawContainer?.()}
                        disabled={disabled}
                        aria-pressed={props.drawingContainer === true}
                        title={props.drawingContainer ? t('editor:drawcontainerdone') : t('editor:drawcontainer')}
                        aria-label={props.drawingContainer ? t('editor:drawcontainerdone') : t('editor:drawcontainer')}
                    >
                        <Icon name={FA.container} />
                    </button>
                )}
                {!expanded && (
                    <div className="vimipad-export" ref={exportRef}>
                        <button
                            type="button"
                            className="btn btn-light vimipad-canvas-action"
                            onClick={() => setExportOpen((o) => !o)}
                            aria-haspopup="menu"
                            aria-expanded={exportOpen}
                            title={t('editor:export')}
                            aria-label={t('editor:export')}
                        >
                            <Icon name={FA.export} />
                        </button>
                        {exportOpen && (
                            <div className="vimipad-export-menu" role="menu">
                                <button
                                    type="button"
                                    role="menuitem"
                                    className="vimipad-export-item"
                                    onClick={() => { onExportSvg?.(); setExportOpen(false); }}
                                >SVG</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    className="vimipad-export-item"
                                    onClick={() => { onExportPng?.(); setExportOpen(false); }}
                                >PNG</button>
                                <button
                                    type="button"
                                    role="menuitem"
                                    className="vimipad-export-item"
                                    onClick={() => { onExportPdf?.(); setExportOpen(false); }}
                                >PDF</button>
                            </div>
                        )}
                    </div>
                )}
            </div>
            <div
                className="vimipad-canvas-actions vimipad-canvas-actions--right"
                role="toolbar"
                aria-label={t('editor:actions')}
            >
                {props.canLock && props.onToggleLockMode && (
                    <button
                        type="button"
                        className={`btn btn-light vimipad-canvas-action${props.lockMode ? ' active' : ''}`}
                        onClick={() => props.onToggleLockMode?.()}
                        aria-pressed={props.lockMode === true}
                        title={t('editor:lockmode')}
                        aria-label={t('editor:lockmode')}
                    >
                        <Icon name={props.lockMode ? FA.lock : FA.unlock} />
                    </button>
                )}
                <button
                    type="button"
                    className="btn btn-light vimipad-canvas-fullview vimipad-canvas-action"
                    onClick={() => { void toggleFullview(); }}
                    title={expanded ? t('editor:normalview') : t('editor:fullview')}
                    aria-label={expanded ? t('editor:normalview') : t('editor:fullview')}
                    aria-pressed={expanded}
                >
                    <Icon name={expanded ? FA.compress : FA.expand} />
                </button>
            </div>
            <svg
                ref={svgRef}
                className="vimipad-canvas border rounded"
            viewBox={`${view.x} ${view.y} ${view.w} ${view.h}`}
            width="100%"
            role="img"
            tabIndex={0}
            style={{touchAction: 'none'}}
            aria-labelledby="vimipad-canvas-title"
            aria-describedby="vimipad-canvas-desc"
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={abortDrag}
            onLostPointerCapture={abortDrag}
            onKeyDown={onKeyDown}
        >
            <title id="vimipad-canvas-title">{t('editor:canvasaria')}</title>
            <desc id="vimipad-canvas-desc">{t('editor:canvashint')}</desc>
            <defs>
                <marker
                    id="vimipad-arrow"
                    viewBox="0 0 10 10"
                    refX="9"
                    refY="5"
                    markerWidth="7"
                    markerHeight="7"
                    orient="auto-start-reverse"
                >
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                </marker>
            </defs>

            {/* Background: pan/pinch surface; a tap also clears the selection. */}
            <rect
                x={0}
                y={0}
                width={CANVAS_WIDTH}
                height={CANVAS_HEIGHT}
                fill="transparent"
                style={{touchAction: 'none', cursor: 'grab'}}
                onPointerDown={onBackgroundPointerDown}
            />

            {/* Container layer (behind the graph): author/teacher background boxes. */}
            {(state.containers ?? []).map(container => {
                const stored = parseGeometry(container.geometryjson);
                if (!stored) {
                    return null;
                }
                const box = (containerDrag?.stableid === container.stableid && containerPreview)
                    ? containerPreview
                    : stored;
                const titleH = 24;
                const renaming = renamingContainer === container.stableid;
                // A locked container is only editable by an author/manager.
                const editable = !disabled && (state.canmanage === true || !isLocked(container.metadatajson));
                // T4: containers carry the same style model as nodes (shape, fill,
                // text) in their metadatajson. With no style set they keep the
                // default dashed look; otherwise they render like a shaped node.
                const cstyle = parseNodeStyle(container.metadatajson);
                const cselected = isSelected(interaction, 'container', container.stableid);
                const hasShape = cstyle.shape !== undefined && cstyle.shape !== null;
                const bodyFill = cstyle.fill ?? 'rgba(120, 144, 156, 0.08)';
                const bodyStroke = cselected ? selColor : 'rgba(84, 110, 122, 0.55)';
                const labelColor = cstyle.text?.color ?? 'rgba(55, 71, 79, 0.95)';
                return (
                    <g key={`container-${container.stableid}`} className="vimipad-canvas-container">
                        {/* Body: non-interactive so nodes underneath stay clickable. */}
                        {hasShape ? (
                            <g transform={`translate(${box.x + box.w / 2}, ${box.y + box.h / 2})`} pointerEvents="none">
                                {shapeElement(cstyle.shape ?? 'roundrect', box.w, box.h, {
                                    fill: bodyFill,
                                    stroke: bodyStroke,
                                    strokeWidth: cselected ? 2 : 1,
                                })}
                            </g>
                        ) : (
                            <rect
                                x={box.x}
                                y={box.y}
                                width={box.w}
                                height={box.h}
                                rx={8}
                                fill={bodyFill}
                                stroke={bodyStroke}
                                strokeDasharray="6 4"
                                strokeWidth={cselected ? 2 : 1}
                                pointerEvents="none"
                            />
                        )}
                        {/* Title bar: move handle, select and rename target. It
                            stays interactive (transparent) even with no label, so
                            an unlabelled container is still selectable/movable; the
                            grey fill is only shown when it carries a label or is
                            active, so an empty container shows no grey strip (R9). */}
                        <rect
                            className="vimipad-container-title"
                            x={box.x}
                            y={box.y}
                            width={box.w}
                            height={titleH}
                            rx={8}
                            fill={((container.label ?? '').trim() !== '' || cselected || renaming)
                                ? 'rgba(84, 110, 122, 0.14)' : 'transparent'}
                            style={{cursor: editable ? 'move' : 'default', touchAction: 'none'}}
                            onPointerDown={editable
                                ? (e => {
                                    dispatchInteraction({
                                        kind: 'select', target: {kind: 'container', id: container.stableid},
                                    });
                                    beginContainerDrag(e, 'move', container.stableid, stored);
                                })
                                : undefined}
                            onPointerMove={onContainerDragMove}
                            onPointerUp={onContainerDragUp}
                            onDoubleClick={editable
                                ? (() => startRenameContainer(container.stableid, container.label))
                                : undefined}
                        />
                        {renaming ? (
                            <foreignObject
                                x={box.x + 4}
                                y={box.y + 2}
                                width={Math.max(40, box.w - 30)}
                                height={titleH - 2}
                            >
                                <input
                                    className="form-control form-control-sm vimipad-container-rename"
                                    autoFocus
                                    value={containerName}
                                    onChange={e => setContainerName(e.target.value)}
                                    onBlur={() => commitRenameContainer(container.stableid)}
                                    onKeyDown={e => {
                                        if (e.key === 'Enter') {
                                            commitRenameContainer(container.stableid);
                                        }
                                        if (e.key === 'Escape') {
                                            setRenamingContainer(null);
                                        }
                                    }}
                                    onPointerDown={e => e.stopPropagation()}
                                />
                            </foreignObject>
                        ) : (
                            <text
                                x={box.x + 10}
                                y={box.y + 17}
                                className="vimipad-container-label"
                                style={{
                                    fontSize: 13,
                                    fill: labelColor,
                                    fontWeight: cstyle.text?.bold ? 700 : undefined,
                                    fontStyle: cstyle.text?.italic ? 'italic' : undefined,
                                }}
                                pointerEvents="none"
                            >
                                {container.label}
                            </text>
                        )}
                        {/* Delete lives in the container's format menu (the
                          * trash-can button); the title-bar × was redundant. */}
                        {editable && !renaming && props.onUpdateContainer && ([
                            {c: 'nw' as BoxCorner, x: box.x, y: box.y, cursor: 'nwse-resize'},
                            {c: 'ne' as BoxCorner, x: box.x + box.w, y: box.y, cursor: 'nesw-resize'},
                            {c: 'sw' as BoxCorner, x: box.x, y: box.y + box.h, cursor: 'nesw-resize'},
                            {c: 'se' as BoxCorner, x: box.x + box.w, y: box.y + box.h, cursor: 'nwse-resize'},
                        ].map(h => (
                            <rect
                                key={`cresize-${container.stableid}-${h.c}`}
                                className="vimipad-container-resize"
                                x={h.x - 6}
                                y={h.y - 6}
                                width={12}
                                height={12}
                                rx={2}
                                fill="rgba(84, 110, 122, 0.5)"
                                style={{cursor: h.cursor, touchAction: 'none'}}
                                onPointerDown={e => beginContainerDrag(e, 'resize', container.stableid, stored, h.c)}
                                onPointerMove={onContainerDragMove}
                                onPointerUp={onContainerDragUp}
                            />
                        )))}
                        {/* The container's format menu is rendered in the top
                          * menu overlay (Layer 4), not here in the bottom
                          * container layer, so nodes cannot occlude it. */}
                    </g>
                );
            })}

            {/* Layer 1 (bottom): connector lines and their hit targets. */}
            {state.relations.map(rel => {
                const srcNode = state.nodes.find(n => n.stableid === rel.sourceid);
                const tgtNode = state.nodes.find(n => n.stableid === rel.targetid);
                const fromC = positionOf(rel.sourceid);
                const toC = positionOf(rel.targetid);
                const fromSize = srcNode ? sizeOf(srcNode.stableid, srcNode.label) : {w: 70, h: 40};
                const toSize = tgtNode ? sizeOf(tgtNode.stableid, tgtNode.label) : {w: 70, h: 40};
                const isTree = sharedBifurcation;
                const slot = siblingSlots.get(rel.stableid) ?? {index: 0, count: 1};
                const slotOffset = siblingOffsets(slot.count, SIBLING_SPACING)[slot.index] ?? 0;
                const baseFrom = isTree
                    ? {x: fromC.x, y: fromC.y + fromSize.h / 2}
                    : edgePoint(fromC, fromSize, toC);
                const baseTo = isTree
                    ? {x: toC.x, y: toC.y - toSize.h / 2}
                    : edgePoint(toC, toSize, fromC);
                // Multiple relations between the same pair are shifted symmetrically
                // perpendicular to the direct line, so they run parallel.
                const shifted = isTree ? {from: baseFrom, to: baseTo} : offsetAnchors(baseFrom, baseTo, slotOffset);
                const from = shifted.from;
                const to = shifted.to;
                const selected = isSelected(interaction, 'relation', rel.stableid);
                const d = rel.direction ?? 0;
                const path = isTree
                    ? treeBusPath(fromC, fromSize, toC, toSize)
                    : (relLine === 'curved'
                        ? freeConnectorPath(from, to, ARROW_STUB)
                        : relLinePath(from, to, relLine));
                const stroke = selected ? selColor : 'currentColor';
                const strokeWidth = selected ? 2.5 : 1.5;
                const markerStart = d === -1 || d === 2 ? 'url(#vimipad-arrow)' : undefined;
                const markerEnd = d === 1 || d === 2 ? 'url(#vimipad-arrow)' : undefined;
                return (
                    <g key={`line-${rel.stableid}`} className="vimipad-canvas-relation">
                        {path ? (
                            <path
                                d={path}
                                fill="none"
                                stroke={stroke}
                                strokeWidth={strokeWidth}
                                markerStart={markerStart}
                                markerEnd={markerEnd}
                            />
                        ) : (
                            <line
                                x1={from.x}
                                y1={from.y}
                                x2={to.x}
                                y2={to.y}
                                stroke={stroke}
                                strokeWidth={strokeWidth}
                                markerStart={markerStart}
                                markerEnd={markerEnd}
                            />
                        )}
                        {path ? (
                            <path
                                d={path}
                                fill="none"
                                stroke="transparent"
                                strokeWidth={12}
                                style={{cursor: 'pointer'}}
                                onPointerDown={e => selectRelation(e, rel.stableid)}
                            />
                        ) : (
                            <line
                                x1={from.x}
                                y1={from.y}
                                x2={to.x}
                                y2={to.y}
                                stroke="transparent"
                                strokeWidth={12}
                                style={{cursor: 'pointer'}}
                                onPointerDown={e => selectRelation(e, rel.stableid)}
                            />
                        )}
                    </g>
                );
            })}

            {/* Layer 2 (middle): connector labels, inline editors and the direction dock. */}
            {state.relations.map(rel => {
                // Anchor the label on the same routed connection as the line layer,
                // including the parallel-sibling offset, so multi-relation labels
                // follow their own connector instead of piling on the centre line.
                const srcNode = state.nodes.find(n => n.stableid === rel.sourceid);
                const tgtNode = state.nodes.find(n => n.stableid === rel.targetid);
                const fromC = positionOf(rel.sourceid);
                const toC = positionOf(rel.targetid);
                const fromSize = srcNode ? sizeOf(srcNode.stableid, srcNode.label) : {w: 70, h: 40};
                const toSize = tgtNode ? sizeOf(tgtNode.stableid, tgtNode.label) : {w: 70, h: 40};
                const isTree = sharedBifurcation;
                const slot = siblingSlots.get(rel.stableid) ?? {index: 0, count: 1};
                const slotOffset = siblingOffsets(slot.count, SIBLING_SPACING)[slot.index] ?? 0;
                const baseFrom = isTree
                    ? {x: fromC.x, y: fromC.y + fromSize.h / 2}
                    : edgePoint(fromC, fromSize, toC);
                const baseTo = isTree
                    ? {x: toC.x, y: toC.y - toSize.h / 2}
                    : edgePoint(toC, toSize, fromC);
                const anchors = isTree ? {from: baseFrom, to: baseTo} : offsetAnchors(baseFrom, baseTo, slotOffset);
                // The label sits at the curve peak: the midpoint lifted perpendicular
                // by the sibling offset, matching freeConnectorPath's own bulge.
                const peak = labelPoint(anchors.from, anchors.to, slotOffset);
                const midX = peak.x;
                const midY = peak.y;
                const editing = isEditing(interaction, 'relation', rel.stableid);
                return (
                    <g key={`lbl-${rel.stableid}`} className="vimipad-canvas-relation">
                        {editing ? (
                            <foreignObject x={midX - 80} y={midY - 18} width={160} height={34}>
                                <input
                                    className="vimipad-canvas-relation-edit"
                                    value={editValue}
                                    autoFocus
                                    onChange={e => setEditValue(e.target.value)}
                                    onKeyDown={onEditKeyDown}
                                    onFocus={() => vdbg('relation-input focus', rel.stableid)}
                                    onBlur={() => vdbg('relation-input blur', rel.stableid)}
                                    onPointerDown={e => e.stopPropagation()}
                                />
                            </foreignObject>
                        ) : (rel.label && (
                            <text
                                x={midX}
                                y={midY - 4}
                                textAnchor="middle"
                                className="vimipad-canvas-label"
                                paintOrder="stroke"
                                stroke="var(--vimipad-label-outline, #ffffff)"
                                strokeWidth={3}
                                strokeLinejoin="round"
                                fill="currentColor"
                                onDoubleClick={() => startRelationEdit(rel.stableid, rel.label)}
                            >
                                {rel.label}
                            </text>
                        ))}
                    </g>
                );
            })}

            {/* Layer 3 (top of the graph, below the menu overlay): nodes. */}
            {state.nodes.map(node => {
                const pos = positionOf(node.stableid);
                const editing = isEditing(interaction, 'node', node.stableid);
                // While editing, size from the live text so the box grows as lines are added.
                const sizingLabel = editing ? editValue : node.label;
                const {w, h} = sizeOf(node.stableid, sizingLabel);
                const otherLock = lockedByOther(node.stableid);
                const selected = isSelected(interaction, 'node', node.stableid);
                const style = parseNodeStyle(node.metadatajson);
                const shape = formClampShape(formconfig, profile, style.shape);
                const fill = otherLock
                    ? 'var(--vimipad-node-locked-fill, #f3f4f6)'
                    : (style.fill ?? 'var(--vimipad-node-fill, #eef2ff)');
                const hovered = hoveredId === node.stableid;
                // Hover/selection reveals the quick affordances (resize corners, connector docks).
                const affordances = (selected || hovered) && !disabled && !otherLock && !editing;
                const canResize = !!onNodeResized && affordances;
                return (
                    <g
                        key={node.stableid}
                        className={`vimipad-canvas-node${otherLock ? ' vimipad-canvas-node-locked' : ''}`
                            + `${selected ? ' vimipad-canvas-node-selected' : ''}`}
                        transform={`translate(${pos.x}, ${pos.y})`}
                        onPointerDown={e => onNodePointerDown(e, node.stableid, node.label)}
                        onPointerEnter={() => setHoveredId(node.stableid)}
                        onPointerLeave={() => setHoveredId(cur => (cur === node.stableid ? null : cur))}
                        style={{cursor: disabled || otherLock ? 'not-allowed' : 'move'}}
                        aria-disabled={otherLock}
                    >
                        {shapeElement(shape, w, h, {
                            fill,
                            stroke: selected ? selColor : 'currentColor',
                            strokeWidth: selected ? 2.5 : 1,
                            strokeDasharray: otherLock ? '4 2' : undefined,
                        })}
                        {/* Marching-ants move affordance for the selected node. */}
                        {selected && !otherLock && shapeElement(shape, w + 6, h + 6, {
                            className: 'vimipad-canvas-seloutline',
                            fill: 'none',
                            stroke: selColor,
                            strokeWidth: 1.5,
                            strokeDasharray: '6 4',
                        })}
                        {/* The template-lock badge is drawn in a dedicated
                            overlay layer above all elements (top-right, outside),
                            not here inside the node. */}
                        {editing ? (
                            <foreignObject key={`edit-${node.stableid}`} x={-w / 2} y={-h / 2} width={w} height={h}>
                                {/*
                                  * The centring wrapper carries the flex layout; the editable
                                  * element itself must stay a block. As a flex container it
                                  * would turn each line block the browser inserts on Enter
                                  * into a flex item, laying the lines out side by side as
                                  * columns instead of stacking them.
                                  */}
                                <div style={labelBox(style.text)}>
                                    <div
                                        ref={setEditRef}
                                        className="vimipad-canvas-edit"
                                        contentEditable
                                        suppressContentEditableWarning
                                        onInput={e => {
                                            const text = editableToText(e.currentTarget);
                                            editValueRef.current = text;
                                            setEditValue(text);
                                        }}
                                        onKeyDown={onEditKeyDown}
                                        onFocus={() => vdbg('node-editor focus', node.stableid)}
                                        onBlur={() => vdbg('node-editor blur', node.stableid)}
                                        onPointerDown={e => e.stopPropagation()}
                                        style={{
                                            display: 'block',
                                            width: '100%',
                                            textAlign: 'center',
                                            whiteSpace: 'pre-wrap',
                                            overflowWrap: 'anywhere',
                                            wordBreak: 'break-word',
                                            background: style.text?.background ?? 'transparent',
                                            outline: 'none',
                                            cursor: 'text',
                                        }}
                                    />
                                </div>
                            </foreignObject>
                        ) : (
                            <foreignObject
                                key={`label-${node.stableid}`}
                                x={-w / 2}
                                y={-h / 2}
                                width={w}
                                height={h}
                                style={{pointerEvents: 'none'}}
                            >
                                <div style={labelBox(style.text)}>
                                    <span
                                        style={style.text?.background
                                            ? {background: style.text.background, padding: '0 3px', borderRadius: 3}
                                            : undefined}
                                    >{node.label}</span>
                                </div>
                            </foreignObject>
                        )}
                        {/* Four corner resize handles when the node is selected. */}
                        {canResize && ([
                            {x: -w / 2, y: -h / 2, cursor: 'nwse-resize'},
                            {x: w / 2, y: -h / 2, cursor: 'nesw-resize'},
                            {x: -w / 2, y: h / 2, cursor: 'nesw-resize'},
                            {x: w / 2, y: h / 2, cursor: 'nwse-resize'},
                        ].map((c, i) => (
                            <g key={i}>
                                {/* Enlarged transparent hit target for finger use. */}
                                <rect
                                    x={c.x - HANDLE_HIT / 2}
                                    y={c.y - HANDLE_HIT / 2}
                                    width={HANDLE_HIT}
                                    height={HANDLE_HIT}
                                    fill="transparent"
                                    style={{cursor: c.cursor}}
                                    onPointerDown={e => onHandlePointerDown(e, node.stableid, node.label)}
                                />
                                <rect
                                    className="vimipad-canvas-handle"
                                    x={c.x - HANDLE / 2}
                                    y={c.y - HANDLE / 2}
                                    width={HANDLE}
                                    height={HANDLE}
                                    fill={selColor}
                                    style={{pointerEvents: 'none'}}
                                />
                            </g>
                        )))}
                        {/* Connector docks at the four edge midpoints: drag one to another node. */}
                        {affordances && onCreateRelation && [
                            {x: 0, y: -h / 2},
                            {x: w / 2, y: 0},
                            {x: 0, y: h / 2},
                            {x: -w / 2, y: 0},
                        ].map((c, i) => (
                            <circle
                                key={`conn${i}`}
                                className="vimipad-canvas-connector"
                                cx={c.x}
                                cy={c.y}
                                r={7}
                                fill={selColor}
                                stroke="var(--vimipad-connector-ring, #ffffff)"
                                strokeWidth={1.5}
                                style={{cursor: 'crosshair'}}
                                onPointerDown={e => startConnect(e, node.stableid)}
                            />
                        ))}
                        {otherLock && (
                            <text
                                textAnchor="middle"
                                y={h / 2 + 14}
                                className="vimipad-canvas-lockhint"
                                fill="var(--vimipad-lock-hint, #6b7280)"
                            >
                                {t('editor:beingedited')}
                            </text>
                        )}
                    </g>
                );
            })}

            {connectFrom !== null && connectTo && (
                <line
                    className="vimipad-canvas-connectline"
                    x1={positionOf(connectFrom).x}
                    y1={positionOf(connectFrom).y}
                    x2={connectTo.x}
                    y2={connectTo.y}
                    stroke={selColor}
                    strokeWidth={2}
                    strokeDasharray="6 4"
                    markerEnd="url(#vimipad-arrow)"
                    style={{pointerEvents: 'none'}}
                />
            )}

            {/* Layer 3b (above all graph elements, below the menu overlay):
                template-lock badges. A grey padlock with a white outline is drawn
                just outside the top-right corner of every locked node, container
                and relation, so it sits on top of the elements and their text but
                under the menu overlay (Layer 4). */}
            <g className="vimipad-lock-badges" pointerEvents="none" aria-hidden="true">
                {(state.containers ?? []).map(container => {
                    if (!isLocked(container.metadatajson)) {
                        return null;
                    }
                    const box = (containerDrag?.stableid === container.stableid && containerPreview)
                        ? containerPreview
                        : parseGeometry(container.geometryjson);
                    if (!box) {
                        return null;
                    }
                    return (
                        <SvgLockBadge key={`lockbadge-c-${container.stableid}`} x={box.x + box.w - 2} y={box.y + 2} />
                    );
                })}
                {state.relations.map(rel => {
                    if (!isLocked(rel.metadatajson)) {
                        return null;
                    }
                    const from = positionOf(rel.sourceid);
                    const to = positionOf(rel.targetid);
                    return (
                        <SvgLockBadge
                            key={`lockbadge-r-${rel.stableid}`}
                            x={(from.x + to.x) / 2 + 12}
                            y={(from.y + to.y) / 2 - 14}
                        />
                    );
                })}
                {state.nodes.map(node => {
                    if (!isLocked(node.metadatajson)) {
                        return null;
                    }
                    const editing = isEditing(interaction, 'node', node.stableid);
                    const p = positionOf(node.stableid);
                    const {w, h} = sizeOf(node.stableid, editing ? editValue : node.label);
                    return (
                        <SvgLockBadge key={`lockbadge-n-${node.stableid}`} x={p.x + w / 2 + 3} y={p.y - h / 2 - 3} />
                    );
                })}
            </g>

            {/* Layer 4 (top): exactly one menu, for the active (selected/edited) element. */}
            {(() => {
                const active = interaction.editing ?? interaction.selected;
                if (!active || disabled) {
                    return null;
                }
                if (active.kind === 'container') {
                    const container = (state.containers ?? []).find(c => c.stableid === active.id);
                    if (!container || !props.onUpdateContainerStyle) {
                        return null;
                    }
                    // Track the live drag preview so the menu stays anchored to
                    // the container while it is moved or resized, instead of
                    // detaching to the committed geometry and snapping back on
                    // release (the "menu jumps around" bug on overlapping
                    // containers, where a stray nudge starts a move).
                    const box = (containerDrag?.stableid === container.stableid && containerPreview)
                        ? containerPreview
                        : parseGeometry(container.geometryjson);
                    if (!box) {
                        return null;
                    }
                    const renaming = renamingContainer === container.stableid;
                    const editable = !disabled && (state.canmanage === true || !isLocked(container.metadatajson));
                    if (!editable || renaming) {
                        return null;
                    }
                    // Rendered in the top overlay so nodes overlapping the
                    // container can never occlude the menu (fixes the z-order
                    // and the resulting pointer/cursor and click-through bugs).
                    return (
                        <MenuOverlay x={box.x} y={box.y - 52} width={320} height={48}>
                                <NodeFormatToolbar
                                    kind="node"
                                    target={{metadatajson: container.metadatajson}}
                                    profile={profile}
                                    formconfig={formconfig}
                                    disabled={disabled}
                                    onChangeStyle={m => props.onUpdateContainerStyle?.(container.stableid, m)}
                                    onDelete={() => props.onDeleteContainer?.(container.stableid)}
                                    onEditText={() => startRenameContainer(container.stableid, container.label)}
                                    lockGroups={props.onSetElementLock && props.canLock
                                        ? ['move', 'color', 'text'] : undefined}
                                    onToggleLockGroup={props.onSetElementLock && props.canLock
                                        ? (group) => toggleLockGroup(
                                            'container', container.stableid, container.metadatajson, group)
                                        : undefined}
                                    t={t}
                                />
                        </MenuOverlay>
                    );
                }
                if (active.kind === 'node') {
                    const node = state.nodes.find(n => n.stableid === active.id);
                    if (!node || lockedByOther(node.stableid) || !onChangeStyle) {
                        return null;
                    }
                    const pos = positionOf(node.stableid);
                    const editing = isEditing(interaction, 'node', node.stableid);
                    const {h} = sizeOf(node.stableid, editing ? editValue : node.label);
                    return (
                        <MenuOverlay
                            x={pos.x - 150}
                            y={pos.y + h / 2 + 12}
                            width={300}
                            height={editing ? 300 : 320}
                        >
                                {editing ? (
                                    <TextEditMenu
                                        metadatajson={node.metadatajson}
                                        disabled={disabled}
                                        onChangeStyle={m => onChangeStyle(node.stableid, m)}
                                        onConfirm={commitEdit}
                                        t={t}
                                    />
                                ) : (
                                    <NodeFormatToolbar
                                        target={node}
                                        profile={profile}
                                        formconfig={formconfig}
                                        disabled={disabled}
                                        onChangeStyle={m => onChangeStyle(node.stableid, m)}
                                        onDuplicate={() => onDuplicateNode && onDuplicateNode(node.stableid)}
                                        onDelete={() => onDeleteNode && onDeleteNode(node.stableid)}
                                        onEditText={() => startNodeEdit(node.stableid, node.label)}
                                        lockGroups={props.onSetElementLock && props.canLock
                                            ? ['move', 'color', 'text'] : undefined}
                                        onToggleLockGroup={props.onSetElementLock && props.canLock
                                            ? (group) => toggleLockGroup('node', node.stableid, node.metadatajson, group)
                                            : undefined}
                                        t={t}
                                    />
                                )}
                        </MenuOverlay>
                    );
                }
                const rel = state.relations.find(r => r.stableid === active.id);
                if (!rel) {
                    return null;
                }
                const from = positionOf(rel.sourceid);
                const to = positionOf(rel.targetid);
                const midX = (from.x + to.x) / 2;
                const midY = (from.y + to.y) / 2;
                const editing = isEditing(interaction, 'relation', rel.stableid);
                const d = rel.direction ?? 0;
                return (
                    <MenuOverlay x={midX - 150} y={midY + 14} width={300} height={70}>
                            {editing ? (
                                <TextEditMenu disabled={disabled} onConfirm={commitEdit} t={t} />
                            ) : (
                                <RelationMenu
                                    stableid={rel.stableid}
                                    direction={d}
                                    metadatajson={rel.metadatajson}
                                    disabled={disabled}
                                    canLock={Boolean(props.onSetElementLock && props.canLock)}
                                    onEditText={() => startRelationEdit(rel.stableid, rel.label)}
                                    onChangeDirection={onChangeDirection}
                                    onDelete={onDeleteRelation
                                        ? () => onDeleteRelation(rel.stableid) : undefined}
                                    onToggleLockGroup={props.onSetElementLock && props.canLock
                                        ? (group) => toggleLockGroup(
                                            'relation', rel.stableid, rel.metadatajson, group)
                                        : undefined}
                                    t={t}
                                />
                            )}
                    </MenuOverlay>
                );
            })()}

            {/* Container draw overlay (topmost): active only while the tool is on. */}
            {props.drawingContainer && (
                <g className="vimipad-container-draw">
                    <rect
                        x={0}
                        y={0}
                        width={CANVAS_WIDTH}
                        height={CANVAS_HEIGHT}
                        fill="rgba(38, 50, 56, 0.04)"
                        style={{touchAction: 'none', cursor: 'crosshair'}}
                        onPointerDown={onDrawDown}
                        onPointerMove={onDrawMove}
                        onPointerUp={onDrawUp}
                    />
                    {drawBox && (
                        <rect
                            x={drawBox.x}
                            y={drawBox.y}
                            width={drawBox.w}
                            height={drawBox.h}
                            rx={8}
                            fill="rgba(120, 144, 156, 0.12)"
                            stroke="rgba(84, 110, 122, 0.9)"
                            strokeDasharray="6 4"
                            pointerEvents="none"
                        />
                    )}
                </g>
            )}
        </svg>
        </div>
    );
}
