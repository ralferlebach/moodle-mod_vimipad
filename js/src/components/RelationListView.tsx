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
 * The relation list view: an equal-rights, keyboard- and mobile-friendly
 * editing surface showing each relation as a subject-relation-object row.
 *
 * Retargeting works two ways, satisfying the accessibility requirement that
 * every drag-and-drop action has a keyboard alternative:
 *  - Keyboard/pointer: dropdowns in an expandable row editor change source or
 *    target, confirmed with a button.
 *  - Pointer enhancement: a node chip can be dropped onto a subject/object cell
 *    (native HTML5 drag-and-drop) to retarget without opening the editor.
 *
 * @module     mod_vimipad/components/RelationListView
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {useState} from 'react';
import {EditorState, labelFor} from '../store/reducer';

interface Props {
    state: EditorState;
    disabled: boolean;
    onDeleteRelation: (stableid: string) => void;
    onRetarget: (stableid: string, change: {sourceid?: string; targetid?: string}) => void;
    t: (key: string) => string;
}

const DND_MIME = 'application/x-vimipad-node';

/**
 * Render the relation table with per-row retarget editing.
 *
 * @param props Component props.
 * @returns The rendered list view.
 */
export function RelationListView(props: Props): React.ReactElement {
    const {state, disabled, onDeleteRelation, onRetarget, t} = props;
    const [editing, setEditing] = useState<string | null>(null);

    if (state.relations.length === 0) {
        return <p className="text-muted">{t('editor:norelations')}</p>;
    }

    const nodeOptions = state.nodes.map(n =>
        <option key={n.stableid} value={n.stableid}>{n.label}</option>);

    const handleDrop = (event: React.DragEvent, stableid: string, end: 'source' | 'target') => {
        event.preventDefault();
        const dropped = event.dataTransfer.getData(DND_MIME);
        if (dropped) {
            onRetarget(stableid, end === 'source' ? {sourceid: dropped} : {targetid: dropped});
        }
    };

    const allowDrop = (event: React.DragEvent) => {
        if (!disabled && event.dataTransfer.types.includes(DND_MIME)) {
            event.preventDefault();
        }
    };

    return (
        <>
            <ul className="vimipad-node-chips list-inline mb-2" aria-label={t('editor:dragnodes')}>
                {state.nodes.map(n => (
                    <li
                        key={n.stableid}
                        className="list-inline-item badge badge-secondary"
                        draggable={!disabled}
                        onDragStart={e => e.dataTransfer.setData(DND_MIME, n.stableid)}
                    >
                        {n.label}
                    </li>
                ))}
            </ul>

            <table className="table table-sm vimipad-relation-list">
                <thead>
                    <tr>
                        <th scope="col">{t('editor:subject')}</th>
                        <th scope="col">{t('editor:relation')}</th>
                        <th scope="col">{t('editor:object')}</th>
                        <th scope="col"><span className="sr-only">{t('editor:actions')}</span></th>
                    </tr>
                </thead>
                <tbody>
                    {state.relations.map(rel => (
                        <React.Fragment key={rel.stableid}>
                            <tr>
                                <td
                                    onDragOver={allowDrop}
                                    onDrop={e => handleDrop(e, rel.stableid, 'source')}
                                >
                                    {labelFor(state, rel.sourceid)}
                                </td>
                                <td><em>{rel.label || rel.type}</em></td>
                                <td
                                    onDragOver={allowDrop}
                                    onDrop={e => handleDrop(e, rel.stableid, 'target')}
                                >
                                    {labelFor(state, rel.targetid)}
                                </td>
                                <td className="text-right">
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-outline-secondary mr-1"
                                        disabled={disabled}
                                        aria-expanded={editing === rel.stableid}
                                        onClick={() => setEditing(editing === rel.stableid ? null : rel.stableid)}
                                    >
                                        {t('editor:retarget')}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-outline-danger"
                                        disabled={disabled}
                                        onClick={() => onDeleteRelation(rel.stableid)}
                                        aria-label={t('editor:deleterelation')}
                                    >
                                        &times;
                                    </button>
                                </td>
                            </tr>
                            {editing === rel.stableid && (
                                <tr className="vimipad-retarget-editor">
                                    <td colSpan={4}>
                                        <div className="form-inline">
                                            <label className="mr-1" htmlFor={`src-${rel.stableid}`}>
                                                {t('editor:subject')}
                                            </label>
                                            <select
                                                id={`src-${rel.stableid}`}
                                                className="form-control form-control-sm mr-3"
                                                value={rel.sourceid}
                                                disabled={disabled}
                                                onChange={e => onRetarget(rel.stableid, {sourceid: e.target.value})}
                                            >
                                                {nodeOptions}
                                            </select>
                                            <label className="mr-1" htmlFor={`tgt-${rel.stableid}`}>
                                                {t('editor:object')}
                                            </label>
                                            <select
                                                id={`tgt-${rel.stableid}`}
                                                className="form-control form-control-sm"
                                                value={rel.targetid}
                                                disabled={disabled}
                                                onChange={e => onRetarget(rel.stableid, {targetid: e.target.value})}
                                            >
                                                {nodeOptions}
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </React.Fragment>
                    ))}
                </tbody>
            </table>
        </>
    );
}
