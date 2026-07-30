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
 * Template lock panel for authors: lists nodes and relations with a per-element
 * lock toggle and an "allow renaming" sub-toggle. Setting a lock writes the
 * lock metadata (server-enforced since 0.6.4) via the host's update handler.
 *
 * Shown only to users who may manage the template (canmanage). A locked element
 * cannot be restructured or deleted by learners; enabling "allow renaming" keeps
 * the label editable (editable: ['label']).
 *
 * @module     mod_vimipad/components/LockPanel
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {VimiContainer, VimiNode, VimiRelation} from '../types';
import {readLock, writeLock} from '../canvas/element_lock';

/** The kind of element a lock is applied to. */
export type LockKind = 'node' | 'relation' | 'container';

interface Props {
    nodes: VimiNode[];
    relations: VimiRelation[];
    containers: VimiContainer[];
    disabled: boolean;
    t: (key: string) => string;
    /** Persist new metadata JSON for an element. */
    onSetLock: (kind: LockKind, stableid: string, metadatajson: string) => void;
}

interface RowProps {
    kind: LockKind;
    stableid: string;
    label: string;
    metadatajson?: string;
    disabled: boolean;
    t: (key: string) => string;
    onSetLock: (kind: LockKind, stableid: string, metadatajson: string) => void;
}

/**
 * A single lockable element row.
 *
 * @param props The row props.
 * @returns The row element.
 */
function LockRow(props: RowProps): React.ReactElement {
    const {kind, stableid, label, metadatajson, disabled, t, onSetLock} = props;
    const state = readLock(metadatajson);
    const allowLabel = state.editable.includes('label');

    const setLocked = (locked: boolean): void => {
        onSetLock(kind, stableid, writeLock(metadatajson, {
            locked,
            editable: locked && allowLabel ? ['label'] : [],
        }));
    };
    const setAllowLabel = (allow: boolean): void => {
        onSetLock(kind, stableid, writeLock(metadatajson, {
            locked: true,
            editable: allow ? ['label'] : [],
        }));
    };

    return (
        <li className="vimipad-lock-row">
            <label className="vimipad-lock-toggle">
                <input
                    type="checkbox"
                    checked={state.locked}
                    disabled={disabled}
                    onChange={e => setLocked(e.target.checked)}
                />
                {' '}
                <span className="vimipad-lock-label">{label || t(`editor:${kind}`)}</span>
            </label>
            {state.locked && (
                <label className="vimipad-lock-suboption ml-3">
                    <input
                        type="checkbox"
                        checked={allowLabel}
                        disabled={disabled}
                        onChange={e => setAllowLabel(e.target.checked)}
                    />
                    {' '}
                    {t('editor:lockallowlabel')}
                </label>
            )}
        </li>
    );
}

/**
 * @param props The panel props.
 * @returns The lock panel, or null when there is nothing to lock.
 */
export function LockPanel(props: Props): React.ReactElement | null {
    const {nodes, relations, containers, disabled, t, onSetLock} = props;
    if (nodes.length === 0 && relations.length === 0 && containers.length === 0) {
        return null;
    }
    return (
        <fieldset disabled={disabled} className="vimipad-control vimipad-lock-panel">
            <legend className="h6">{t('editor:templatelocks')}</legend>
            <p className="small text-muted mb-1">{t('editor:templatelockshint')}</p>
            <ul className="list-unstyled mb-0">
                {nodes.map(node => (
                    <LockRow
                        key={`lock-node-${node.stableid}`}
                        kind="node"
                        stableid={node.stableid}
                        label={node.label}
                        metadatajson={node.metadatajson}
                        disabled={disabled}
                        t={t}
                        onSetLock={onSetLock}
                    />
                ))}
                {relations.map(relation => (
                    <LockRow
                        key={`lock-relation-${relation.stableid}`}
                        kind="relation"
                        stableid={relation.stableid}
                        label={relation.label}
                        metadatajson={relation.metadatajson}
                        disabled={disabled}
                        t={t}
                        onSetLock={onSetLock}
                    />
                ))}
                {containers.map(container => (
                    <LockRow
                        key={`lock-container-${container.stableid}`}
                        kind="container"
                        stableid={container.stableid}
                        label={container.label || t('editor:containers')}
                        metadatajson={container.metadatajson}
                        disabled={disabled}
                        t={t}
                        onSetLock={onSetLock}
                    />
                ))}
            </ul>
        </fieldset>
    );
}
