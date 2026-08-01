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
 * Journal revision-viewer bootstrap for mod_vimipad.
 *
 * Wires the "show editing state" buttons in the Journal & submission tab to the
 * read-only revision viewer exported by the bundled editor. Kept separate from
 * the editor bootstrap so a problem here cannot affect the editor itself.
 *
 * @module     mod_vimipad/revision
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_strings as getStrings} from 'core/str';
import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';

/**
 * Strings the read-only viewer may render. Mirrors the editor's set (the viewer
 * reuses the canvas and relation-list components) plus the viewer title.
 *
 * @type {string[]}
 */
const STRING_KEYS = [
    'editor:canvasaria', 'editor:canvashint', 'editor:canvasview', 'editor:listview',
    'editor:loading', 'editor:nodelabel', 'editor:norelations', 'editor:object',
    'editor:relation', 'editor:relations', 'editor:reledit', 'editor:retarget',
    'editor:reverse', 'journal:revisiontitle',
    'revision:play', 'revision:pause', 'revision:playtitle', 'revision:scrubber',
];

/**
 * Load the viewer strings and return a key → text map.
 *
 * @returns {Promise<Object.<string, string>>} Resolved strings.
 */
const loadStrings = async() => {
    const requests = STRING_KEYS.map((key) => ({key, component: 'mod_vimipad'}));
    const values = await getStrings(requests);
    const map = {};
    STRING_KEYS.forEach((key, index) => {
        map[key] = values[index];
    });
    return map;
};

/**
 * Build a (methodname, args) transport bound to Moodle's core/ajax.
 *
 * @returns {function(string, Object): Promise<*>} The transport.
 */
const buildTransport = () => (methodname, args) => {
    const [promise] = fetchMany([{methodname, args}]);
    return promise;
};

/**
 * Load the prebuilt editor module and return its revision-viewer entry point.
 *
 * @returns {Promise<{mountRevision: function(HTMLElement, Object): void}>} The API.
 */
const loadViewer = () => new Promise((resolve, reject) => {
    require(['mod_vimipad/editor_lazy'], (module) => {
        const editor = module && module.default ? module.default : module;
        if (editor && typeof editor.mountRevision === 'function' && typeof editor.mountPlayer === 'function') {
            resolve(editor);
        } else {
            reject(new Error('ViMi Pad editor module did not expose mountRevision()/mountPlayer().'));
        }
    }, reject);
});

/**
 * Wire the journal revision buttons on the page.
 *
 * @param {number} cmid The course module id.
 * @returns {Promise<void>}
 */
export const init = async(cmid) => {
    const buttons = Array.prototype.slice.call(document.querySelectorAll('[data-vimipad-revision]'));
    const playButtons = Array.prototype.slice.call(document.querySelectorAll('[data-vimipad-play-revision]'));
    const container = document.getElementById('vimipad-revision-viewer');
    if ((buttons.length === 0 && playButtons.length === 0) || !container) {
        return;
    }

    try {
        const [strings, editor] = await Promise.all([loadStrings(), loadViewer()]);
        const getString = (key) => strings[key] ?? key;

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const workspaceid = parseInt(button.dataset.workspaceid || '0', 10);
                const revision = parseInt(button.dataset.vimipadRevision || '0', 10);
                editor.mountRevision(container, {
                    cmid,
                    workspaceid,
                    revision,
                    callService: buildTransport(),
                    getString,
                });
                container.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            });
        });

        playButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const workspaceid = parseInt(button.dataset.workspaceid || '0', 10);
                const maxRevision = parseInt(button.dataset.vimipadPlayRevision || '0', 10);
                editor.mountPlayer(container, {
                    cmid,
                    workspaceid,
                    revision: maxRevision,
                    maxRevision,
                    callService: buildTransport(),
                    getString,
                });
                container.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            });
        });
    } catch (error) {
        Notification.exception(error);
    }
};
