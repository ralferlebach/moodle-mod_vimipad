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
 * The lock submenu: a row of per-group lock toggles that opens from the lock
 * button in an element's normal menu (nodes, relations and containers).
 *
 * Each toggle shows the *same* icon as the function it locks, struck through
 * like a no-parking sign, so it reads as "forbid this function". A group that
 * is currently locked shows its button in the active state; clicking a button
 * toggles that group's lock. This is deliberately separate from the top-right
 * lock-mode button, which only arms enforcement — locking an element is a menu
 * action, available whether or not enforcement is currently armed.
 *
 * @module     mod_vimipad/components/LockSubmenu
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {FA, StruckIcon} from '../canvas/icons';
import {LockGroup, readGroupLocks} from '../canvas/element_lock';

/** The function glyph each lock group forbids. */
const GROUP_ICON: Record<LockGroup, string> = {
    move: FA.move,
    color: FA.fill,
    text: FA.text,
};

/** The label string key for each lock group. */
const GROUP_LABEL: Record<LockGroup, string> = {
    move: 'editor:lockgroup_move',
    color: 'editor:lockgroup_color',
    text: 'editor:lockgroup_text',
};

interface Props {
    /** The groups offered for this element kind (relations omit colour). */
    groups: LockGroup[];
    /** The element's metadata JSON (its current lock state is read from it). */
    metadatajson?: string;
    /** Toggle a single group's lock. */
    onToggle: (group: LockGroup) => void;
    disabled?: boolean;
    t: (key: string) => string;
}

/**
 * Render the per-group lock toggle row.
 *
 * @param props Component props.
 * @returns The lock submenu element.
 */
export function LockSubmenu(props: Props): React.ReactElement {
    const {groups, metadatajson, onToggle, disabled, t} = props;
    const locks = readGroupLocks(metadatajson);
    return (
        <div className="vimipad-node-dock-panel vimipad-lock-submenu" role="group" aria-label={t('editor:templatelocks')}>
            {groups.map(group => {
                const locked = locks[group];
                const label = t(GROUP_LABEL[group]) + ' — '
                    + t(locked ? 'editor:unlockelement' : 'editor:lockelement');
                return (
                    <button
                        key={group}
                        type="button"
                        className={`vimipad-dock-btn${locked ? ' active' : ''}`}
                        aria-pressed={locked}
                        disabled={disabled}
                        title={label}
                        aria-label={label}
                        onClick={() => onToggle(group)}
                    >
                        <StruckIcon name={GROUP_ICON[group]} />
                    </button>
                );
            })}
        </div>
    );
}
