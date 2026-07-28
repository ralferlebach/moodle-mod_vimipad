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
 * Colour field with an explicit OK/Cancel popover.
 *
 * The native colour input commits on every change and offers no real cancel, so
 * a change is only applied when the user presses OK; Cancel discards the draft
 * and leaves the original colour untouched. Presets allow quick picks.
 *
 * @module     mod_vimipad/components/ColorField
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useState} from 'react';
import {FA, Icon} from '../canvas/icons';

interface Props {
    /** Current colour, or undefined if unset. */
    value?: string;
    /** Colour shown when none is set. */
    fallback: string;
    disabled: boolean;
    /** Font Awesome icon class for the trigger button. */
    icon: string;
    /** Accessible label / tooltip. */
    label: string;
    /** Commit a chosen colour (only fired on OK). */
    onChange: (color: string) => void;
    t: (key: string) => string;
}

/** A small, neutral preset palette. */
const SWATCHES = [
    '#212529', '#ffffff', '#dc3545', '#fd7e14', '#ffc107',
    '#198754', '#0d6efd', '#6f42c1', '#e83e8c', '#eef2ff',
];

/**
 * Render the colour field.
 *
 * @param props Component props.
 * @returns The colour field element.
 */
export function ColorField(props: Props): React.ReactElement {
    const {value, fallback, disabled, icon, label, onChange, t} = props;
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState(value ?? fallback);

    const openPopover = (): void => {
        setDraft(value ?? fallback);
        setOpen(true);
    };
    const ok = (): void => {
        onChange(draft);
        setOpen(false);
    };
    const cancel = (): void => setOpen(false);

    return (
        <span className="vimipad-colorfield">
            <button
                type="button"
                className="vimipad-dock-btn"
                title={label}
                aria-label={label}
                disabled={disabled}
                onClick={() => (open ? cancel() : openPopover())}
            >
                <Icon name={icon} />
                <span className="vimipad-colorfield-dot" style={{background: value ?? fallback}} />
            </button>
            {open && (
                <div className="vimipad-color-popover" onPointerDown={e => e.stopPropagation()}>
                    <input
                        type="color"
                        aria-label={label}
                        value={draft}
                        onChange={e => setDraft(e.target.value)}
                    />
                    <div className="vimipad-color-swatches">
                        {SWATCHES.map(c => (
                            <button
                                key={c}
                                type="button"
                                className="vimipad-swatch"
                                style={{background: c}}
                                aria-label={c}
                                onClick={() => setDraft(c)}
                            />
                        ))}
                    </div>
                    <div className="vimipad-color-actions">
                        <button
                            type="button"
                            className="vimipad-dock-btn vimipad-dock-confirm"
                            title={t('editor:confirm')}
                            aria-label={t('editor:confirm')}
                            onClick={ok}
                        ><Icon name={FA.confirm} /></button>
                        <button
                            type="button"
                            className="vimipad-dock-btn"
                            title={t('editor:cancel')}
                            aria-label={t('editor:cancel')}
                            onClick={cancel}
                        ><Icon name={FA.cancel} /></button>
                    </div>
                </div>
            )}
        </span>
    );
}
