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
 * Tests for reading line breaks out of a contenteditable.
 *
 * @module     mod_vimipad/tests/editable_text
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {editableToText} from '../src/canvas/editable_text';

const build = (html: string): HTMLElement => {
    const el = document.createElement('div');
    el.innerHTML = html;
    return el;
};

describe('editableToText', () => {
    test('plain text is returned unchanged', () => {
        expect(editableToText(build('hello'))).toBe('hello');
    });

    test('a <br> becomes a newline', () => {
        expect(editableToText(build('A<br>B'))).toBe('A\nB');
    });

    test('div-per-line markup (Chrome/Firefox on Enter) becomes newlines', () => {
        // What the browser leaves behind after typing A, Enter, B, Enter, C.
        expect(editableToText(build('A<div>B</div><div>C</div>'))).toBe('A\nB\nC');
    });

    test('textContent would have lost those breaks', () => {
        const el = build('A<div>B</div><div>C</div>');
        expect(el.textContent).toBe('ABC');
        expect(editableToText(el)).toBe('A\nB\nC');
    });

    test('nested inline formatting is kept as text', () => {
        expect(editableToText(build('<div><b>bold</b> and <i>italic</i></div>'))).toBe('bold and italic');
    });

    test('paragraphs are separated once, not twice', () => {
        expect(editableToText(build('<p>one</p><p>two</p>'))).toBe('one\ntwo');
    });

    test('an empty element yields an empty string', () => {
        expect(editableToText(build(''))).toBe('');
    });

    test('a trailing empty line block still separates', () => {
        expect(editableToText(build('A<div><br></div>'))).toBe('A\n\n');
    });
});
