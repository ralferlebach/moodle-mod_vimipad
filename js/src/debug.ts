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
 * Lightweight diagnostic logger shared across the editor. Enable in the browser
 * console with `window.VIMIPAD_DEBUG = true` (disable with `= false`).
 *
 * @module     mod_vimipad/debug
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Log diagnostic values when debugging is enabled.
 *
 * @param args Values to log.
 * @returns void
 */
export function vdbg(...args: unknown[]): void {
    const w = typeof window !== 'undefined' ? (window as unknown as {VIMIPAD_DEBUG?: boolean}) : undefined;
    if (w && w.VIMIPAD_DEBUG) {
        // eslint-disable-next-line no-console
        console.log('[vimipad]', new Date().toISOString().slice(11, 23), ...args);
    }
}
