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
 * Renders nodes as boxes and relations as connectors on an SVG surface. Nodes
 * can be dragged (position committed on drop). A click selects an element and
 * reveals its affordances; ESC clears the selection; Del removes the selected
 * element; a double-click on a node's text opens inline editing (Enter commits,
 * Shift+Enter inserts a newline). The pure interaction rules live in
 * ../canvas/interaction.
 *
 * @module     mod_vimipad/components/CanvasView
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useReducer, useRef, useState} from 'react';
import {CANVAS_HEIGHT, CANVAS_WIDTH, clampToCanvas} from '../graph/autolayout';
import {EditorState} from '../store/reducer';
import {LayoutMap, Point} from '../types';
import {
    deletableTarget,
    initialInteraction,
    interactionReduce,
    isEditing,
    isSelected,
} from '../canvas/interaction';

interface Props {
    state: EditorState;
    layout: LayoutMap;
    disabled: boolean;
    onNodeMoved: (stableid: string, point: Point) => void;
    onDeleteNode?: (stableid: string) => void;
    onDeleteRelation?: (stableid: string) => void;
    onRenameNode?: (stableid: string, label: string) => void;
    onRenameRelation?: (stableid: string, label: string) => void;
    t: (key: string) => string;
    /** True if a node is held by another collaborator (renders as locked). */
    isLockedByOther?: (targettype: string, stableid: string) => boolean;
    /** Take an editing lock on drag-start; resolves to whether we may drag. */
    beginEdit?: (targettype: string, stableid: string) => Promise<boolean>;
    /** Release the editing lock on drag-end. */
    endEdit?: (targettype: string, stableid: string) => Promise<void>;
}

/** Width of a node box for the given label. */
const nodeWidth = (label: string): number => Math.max(70, label.length * 8 + 20);

/**
 * Render the SVG canvas.
 *
 * @param props Component props.
 * @returns The rendered canvas.
 */
export function CanvasView(props: Props): React.ReactElement {
    const {
        state, layout, disabled, onNodeMoved, onDeleteNode, onDeleteRelation,
        onRenameNode, onRenameRelation, t, isLockedByOther, beginEdit, endEdit,
    } = props;
    const svgRef = useRef<SVGSVGElement>(null);
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragPos, setDragPos] = useState<Point | null>(null);
    const [moved, setMoved] = useState(false);
    const [interaction, dispatchInteraction] = useReducer(interactionReduce, initialInteraction);
    const [editValue, setEditValue] = useState('');

    const lockedByOther = useCallback((stableid: string): boolean =>
        isLockedByOther ? isLockedByOther('node', stableid) : false, [isLockedByOther]);

    const positionOf = useCallback((stableid: string): Point => {
        if (dragId === stableid && dragPos) {
            return dragPos;
        }
        return layout[stableid] ?? {x: CANVAS_WIDTH / 2, y: CANVAS_HEIGHT / 2};
    }, [dragId, dragPos, layout]);

    const toSvgPoint = useCallback((clientX: number, clientY: number): Point => {
        const svg = svgRef.current;
        if (!svg) {
            return {x: 0, y: 0};
        }
        const rect = svg.getBoundingClientRect();
        const scaleX = CANVAS_WIDTH / rect.width;
        const scaleY = CANVAS_HEIGHT / rect.height;
        return clampToCanvas({
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        });
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
        setDragPos(positionOf(stableid));
        setMoved(false);
    }, [disabled, lockedByOther, beginEdit, positionOf]);

    const onPointerMove = useCallback((event: React.PointerEvent) => {
        if (dragId === null) {
            return;
        }
        setMoved(true);
        setDragPos(toSvgPoint(event.clientX, event.clientY));
    }, [dragId, toSvgPoint]);

    const onPointerUp = useCallback(() => {
        if (dragId !== null && dragPos && moved) {
            onNodeMoved(dragId, dragPos);
        }
        if (dragId !== null && endEdit) {
            void endEdit('node', dragId);
        }
        setDragId(null);
        setDragPos(null);
        setMoved(false);
    }, [dragId, dragPos, moved, onNodeMoved, endEdit]);

    // Begin inline editing of a node's label (double-click on its text).
    const startNodeEdit = useCallback((stableid: string, label: string) => {
        if (disabled || lockedByOther(stableid)) {
            return;
        }
        setEditValue(label);
        dispatchInteraction({kind: 'startEditing', target: {kind: 'node', id: stableid}});
    }, [disabled, lockedByOther]);

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
    const onEditKeyDown = useCallback((event: React.KeyboardEvent<HTMLTextAreaElement>) => {
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
                        {rel.label && (
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
                            >
                                {rel.label}
                            </text>
                        )}
                    </g>
                );
            })}

            {state.nodes.map(node => {
                const pos = positionOf(node.stableid);
                const width = nodeWidth(node.label);
                const otherLock = lockedByOther(node.stableid);
                const selected = isSelected(interaction, 'node', node.stableid);
                const editing = isEditing(interaction, 'node', node.stableid);
                return (
                    <g
                        key={node.stableid}
                        className={`vimipad-canvas-node${otherLock ? ' vimipad-canvas-node-locked' : ''}`}
                        transform={`translate(${pos.x}, ${pos.y})`}
                        onPointerDown={e => onNodePointerDown(e, node.stableid)}
                        style={{cursor: disabled || otherLock ? 'not-allowed' : 'grab'}}
                        aria-disabled={otherLock}
                    >
                        <rect
                            x={-width / 2}
                            y={-16}
                            width={width}
                            height={32}
                            rx={6}
                            fill={otherLock ? 'var(--vimipad-node-locked-fill, #f3f4f6)' : 'var(--vimipad-node-fill, #eef2ff)'}
                            stroke={selected ? selColor : 'currentColor'}
                            strokeWidth={selected ? 2.5 : 1}
                            strokeDasharray={otherLock ? '4 2' : undefined}
                        />
                        {editing ? (
                            <foreignObject x={-width / 2} y={-16} width={width} height={32}>
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
                            <text
                                textAnchor="middle"
                                dominantBaseline="central"
                                className="vimipad-canvas-nodelabel"
                                onDoubleClick={() => startNodeEdit(node.stableid, node.label)}
                            >
                                {node.label}
                            </text>
                        )}
                        {otherLock && (
                            <text
                                textAnchor="middle"
                                y={28}
                                className="vimipad-canvas-lockhint"
                                fill="var(--vimipad-lock-hint, #6b7280)"
                            >
                                {t('editor:beingedited')}
                            </text>
                        )}
                    </g>
                );
            })}
        </svg>
    );
}
