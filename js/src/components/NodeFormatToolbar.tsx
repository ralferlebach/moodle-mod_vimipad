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
 * Element control dock.
 *
 * A cluster of round buttons that floats just below a hovered/selected element,
 * replacing hover menus uniformly for mouse and touch. For nodes the primary row
 * exposes shape, fill, text, duplicate and delete; shape, fill and text each open
 * a compact panel so a plain selection never dumps a full menu. For relations the
 * row is reduced to text (opens the inline label editor) and delete, since a line
 * has no shape or fill. Every node style edit is a node_update operation, so it is
 * revisioned, graded and propagated. Typography applies to the whole label for now.
 *
 * @module     mod_vimipad/components/NodeFormatToolbar
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useState} from 'react';
import {NodeShape} from '../canvas/shape_catalog';
import {formShapes, formClampShape} from '../canvas/form_config';
import {FormConfig} from '../types';
import {NodeStyle, parseNodeStyle, serialiseNodeStyle, withNodeStyle} from '../canvas/node_style';
import {FA, Icon, ShapeGlyph} from '../canvas/icons';
import {ColorField} from './ColorField';
import {LockGroup, readGroupLocks} from '../canvas/element_lock';
import {LockSubmenu} from './LockSubmenu';

interface Props {
    /** Element kind: nodes get the full toolset, relations a reduced one. */
    kind?: 'node' | 'relation';
    /** The styled element (only its metadatajson is read). */
    target: {metadatajson?: string};
    profile: string;
    /** Backend form config; preferred over the built-in shape table. */
    formconfig?: FormConfig;
    disabled: boolean;
    /** Panel to open initially (e.g. 'text' while inline editing). */
    defaultPanel?: Panel;
    /** Persist a new full metadatajson for the element (nodes only for now). */
    onChangeStyle: (metadatajson: string) => void;
    onDuplicate?: () => void;
    onDelete: () => void;
    /** Start inline text editing of the element's label. */
    onEditText?: () => void;
    t: (key: string) => string;
    /**
     * The lock groups offered for this element. When provided together with
     * onToggleLockGroup, the dock shows a lock button that opens the lock
     * submenu. Nodes/containers pass move/color/text; relations omit colour.
     */
    lockGroups?: LockGroup[];
    /** Toggle a single lock group (move/color/text) on this element. */
    onToggleLockGroup?: (group: LockGroup) => void;
}

/** Which expandable panel is open, if any. */
type Panel = 'none' | 'shape' | 'fill' | 'text' | 'lock';

/** Default colours shown in the pickers before the user sets one. */
const DEFAULT_FILL = '#eef2ff';

/** Title string per shape, for the picker buttons. */
const SHAPE_LABEL: Record<NodeShape, string> = {
    roundrect: 'editor:fmt_roundrect',
    rect: 'editor:fmt_rect',
    ellipse: 'editor:fmt_ellipse',
};

/**
 * Render the element control dock.
 *
 * @param props Component props.
 * @returns The dock element.
 */
export function NodeFormatToolbar(props: Props): React.ReactElement {
    const {kind = 'node', target, profile, formconfig, disabled, defaultPanel, onChangeStyle, onDuplicate, onDelete,
        onEditText, lockGroups, onToggleLockGroup, t} = props;
    const isNode = kind !== 'relation';
    const [panel, setPanel] = useState<Panel>(defaultPanel ?? 'none');
    const style = parseNodeStyle(target.metadatajson);
    const activeShape = formClampShape(formconfig, profile, style.shape);
    const groupLocks = readGroupLocks(target.metadatajson);
    const canLock = Boolean(onToggleLockGroup && lockGroups && lockGroups.length > 0);
    const anyLocked = groupLocks.move || groupLocks.color || groupLocks.text;

    const apply = (change: NodeStyle): void => onChangeStyle(withNodeStyle(target.metadatajson, change));
    const toggle = (p: Panel): void => setPanel(cur => (cur === p ? 'none' : p));

    // Normal mode (learner/author editing): a control is only offered when its
    // group is not locked, so a locked action is never presented and then
    // silently discarded on save. The lock button (when the user may lock) opens
    // the lock submenu; it is always available, independent of the top-right
    // lock-mode enforcement toggle.
    const moveLocked = groupLocks.move;
    const colorLocked = groupLocks.color;
    const textLocked = groupLocks.text;

    return (
        <div className="vimipad-node-dock" role="toolbar" aria-label={t('editor:fmt_toolbar')}>
            <div className="vimipad-node-dock-row">
                {isNode && !moveLocked && (
                    <button
                        type="button"
                        className={`vimipad-dock-btn${panel === 'shape' ? ' active' : ''}`}
                        aria-pressed={panel === 'shape'}
                        disabled={disabled}
                        title={t('editor:fmt_shape')}
                        aria-label={t('editor:fmt_shape')}
                        onClick={() => toggle('shape')}
                    ><Icon name={FA.shape} /></button>
                )}
                {isNode && !colorLocked && (
                    <ColorField
                        value={style.fill}
                        fallback={DEFAULT_FILL}
                        disabled={disabled}
                        icon={FA.fill}
                        label={t('editor:fmt_fill')}
                        onChange={c => apply({fill: c})}
                        onReset={() => onChangeStyle(serialiseNodeStyle({}))}
                        t={t}
                    />
                )}
                {!textLocked && (
                <button
                    type="button"
                    className="vimipad-dock-btn"
                    disabled={disabled}
                    title={t('editor:fmt_text')}
                    aria-label={t('editor:fmt_text')}
                    onClick={() => onEditText && onEditText()}
                ><Icon name={FA.text} /></button>
                )}
                {isNode && onDuplicate && (
                    <button
                        type="button"
                        className="vimipad-dock-btn"
                        disabled={disabled}
                        title={t('editor:fmt_duplicate')}
                        aria-label={t('editor:fmt_duplicate')}
                        onClick={onDuplicate}
                    ><Icon name={FA.duplicate} /></button>
                )}
                <button
                    type="button"
                    className="vimipad-dock-btn vimipad-dock-danger"
                    disabled={disabled}
                    title={t('editor:fmt_delete')}
                    aria-label={t('editor:fmt_delete')}
                    onClick={onDelete}
                ><Icon name={FA.delete} /></button>
                {canLock && (
                    <button
                        type="button"
                        className={`vimipad-dock-btn${panel === 'lock' ? ' active' : ''}${anyLocked ? ' vimipad-dock-locked' : ''}`}
                        aria-pressed={panel === 'lock'}
                        disabled={disabled}
                        title={t('editor:templatelocks')}
                        aria-label={t('editor:templatelocks')}
                        onClick={() => toggle('lock')}
                    ><Icon name={anyLocked ? FA.lock : FA.unlock} /></button>
                )}
            </div>

            {canLock && panel === 'lock' && (
                <LockSubmenu
                    groups={lockGroups ?? []}
                    metadatajson={target.metadatajson}
                    onToggle={group => onToggleLockGroup?.(group)}
                    disabled={disabled}
                    t={t}
                />
            )}

            {isNode && panel === 'shape' && (
                <div className="vimipad-node-dock-panel" role="group" aria-label={t('editor:fmt_shape')}>
                    {formShapes(formconfig, profile).map(shape => (
                        <button
                            key={shape}
                            type="button"
                            className={`vimipad-dock-btn${activeShape === shape ? ' active' : ''}`}
                            aria-pressed={activeShape === shape}
                            disabled={disabled}
                            title={t(SHAPE_LABEL[shape])}
                            aria-label={t(SHAPE_LABEL[shape])}
                            onClick={() => apply({shape})}
                        ><ShapeGlyph shape={shape} /></button>
                    ))}
                </div>
            )}


        </div>
    );
}
