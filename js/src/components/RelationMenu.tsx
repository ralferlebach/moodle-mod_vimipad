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
 * The relation control dock: text (opens the inline label editor), direction
 * (none/left/right/both), delete and — when the user may lock — a lock button
 * that opens the per-group lock submenu. A relation has no fill, so only the
 * move and text lock groups apply.
 *
 * Controls whose group is locked are hidden (the styling equivalent is never
 * offered and then discarded on save); the lock submenu is always reachable via
 * the lock button so a lock can be lifted again. This mirrors the node/container
 * dock and is independent of the top-right lock-mode enforcement toggle.
 *
 * @module     mod_vimipad/components/RelationMenu
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useState} from 'react';
import {FA, Icon} from '../canvas/icons';
import {isGroupLocked, readGroupLocks} from '../canvas/element_lock';
import {relationTypeStyle} from '../relation_types';
import {LockSubmenu} from './LockSubmenu';

interface Props {
    /** The relation's stable id. */
    stableid: string;
    /** The relation's current direction (0 none, -1 left, 1 right, 2 both). */
    direction: number;
    /** The relation's metadata JSON (lock state). */
    metadatajson?: string;
    disabled: boolean;
    /** Whether the current user may change template locks. */
    canLock: boolean;
    /** The relation's current type (e.g. 'support' | 'attack' | 'link'). */
    type?: string;
    /** Relation types this display type offers; a typed picker shows when >1 non-link. */
    relationTypes?: string[];
    onEditText: () => void;
    onChangeDirection?: (stableid: string, direction: number) => void;
    onChangeType?: (stableid: string, type: string) => void;
    onDelete?: () => void;
    onToggleLockGroup?: (group: 'move' | 'text') => void;
    t: (key: string) => string;
}



/** The direction options, in dock order. */
const DIRECTIONS: {value: number; icon: string; label: string}[] = [
    {value: 0, icon: FA.dirNone, label: 'editor:dir_none'},
    {value: -1, icon: FA.dirLeft, label: 'editor:dir_left'},
    {value: 1, icon: FA.dirRight, label: 'editor:dir_right'},
    {value: 2, icon: FA.dirBoth, label: 'editor:dir_both'},
];

/**
 * Render the relation control dock.
 *
 * @param props Component props.
 * @returns The dock element.
 */
export function RelationMenu(props: Props): React.ReactElement {
    const {stableid, direction, metadatajson, disabled, canLock,
        type, relationTypes, onEditText, onChangeDirection, onChangeType,
        onDelete, onToggleLockGroup, t} = props;
    const [lockPanel, setLockPanel] = useState(false);
    const textLocked = isGroupLocked(metadatajson, 'text');
    const moveLocked = isGroupLocked(metadatajson, 'move');
    const locks = readGroupLocks(metadatajson);
    const anyLocked = locks.move || locks.text;
    // Typed relations (e.g. support/attack) are offered only when the profile
    // declares more than the single default 'link' type.
    const typeChoices = (relationTypes ?? []).filter(rt => rt !== 'link');
    const showTypes = onChangeType && typeChoices.length > 0;

    return (
        <div className="vimipad-node-dock" role="toolbar" aria-label={t('editor:relation')}>
            <div className="vimipad-node-dock-row">
                {!textLocked && (
                    <button
                        type="button"
                        className="vimipad-dock-btn"
                        disabled={disabled}
                        title={t('editor:fmt_text')}
                        aria-label={t('editor:fmt_text')}
                        onClick={onEditText}
                    ><Icon name={FA.text} /></button>
                )}
                {showTypes && typeChoices.map(rt => (
                    <button
                        key={`reltype-${rt}`}
                        type="button"
                        className={`vimipad-dock-btn${type === rt ? ' active' : ''}`}
                        aria-pressed={type === rt}
                        disabled={disabled}
                        title={t(`editor:reltype_${rt}`)}
                        aria-label={t(`editor:reltype_${rt}`)}
                        onClick={() => onChangeType(stableid, rt)}
                    >
                        <span
                            aria-hidden="true"
                            style={{
                                display: 'inline-block', width: '0.8em', height: '0.8em',
                                borderRadius: '50%',
                                background: relationTypeStyle(rt)?.color ?? 'currentColor',
                            }}
                        />
                    </button>
                ))}
                {!moveLocked && onChangeDirection && DIRECTIONS.map(d => (
                    <button
                        key={d.value}
                        type="button"
                        className={`vimipad-dock-btn${direction === d.value ? ' active' : ''}`}
                        aria-pressed={direction === d.value}
                        disabled={disabled}
                        title={t(d.label)}
                        aria-label={t(d.label)}
                        onClick={() => onChangeDirection(stableid, d.value)}
                    ><Icon name={d.icon} /></button>
                ))}
                {onDelete && (
                    <button
                        type="button"
                        className="vimipad-dock-btn vimipad-dock-danger"
                        disabled={disabled}
                        title={t('editor:fmt_delete')}
                        aria-label={t('editor:fmt_delete')}
                        onClick={onDelete}
                    ><Icon name={FA.delete} /></button>
                )}
                {canLock && onToggleLockGroup && (
                    <button
                        type="button"
                        className={`vimipad-dock-btn${lockPanel ? ' active' : ''}${anyLocked ? ' vimipad-dock-locked' : ''}`}
                        aria-pressed={lockPanel}
                        disabled={disabled}
                        title={t('editor:templatelocks')}
                        aria-label={t('editor:templatelocks')}
                        onClick={() => setLockPanel(v => !v)}
                    ><Icon name={anyLocked ? FA.lock : FA.unlock} /></button>
                )}
            </div>
            {canLock && onToggleLockGroup && lockPanel && (
                <LockSubmenu
                    groups={['move', 'text']}
                    metadatajson={metadatajson}
                    onToggle={group => onToggleLockGroup(group as 'move' | 'text')}
                    disabled={disabled}
                    t={t}
                />
            )}
        </div>
    );
}
