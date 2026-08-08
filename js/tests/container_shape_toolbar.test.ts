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
 * Reproduction guard for the container shape picker: selecting a shape from the
 * container format dock must commit that shape (Ralf: "can no longer set the
 * container shape"). Containers drive the dock with kind="node", so this test
 * renders it exactly as CanvasView does for a container.
 *
 * @module     mod_vimipad/tests/container_shape_toolbar
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {act} from 'react';
import {NodeFormatToolbar} from '../src/components/NodeFormatToolbar';
import {parseNodeStyle} from '../src/canvas/node_style';

(globalThis as unknown as {IS_REACT_ACT_ENVIRONMENT: boolean}).IS_REACT_ACT_ENVIRONMENT = true;

describe('container shape picker', () => {
    let host: HTMLDivElement;
    let root: Root;

    beforeEach(() => {
        host = document.createElement('div');
        document.body.appendChild(host);
        root = createRoot(host);
    });

    afterEach(() => {
        act(() => root.unmount());
        host.remove();
    });

    const findByLabel = (label: string): HTMLElement | undefined =>
        [...host.querySelectorAll('button')].find(
            b => b.getAttribute('aria-label') === label || b.getAttribute('title') === label
        ) as HTMLElement | undefined;

    test('choosing "ellipse" commits shape:ellipse for a container', () => {
        const commits: string[] = [];
        // Identity translator: keys pass through, so we can target by key.
        const t = (k: string): string => k;

        act(() => {
            root.render(React.createElement(NodeFormatToolbar, {
                kind: 'node', // containers render the dock exactly like this
                target: {metadatajson: '{}'}, // a container with no shape yet
                profile: 'mindmap',
                disabled: false,
                onChangeStyle: (m: string) => commits.push(m),
                onDelete: () => undefined,
                t,
            }));
        });

        // Open the shape sub-panel, then pick the ellipse.
        const shapeBtn = findByLabel('editor:fmt_shape');
        expect(shapeBtn).toBeDefined();
        act(() => shapeBtn!.dispatchEvent(new MouseEvent('click', {bubbles: true})));

        const ellipseBtn = findByLabel('editor:fmt_ellipse');
        expect(ellipseBtn).toBeDefined();
        act(() => ellipseBtn!.dispatchEvent(new MouseEvent('click', {bubbles: true})));

        expect(commits).toHaveLength(1);
        expect(parseNodeStyle(commits[0]).shape).toBe('ellipse');
    });
});
