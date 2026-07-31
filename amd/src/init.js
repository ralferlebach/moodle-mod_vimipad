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
 * Editor bootstrap for mod_vimipad.
 *
 * This is the idiomatic Moodle (AMD/ES6) entry point. It is deliberately thin:
 * it resolves the language strings via core/str, resolves an AJAX transport via
 * core/ajax, and then loads the separately bundled React editor as a static
 * asset and hands it a mount target plus injected dependencies.
 *
 * React itself cannot be built through Moodle's Grunt/AMD pipeline on 4.5-5.2
 * (core does not provide a React runtime there), so the React app is bundled
 * separately into js/build/. From Moodle 5.3 onwards, where React ships in core
 * via react_autoinit, this module can be simplified to mount through the core
 * runtime instead of loading the standalone bundle.
 *
 * @module     mod_vimipad/init
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_strings as getStrings} from 'core/str';
import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';

/** @type {string[]} Editor string keys, kept in sync with lang/en/vimipad.php. */
const STRING_KEYS = [
    'constraint:hintsheading',
    'editor:containers', 'editor:drawcontainer', 'editor:drawcontainerdone',
    'editor:node', 'editor:templatelocks', 'editor:templatelockshint', 'editor:lockallowlabel',
    'editor:importnovimidata', 'editor:authortools',
    'editor:lockmode', 'editor:lockelement', 'editor:unlockelement',
    'editor:fmt_fontsans', 'editor:fmt_fontserif', 'editor:fmt_fontmono',
    'editor:add', 'editor:addnode', 'editor:addrelation', 'editor:actions',
    'editor:beingedited', 'editor:cancel', 'editor:canvasaria', 'editor:canvashint',
    'editor:canvasview', 'editor:canvasplaceholder',
    'editor:confirm', 'editor:deleterelation', 'editor:fmt_bold', 'editor:fmt_italic', 'editor:fmt_underline', 'editor:fullview',
    'editor:dir_both', 'editor:dir_left', 'editor:dir_none', 'editor:dir_right',
    'editor:import', 'editor:importheading', 'editor:importhint', 'editor:importreplace',
    'editor:journal', 'editor:journalnew', 'editor:journalprivate', 'editor:journalsave', 'editor:journalsaved',
    'editor:dragnodes', 'editor:export', 'editor:line_curved', 'editor:line_orthogonal',
    'editor:line_straight', 'editor:listview', 'editor:loading',
    'editor:locked', 'editor:nodelabel', 'editor:norelations', 'editor:normalview', 'editor:object',
    'editor:readonly', 'editor:rearrange', 'editor:redo', 'editor:undo',
    'editor:relation', 'editor:relations', 'editor:reledit',
    'editor:reverse', 'editor:retarget', 'editor:revision',
    'editor:subject', 'editor:submit', 'editor:submitconfirm', 'editor:submitpending',
    'editor:fmt_bigger', 'editor:fmt_delete', 'editor:fmt_duplicate', 'editor:fmt_ellipse',
    'editor:fmt_fill', 'editor:fmt_font', 'editor:fmt_fontdefault', 'editor:fmt_highlight',
    'editor:fmt_move', 'editor:fmt_rect', 'editor:fmt_reset', 'editor:fmt_roundrect',
    'editor:fmt_shape', 'editor:fmt_smaller', 'editor:fmt_text', 'editor:fmt_textcolor',
    'editor:fmt_toolbar',
];

/**
 * Load all editor strings and return a key → text map.
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
 * Build a transport bound to Moodle's core/ajax, so the React app never needs
 * to know about Moodle's web service internals.
 *
 * @returns {function(string, Object): Promise<*>} A (methodname, args) transport.
 */
const buildTransport = () => (methodname, args) => {
    const [promise] = fetchMany([{methodname, args}]);
    return promise;
};

/**
 * Load the prebuilt React editor AMD module.
 *
 * Using the module loader (rather than injecting a <script> tag) keeps the load
 * inside Moodle's JS tracking, so Behat's wait_for_pending_js resolves cleanly.
 *
 * @returns {Promise<{mount: function(HTMLElement, Object): void}>} The editor API.
 */
const loadEditor = () => new Promise((resolve, reject) => {
    require(['mod_vimipad/editor_lazy'], (module) => {
        const editor = module && module.default ? module.default : module;
        if (editor && typeof editor.mount === 'function') {
            resolve(editor);
        } else {
            reject(new Error('ViMi Pad editor module did not expose mount().'));
        }
    }, reject);
});

/**
 * Initialise the editor on a page.
 *
 * @param {number} cmid The course module id.
 * @param {string} [selector] The mount element id (default vimipad-editor-root).
 * @returns {Promise<void>}
 */
export const init = async(cmid, selector = 'vimipad-editor-root') => {
    const element = document.getElementById(selector);
    if (!element) {
        return;
    }

    try {
        const [strings, editor] = await Promise.all([loadStrings(), loadEditor()]);
        editor.mount(element, {
            cmid,
            groupid: parseInt(element.dataset.groupid || '0', 10),
            initialView: element.dataset.view === 'list'
                ? 'list'
                : (element.dataset.view === 'tools' ? 'tools' : 'canvas'),
            readonly: element.dataset.readonly === '1',
            targetUserid: parseInt(element.dataset.targetuserid || '0', 10),
            callService: buildTransport(),
            // Return undefined for unknown keys so the bundle's own English
            // fallbacks apply. Echoing the key here would surface raw ids like
            // "editor:authortools" in the UI whenever this built module is older
            // than the language file (e.g. amd/build not rebuilt after an edit).
            getString: (key) => strings[key],
        });
    } catch (error) {
        element.textContent = error.message;
        Notification.exception(error);
    }
};
