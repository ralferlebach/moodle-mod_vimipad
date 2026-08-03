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
 * A structural guard for the canvas menu click-through invariant.
 *
 * The click-through / z-order bug recurred whenever a canvas menu was hand-
 * written as a raw <foreignObject> with its own pointer-events handling instead
 * of going through MenuOverlay, which centralises the rule. Rather than fully
 * render the prop-heavy CanvasView, this test reads its source and fails if the
 * two regression markers reappear:
 *   1. the `.vimipad-node-dock-fo` wrapper is written directly in CanvasView
 *      (it must only exist inside MenuOverlay), or
 *   2. any `pointerEvents: 'auto'` inline override appears, which would make a
 *      whole overlay box swallow clicks.
 *
 * @module     mod_vimipad/tests/canvas_menu_overlay_usage
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as fs from 'fs';
import * as path from 'path';

const read = (rel: string): string =>
    fs.readFileSync(path.join(__dirname, '..', 'src', 'components', rel), 'utf8');

describe('canvas menu overlay usage', () => {
    test('CanvasView routes every menu through MenuOverlay', () => {
        const src = read('CanvasView.tsx');
        expect(src).toContain('import {MenuOverlay}');
        expect(src).toContain('<MenuOverlay');
    });

    test('CanvasView does not hand-write the dock-fo wrapper', () => {
        const src = read('CanvasView.tsx');
        // The wrapper class must live only in MenuOverlay.
        expect(src).not.toContain('vimipad-node-dock-fo');
    });

    test('no inline pointer-events:auto override on a menu wrapper', () => {
        const src = read('CanvasView.tsx');
        // This override is exactly what made the overlay box clickable and stole
        // clicks from the graph beneath it.
        expect(src.replace(/\s/g, '')).not.toContain("pointerEvents:'auto'");
    });

    test('MenuOverlay keeps the foreignObject click-through', () => {
        const src = read('MenuOverlay.tsx');
        expect(src).toContain('pointerEvents="none"');
        expect(src).toContain('vimipad-node-dock-fo');
        // It must not reintroduce an auto override itself.
        expect(src.replace(/\s/g, '')).not.toContain("pointerEvents:'auto'");
    });
});
