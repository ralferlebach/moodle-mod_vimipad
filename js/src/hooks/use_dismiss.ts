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
 * A hook that dismisses an open overlay (dropdown, menu, popover) when the user
 * clicks outside its element or presses Escape. Extracted from CanvasView so
 * the interaction is reusable and independently testable.
 *
 * @module     mod_vimipad/hooks/use_dismiss
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {RefObject, useEffect} from 'react';

/**
 * Call onDismiss on an outside click or an Escape key press while active.
 *
 * @param ref The element that should stay open when clicked inside.
 * @param active Whether the overlay is currently open.
 * @param onDismiss Called to close the overlay. Should be stable (useCallback).
 */
export function useDismiss(
    ref: RefObject<HTMLElement>,
    active: boolean,
    onDismiss: () => void
): void {
    useEffect(() => {
        if (!active) {
            return undefined;
        }
        const onDown = (event: MouseEvent): void => {
            if (ref.current && !ref.current.contains(event.target as Node)) {
                onDismiss();
            }
        };
        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                onDismiss();
            }
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [ref, active, onDismiss]);
}
