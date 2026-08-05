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
 * Gradient checks for the layout refiner: every analytic derivative is verified
 * against a central finite difference. If the assembled energy gradient matches
 * finite differences, the whole descent is trustworthy.
 *
 * @module     mod_vimipad/tests/refine_gradient
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    edgePotential, edgePotentialDeriv, dirFactor, dirFactorDeriv, repShape, repShapeDeriv,
    softplus, sigmoid,
} from '../src/graph/refine/potentials';
import {buildProblem, energyAndGradient, defaultRefineOptions, RefineNode, RefineEdge, RefineOptions, RefineContainer} from '../src/graph/refine/refine_layout';

/** Full options with a fixed scale and directed preference, over the defaults. */
function buildProblemOptions(): RefineOptions {
    return {
        ...defaultRefineOptions(), preferredDir: {x: 0, y: 1}, directionFloor: 0.1,
        stabilityScale: 1.5, scale: 150, orderAxis: {x: 1, y: 0}, orderStrength: 2,
    };
}

/** Central finite difference of a scalar function. */
function fd(f: (x: number) => number, x: number, eps = 1e-6): number {
    return (f(x + eps) - f(x - eps)) / (2 * eps);
}

describe('refiner potential derivatives (finite-difference check)', () => {
    const L = 140;
    const p0 = 5;

    test('edgePotential derivative matches, both branches and across the seam', () => {
        for (const r of [10, 60, 139, 140, 141, 200, 400]) {
            expect(edgePotentialDeriv(r, L, p0)).toBeCloseTo(fd(x => edgePotential(x, L, p0), r), 4);
        }
    });

    test('edge potential is a well: min at L, bounded ends', () => {
        expect(edgePotential(L, L, p0)).toBeCloseTo(-1, 6);
        expect(edgePotential(0, L, p0)).toBeCloseTo(p0 - 1, 6);
        expect(edgePotential(1e6, L, p0)).toBeCloseTo(0, 6);
        // Zero slope at the well bottom (C1 across the seam).
        expect(edgePotentialDeriv(L, L, p0)).toBeCloseTo(0, 6);
    });

    test('directional factor derivative matches', () => {
        for (const eps0 of [0, 0.25]) {
            for (const c of [-0.9, -0.3, 0, 0.5, 0.95]) {
                expect(dirFactorDeriv(eps0)).toBeCloseTo(fd(x => dirFactor(x, eps0), c), 5);
            }
        }
    });

    test('repulsion shape derivative matches and has non-zero central slope', () => {
        for (const rho of [0.05, 0.3, 0.6, 0.95]) {
            expect(repShapeDeriv(rho)).toBeCloseTo(fd(x => repShape(x), rho), 5);
        }
        expect(repShapeDeriv(0)).toBeLessThan(0); // pushes outward at the centre
        expect(repShape(1)).toBe(0);
        expect(repShapeDeriv(1.5)).toBe(0);
    });

    test('softplus derivative is the sigmoid (finite-difference check)', () => {
        for (const z of [-40, -5, -1, 0, 1, 5, 40]) {
            expect(sigmoid(z)).toBeCloseTo(fd(x => softplus(x), z), 4);
        }
        expect(softplus(-50)).toBeCloseTo(0, 6);
        expect(softplus(50)).toBeCloseTo(50, 6);
    });
});

describe('assembled energy gradient (finite-difference check)', () => {
    const opts: RefineOptions = buildProblemOptions();

    // A small mixed graph: directed and plain edges, overlapping and distant pairs.
    const nodes: RefineNode[] = [
        {stableid: 'a', x: 100, y: 100, w: 90, h: 40},
        {stableid: 'b', x: 130, y: 120, w: 120, h: 40}, // close to a -> repulsion active
        {stableid: 'c', x: 400, y: 300, w: 80, h: 40},
        {stableid: 'd', x: 250, y: 500, w: 80, h: 40},
    ];
    const edges: RefineEdge[] = [
        {source: 'a', target: 'c', directed: true},
        {source: 'b', target: 'd', directed: false},
        {source: 'c', target: 'd', directed: true},
    ];

    test('analytic gradient equals the finite-difference gradient at every node', () => {
        const prob = buildProblem(nodes, edges, opts);
        checkGradient(prob);
    });

    test('gradient stays correct with a container (member + non-member)', () => {
        // 'a' is a member (sitting off-centre inside), 'c' a non-member sitting
        // inside the box (must be pushed out) — both container terms exercised.
        const cnodes: RefineNode[] = [
            {stableid: 'a', x: 320, y: 300, w: 90, h: 40},
            {stableid: 'b', x: 130, y: 120, w: 120, h: 40},
            {stableid: 'c', x: 360, y: 320, w: 80, h: 40},
            {stableid: 'd', x: 250, y: 600, w: 80, h: 40},
        ];
        const containers: RefineContainer[] = [
            {stableid: 'box', x: 250, y: 250, w: 220, h: 160, members: ['a']},
        ];
        const prob = buildProblem(cnodes, edges, opts, containers);
        checkGradient(prob);
    });
});

/** Compare the analytic gradient to a central finite difference at each node. */
function checkGradient(prob: ReturnType<typeof buildProblem>): void {
    const n = prob.n;
    const gx = new Float64Array(n);
    const gy = new Float64Array(n);
    energyAndGradient(prob, [gx, gy]);
    const eps = 1e-4;
    for (let i = 0; i < n; i++) {
        const ox = prob.px[i];
        const oy = prob.py[i];
        prob.px[i] = ox + eps; const exPlus = energyAndGradient(prob);
        prob.px[i] = ox - eps; const exMinus = energyAndGradient(prob);
        prob.px[i] = ox;
        prob.py[i] = oy + eps; const eyPlus = energyAndGradient(prob);
        prob.py[i] = oy - eps; const eyMinus = energyAndGradient(prob);
        prob.py[i] = oy;
        expect(gx[i]).toBeCloseTo((exPlus - exMinus) / (2 * eps), 3);
        expect(gy[i]).toBeCloseTo((eyPlus - eyMinus) / (2 * eps), 3);
    }
}
