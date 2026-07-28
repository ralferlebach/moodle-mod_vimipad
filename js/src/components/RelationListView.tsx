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
import {VimiRelation} from '../types';
import {FA, Icon} from '../canvas/icons';

interface Props {
    state: EditorState;
    disabled: boolean;
    onDeleteRelation: (stableid: string) => void;
    onRetarget: (stableid: string, change: {sourceid?: string; targetid?: string}) => void;
    onRenameRelation: (stableid: string, label: string) => void;
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
    const {state, disabled, onDeleteRelation, onRetarget, onRenameRelation, t} = props;
    const [editing, setEditing] = useState<string | null>(null);

    if (state.relations.length === 0) {
        return <p className="text-muted">{t('editor:norelations')}</p>;
    }

    const nodeOptions = state.nodes.map(n =>
        <option key={n.stableid} value={n.stableid}>{n.label}</option>);

    // A double arrow (direction 2) shows as two connected entries (A->B and B->A)
    // sharing one underlying relation and its label.
    const rows: {rel: VimiRelation; reversed: boolean}[] = [];
    state.relations.forEach(rel => {
        rows.push({rel, reversed: false});
        if (rel.direction === 2) {
            rows.push({rel, reversed: true});
        }
    });

    const handleDrop = (event: React.DragEvent, apply: (id: string) => void) => {
        event.preventDefault();
        const dropped = event.dataTransfer.getData(DND_MIME);
        if (dropped) {
            apply(dropped);
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
                    {rows.map(({rel, reversed}) => {
                        const isEd = editing === rel.stableid;
                        const srcId = reversed ? rel.targetid : rel.sourceid;
                        const tgtId = reversed ? rel.sourceid : rel.targetid;
                        const onSrc = (id: string) =>
                            onRetarget(rel.stableid, reversed ? {targetid: id} : {sourceid: id});
                        const onTgt = (id: string) =>
                            onRetarget(rel.stableid, reversed ? {sourceid: id} : {targetid: id});
                        return (
                            <tr key={`${rel.stableid}-${reversed ? 'r' : 'f'}`}>
                                <td
                                    onDragOver={allowDrop}
                                    onDrop={e => handleDrop(e, onSrc)}
                                >
                                    {isEd ? (
                                        <select
                                            className="form-control form-control-sm"
                                            aria-label={t('editor:subject')}
                                            value={srcId}
                                            disabled={disabled}
                                            onChange={e => onSrc(e.target.value)}
                                        >{nodeOptions}</select>
                                    ) : labelFor(state, srcId)}
                                </td>
                                <td>
                                    {isEd ? (
                                        <input
                                            key={rel.stableid}
                                            type="text"
                                            className="form-control form-control-sm"
                                            defaultValue={rel.label}
                                            disabled={disabled}
                                            placeholder={t('editor:relation')}
                                            aria-label={t('editor:relation')}
                                            onBlur={e => {
                                                if (e.target.value !== rel.label) {
                                                    onRenameRelation(rel.stableid, e.target.value);
                                                }
                                            }}
                                        />
                                    ) : <em>{rel.label || rel.type}</em>}
                                </td>
                                <td
                                    onDragOver={allowDrop}
                                    onDrop={e => handleDrop(e, onTgt)}
                                >
                                    {isEd ? (
                                        <select
                                            className="form-control form-control-sm"
                                            aria-label={t('editor:object')}
                                            value={tgtId}
                                            disabled={disabled}
                                            onChange={e => onTgt(e.target.value)}
                                        >{nodeOptions}</select>
                                    ) : labelFor(state, tgtId)}
                                </td>
                                <td className="text-right vimipad-relation-actions">
                                    {isEd ? (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-success mr-1"
                                            disabled={disabled}
                                            title={t('editor:confirm')}
                                            aria-label={t('editor:confirm')}
                                            onClick={() => setEditing(null)}
                                        >
                                            <Icon name={FA.confirm} />
                                        </button>
                                    ) : (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-secondary mr-1"
                                            disabled={disabled}
                                            title={t('editor:reledit')}
                                            aria-label={t('editor:reledit')}
                                            onClick={() => setEditing(rel.stableid)}
                                        >
                                            <Icon name={FA.edit} />
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-outline-secondary mr-1"
                                        disabled={disabled}
                                        title={t('editor:reverse')}
                                        aria-label={t('editor:reverse')}
                                        onClick={() => onRetarget(rel.stableid,
                                            {sourceid: rel.targetid, targetid: rel.sourceid})}
                                    >
                                        <Icon name={FA.reverse} />
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
                        );
                    })}
                </tbody>
            </table>
        </>
    );
}
