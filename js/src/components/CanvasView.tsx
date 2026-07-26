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
 * Renders nodes as boxes and relations as directed connectors on an SVG
 * surface. Nodes can be dragged with the pointer; the new position is committed
 * on drop (never on every move) and persisted via the non-revisioned layout
 * endpoint by the parent.
 *
 * @module     mod_vimipad/components/CanvasView
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useRef, useState} from 'react';
import {CANVAS_HEIGHT, CANVAS_WIDTH, clampToCanvas} from '../graph/autolayout';
import {EditorState} from '../store/reducer';
import {LayoutMap, Point} from '../types';

interface Props {
    state: EditorState;
    layout: LayoutMap;
    disabled: boolean;
    onNodeMoved: (stableid: string, point: Point) => void;
    t: (key: string) => string;
    /** True if a node is held by another collaborator (renders as locked). */
    isLockedByOther?: (targettype: string, stableid: string) => boolean;
    /** Take an editing lock on drag-start; resolves to whether we may drag. */
    beginEdit?: (targettype: string, stableid: string) => Promise<boolean>;
    /** Release the editing lock on drag-end. */
    endEdit?: (targettype: string, stableid: string) => Promise<void>;
}

/**
 * Render the SVG canvas.
 *
 * @param props Component props.
 * @returns The rendered canvas.
 */
export function CanvasView(props: Props): React.ReactElement {
    const {state, layout, disabled, onNodeMoved, t, isLockedByOther, beginEdit, endEdit} = props;
    const svgRef = useRef<SVGSVGElement>(null);
    const [dragId, setDragId] = useState<string | null>(null);
    const [dragPos, setDragPos] = useState<Point | null>(null);

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

    const onPointerDown = useCallback(async (event: React.PointerEvent, stableid: string) => {
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
    }, [disabled, lockedByOther, beginEdit, positionOf]);

    const onPointerMove = useCallback((event: React.PointerEvent) => {
        if (dragId === null) {
            return;
        }
        setDragPos(toSvgPoint(event.clientX, event.clientY));
    }, [dragId, toSvgPoint]);

    const onPointerUp = useCallback(() => {
        if (dragId !== null && dragPos) {
            onNodeMoved(dragId, dragPos);
            if (endEdit) {
                void endEdit('node', dragId);
            }
        }
        setDragId(null);
        setDragPos(null);
    }, [dragId, dragPos, onNodeMoved, endEdit]);

    return (
        <svg
            ref={svgRef}
            className="vimipad-canvas border rounded"
            viewBox={`0 0 ${CANVAS_WIDTH} ${CANVAS_HEIGHT}`}
            width="100%"
            role="img"
            aria-label={t('editor:canvasaria')}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
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

            {state.relations.map(rel => {
                const from = positionOf(rel.sourceid);
                const to = positionOf(rel.targetid);
                const midX = (from.x + to.x) / 2;
                const midY = (from.y + to.y) / 2;
                return (
                    <g key={rel.stableid} className="vimipad-canvas-relation">
                        <line
                            x1={from.x}
                            y1={from.y}
                            x2={to.x}
                            y2={to.y}
                            stroke="currentColor"
                            strokeWidth={1.5}
                            markerEnd={rel.direction !== 0 ? 'url(#vimipad-arrow)' : undefined}
                        />
                        {rel.label && (
                            <text x={midX} y={midY - 4} textAnchor="middle" className="vimipad-canvas-label">
                                {rel.label}
                            </text>
                        )}
                    </g>
                );
            })}

            {state.nodes.map(node => {
                const pos = positionOf(node.stableid);
                const width = Math.max(70, node.label.length * 8 + 20);
                const otherLock = lockedByOther(node.stableid);
                return (
                    <g
                        key={node.stableid}
                        className={`vimipad-canvas-node${otherLock ? ' vimipad-canvas-node-locked' : ''}`}
                        transform={`translate(${pos.x}, ${pos.y})`}
                        onPointerDown={e => onPointerDown(e, node.stableid)}
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
                            stroke="currentColor"
                            strokeWidth={1}
                            strokeDasharray={otherLock ? '4 2' : undefined}
                        />
                        <text textAnchor="middle" dominantBaseline="central" className="vimipad-canvas-nodelabel">
                            {node.label}
                        </text>
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
