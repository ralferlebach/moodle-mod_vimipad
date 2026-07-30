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
 * Non-blocking banner that shows the activity's map requirements as soft,
 * edit-time hints. It renders nothing when no constraint is configured or the
 * map already satisfies them, so it only appears when there is something to fix.
 *
 * @module     mod_vimipad/components/ConstraintBanner
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {ConstraintStatus} from '../types';

interface Props {
    status: ConstraintStatus | null;
    t: (key: string) => string;
}

/**
 * @param props The banner props.
 * @returns The banner element, or null when there is nothing to show.
 */
export function ConstraintBanner(props: Props): React.ReactElement | null {
    const {status, t} = props;
    if (!status || !status.configured || status.satisfied || status.messages.length === 0) {
        return null;
    }
    return (
        <div className="alert alert-warning vimipad-constraint-hints" role="status" aria-live="polite">
            <strong>{t('constraint:hintsheading')}</strong>
            <ul className="mb-0 mt-1">
                {status.messages.map((message, index) => (
                    <li key={index}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
