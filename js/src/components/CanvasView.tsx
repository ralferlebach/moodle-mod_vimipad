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
import {
    deletableTarget,
    initialInteraction,
    interactionReduce,
    isEditing,
    isSelected,
    Target,
} from '../canvas/interaction';

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
/** Base label font size, in canvas units; each size step adds 2. */
const BASE_FONT = 13;

/**
 * Resolve CSS font properties for a node label from its text style.
 *
 * @param text The parsed text style, if any.
 * @returns Inline style properties for the label text element.
 */
function labelFont(text: TextStyle | undefined): React.CSSProperties {
    const family = text?.font === 'serif' ? 'Georgia, "Times New Roman", serif'
        : text?.font === 'mono' ? 'ui-monospace, Menlo, Consolas, monospace'
            : text?.font === 'sans' ? 'system-ui, -apple-system, "Segoe UI", sans-serif'
                : undefined;
    const props: React.CSSProperties = {fontSize: `${BASE_FONT + (text?.size ?? 0) * 2}px`};
    if (family) {
        props.fontFamily = family;
    }
    if (text?.color) {
        props.fill = text.color;
    }
    return props;
}

/** Default width of a node box for the given label. */
const nodeWidth = (label: string): number => Math.max(70, label.length * 8 + 20);

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
        onCreateRelation,
    } = props;
    const svgRef = useRef<SVGSVGElement>(null);
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragPos, setDragPos] = useState<Point | null>(null);
    const [moved, setMoved] = useState(false);
    const [resizeId, setResizeId] = useState<string | null>(null);
    const [resizeSize, setResizeSize] = useState<Size | null>(null);
    const [interaction, dispatchInteraction] = useReducer(interactionReduce, initialInteraction);
    const [editValue, setEditValue] = useState('');
    const [hoveredId, setHoveredId] = useState<string | null>(null);
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
        const scaleX = CANVAS_WIDTH / rect.width;
        const scaleY = CANVAS_HEIGHT / rect.height;
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        };
    }, []);

    const onNodePointerDown = useCallback(async (event: React.PointerEvent, stableid: string) => {
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

    const onPointerUp = useCallback(() => {
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

    // Begin inline editing of a node's label (double-click on its text).
    const startNodeEdit = useCallback((stableid: string, label: string) => {
        if (disabled || lockedByOther(stableid)) {
            return;
        }
        setEditValue(label);
        dispatchInteraction({kind: 'startEditing', target: {kind: 'node', id: stableid}});
    }, [disabled, lockedByOther]);

    // Begin inline editing of a relation's label (double-click / text button).
    const startRelationEdit = useCallback((stableid: string, label: string) => {
        if (disabled) {
            return;
        }
        setEditValue(label);
        dispatchInteraction({kind: 'startEditing', target: {kind: 'relation', id: stableid}});
    }, [disabled]);

    // Commit the current inline edit to the parent.
    const commitEdit = useCallback(() => {
        const target = interaction.editing;
        if (target) {
            if (target.kind === 'node' && onRenameNode) {
                onRenameNode(target.id, editValue);
            } else if (target.kind === 'relation' && onRenameRelation) {
                onRenameRelation(target.id, editValue);
            }
        }
        dispatchInteraction({kind: 'stopEditing'});
    }, [interaction.editing, editValue, onRenameNode, onRenameRelation]);

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
    const onEditKeyDown = useCallback((event: React.KeyboardEvent<HTMLTextAreaElement | HTMLInputElement>) => {
        event.stopPropagation();
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            commitEdit();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            dispatchInteraction({kind: 'clear'});
        }
    }, [commitEdit]);

    const selectRelation = useCallback((event: React.PointerEvent, stableid: string) => {
        event.stopPropagation();
        dispatchInteraction({kind: 'select', target: {kind: 'relation', id: stableid}});
    }, []);

    const selColor = 'var(--vimipad-selected, #2563eb)';

    return (
        <svg
            ref={svgRef}
            className="vimipad-canvas border rounded"
            viewBox={`0 0 ${CANVAS_WIDTH} ${CANVAS_HEIGHT}`}
            width="100%"
            role="img"
            tabIndex={0}
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

            {/* Background: a click on empty canvas clears the selection. */}
            <rect
                x={0}
                y={0}
                width={CANVAS_WIDTH}
                height={CANVAS_HEIGHT}
                fill="transparent"
                onPointerDown={() => dispatchInteraction({kind: 'clear'})}
            />

            {state.relations.map(rel => {
                const from = positionOf(rel.sourceid);
                const to = positionOf(rel.targetid);
                const midX = (from.x + to.x) / 2;
                const midY = (from.y + to.y) / 2;
                const selected = isSelected(interaction, 'relation', rel.stableid);
                const editing = isEditing(interaction, 'relation', rel.stableid);
                return (
                    <g key={rel.stableid} className="vimipad-canvas-relation">
                        <line
                            x1={from.x}
                            y1={from.y}
                            x2={to.x}
                            y2={to.y}
                            stroke={selected ? selColor : 'currentColor'}
                            strokeWidth={selected ? 2.5 : 1.5}
                            markerEnd={rel.direction !== 0 ? 'url(#vimipad-arrow)' : undefined}
                        />
                        {/* Wide transparent hit line so the thin connector is easy to click. */}
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
                        {editing ? (
                            <foreignObject x={midX - 80} y={midY - 18} width={160} height={34}>
                                <input
                                    className="vimipad-canvas-relation-edit"
                                    value={editValue}
                                    autoFocus
                                    onChange={e => setEditValue(e.target.value)}
                                    onKeyDown={onEditKeyDown}
                                    onBlur={commitEdit}
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
                        {selected && !disabled && !editing && onDeleteRelation && (
                            <foreignObject
                                x={midX - 150}
                                y={midY + 10}
                                width={300}
                                height={90}
                                style={{overflow: 'visible'}}
                            >
                                <div className="vimipad-node-dock-fo" onPointerDown={e => e.stopPropagation()}>
                                    <NodeFormatToolbar
                                        kind="relation"
                                        target={rel}
                                        profile={profile}
                                        disabled={disabled}
                                        onChangeStyle={() => undefined}
                                        onDelete={() => onDeleteRelation(rel.stableid)}
                                        onEditText={() => startRelationEdit(rel.stableid, rel.label)}
                                        t={t}
                                    />
                                </div>
                            </foreignObject>
                        )}
                    </g>
                );
            })}

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
                // The format dock stays open while the node is selected or being edited.
                const dockVisible = (selected || editing) && !disabled && !otherLock;
                return (
                    <g
                        key={node.stableid}
                        className={`vimipad-canvas-node${otherLock ? ' vimipad-canvas-node-locked' : ''}`
                            + `${selected ? ' vimipad-canvas-node-selected' : ''}`}
                        transform={`translate(${pos.x}, ${pos.y})`}
                        onPointerDown={e => onNodePointerDown(e, node.stableid)}
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
                            <foreignObject x={-w / 2} y={-h / 2} width={w} height={h}>
                                <textarea
                                    className="vimipad-canvas-edit"
                                    value={editValue}
                                    autoFocus
                                    onChange={e => setEditValue(e.target.value)}
                                    onKeyDown={onEditKeyDown}
                                    onBlur={commitEdit}
                                    onPointerDown={e => e.stopPropagation()}
                                    style={{width: '100%', height: '100%', resize: 'none', textAlign: 'center', border: 'none', background: 'transparent'}}
                                />
                            </foreignObject>
                        ) : (
                            <>
                                {style.text?.background && (
                                    <rect
                                        className="vimipad-canvas-texthl"
                                        x={-Math.min(w - 6, node.label.length
                                            * (BASE_FONT + (style.text.size ?? 0) * 2) * 0.62 + 10) / 2}
                                        y={-(BASE_FONT + (style.text.size ?? 0) * 2) * 0.8}
                                        width={Math.min(w - 6, node.label.length
                                            * (BASE_FONT + (style.text.size ?? 0) * 2) * 0.62 + 10)}
                                        height={(BASE_FONT + (style.text.size ?? 0) * 2) * 1.6}
                                        rx={3}
                                        fill={style.text.background}
                                    />
                                )}
                                <text
                                    textAnchor="middle"
                                    dominantBaseline="central"
                                    className="vimipad-canvas-nodelabel"
                                    style={labelFont(style.text)}
                                    onDoubleClick={() => startNodeEdit(node.stableid, node.label)}
                                >
                                    {node.label}
                                </text>
                            </>
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
                        {dockVisible && onChangeStyle && (
                            <foreignObject
                                x={-150}
                                y={h / 2 + 12}
                                width={300}
                                height={160}
                                style={{overflow: 'visible'}}
                            >
                                <div className="vimipad-node-dock-fo" onPointerDown={e => e.stopPropagation()}>
                                    <NodeFormatToolbar
                                        target={node}
                                        profile={profile}
                                        disabled={disabled}
                                        defaultPanel={editing ? 'text' : undefined}
                                        onChangeStyle={m => onChangeStyle(node.stableid, m)}
                                        onDuplicate={() => onDuplicateNode && onDuplicateNode(node.stableid)}
                                        onDelete={() => onDeleteNode && onDeleteNode(node.stableid)}
                                        t={t}
                                    />
                                </div>
                            </foreignObject>
                        )}
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
        </svg>
    );
}
