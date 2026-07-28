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
 * Inline text-edit menu.
 *
 * A single row shown below a node while its label is being edited: a green
 * confirm button plus typography controls (font, size, colour, highlight).
 * Kept to one row so it always appears in the same, predictable place. For
 * elements without persisted style (relations) only the confirm button shows.
 *
 * @module     mod_vimipad/components/TextEditMenu
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {FontFamily, NodeStyle, nextSizeStep, parseNodeStyle, withNodeStyle} from '../canvas/node_style';
import {FA, Icon} from '../canvas/icons';

interface Props {
    /** Current metadatajson of the edited element, or undefined for style-less ones. */
    metadatajson?: string;
    disabled: boolean;
    /** Persist a new metadatajson (nodes). Omit for elements without style. */
    onChangeStyle?: (metadatajson: string) => void;
    /** Commit the current label edit. */
    onConfirm: () => void;
    t: (key: string) => string;
}

const DEFAULT_TEXT = '#212529';
const DEFAULT_HIGHLIGHT = '#fff3cd';

/**
 * Render the text-edit menu.
 *
 * @param props Component props.
 * @returns The menu element.
 */
export function TextEditMenu(props: Props): React.ReactElement {
    const {metadatajson, disabled, onChangeStyle, onConfirm, t} = props;
    const style = parseNodeStyle(metadatajson);
    const apply = (change: NodeStyle): void => {
        if (onChangeStyle) {
            onChangeStyle(withNodeStyle(metadatajson, change));
        }
    };

    return (
        <div className="vimipad-node-dock" role="toolbar" aria-label={t('editor:fmt_text')}>
            <div className="vimipad-node-dock-row">
                <button
                    type="button"
                    className="vimipad-dock-btn vimipad-dock-confirm"
                    title={t('editor:confirm')}
                    aria-label={t('editor:confirm')}
                    onClick={onConfirm}
                ><Icon name={FA.confirm} /></button>
                {onChangeStyle && (
                    <>
                        <select
                            className="vimipad-dock-select form-control form-control-sm"
                            aria-label={t('editor:fmt_font')}
                            title={t('editor:fmt_font')}
                            disabled={disabled}
                            value={style.text?.font ?? ''}
                            onChange={e => apply({text: {font: (e.target.value || undefined) as FontFamily | undefined}})}
                        >
                            <option value="">{t('editor:fmt_fontdefault')}</option>
                            <option value="sans">Sans</option>
                            <option value="serif">Serif</option>
                            <option value="mono">Mono</option>
                        </select>
                        <button
                            type="button"
                            className="vimipad-dock-btn"
                            disabled={disabled}
                            title={t('editor:fmt_smaller')}
                            aria-label={t('editor:fmt_smaller')}
                            onClick={() => apply({text: {size: nextSizeStep(style.text?.size, -1)}})}
                        >A<Icon name={FA.fontSizeDown} /></button>
                        <button
                            type="button"
                            className="vimipad-dock-btn"
                            disabled={disabled}
                            title={t('editor:fmt_bigger')}
                            aria-label={t('editor:fmt_bigger')}
                            onClick={() => apply({text: {size: nextSizeStep(style.text?.size, 1)}})}
                        >A<Icon name={FA.fontSizeUp} /></button>
                        <label className="vimipad-format-color" title={t('editor:fmt_textcolor')}>
                            <Icon name={FA.textColor} />
                            <input
                                type="color"
                                aria-label={t('editor:fmt_textcolor')}
                                disabled={disabled}
                                value={style.text?.color ?? DEFAULT_TEXT}
                                onChange={e => apply({text: {color: e.target.value}})}
                            />
                        </label>
                        <label className="vimipad-format-color" title={t('editor:fmt_highlight')}>
                            <Icon name={FA.highlight} />
                            <input
                                type="color"
                                aria-label={t('editor:fmt_highlight')}
                                disabled={disabled}
                                value={style.text?.background ?? DEFAULT_HIGHLIGHT}
                                onChange={e => apply({text: {background: e.target.value}})}
                            />
                        </label>
                    </>
                )}
            </div>
        </div>
    );
}
