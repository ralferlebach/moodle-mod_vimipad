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

import React from 'react';

interface Props {
    /** Left edge of the overlay box, in canvas coordinates. */
    x: number;
    /** Top edge of the overlay box, in canvas coordinates. */
    y: number;
    /** Width of the overlay box. */
    width: number;
    /** Height of the overlay box. */
    height: number;
    /** The menu content (a `.vimipad-node-dock` toolbar). */
    children?: React.ReactNode;
}

/**
 * A canvas menu overlay: a `foreignObject` that holds a floating toolbar above
 * the graph without stealing clicks from the elements it overlaps.
 *
 * The canvas is an SVG whose layering follows DOM order, so menus live in the
 * topmost layer and their `foreignObject` boxes are deliberately larger than
 * the visible toolbar. To stop that empty box from swallowing pointer events on
 * the nodes and relations beneath it, this component enforces one invariant in
 * a single place:
 *
 *  - the `foreignObject` and its `.vimipad-node-dock-fo` wrapper are
 *    `pointer-events: none` (click-through), and
 *  - only the inner `.vimipad-node-dock` toolbar is `pointer-events: auto`.
 *
 * Every canvas menu (node, relation, container) renders through here so the
 * rule cannot drift per call site — the recurring cause of the click-through
 * and z-order bugs. A pointer-down on the toolbar is stopped from reaching the
 * canvas so selecting a menu button never deselects or pans.
 *
 * @param props Component props.
 * @returns The overlay element.
 *
 * @module mod_vimipad/components/MenuOverlay
 */
export function MenuOverlay(props: Props): React.ReactElement {
    const {x, y, width, height, children} = props;
    return (
        <foreignObject
            x={x}
            y={y}
            width={width}
            height={height}
            style={{overflow: 'visible'}}
            pointerEvents="none"
        >
            <div className="vimipad-node-dock-fo" onPointerDown={e => e.stopPropagation()}>
                {children}
            </div>
        </foreignObject>
    );
}
