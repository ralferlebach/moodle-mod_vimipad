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
 * The editor language string keys and a loader for them.
 *
 * Single source of truth for the ViMi Pad editor string keys (kept in sync with
 * lang/en/vimipad.php). The activity bootstrap imports STRING_KEYS from here;
 * embedding hosts that mount the editor on their own page can call load() to get
 * a ready getString resolver without duplicating the list.
 *
 * @module     mod_vimipad/editor_strings
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_strings as getStrings} from 'core/str';

export const STRING_KEYS = [
    'constraint:hintsheading',
    'editor:concepts', 'editor:conceptsandrelations',
    'editor:containers', 'editor:drawcontainer', 'editor:drawcontainerdone', 'editor:newcontainer',
    'editor:node', 'editor:templatelocks', 'editor:templatelockshint', 'editor:lockallowlabel',
    'editor:importnovimidata', 'editor:authortools',
    'editor:lockmode', 'editor:lockelement', 'editor:unlockelement', 'editor:elementlocked',
    'editor:lockgroup_move', 'editor:lockgroup_color', 'editor:lockgroup_text',
    'editor:fmt_fontsans', 'editor:fmt_fontserif', 'editor:fmt_fontmono',
    'editor:add', 'editor:addnode', 'editor:addrelation', 'editor:actions',
    'editor:beingedited', 'editor:cancel', 'editor:canvasaria', 'editor:canvashint',
    'editor:canvasview', 'editor:canvasplaceholder',
    'editor:confirm', 'editor:deleterelation', 'editor:fmt_bold', 'editor:fmt_italic', 'editor:fmt_underline', 'editor:fullview',
    'editor:dir_both', 'editor:dir_left', 'editor:dir_none', 'editor:dir_right',
    'editor:import', 'editor:importheading', 'editor:importhint', 'editor:importreplace',
    'editor:exportdataheading', 'editor:exportdatahint', 'editor:exportjson', 'editor:exportxml',
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
    'editor:fmt_diamond', 'editor:fmt_move', 'editor:fmt_parallelogram',
    'editor:fmt_rect', 'editor:fmt_reset', 'editor:fmt_roundrect',
    'editor:fmt_terminator',
    'editor:fmt_shape', 'editor:fmt_smaller', 'editor:fmt_text', 'editor:fmt_textcolor',
    'editor:fmt_toolbar',
];

/**
 * Load the editor strings and return a getString resolver.
 *
 * @returns {Promise<function(string): string>} Resolves a key to its text,
 *     falling back to the key itself when it is not translated.
 */
export const load = async() => {
    const requests = STRING_KEYS.map((key) => ({key, component: 'mod_vimipad'}));
    const values = await getStrings(requests);
    const map = {};
    STRING_KEYS.forEach((key, index) => {
        map[key] = values[index];
    });
    return (key) => (map[key] !== undefined ? map[key] : key);
};
