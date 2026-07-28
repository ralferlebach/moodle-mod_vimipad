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
 * Collapsible learner-journal panel: lists the user's own entries and adds new
 * ones (optionally teacher-visible). Fetches and persists via the API client.
 *
 * @module     mod_vimipad/components/JournalPanel
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useCallback, useEffect, useState} from 'react';
import {ApiClient} from '../api/service';
import {JournalEntry} from '../types';

interface Props {
    api: ApiClient;
    workspaceid: number;
    t: (key: string) => string;
}

/**
 * Render the journal panel.
 *
 * @param props Component props.
 * @returns The panel, or null before a workspace is loaded.
 */
export function JournalPanel(props: Props): React.ReactElement | null {
    const {api, workspaceid, t} = props;
    const [entries, setEntries] = useState<JournalEntry[]>([]);
    const [text, setText] = useState('');
    const [teacherVisible, setTeacherVisible] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const refresh = useCallback(async () => {
        if (!workspaceid) {
            return;
        }
        try {
            const res = await api.getJournalEntries(workspaceid);
            setEntries(res.entries);
        } catch (e) {
            setError((e as Error).message);
        }
    }, [api, workspaceid]);

    useEffect(() => {
        void refresh();
    }, [refresh]);

    const submit = useCallback(async () => {
        const trimmed = text.trim();
        if (trimmed === '' || busy) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            await api.addJournalEntry(workspaceid, trimmed, teacherVisible ? 1 : 0);
            setText('');
            setTeacherVisible(false);
            await refresh();
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    }, [api, workspaceid, text, teacherVisible, busy, refresh]);

    if (!workspaceid) {
        return null;
    }

    return (
        <details className="vimipad-journal">
            <summary>{t('editor:journal')}</summary>
            <div className="vimipad-journal-body">
                <textarea
                    className="form-control vimipad-journal-input"
                    rows={3}
                    value={text}
                    placeholder={t('editor:journalnew')}
                    aria-label={t('editor:journalnew')}
                    onChange={(e) => setText(e.target.value)}
                />
                <div className="vimipad-journal-controls">
                    <label className="vimipad-journal-visibility">
                        <input
                            type="checkbox"
                            checked={teacherVisible}
                            onChange={(e) => setTeacherVisible(e.target.checked)}
                        />{' '}
                        {t('editor:journalteachervisible')}
                    </label>
                    <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={() => void submit()}
                        disabled={busy || text.trim() === ''}
                    >
                        {t('editor:journalsave')}
                    </button>
                </div>
                {error && <div className="alert alert-danger" role="alert">{error}</div>}
                <ul className="vimipad-journal-list">
                    {entries.map((entry) => (
                        <li key={entry.id} className="vimipad-journal-entry">
                            <div className="vimipad-journal-meta">
                                {new Date(entry.timecreated * 1000).toLocaleString()}
                                {entry.visibility === 1 ? ` · ${t('editor:journalteachervisible')}` : ''}
                            </div>
                            <div className="vimipad-journal-text">{entry.entrytext}</div>
                        </li>
                    ))}
                </ul>
            </div>
        </details>
    );
}
