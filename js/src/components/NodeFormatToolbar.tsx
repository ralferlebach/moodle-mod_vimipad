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
 * Node format toolbar.
 *
 * Appears while a node is selected and lets the user change its shape (from the
 * shapes the active profile permits), fill colour, label typography (family,
 * relative size, text colour, highlight) and delete it. Every change is written
 * to the node's style metadata via a node_update operation, so it is revisioned,
 * graded and propagated to collaborators like any other edit. The typography
 * here applies to the whole label; selection-range rich text is a later step.
 *
 * @module     mod_vimipad/components/NodeFormatToolbar
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {VimiNode} from '../types';
import {allowedShapes, clampShape, NodeShape} from '../canvas/shape_catalog';
import {FontFamily, NodeStyle, nextSizeStep, parseNodeStyle, serialiseNodeStyle, withNodeStyle} from '../canvas/node_style';
import {FA, Icon, ShapeGlyph} from '../canvas/icons';

interface Props {
    node: VimiNode;
    profile: string;
    disabled: boolean;
    /** Persist a new full metadatajson for the node. */
    onChangeStyle: (metadatajson: string) => void;
    onDelete: () => void;
    t: (key: string) => string;
}

/** Default colours shown in the pickers before the user sets one. */
const DEFAULT_FILL = '#eef2ff';
const DEFAULT_TEXT = '#212529';
const DEFAULT_HIGHLIGHT = '#fff3cd';

/** Title string per shape, for the picker buttons. */
const SHAPE_LABEL: Record<NodeShape, string> = {
    roundrect: 'editor:fmt_roundrect',
    rect: 'editor:fmt_rect',
    ellipse: 'editor:fmt_ellipse',
};

/**
 * Render the node format toolbar.
 *
 * @param props Component props.
 * @returns The toolbar element.
 */
export function NodeFormatToolbar(props: Props): React.ReactElement {
    const {node, profile, disabled, onChangeStyle, onDelete, t} = props;
    const style = parseNodeStyle(node.metadatajson);
    const activeShape = clampShape(profile, style.shape);

    const apply = (change: NodeStyle): void => onChangeStyle(withNodeStyle(node.metadatajson, change));

    return (
        <div className="vimipad-format-toolbar" role="toolbar" aria-label={t('editor:fmt_toolbar')}>
            <div className="vimipad-format-group" role="group" aria-label={t('editor:fmt_shape')}>
                {allowedShapes(profile).map(shape => (
                    <button
                        key={shape}
                        type="button"
                        className={`btn btn-sm btn-light vimipad-format-shape${activeShape === shape ? ' active' : ''}`}
                        aria-pressed={activeShape === shape}
                        disabled={disabled}
                        title={t(SHAPE_LABEL[shape])}
                        aria-label={t(SHAPE_LABEL[shape])}
                        onClick={() => apply({shape})}
                    >
                        <ShapeGlyph shape={shape} />
                    </button>
                ))}
            </div>

            <span className="vimipad-format-divider" aria-hidden="true" />

            <label className="vimipad-format-color" title={t('editor:fmt_fill')}>
                <Icon name={FA.fill} />
                <input
                    type="color"
                    aria-label={t('editor:fmt_fill')}
                    disabled={disabled}
                    value={style.fill ?? DEFAULT_FILL}
                    onChange={e => apply({fill: e.target.value})}
                />
            </label>

            <span className="vimipad-format-divider" aria-hidden="true" />

            <div className="vimipad-format-group" role="group" aria-label={t('editor:fmt_text')}>
                <Icon name={FA.text} className="vimipad-format-lead" />
                <select
                    className="form-control form-control-sm"
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
                    className="btn btn-sm btn-light"
                    disabled={disabled}
                    title={t('editor:fmt_smaller')}
                    aria-label={t('editor:fmt_smaller')}
                    onClick={() => apply({text: {size: nextSizeStep(style.text?.size, -1)}})}
                >
                    A<Icon name={FA.fontSizeDown} />
                </button>
                <button
                    type="button"
                    className="btn btn-sm btn-light"
                    disabled={disabled}
                    title={t('editor:fmt_bigger')}
                    aria-label={t('editor:fmt_bigger')}
                    onClick={() => apply({text: {size: nextSizeStep(style.text?.size, 1)}})}
                >
                    A<Icon name={FA.fontSizeUp} />
                </button>
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
            </div>

            <span className="vimipad-format-divider" aria-hidden="true" />

            <button
                type="button"
                className="btn btn-sm btn-light"
                disabled={disabled}
                title={t('editor:fmt_reset')}
                aria-label={t('editor:fmt_reset')}
                onClick={() => onChangeStyle(serialiseNodeStyle({}))}
            >
                <Icon name={FA.reset} />
            </button>
            <button
                type="button"
                className="btn btn-sm btn-outline-danger"
                disabled={disabled}
                title={t('editor:fmt_delete')}
                aria-label={t('editor:fmt_delete')}
                onClick={onDelete}
            >
                <Icon name={FA.delete} />
            </button>
        </div>
    );
}
