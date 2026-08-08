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
 * Regression test for the reported "Anordnen" behaviour: a node that sits in the
 * outer container but outside an inner container must NOT be dragged into the
 * inner one; a locked node's spacing must be respected; and repeated application
 * must not drift nodes steadily outward (it must settle).
 *
 * Layout mirrors the reported screenshots: an outer rectangle contains "Hallo"
 * and the locked "Didaktik"; an inner ellipse-box contains only "Didaktik";
 * "was anderes" sits above the outer box; edges run from Hallo and Didaktik up
 * to "was anderes".
 *
 * @module     mod_vimipad/tests/refine_arrange_regression
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineArrangement} from '../src/graph/refine/refine_arrange';
import {VimiNode, VimiRelation, VimiContainer, LayoutMap, SizeMap} from '../src/types';

function node(stableid: string): VimiNode {
    return {stableid, type: 'concept', label: stableid, content: '', contentformat: 1, metadatajson: ''} as VimiNode;
}
function rel(id: string, s: string, t: string): VimiRelation {
    return {stableid: id, sourceid: s, targetid: t, type: 'link', direction: 1, metadatajson: ''} as VimiRelation;
}
const innerBox = {x: 150, y: 230, w: 200, h: 120};
function inInner(p: {x: number; y: number}): boolean {
    return p.x >= innerBox.x && p.x <= innerBox.x + innerBox.w &&
        p.y >= innerBox.y && p.y <= innerBox.y + innerBox.h;
}
const dist = (a: {x: number; y: number}, b: {x: number; y: number}): number => Math.hypot(a.x - b.x, a.y - b.y);

const nodes = [node('hallo'), node('didaktik'), node('wasanderes')];
const relations = [rel('r1', 'hallo', 'wasanderes'), rel('r2', 'didaktik', 'wasanderes')];
const containers: VimiContainer[] = [
    {stableid: 'outer', type: 'group', label: 'Outer',
        geometryjson: JSON.stringify({x: 40, y: 90, w: 400, h: 380})} as VimiContainer,
    {stableid: 'inner', type: 'group', label: 'Inner',
        geometryjson: JSON.stringify(innerBox)} as VimiContainer,
];
const sizes: SizeMap = {
    hallo: {w: 90, h: 44}, didaktik: {w: 70, h: 36}, wasanderes: {w: 80, h: 30},
};
const start: LayoutMap = {
    hallo: {x: 130, y: 150}, didaktik: {x: 250, y: 290}, wasanderes: {x: 250, y: 40},
};

function arrangeOnce(positions: LayoutMap) {
    return refineArrangement({
        nodes, relations, containers, profile: 'mindmap', positions, sizes,
        pinned: new Set(['didaktik']),
    });
}

describe('arrange regression: outer container must not swallow an outside node', () => {
    test('the locked node never moves', () => {
        const res = arrangeOnce(start);
        expect(res.positions.didaktik).toEqual({x: 250, y: 290});
    });

    test('"hallo" is not dragged into the inner container', () => {
        expect(inInner(start.hallo)).toBe(false);
        const res = arrangeOnce(start);
        expect(inInner(res.positions.hallo)).toBe(false);
    });

    test('spacing to the locked "didaktik" is not reduced', () => {
        const before = dist(start.hallo, start.didaktik);
        const res = arrangeOnce(start);
        expect(dist(res.positions.hallo, res.positions.didaktik)).toBeGreaterThanOrEqual(before - 5);
    });

    test('repeated application settles (no steady outward drift of "was anderes")', () => {
        const p1 = arrangeOnce(start);
        const p2 = arrangeOnce(p1.positions);
        const p3 = arrangeOnce(p2.positions);
        // Movement shrinks pass over pass (it converges, not drifts).
        const move12 = dist(p1.positions.wasanderes, p2.positions.wasanderes);
        const move23 = dist(p2.positions.wasanderes, p3.positions.wasanderes);
        expect(move23).toBeLessThanOrEqual(move12 + 1);
        // "was anderes" does not run steadily further out (up = smaller y) each pass.
        const totalDrift = start.wasanderes.y - p3.positions.wasanderes.y; // positive = moved up/out
        expect(totalDrift).toBeLessThan(60);
    });
});
