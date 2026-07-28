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

import React, {useCallback, useEffect, useReducer, useRef, useState} from 'react';
import {CANVAS_HEIGHT, CANVAS_WIDTH, clampToCanvas} from '../graph/autolayout';
import {EditorState} from '../store/reducer';
import {LayoutMap, Point, Size, SizeMap} from '../types';
import {clampShape, NodeShape} from '../canvas/shape_catalog';
import {parseNodeStyle, TextStyle} from '../canvas/node_style';
import {NodeFormatToolbar} from './NodeFormatToolbar';
import {TextEditMenu} from './TextEditMenu';
import {FA, Icon} from '../canvas/icons';
import {
    deletableTarget,
    initialInteraction,
    interactionReduce,
    isEditing,
    isSelected,
    Target,
} from '../canvas/interaction';

/**
 * Lightweight diagnostic logger. Enable in the browser console with
 * `window.VIMIPAD_DEBUG = true` (disable with `= false`). Logs the edit/selection
 * lifecycle so interaction bugs can be traced without a debugger.
 *
 * @param args Values to log.
 */
function vdbg(...args: unknown[]): void {
    const w = typeof window !== 'undefined' ? (window as unknown as {VIMIPAD_DEBUG?: boolean}) : undefined;
    if (w && w.VIMIPAD_DEBUG) {
        // eslint-disable-next-line no-console
        console.log('[vimipad]', new Date().toISOString().slice(11, 23), ...args);
    }
}

interface Props {
    state: EditorState;
    layout: LayoutMap;
    /** Active diagram profile; decides the default and allowed node shapes. */
    profile: string;
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
}

/** Default node box height when no manual size is stored. */
const DEFAULT_NODE_HEIGHT = 40;
/** Resize bounds, kept within the canvas. */
const MIN_W = 60;
const MIN_H = 32;
const MAX_W = 360;
const MAX_H = 240;
/** Size of a corner resize handle, in canvas units. */
const HANDLE = 9;
/** Finger-friendly hit area around each corner handle (touch targets). */
const HANDLE_HIT = 26;
// Pan/zoom viewport limits: view width can shrink to a 4x zoom-in, grow to full canvas.
const MIN_VIEW_W = CANVAS_WIDTH * 0.25;
const MAX_VIEW_W = CANVAS_WIDTH;
const VIEW_ASPECT = CANVAS_HEIGHT / CANVAS_WIDTH;

/** Keep the viewport within the canvas bounds. */
function clampView(v: {x: number; y: number; w: number; h: number}): {x: number; y: number; w: number; h: number} {
    return {
        w: v.w,
        h: v.h,
        x: Math.min(Math.max(0, v.x), Math.max(0, CANVAS_WIDTH - v.w)),
        y: Math.min(Math.max(0, v.y), Math.max(0, CANVAS_HEIGHT - v.h)),
    };
}
/** Base label font size, in canvas units; each size step adds 2. */
const BASE_FONT = 13;

/**
 * Resolve CSS font properties for a node label from its text style.
 *
 * @param text The parsed text style, if any.
 * @returns Inline style properties for the label text element.
 */
/** Default width of a node box for the given label. */
const nodeWidth = (label: string): number => Math.max(70, label.length * 8 + 20);

/**
 * Shared CSS for the node label div and its inline editor, so switching into
 * edit mode does not move or recolour the text. Centred, wrapping, multi-line.
 *
 * @param text The text style, if any.
 * @returns CSS properties for an HTML box.
 */
function labelBox(text: TextStyle | undefined): React.CSSProperties {
    const family = text?.font === 'serif' ? 'Georgia, "Times New Roman", serif'
        : text?.font === 'mono' ? 'ui-monospace, Menlo, Consolas, monospace'
            : text?.font === 'sans' ? 'system-ui, -apple-system, "Segoe UI", sans-serif'
                : 'inherit';
    return {
        boxSizing: 'border-box',
        width: '100%',
        height: '100%',
        margin: 0,
        padding: '2px 6px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center',
        whiteSpace: 'pre-wrap',
        overflowWrap: 'anywhere',
        wordBreak: 'break-word',
        lineHeight: 1.2,
        fontFamily: family,
        fontSize: `${BASE_FONT + (text?.size ?? 0) * 2}px`,
        fontWeight: text?.bold ? 700 : undefined,
        fontStyle: text?.italic ? 'italic' : undefined,
        textDecoration: text?.underline ? 'underline' : undefined,
        color: text?.color ?? 'var(--vimipad-node-text, #212529)',
    };
}

/**
 * Clamp a candidate size to the accepted resize bounds.
 *
 * @param w Candidate width.
 * @param h Candidate height.
 * @returns The clamped size.
 */
function clampSize(w: number, h: number): Size {
    return {
        w: Math.max(MIN_W, Math.min(MAX_W, Math.round(w))),
        h: Math.max(MIN_H, Math.min(MAX_H, Math.round(h))),
    };
}

/**
 * Render the outline element for a shape at the origin (node group is centred).
 *
 * @param shape The node shape.
 * @param w The box width.
 * @param h The box height.
 * @param extra Extra SVG props (fill, stroke, class …).
 * @returns The shape element.
 */
function shapeElement(
    shape: NodeShape,
    w: number,
    h: number,
    extra: React.SVGProps<SVGRectElement & SVGEllipseElement>
): React.ReactElement {
    if (shape === 'ellipse') {
        return <ellipse cx={0} cy={0} rx={w / 2} ry={h / 2} {...extra} />;
    }
    return <rect x={-w / 2} y={-h / 2} width={w} height={h} rx={shape === 'roundrect' ? 10 : 0} {...extra} />;
}

/**
 * Render the SVG canvas.
 *
 * @param props Component props.
 * @returns The rendered canvas.
 */
export function CanvasView(props: Props): React.ReactElement {
    const {
        state, layout, profile, sizes, disabled, onNodeMoved, onNodeResized,
        onDeleteNode, onDeleteRelation, onRenameNode, onRenameRelation, t,
        isLockedByOther, beginEdit, endEdit, onSelectionChange, onChangeStyle, onDuplicateNode,
        onCreateRelation, onChangeDirection,
    } = props;
    const svgRef = useRef<SVGSVGElement>(null);
    // Manual double-click detection: pointer capture on nodes swallows native dblclick.
    const lastNodeClick = useRef<{id: string; t: number}>({id: '', t: 0});
    // Pan/zoom viewport (SVG viewBox) plus a ref mirror for use inside gesture handlers.
    const [view, setView] = useState({x: 0, y: 0, w: CANVAS_WIDTH, h: CANVAS_HEIGHT});
    const viewRef = useRef(view);
    useEffect(() => { viewRef.current = view; }, [view]);
    // Active pointers on the background, for one-finger pan and two-finger pinch.
    const pointers = useRef<Map<number, {x: number; y: number}>>(new Map());
    const panStart = useRef<{cx: number; cy: number; vx: number; vy: number} | null>(null);
    const pinchDist = useRef<number | null>(null);
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragPos, setDragPos] = useState<Point | null>(null);
    const [moved, setMoved] = useState(false);
    const [resizeId, setResizeId] = useState<string | null>(null);
    const [resizeSize, setResizeSize] = useState<Size | null>(null);
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
        return sizes[stableid] ?? {w: nodeWidth(label), h: DEFAULT_NODE_HEIGHT};
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
        const rect = svg.getBoundingClientRect();
        const v = viewRef.current;
        return {
            x: v.x + (clientX - rect.left) / rect.width * v.w,
            y: v.y + (clientY - rect.top) / rect.height * v.h,
        };
    }, []);

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
        if (disabled || lockedByOther(stableid)) {
            return;
        }
        // Lock on drag-start: only proceed if we secure the lease.
        if (beginEdit) {
            const granted = await beginEdit('node', stableid);
            if (!granted) {
                return;
            }
        }
        event.preventDefault();
        (event.target as Element).setPointerCapture(event.pointerId);
        setDragId(stableid);
        setDragPos(clampToCanvas(positionOf(stableid)));
        setMoved(false);
    }, [disabled, lockedByOther, beginEdit, positionOf]);

    const onHandlePointerDown = useCallback(async (
        event: React.PointerEvent, stableid: string, label: string
    ) => {
        event.stopPropagation();
        if (disabled || lockedByOther(stableid) || !onNodeResized) {
            return;
        }
        if (beginEdit) {
            const granted = await beginEdit('node', stableid);
            if (!granted) {
                return;
            }
        }
        event.preventDefault();
        (event.target as Element).setPointerCapture(event.pointerId);
        setResizeId(stableid);
        setResizeSize(sizeOf(stableid, label));
    }, [disabled, lockedByOther, onNodeResized, beginEdit, sizeOf]);

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
            if (el && el.closest && el.closest('.vimipad-canvas-edit, .vimipad-canvas-relation-edit, .vimipad-node-dock')) {
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
        <svg
            ref={svgRef}
            className="vimipad-canvas border rounded"
            viewBox={`${view.x} ${view.y} ${view.w} ${view.h}`}
            width="100%"
            role="img"
            tabIndex={0}
            style={{touchAction: 'none'}}
            aria-label={t('editor:canvasaria')}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onKeyDown={onKeyDown}
        >
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

            {/* Layer 1 (bottom): connector lines and their hit targets. */}
            {state.relations.map(rel => {
                const from = positionOf(rel.sourceid);
                const to = positionOf(rel.targetid);
                const selected = isSelected(interaction, 'relation', rel.stableid);
                const d = rel.direction ?? 0;
                return (
                    <g key={`line-${rel.stableid}`} className="vimipad-canvas-relation">
                        <line
                            x1={from.x}
                            y1={from.y}
                            x2={to.x}
                            y2={to.y}
                            stroke={selected ? selColor : 'currentColor'}
                            strokeWidth={selected ? 2.5 : 1.5}
                            markerStart={d === -1 || d === 2 ? 'url(#vimipad-arrow)' : undefined}
                            markerEnd={d === 1 || d === 2 ? 'url(#vimipad-arrow)' : undefined}
                        />
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
                    </g>
                );
            })}

            {/* Layer 2 (middle): connector labels, inline editors and the direction dock. */}
            {state.relations.map(rel => {
                const from = positionOf(rel.sourceid);
                const to = positionOf(rel.targetid);
                const midX = (from.x + to.x) / 2;
                const midY = (from.y + to.y) / 2;
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
                const {w, h} = sizeOf(node.stableid, node.label);
                const otherLock = lockedByOther(node.stableid);
                const selected = isSelected(interaction, 'node', node.stableid);
                const editing = isEditing(interaction, 'node', node.stableid);
                const style = parseNodeStyle(node.metadatajson);
                const shape = clampShape(profile, style.shape);
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
                        {editing ? (
                            <foreignObject key={`edit-${node.stableid}`} x={-w / 2} y={-h / 2} width={w} height={h}>
                                <div
                                    ref={setEditRef}
                                    className="vimipad-canvas-edit"
                                    contentEditable
                                    suppressContentEditableWarning
                                    onInput={e => {
                                        const text = e.currentTarget.textContent ?? '';
                                        editValueRef.current = text;
                                        setEditValue(text);
                                    }}
                                    onKeyDown={onEditKeyDown}
                                    onFocus={() => vdbg('node-editor focus', node.stableid)}
                                    onBlur={() => vdbg('node-editor blur', node.stableid)}
                                    onPointerDown={e => e.stopPropagation()}
                                    style={{
                                        ...labelBox(style.text),
                                        background: style.text?.background ?? 'transparent',
                                        outline: 'none',
                                        cursor: 'text',
                                    }}
                                />
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

            {/* Layer 4 (top): exactly one menu, for the active (selected/edited) element. */}
            {(() => {
                const active = interaction.editing ?? interaction.selected;
                if (!active || disabled) {
                    return null;
                }
                if (active.kind === 'node') {
                    const node = state.nodes.find(n => n.stableid === active.id);
                    if (!node || lockedByOther(node.stableid) || !onChangeStyle) {
                        return null;
                    }
                    const pos = positionOf(node.stableid);
                    const {h} = sizeOf(node.stableid, node.label);
                    const editing = isEditing(interaction, 'node', node.stableid);
                    return (
                        <foreignObject
                            x={pos.x - 150}
                            y={pos.y + h / 2 + 12}
                            width={300}
                            height={editing ? 120 : 170}
                            style={{overflow: 'visible'}}
                        >
                            <div className="vimipad-node-dock-fo" onPointerDown={e => e.stopPropagation()}>
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
                                        disabled={disabled}
                                        onChangeStyle={m => onChangeStyle(node.stableid, m)}
                                        onDuplicate={() => onDuplicateNode && onDuplicateNode(node.stableid)}
                                        onDelete={() => onDeleteNode && onDeleteNode(node.stableid)}
                                        onEditText={() => startNodeEdit(node.stableid, node.label)}
                                        t={t}
                                    />
                                )}
                            </div>
                        </foreignObject>
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
                    <foreignObject
                        x={midX - 150}
                        y={midY + 14}
                        width={300}
                        height={70}
                        style={{overflow: 'visible'}}
                    >
                        <div className="vimipad-node-dock-fo" onPointerDown={e => e.stopPropagation()}>
                            {editing ? (
                                <TextEditMenu disabled={disabled} onConfirm={commitEdit} t={t} />
                            ) : (onChangeDirection && (
                                <div className="vimipad-node-dock" role="toolbar" aria-label={t('editor:relation')}>
                                    <div className="vimipad-node-dock-row">
                                        <button
                                            type="button"
                                            className={`vimipad-dock-btn${d === 0 ? ' active' : ''}`}
                                            aria-pressed={d === 0}
                                            title={t('editor:dir_none')}
                                            aria-label={t('editor:dir_none')}
                                            onClick={() => onChangeDirection(rel.stableid, 0)}
                                        ><Icon name={FA.dirNone} /></button>
                                        <button
                                            type="button"
                                            className={`vimipad-dock-btn${d === -1 ? ' active' : ''}`}
                                            aria-pressed={d === -1}
                                            title={t('editor:dir_left')}
                                            aria-label={t('editor:dir_left')}
                                            onClick={() => onChangeDirection(rel.stableid, -1)}
                                        ><Icon name={FA.dirLeft} /></button>
                                        <button
                                            type="button"
                                            className={`vimipad-dock-btn${d === 1 ? ' active' : ''}`}
                                            aria-pressed={d === 1}
                                            title={t('editor:dir_right')}
                                            aria-label={t('editor:dir_right')}
                                            onClick={() => onChangeDirection(rel.stableid, 1)}
                                        ><Icon name={FA.dirRight} /></button>
                                        <button
                                            type="button"
                                            className={`vimipad-dock-btn${d === 2 ? ' active' : ''}`}
                                            aria-pressed={d === 2}
                                            title={t('editor:dir_both')}
                                            aria-label={t('editor:dir_both')}
                                            onClick={() => onChangeDirection(rel.stableid, 2)}
                                        ><Icon name={FA.dirBoth} /></button>
                                        {onDeleteRelation && (
                                            <button
                                                type="button"
                                                className="vimipad-dock-btn vimipad-dock-danger"
                                                title={t('editor:fmt_delete')}
                                                aria-label={t('editor:fmt_delete')}
                                                onClick={() => onDeleteRelation(rel.stableid)}
                                            ><Icon name={FA.delete} /></button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </foreignObject>
                );
            })()}
        </svg>
    );
}
