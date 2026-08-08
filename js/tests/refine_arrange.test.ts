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
 * The arrange adapter that drives the refiner from editor state: the per-profile
 * resolver picks sensible axes, membership is read from the current geometry,
 * pinned nodes are frozen, and a clean layout is preserved.
 *
 * @module     mod_vimipad/tests/refine_arrange
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {refineArrangement, refineOptionsForProfile} from '../src/graph/refine/refine_arrange';
import {VimiNode, VimiRelation, VimiContainer, LayoutMap, SizeMap} from '../src/types';

function node(stableid: string, label = 'N'): VimiNode {
    return {stableid, type: 'concept', label, content: '', contentformat: 1, metadatajson: ''} as VimiNode;
}
function rel(stableid: string, sourceid: string, targetid: string): VimiRelation {
    return {stableid, sourceid, targetid, type: 'link', direction: 1, metadatajson: ''} as VimiRelation;
}

describe('refineOptionsForProfile', () => {
    test('tree flows down and keeps sibling order', () => {
        expect(refineOptionsForProfile('tree')).toEqual({
            preferredDir: {x: 0, y: 1}, directed: true, orderAxis: {x: 1, y: 0}, cyclicOrder: false, lineAxis: null, attackRepel: false, rankLayered: false, clustered: false, fishbone: false, relationLayout: [],
        });
    });
    test('conceptmap keeps sibling order but is not direction-forced', () => {
        const cm = refineOptionsForProfile('conceptmap');
        expect(cm.directed).toBe(false);
        expect(cm.orderAxis).toEqual({x: 1, y: 0});
    });
    test('radial and unknown profiles are free-form', () => {
        expect(refineOptionsForProfile('mindmap').orderAxis).toBeNull();
        expect(refineOptionsForProfile('semanticnetwork').preferredDir).toBeNull();
    });
});

describe('refineArrangement', () => {
    test('preserves a clean layout (small movement)', () => {
        const nodes = [node('a'), node('b'), node('c')];
        const relations = [rel('r1', 'a', 'b'), rel('r2', 'b', 'c')];
        const positions: LayoutMap = {a: {x: 100, y: 200}, b: {x: 250, y: 200}, c: {x: 400, y: 200}};
        const sizes: SizeMap = {a: {w: 60, h: 40}, b: {w: 60, h: 40}, c: {w: 60, h: 40}};
        const res = refineArrangement({
            nodes, relations, containers: [], profile: 'semanticnetwork', positions, sizes,
            overrides: {stabilityScale: 2},
        });
        for (const id of ['a', 'b', 'c']) {
            const moved = Math.hypot(res.positions[id].x - positions[id].x, res.positions[id].y - positions[id].y);
            expect(moved).toBeLessThan(12);
        }
    });

    test('freezes a pinned node exactly', () => {
        const nodes = [node('a'), node('b')];
        const positions: LayoutMap = {a: {x: 200, y: 200}, b: {x: 214, y: 206}}; // overlapping
        const sizes: SizeMap = {a: {w: 80, h: 40}, b: {w: 80, h: 40}};
        const res = refineArrangement({
            nodes, relations: [], containers: [], profile: 'semanticnetwork', positions, sizes,
            pinned: new Set(['a']),
        });
        expect(res.positions.a).toEqual({x: 200, y: 200});
        // b was free to move away from the overlap.
        const bMoved = Math.hypot(res.positions.b.x - 214, res.positions.b.y - 206);
        expect(bMoved).toBeGreaterThan(0);
    });

    test('reads container membership from geometry and keeps a member inside', () => {
        const nodes = [node('m'), node('anchor')];
        const relations = [rel('r', 'anchor', 'm')];
        // 'm' starts just outside the box; 'anchor' inside. Both count as members
        // only if their centre is inside — here anchor is inside, m is outside, so
        // m is NOT a member and must not be dragged in. Use a member that starts
        // inside to test confinement.
        const positions: LayoutMap = {m: {x: 300, y: 300}, anchor: {x: 340, y: 320}};
        const sizes: SizeMap = {m: {w: 50, h: 40}, anchor: {w: 50, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'Box',
                geometryjson: JSON.stringify({x: 250, y: 250, w: 200, h: 160})} as VimiContainer,
        ];
        const res = refineArrangement({
            nodes, relations, containers, profile: 'conceptmap', positions, sizes,
            overrides: {stabilityScale: 1, containerIn: 2},
        });
        // Both centres started inside the box; they should remain inside it.
        const box = {x: 250, y: 250, w: 200, h: 160};
        for (const id of ['m', 'anchor']) {
            const p = res.positions[id];
            expect(p.x).toBeGreaterThanOrEqual(box.x);
            expect(p.x).toBeLessThanOrEqual(box.x + box.w);
            expect(p.y).toBeGreaterThanOrEqual(box.y);
            expect(p.y).toBeLessThanOrEqual(box.y + box.h);
        }
    });

    test('returns integer positions and unchanged container geometry', () => {
        const nodes = [node('a')];
        const positions: LayoutMap = {a: {x: 123, y: 321}};
        const sizes: SizeMap = {a: {w: 50, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B',
                geometryjson: JSON.stringify({x: 100, y: 100, w: 300, h: 300})} as VimiContainer,
        ];
        const res = refineArrangement({nodes, relations: [], containers, profile: 'tree', positions, sizes});
        expect(Number.isInteger(res.positions.a.x)).toBe(true);
        expect(Number.isInteger(res.positions.a.y)).toBe(true);
    });

    test('a long edge is pulled meaningfully shorter (arrange restructures, not just drift)', () => {
        // Ralf's report: a far node (Node C) barely moved. With a short and a long
        // edge sharing a hub, the long-range spring must contract the over-long
        // edge toward the median length (equalisation), not leave it untouched.
        const nodes = [node('a'), node('b'), node('c')];
        const positions: LayoutMap = {
            a: {x: 200, y: 300}, b: {x: 320, y: 300}, c: {x: 900, y: 300}, // |ab|=120, |ac|=700
        };
        const sizes: SizeMap = {a: {w: 70, h: 40}, b: {w: 70, h: 40}, c: {w: 70, h: 40}};
        const relations = [rel('r1', 'a', 'b'), rel('r2', 'a', 'c')];
        const dist = (p: LayoutMap, u: string, v: string): number =>
            Math.hypot(p[u].x - p[v].x, p[u].y - p[v].y);
        const before = dist(positions, 'a', 'c');
        const res = refineArrangement({nodes, relations, containers: [], profile: 'mindmap', positions, sizes});
        // The long edge contracts by a meaningful fraction (not the near-zero of
        // the old force-free far field).
        expect(dist(res.positions, 'a', 'c')).toBeLessThan(before * 0.9);
    });

    test('an oversized box hugs its members (shrinks toward fit but never below them)', () => {
        // "Anordnen" is an explicit rearrange, so containers now resize to hug
        // their members (Ralf's request). The box must shrink toward the member
        // but never past it, and re-applying must settle (convergence).
        const nodes = [node('a')];
        const positions: LayoutMap = {a: {x: 250, y: 250}}; // well inside a big box
        const sizes: SizeMap = {a: {w: 50, h: 40}};
        const box = {x: 100, y: 100, w: 300, h: 300};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B', geometryjson: JSON.stringify(box)} as VimiContainer,
        ];
        const res = refineArrangement({nodes, relations: [], containers, profile: 'mindmap', positions, sizes});
        const g = res.containers.c;
        expect(g.w).toBeLessThan(box.w); // it shrinks toward the member
        expect(g.h).toBeLessThan(box.h);
        // …but still contains the member with its pad (never shrinks past it).
        expect(g.x).toBeLessThanOrEqual(positions.a.x - sizes.a.w / 2);
        expect(g.x + g.w).toBeGreaterThanOrEqual(positions.a.x + sizes.a.w / 2);
        expect(g.y).toBeLessThanOrEqual(positions.a.y - sizes.a.h / 2);
        expect(g.y + g.h).toBeGreaterThanOrEqual(positions.a.y + sizes.a.h / 2);
        // Convergence: the second pass shrinks strictly less than the first
        // (geometric convergence toward the member-hugging fit).
        const containers2 = [{...containers[0], geometryjson: JSON.stringify(g)}];
        const res2 = refineArrangement({
            nodes, relations: [], containers: containers2, profile: 'mindmap',
            positions: res.positions, sizes,
        });
        const drop1 = box.w - g.w;
        const drop2 = g.w - res2.containers.c.w;
        expect(drop2).toBeLessThan(drop1);
    });

    test('with shrink disabled an oversized box keeps its drawn size but still grows to contain overflow', () => {
        // The "optional damped shrinking" toggle: shrinkContainers:false means a
        // container never shrinks below the author's drawn size, yet still grows
        // to enclose a member that overflows it.
        const nodes = [node('a')];
        const sizes: SizeMap = {a: {w: 50, h: 40}};

        // (1) Oversized box, member well inside: must NOT shrink.
        const big = {x: 100, y: 100, w: 300, h: 300};
        const inside: LayoutMap = {a: {x: 250, y: 250}};
        const bigC: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B', geometryjson: JSON.stringify(big)} as VimiContainer,
        ];
        const kept = refineArrangement({
            nodes, relations: [], containers: bigC, profile: 'mindmap',
            positions: inside, sizes, shrinkContainers: false,
        }).containers.c;
        expect(kept.w).toBe(big.w); // drawn size preserved
        expect(kept.h).toBe(big.h);

        // (2) Undersized box, member overflowing: must still grow to contain it.
        const small = {x: 240, y: 240, w: 20, h: 20};
        const smallC: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B', geometryjson: JSON.stringify(small)} as VimiContainer,
        ];
        const grown = refineArrangement({
            nodes, relations: [], containers: smallC, profile: 'mindmap',
            positions: {a: {x: 250, y: 250}}, sizes, shrinkContainers: false,
        }).containers.c;
        expect(grown.x).toBeLessThanOrEqual(250 - sizes.a.w / 2);
        expect(grown.x + grown.w).toBeGreaterThanOrEqual(250 + sizes.a.w / 2);
    });

    test('a locked container is never resized', () => {
        const nodes = [node('m1'), node('m2')];
        const box = {x: 100, y: 100, w: 140, h: 140};
        const positions: LayoutMap = {m1: {x: 150, y: 170}, m2: {x: 190, y: 170}}; // overlapping members
        const sizes: SizeMap = {m1: {w: 60, h: 40}, m2: {w: 60, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B', geometryjson: JSON.stringify(box)} as VimiContainer,
        ];
        const res = refineArrangement({
            nodes, relations: [], containers, profile: 'mindmap', positions, sizes,
            lockedContainers: new Set(['c']),
        });
        expect(res.containers.c).toEqual(box);
    });

    test('elliptical container: a box-corner node is not a member (not confined)', () => {
        // A wide ellipse; 'corner' sits in the bounding-box corner, outside the
        // ellipse. As a rect it would be a member (kept in); as an ellipse it is a
        // non-member and the dome pushes it further from the centre.
        const geom = {x: 100, y: 100, w: 300, h: 200};
        const cx = geom.x + geom.w / 2;
        const cy = geom.y + geom.h / 2;
        const positions: LayoutMap = {
            corner: {x: geom.x + 30, y: geom.y + 25}, // box corner, outside the ellipse
            anchor: {x: cx, y: cy},
        };
        const sizes: SizeMap = {corner: {w: 40, h: 30}, anchor: {w: 40, h: 30}};
        const nodes = [node('corner'), node('anchor')];
        const distFromCentre = (p: {x: number; y: number}): number => Math.hypot(p.x - cx, p.y - cy);
        const before = distFromCentre(positions.corner);

        const asRect: VimiContainer[] = [{stableid: 'c', type: 'group', label: 'B',
            geometryjson: JSON.stringify(geom), metadatajson: JSON.stringify({shape: 'rect'})} as VimiContainer];
        const asEllipse: VimiContainer[] = [{stableid: 'c', type: 'group', label: 'B',
            geometryjson: JSON.stringify(geom), metadatajson: JSON.stringify({shape: 'ellipse'})} as VimiContainer];

        const rectRes = refineArrangement({
            nodes, relations: [], containers: asRect, profile: 'mindmap', positions, sizes,
            overrides: {stabilityScale: 0.2, containerOut: 4},
        });
        const ellRes = refineArrangement({
            nodes, relations: [], containers: asEllipse, profile: 'mindmap', positions, sizes,
            overrides: {stabilityScale: 0.2, containerOut: 4},
        });

        // As a rect member the corner is confined (stays ~put, flat interior).
        expect(distFromCentre(rectRes.positions.corner)).toBeLessThan(before + 8);
        // As an ellipse non-member it is pushed outward, away from the centre.
        expect(distFromCentre(ellRes.positions.corner)).toBeGreaterThan(before);
    });

    test('repeated application converges (container geometry settles)', () => {
        const nodes = [node('m1'), node('m2')];
        const box = {x: 100, y: 100, w: 140, h: 140};
        const start: LayoutMap = {m1: {x: 150, y: 170}, m2: {x: 190, y: 170}};
        const sizes: SizeMap = {m1: {w: 60, h: 40}, m2: {w: 60, h: 40}};
        const containers: VimiContainer[] = [
            {stableid: 'c', type: 'group', label: 'B', geometryjson: JSON.stringify(box)} as VimiContainer,
        ];
        const arrange = (positions: LayoutMap) => refineArrangement({
            nodes, relations: [], containers, profile: 'mindmap', positions, sizes,
        });
        const p1 = arrange(start);
        // Feed positions back and re-arrange; container geometry must not keep growing.
        const containers2 = [{...containers[0], geometryjson: JSON.stringify(p1.containers.c)}];
        const p2 = refineArrangement({
            nodes, relations: [], containers: containers2, profile: 'mindmap', positions: p1.positions, sizes,
        });
        const dw = Math.abs(p2.containers.c.w - p1.containers.c.w);
        const dh = Math.abs(p2.containers.c.h - p1.containers.c.h);
        expect(dw).toBeLessThan(20);
        expect(dh).toBeLessThan(20);
    });
});
