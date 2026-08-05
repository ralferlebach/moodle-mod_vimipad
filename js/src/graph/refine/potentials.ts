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
 * Pure potential functions and their analytic derivatives for the layout
 * refiner. Everything here is a scalar function of a scalar argument (a
 * distance, a cosine); the vector assembly lives in refine_layout.ts. Keeping
 * these isolated lets a gradient-check test verify each derivative against a
 * finite difference.
 *
 * @module     mod_vimipad/graph/refine/potentials
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Edge pair potential: the C2-smooth image formula shifted down by one, so it is
 * a bound well rather than a barrier-plus-plateau.
 *
 *   - r = 0    -> p0 - 1   (repulsive core, keeps connected nodes from overlapping)
 *   - r = L    -> -1       (the binding minimum: the ideal edge length)
 *   - r -> inf ->  0       (force-free far field)
 *
 * @param r The (regularised) distance between the two nodes.
 * @param l The ideal edge length (master scale L).
 * @param p0 The core height parameter (2 + max degree).
 * @returns The potential value.
 */
export function edgePotential(r: number, l: number, p0: number): number {
    if (r <= l) {
        const t = r / l;
        return p0 * (3 * t * t * t * t - 4 * t * t * t + 1) - 1;
    }
    const a = (6 * p0) / (l * l);
    const d = r - l;
    return -Math.exp(-a * d * d);
}

/**
 * Derivative of {@see edgePotential} with respect to r. Zero at r = L (the well
 * bottom) and vanishing as r -> inf.
 *
 * @param r The distance.
 * @param l The ideal edge length.
 * @param p0 The core height parameter.
 * @returns dp/dr.
 */
export function edgePotentialDeriv(r: number, l: number, p0: number): number {
    if (r <= l) {
        const t = r / l;
        return (p0 / l) * (12 * t * t * t - 12 * t * t);
    }
    const a = (6 * p0) / (l * l);
    const d = r - l;
    return 2 * a * d * Math.exp(-a * d * d);
}

/**
 * Directional coupling factor for a directed edge, as a function of the cosine
 * c = e_hat . u_hat between the edge direction and the profile's preferred
 * direction. Multiplies the (negative) edge well, so a directed edge is bound
 * most strongly when it points the preferred way.
 *
 * With eps0 = 0 an anti-aligned edge (c = -1) exerts no force at all (a
 * preserving default); a positive eps0 floors the factor so alignment is always
 * enforced (for profiles where direction is mandatory).
 *
 * @param c The cosine of the angle to the preferred direction, in [-1, 1].
 * @param eps0 The floor in [0, 1].
 * @returns The factor in [eps0, 1].
 */
export function dirFactor(c: number, eps0: number): number {
    return eps0 + (1 - eps0) * (1 + c) / 2;
}

/**
 * Derivative of {@see dirFactor} with respect to c (a constant).
 *
 * @param eps0 The floor.
 * @returns dg/dc.
 */
export function dirFactorDeriv(eps0: number): number {
    return (1 - eps0) / 2;
}

/**
 * Repulsion shape as a function of the (anisotropic, elliptical) scaled distance
 * rho. A domed bump: 1 at the centre, 0 beyond rho = 1, with a non-zero outward
 * slope everywhere inside so overlapping boxes are actively pushed apart (unlike
 * a flat-top plateau, which would give zero force in its interior).
 *
 * @param rho The scaled distance (box-normalised).
 * @returns The shape value in [0, 1].
 */
export function repShape(rho: number): number {
    if (rho >= 1) {
        return 0;
    }
    const u = 1 - rho;
    return u * u;
}

/**
 * Derivative of {@see repShape} with respect to rho. Negative inside (outward
 * push), zero at and beyond rho = 1 (finite range, C1 join).
 *
 * @param rho The scaled distance.
 * @returns df/drho.
 */
export function repShapeDeriv(rho: number): number {
    if (rho >= 1) {
        return 0;
    }
    return -2 * (1 - rho);
}

/**
 * Overflow-safe cosh well for a container interior: cosh(u) - 1 up to a cap, then
 * a C1 linear continuation. The cap matters for preservation: a member that
 * currently sits far outside its container must be pulled in with a bounded
 * force, not an explosive exponential one.
 *
 *   - u = 0            -> 0        (soft, flat floor near the centre)
 *   - |u| grows        -> steep    (a firm wall)
 *   - |u| > uMax       -> linear   (bounded force, capped pull-in)
 *
 * @param u The scaled interior coordinate.
 * @param uMax The cap beyond which the well continues linearly.
 * @returns The potential value.
 */
export function softCosh(u: number, uMax: number): number {
    const a = Math.abs(u);
    if (a <= uMax) {
        return Math.cosh(u) - 1;
    }
    return (Math.cosh(uMax) - 1) + Math.sinh(uMax) * (a - uMax);
}

/**
 * Derivative of {@see softCosh} with respect to u. Bounded by sinh(uMax).
 *
 * @param u The scaled interior coordinate.
 * @param uMax The cap.
 * @returns d/du.
 */
export function softCoshDeriv(u: number, uMax: number): number {
    if (Math.abs(u) <= uMax) {
        return Math.sinh(u);
    }
    return Math.sign(u) * Math.sinh(uMax);
}

/**
 * Numerically stable softplus: ln(1 + e^z). Smooth hinge, ~0 for z << 0 and ~z
 * for z >> 0. Used for the soft order-preservation penalty.
 *
 * @param z The argument.
 * @returns softplus(z).
 */
export function softplus(z: number): number {
    if (z > 30) {
        return z;
    }
    if (z < -30) {
        return Math.exp(z);
    }
    return Math.log1p(Math.exp(z));
}

/**
 * Logistic sigmoid, the derivative of {@see softplus}, computed stably.
 *
 * @param z The argument.
 * @returns sigmoid(z) in (0, 1).
 */
export function sigmoid(z: number): number {
    if (z >= 0) {
        return 1 / (1 + Math.exp(-z));
    }
    const e = Math.exp(z);
    return e / (1 + e);
}
