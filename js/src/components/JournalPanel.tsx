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
 * Learner-journal input area: an always-visible field for writing a new entry,
 * placed alongside the concept/relation controls. It does not render past
 * entries (those are reviewed in the revision/list views); teachers can always
 * read the journal, and a learner may only mark an entry private when the
 * activity allows it.
 *
 * @module     mod_vimipad/components/JournalPanel
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useState} from 'react';
import {ApiClient} from '../api/service';
import {Icon, FA} from '../canvas/icons';

interface Props {
    api: ApiClient;
    workspaceid: number;
    allowPrivate: boolean;
    revision: number;
    t: (key: string) => string;
}

/**
 * Render the journal input area.
 *
 * @param props Component props.
 * @returns The input area, or null before a workspace is loaded or in readonly.
 */
export function JournalPanel(props: Props): React.ReactElement | null {
    const {api, workspaceid, allowPrivate, revision, t} = props;
    const [text, setText] = useState('');
    const [priv, setPriv] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [saved, setSaved] = useState(false);

    const submit = useCallback(async () => {
        const trimmed = text.trim();
        if (trimmed === '' || busy) {
            return;
        }
        setBusy(true);
        setError(null);
        setSaved(false);
        try {
            await api.addJournalEntry(workspaceid, trimmed, priv && allowPrivate);
            setText('');
            setPriv(false);
            setSaved(true);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    }, [api, workspaceid, text, priv, allowPrivate, busy]);

    if (!workspaceid || api.isReadonly()) {
        return null;
    }

    return (
        <fieldset className="vimipad-control vimipad-journal-control">
            <legend className="h6 vimipad-journal-legend">
                <span>{t('editor:journal')}</span>
                <span className="vimipad-journal-ref text-muted font-italic">
                    {t('editor:revision')}: {revision}
                </span>
            </legend>
            <textarea
                className="form-control vimipad-journal-input"
                rows={3}
                value={text}
                placeholder={t('editor:journalnew')}
                aria-label={t('editor:journalnew')}
                onChange={(e) => {
                    setText(e.target.value);
                    setSaved(false);
                }}
            />
            <div className="vimipad-journal-controls">
                {allowPrivate && (
                    <label className="vimipad-journal-visibility">
                        <input
                            type="checkbox"
                            checked={priv}
                            onChange={(e) => setPriv(e.target.checked)}
                        />{' '}
                        {t('editor:journalprivate')}
                    </label>
                )}
                <button
                    type="button"
                    className="btn btn-primary btn-sm vimipad-journal-save"
                    onClick={() => void submit()}
                    disabled={busy || text.trim() === ''}
                >
                    <Icon name={FA.journalSave} /> {t('editor:journalsave')}
                </button>
            </div>
            {saved && (
                <div className="vimipad-journal-saved text-muted small" role="status" aria-live="polite">
                    {t('editor:journalsaved')}
                </div>
            )}
            {error && <div className="alert alert-danger" role="alert">{error}</div>}
        </fieldset>
    );
}
