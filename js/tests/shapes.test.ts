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
 * Tests for the pure canvas shape/label render helpers.
 *
 * @module     mod_vimipad/tests/shapes
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BASE_FONT, labelBox, shapeElement} from '../src/canvas/shapes';
import {TextStyle} from '../src/canvas/node_style';

describe('shapes helpers', () => {
    test('labelBox scales the font size with the text size step', () => {
        expect(labelBox(undefined).fontSize).toBe(`${BASE_FONT}px`);
        const bigger: TextStyle = {size: 2};
        expect(labelBox(bigger).fontSize).toBe(`${BASE_FONT + 4}px`);
    });

    test('labelBox reflects bold/italic/underline', () => {
        const style: TextStyle = {bold: true, italic: true, underline: true};
        const css = labelBox(style);
        expect(css.fontWeight).toBe(700);
        expect(css.fontStyle).toBe('italic');
        expect(css.textDecoration).toBe('underline');
    });

    test('shapeElement returns an ellipse for the ellipse shape', () => {
        const el = shapeElement('ellipse', 40, 20, {});
        expect(el.type).toBe('ellipse');
        expect(el.props.rx).toBe(20);
        expect(el.props.ry).toBe(10);
    });

    test('shapeElement returns a rounded rect for roundrect', () => {
        const el = shapeElement('roundrect', 40, 20, {});
        expect(el.type).toBe('rect');
        expect(el.props.rx).toBe(10);
    });

    test('shapeElement returns a sharp rect for rect', () => {
        const el = shapeElement('rect', 40, 20, {});
        expect(el.type).toBe('rect');
        expect(el.props.rx).toBe(0);
    });
});
