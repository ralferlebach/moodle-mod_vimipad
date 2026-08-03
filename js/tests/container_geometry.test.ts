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
 * Tests for the pure container geometry helpers.
 *
 * @module     mod_vimipad/tests/container_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    boundingBox, boxFromDrag, centerInBox, ContainerBox, isDrawable, MIN_CONTAINER_SIZE, moveBox,
    nestingOrder, nestingParents, normalizeBox, parseGeometry, resizeBox, serializeGeometry,
    isNodePinnedForRearrange,
} from '../src/canvas/container_geometry';

describe('container_geometry', () => {
    test('parseGeometry returns null for empty or malformed input', () => {
        expect(parseGeometry(undefined)).toBeNull();
        expect(parseGeometry('')).toBeNull();
        expect(parseGeometry('not json')).toBeNull();
        expect(parseGeometry('{"x":1,"y":2}')).toBeNull();
        expect(parseGeometry('{"x":1,"y":2,"w":"a","h":4}')).toBeNull();
    });

    test('parseGeometry reads a valid box', () => {
        expect(parseGeometry('{"x":10,"y":20,"w":300,"h":200}')).toEqual({x: 10, y: 20, w: 300, h: 200});
    });

    test('serializeGeometry rounds to whole units and round-trips', () => {
        const json = serializeGeometry({x: 10.4, y: 20.6, w: 300.5, h: 199.5});
        expect(JSON.parse(json)).toEqual({x: 10, y: 21, w: 301, h: 200});
        expect(parseGeometry(json)).toEqual({x: 10, y: 21, w: 301, h: 200});
    });

    test('normalizeBox gives a top-left origin with positive size', () => {
        expect(normalizeBox({x: 100, y: 100, w: -40, h: -30})).toEqual({x: 60, y: 70, w: 40, h: 30});
    });

    test('boxFromDrag normalises regardless of drag direction', () => {
        const a = boxFromDrag({x: 200, y: 200}, {x: 50, y: 60});
        expect(a).toEqual({x: 50, y: 60, w: 150, h: 140});
    });

    test('isDrawable enforces the minimum size', () => {
        expect(isDrawable({x: 0, y: 0, w: MIN_CONTAINER_SIZE, h: MIN_CONTAINER_SIZE})).toBe(true);
        expect(isDrawable({x: 0, y: 0, w: MIN_CONTAINER_SIZE - 1, h: 100})).toBe(false);
    });

    test('moveBox translates without changing size', () => {
        expect(moveBox({x: 10, y: 20, w: 100, h: 80}, 5, -5)).toEqual({x: 15, y: 15, w: 100, h: 80});
    });

    test('resizeBox grows from the bottom-right and clamps to the minimum', () => {
        expect(resizeBox({x: 10, y: 20, w: 100, h: 80}, 20, 10)).toEqual({x: 10, y: 20, w: 120, h: 90});
        const clamped = resizeBox({x: 0, y: 0, w: 50, h: 50}, -100, -100);
        expect(clamped.w).toBe(MIN_CONTAINER_SIZE);
        expect(clamped.h).toBe(MIN_CONTAINER_SIZE);
    });

    test('centerInBox is inclusive of the border', () => {
        const box = {x: 0, y: 0, w: 100, h: 100};
        expect(centerInBox({x: 50, y: 50}, box)).toBe(true);
        expect(centerInBox({x: 0, y: 100}, box)).toBe(true);
        expect(centerInBox({x: 101, y: 50}, box)).toBe(false);
    });

    test('re-arrange refit around a nested container keeps the child enclosed', () => {
        // Reproduces the container-in-container collapse fix: an outer container
        // refits around a member node AND a nested child container, so its
        // bounding box must fully contain the child box (the outer no longer
        // collapses onto the inner one).
        const childBox = {x: 200, y: 200, w: 150, h: 120};
        const memberNodeBox = {x: 40, y: 40, w: 60, h: 30};
        const fit = boundingBox([memberNodeBox, childBox], 24);

        expect(fit).not.toBeNull();
        const box = fit as {x: number; y: number; w: number; h: number};
        // The child container is entirely inside the refitted outer box.
        expect(box.x).toBeLessThanOrEqual(childBox.x);
        expect(box.y).toBeLessThanOrEqual(childBox.y);
        expect(box.x + box.w).toBeGreaterThanOrEqual(childBox.x + childBox.w);
        expect(box.y + box.h).toBeGreaterThanOrEqual(childBox.y + childBox.h);
        // Sanity: the child's centre lies inside the refitted outer box.
        expect(centerInBox({x: childBox.x + childBox.w / 2, y: childBox.y + childBox.h / 2}, box)).toBe(true);
    });

    test('nestingParents picks the tightest strictly-larger encloser', () => {
        // Three concentric boxes: small in medium in large.
        const items = [
            {stableid: 'large', box: {x: 0, y: 0, w: 400, h: 400}},
            {stableid: 'medium', box: {x: 50, y: 50, w: 200, h: 200}},
            {stableid: 'small', box: {x: 80, y: 80, w: 60, h: 60}},
        ];
        const parents = nestingParents(items);
        expect(parents.get('small')).toBe('medium'); // tightest, not 'large'
        expect(parents.get('medium')).toBe('large');
        expect(parents.has('large')).toBe(false); // a root
    });

    test('overlapping same-size containers never nest (no cycle)', () => {
        // Two equal boxes whose centres each lie inside the other. The old
        // centre-only rule made them mutual children (cyclic), which drove the
        // runaway growth and hierarchy flipping. Equal area => neither nests.
        const items = [
            {stableid: 'a', box: {x: 0, y: 0, w: 300, h: 300}},
            {stableid: 'b', box: {x: 120, y: 120, w: 300, h: 300}},
        ];
        const parents = nestingParents(items);
        expect(parents.has('a')).toBe(false);
        expect(parents.has('b')).toBe(false);
    });

    test('nestingOrder lists deepest children before their parents', () => {
        const items = [
            {stableid: 'large', box: {x: 0, y: 0, w: 400, h: 400}},
            {stableid: 'medium', box: {x: 50, y: 50, w: 200, h: 200}},
            {stableid: 'small', box: {x: 80, y: 80, w: 60, h: 60}},
        ];
        const order = nestingOrder(items, nestingParents(items));
        expect(order.indexOf('small')).toBeLessThan(order.indexOf('medium'));
        expect(order.indexOf('medium')).toBeLessThan(order.indexOf('large'));
    });

    test('repeated nested refit converges (no runaway growth)', () => {
        // Simulate the re-arrange refit over several passes and assert the outer
        // container's area stops growing — the property that visibly failed in
        // the reported screenshots.
        const pad = 24;
        let outer = {x: 0, y: 0, w: 400, h: 400};
        const inner = {x: 100, y: 100, w: 150, h: 150};
        const areas: number[] = [];
        for (let pass = 0; pass < 5; pass++) {
            // Outer refits around inner only (inner is its single child, fixed).
            const fit = boundingBox([inner], pad);
            outer = fit as typeof outer;
            areas.push(outer.w * outer.h);
        }
        // After the first pass the area is constant (converged), never growing.
        expect(areas[1]).toBe(areas[0]);
        expect(areas[4]).toBe(areas[0]);
    });

    test('a node enlarging the innermost container propagates out to the root', () => {
        // Child -> parent processing: a node placed far inside the innermost
        // container grows it; the middle container must then grow to enclose the
        // grown inner box, and the outermost must grow to enclose the middle —
        // the size change must reach the end of the nesting chain.
        const pad = 20;

        // Three concentric containers (small in medium in large).
        const items = [
            {stableid: 'large', box: {x: 0, y: 0, w: 300, h: 300}},
            {stableid: 'medium', box: {x: 40, y: 40, w: 200, h: 200}},
            {stableid: 'small', box: {x: 70, y: 70, w: 100, h: 100}},
        ];
        const parents = nestingParents(items);
        const order = nestingOrder(items, parents);
        expect(order).toEqual(['small', 'medium', 'large']);

        const working = new Map(items.map(i => [i.stableid, i.box]));

        // A node relocated to the far corner, outside the current 'small' box,
        // forcing 'small' to grow well beyond its start size.
        const nodeBox = {x: 230, y: 230, w: 40, h: 30};

        for (const cid of order) {
            const memberBoxes = cid === 'small' ? [nodeBox] : [];
            const childBoxes = order
                .filter(id => parents.get(id) === cid)
                .map(id => working.get(id) as ContainerBox);
            const fit = boundingBox([...memberBoxes, ...childBoxes], pad);
            if (fit) {
                working.set(cid, fit);
            }
        }

        const small = working.get('small') as ContainerBox;
        const medium = working.get('medium') as ContainerBox;
        const large = working.get('large') as ContainerBox;
        const encloses = (outerBox: ContainerBox, innerBox: ContainerBox): boolean =>
            outerBox.x <= innerBox.x && outerBox.y <= innerBox.y
            && outerBox.x + outerBox.w >= innerBox.x + innerBox.w
            && outerBox.y + outerBox.h >= innerBox.y + innerBox.h;

        // The node forced 'small' to reach the node, and each ancestor encloses
        // its child — the growth propagated all the way to 'large'.
        expect(small.x + small.w).toBeGreaterThanOrEqual(nodeBox.x + nodeBox.w);
        expect(encloses(medium, small)).toBe(true);
        expect(encloses(large, medium)).toBe(true);
    });

    describe('isNodePinnedForRearrange', () => {
        const moveLocked = (m?: string): boolean => {
            if (!m) {
                return false;
            }
            const meta = JSON.parse(m);
            if (!meta.locked) {
                return false;
            }
            return meta.locks ? Boolean(meta.locks.move) : true;
        };
        const box: ContainerBox = {x: 0, y: 0, w: 100, h: 100};

        test('a move-locked node is pinned regardless of containers', () => {
            const meta = JSON.stringify({locked: true, locks: {move: true, color: false, text: false}});
            expect(isNodePinnedForRearrange(meta, {x: 500, y: 500}, [], moveLocked)).toBe(true);
        });

        test('a free node outside every locked container is not pinned', () => {
            expect(isNodePinnedForRearrange(undefined, {x: 500, y: 500}, [box], moveLocked)).toBe(false);
        });

        test('a free node inside a move-locked container is pinned', () => {
            expect(isNodePinnedForRearrange(undefined, {x: 50, y: 50}, [box], moveLocked)).toBe(true);
        });

        test('a node with no known position is not pinned by containers', () => {
            expect(isNodePinnedForRearrange(undefined, undefined, [box], moveLocked)).toBe(false);
        });
    });
});
