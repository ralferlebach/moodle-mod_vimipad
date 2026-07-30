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
 * Reading plain text, including line breaks, out of a contenteditable element.
 *
 * When Enter is pressed in a contenteditable, browsers insert markup rather than
 * a newline character: Chrome and Firefox wrap lines in <div> elements, others
 * insert <br>. `textContent` ignores that markup and concatenates the text, so
 * "A⏎B" reads back as "AB" and the line break is lost on save. `innerText` does
 * respect the markup but is not implemented consistently outside browsers, which
 * makes it untestable here, so this walks the tree explicitly instead.
 *
 * @module     mod_vimipad/canvas/editable_text
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Elements that start a new visual line inside a contenteditable. */
const BLOCK_TAGS = new Set(['DIV', 'P', 'LI', 'TR', 'SECTION', 'ARTICLE', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6']);

/**
 * Append a newline unless we are at the very start or already on a fresh line.
 *
 * @param value The text collected so far.
 * @returns The text with a separating newline where needed.
 */
function breakBefore(value: string): string {
    if (value === '' || value.endsWith('\n')) {
        return value;
    }
    return value + '\n';
}

/**
 * Collect the text of a node's children, turning block boundaries and <br> into
 * newline characters.
 *
 * @param node The node to walk.
 * @param collected The text collected so far.
 * @returns The text including this node's children.
 */
function collect(node: Node, collected: string): string {
    let value = collected;
    node.childNodes.forEach(child => {
        if (child.nodeType === 3) {
            value += child.textContent ?? '';
            return;
        }
        if (child.nodeType !== 1) {
            return;
        }
        const tag = (child as Element).tagName;
        if (tag === 'BR') {
            value += '\n';
            return;
        }
        if (BLOCK_TAGS.has(tag)) {
            value = collect(child, breakBefore(value));
            return;
        }
        value = collect(child, value);
    });
    return value;
}

/**
 * The plain text of a contenteditable element, with line breaks preserved.
 *
 * @param element The contenteditable element.
 * @returns The text, using "\n" for line breaks.
 */
export function editableToText(element: HTMLElement): string {
    return collect(element, '');
}
