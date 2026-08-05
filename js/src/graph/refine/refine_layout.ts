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
 * Layout refiner: a warm-started, preservation-first optimiser that gently
 * improves a human-made layout instead of rebuilding it. It never initialises
 * or re-seeds; it starts from the given positions and descends the energy
 *
 *   E = E_repulsion (all pairs, anisotropic) + E_edge (length + direction)
 *       + E_stability (anchor to the reference layout),
 *
 * by monotone gradient descent with Armijo backtracking, a per-node step cap and
 * a global movement budget. Containers, order preservation, the active set and
 * discrete swaps are added in later increments and slot onto this core.
 *
 * All parameters are derived from a single master scale L (the median edge
 * length) so the behaviour is scale-invariant. The result is deterministic.
 *
 * @module     mod_vimipad/graph/refine/refine_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    edgePotential, edgePotentialDeriv, dirFactor, dirFactorDeriv, repShape, repShapeDeriv,
    softCosh, softCoshDeriv, softplus, sigmoid,
} from './potentials';

/** A node to refine: its current position, box size and how anchored it is. */
export interface RefineNode {
    stableid: string;
    x: number;
    y: number;
    /** Full box width. */
    w: number;
    /** Full box height. */
    h: number;
    /** Relative stability weight (>= 0); higher means harder to move. Default 1. */
    stabilityWeight?: number;
    /** If true the node is pinned and never moves. */
    fixed?: boolean;
}

/**
 * A container box (top-left x/y plus width/height, matching the stored
 * geometry) together with the stable ids of its intended members. Members are
 * confined inside; non-members are pushed outside.
 */
export interface RefineContainer {
    stableid: string;
    x: number;
    y: number;
    w: number;
    h: number;
    members: string[];
    /** If true the box geometry is never adjusted. */
    fixed?: boolean;
}

/** An edge between two nodes, optionally directed. */
export interface RefineEdge {
    source: string;
    target: string;
    /** If true the edge prefers to point along the profile direction. */
    directed?: boolean;
}

/** Tunable options; every length is interpreted relative to the master scale L. */
export interface RefineOptions {
    /** Preferred direction for directed edges (need not be normalised); null disables it. */
    preferredDir: {x: number; y: number} | null;
    /** Floor of the directional factor in [0,1]; 0 = fully preserving, >0 = always align. */
    directionFloor: number;
    /** Extra separation padding beyond touching boxes, as a fraction of L. */
    padFactor: number;
    /** Stability strength; the per-node anchor weight is stabilityScale / L^2. */
    stabilityScale: number;
    /** Master scale override; when omitted it is the median edge length. */
    scale?: number;
    /** Max solver iterations. */
    maxIterations: number;
    /** Per-node, per-iteration movement cap, as a fraction of L. */
    stepCapFactor: number;
    /** Global movement budget sum(w_i |x_i - x_i^0|^2); Infinity disables it. */
    movementBudget: number;
    /** Convergence: stop when the largest node gradient (per L) falls below this. */
    gradTol: number;
    /** Convergence: stop when the relative energy change falls below this. */
    energyTol: number;
    /** Container interior stiffness (relative to the unit edge-well depth). */
    containerIn: number;
    /** Container exterior stiffness. */
    containerOut: number;
    /** Interior fill factor in (0,1]: near 1 = flat floor, wall only at the edge. */
    containerFill: number;
    /** Exterior dome sharpness exponent n (the super-Gaussian uses power 2n). */
    containerDomeN: number;
    /** Cap for the interior cosh argument (bounds the pull-in force). */
    containerCoshCap: number;
    /** Interior padding and exterior margin, as a fraction of L. */
    containerPadFactor: number;
    /** Damped growth rate of a container box toward the members' bounds. */
    containerGrowRate: number;
    /** Damped shrink rate of an oversized container box. */
    containerShrinkRate: number;
    /**
     * Cross-axis for order preservation (need not be normalised); null disables
     * it. When set, the refiner keeps the reference layout's ordering of nodes
     * along this axis (so they cannot unintentionally swap sides), protecting the
     * human "mental map". A profile supplies the axis it cares about.
     */
    orderAxis: {x: number; y: number} | null;
    /** Order-penalty strength. */
    orderStrength: number;
    /** Order margin as a fraction of L: how firmly a pair must keep its side. */
    orderMarginFactor: number;
    /** Enable the restrictive-swap repair pass after the descent. */
    swaps: boolean;
    /** Max swap passes. */
    swapMaxPasses: number;
    /** Energy budget a swap may add (in well-depth units) while removing a crossing. */
    swapEnergyBudget: number;
    /**
     * If given, only these node ids may move; all others are frozen at their
     * reference position (an active set for localised repair). Empty/undefined
     * means the whole layout is free.
     */
    activeSet?: Set<string>;
}

/** The outcome of a refinement run. */
export interface RefineResult {
    positions: Record<string, {x: number; y: number}>;
    containers: Record<string, RefinedContainer>;
    iterations: number;
    energyStart: number;
    energyEnd: number;
    /** sum(w_i |x_i - x_i^0|^2): how far the layout moved from the human original. */
    movement: number;
    /** The master scale used. */
    scale: number;
}

const DEFAULTS: RefineOptions = {
    preferredDir: null,
    directionFloor: 0,
    padFactor: 0.15,
    stabilityScale: 1,
    maxIterations: 200,
    stepCapFactor: 0.1,
    movementBudget: Infinity,
    gradTol: 1e-4,
    energyTol: 1e-7,
    scale: undefined,
    containerIn: 1,
    containerOut: 1,
    containerFill: 0.7,
    containerDomeN: 2,
    containerCoshCap: 6,
    containerPadFactor: 0.15,
    containerGrowRate: 0.6,
    containerShrinkRate: 0.15,
    orderAxis: null,
    orderStrength: 1,
    orderMarginFactor: 0.1,
    swaps: false,
    swapMaxPasses: 4,
    swapEnergyBudget: 2,
    activeSet: undefined,
};

/**
 * The default options, as a fresh object (useful for tests and callers that want
 * to override a few fields).
 *
 * @returns A copy of the default options.
 */
export function defaultRefineOptions(): RefineOptions {
    return {...DEFAULTS};
}

/** A tiny regulariser so coincident nodes never divide by zero (in L units). */
const EPS_REG_FACTOR = 1e-3;

/** A mutable container box (centre + half-extents) with its member set. */
export interface ProblemContainer {
    cx: number;
    cy: number;
    hx: number;
    hy: number;
    members: Set<number>;
    fixed: boolean;
}

/** The internal, index-based problem: flat arrays for speed and testability. */
export interface Problem {
    n: number;
    px: Float64Array;
    py: Float64Array;
    px0: Float64Array;
    py0: Float64Array;
    hw: Float64Array;
    hh: Float64Array;
    wstab: Float64Array;
    fixed: boolean[];
    edges: {s: number; t: number; directed: boolean}[];
    containers: ProblemContainer[];
    l: number;
    p0: number;
    arep: number;
    padx: number;
    pady: number;
    epsReg: number;
    ux: number;
    uy: number;
    directed: boolean;
    dirFloor: number;
    cIn: number;
    cOut: number;
    cFill: number;
    cDomeN: number;
    cCap: number;
    cPad: number;
    order: {i: number; j: number; nx: number; ny: number; margin: number}[];
    kOrder: number;
}

/** The median of a numeric array (0 for an empty array). */
function median(values: number[]): number {
    if (values.length === 0) {
        return 0;
    }
    const s = [...values].sort((a, b) => a - b);
    const mid = Math.floor(s.length / 2);
    return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
}

/**
 * A small deterministic angle in [0, 2pi) from two ids, to separate exactly
 * coincident nodes without any randomness.
 *
 * @param a First stable id.
 * @param b Second stable id.
 * @returns An angle in radians.
 */
function hashAngle(a: string, b: string): number {
    const s = a < b ? a + '|' + b : b + '|' + a;
    let h = 2166136261;
    for (let i = 0; i < s.length; i++) {
        h ^= s.charCodeAt(i);
        h = Math.imul(h, 16777619);
    }
    return ((h >>> 0) / 4294967296) * 2 * Math.PI;
}

/**
 * Build the internal problem from the public inputs: derive the master scale,
 * the degree-based heights, per-node anchors, and regularise exact coincidences.
 *
 * @param nodes The nodes.
 * @param edges The edges.
 * @param opts The resolved options.
 * @returns The index-based problem.
 */
export function buildProblem(
    nodes: RefineNode[],
    edges: RefineEdge[],
    opts: RefineOptions,
    containers: RefineContainer[] = []
): Problem {
    const n = nodes.length;
    const index = new Map<string, number>();
    nodes.forEach((nd, i) => index.set(nd.stableid, i));

    const px = new Float64Array(n);
    const py = new Float64Array(n);
    const px0 = new Float64Array(n);
    const py0 = new Float64Array(n);
    const hw = new Float64Array(n);
    const hh = new Float64Array(n);
    const fixed: boolean[] = new Array(n).fill(false);
    nodes.forEach((nd, i) => {
        px[i] = nd.x; py[i] = nd.y; px0[i] = nd.x; py0[i] = nd.y;
        hw[i] = Math.max(1, nd.w / 2); hh[i] = Math.max(1, nd.h / 2);
        fixed[i] = !!nd.fixed;
    });
    // Active set: freeze every node that is not listed as active.
    if (opts.activeSet && opts.activeSet.size > 0) {
        nodes.forEach((nd, i) => {
            if (!opts.activeSet!.has(nd.stableid)) {
                fixed[i] = true;
            }
        });
    }

    const e: {s: number; t: number; directed: boolean}[] = [];
    const degree = new Int32Array(n);
    for (const edge of edges) {
        const s = index.get(edge.source);
        const tt = index.get(edge.target);
        if (s === undefined || tt === undefined || s === tt) {
            continue;
        }
        e.push({s, t: tt, directed: !!edge.directed});
        degree[s]++; degree[tt]++;
    }

    // Master scale L: median edge length, else median node extent, else a default.
    const edgeLengths = e.map(({s, t}) => Math.hypot(px[s] - px[t], py[s] - py[t])).filter(d => d > 0);
    const extents = nodes.map(nd => Math.max(nd.w, nd.h)).filter(d => d > 0);
    let l = opts.scale ?? 0;
    if (!l) {
        l = median(edgeLengths) || median(extents) || 150;
    }

    let kmax = 0;
    for (let i = 0; i < n; i++) {
        kmax = Math.max(kmax, degree[i]);
    }
    const p0 = 2 + kmax;
    const arep = 1 + kmax;

    const stabW = opts.stabilityScale / (l * l);
    const wstab = new Float64Array(n);
    nodes.forEach((nd, i) => {
        wstab[i] = (nd.stabilityWeight ?? 1) * stabW;
    });

    let ux = 0;
    let uy = 0;
    const directed = !!opts.preferredDir;
    if (opts.preferredDir) {
        const dl = Math.hypot(opts.preferredDir.x, opts.preferredDir.y) || 1;
        ux = opts.preferredDir.x / dl;
        uy = opts.preferredDir.y / dl;
    }

    const prob: Problem = {
        n, px, py, px0, py0, hw, hh, wstab, fixed, edges: e,
        containers: containers.map(c => {
            const memberSet = new Set<number>();
            for (const id of c.members) {
                const idx = index.get(id);
                if (idx !== undefined) {
                    memberSet.add(idx);
                }
            }
            return {
                cx: c.x + c.w / 2, cy: c.y + c.h / 2,
                hx: Math.max(1, c.w / 2), hy: Math.max(1, c.h / 2),
                members: memberSet, fixed: !!c.fixed,
            };
        }),
        l, p0, arep, padx: opts.padFactor * l, pady: opts.padFactor * l,
        epsReg: EPS_REG_FACTOR * l, ux, uy, directed, dirFloor: opts.directionFloor,
        cIn: opts.containerIn, cOut: opts.containerOut, cFill: opts.containerFill,
        cDomeN: opts.containerDomeN, cCap: opts.containerCoshCap, cPad: opts.containerPadFactor * l,
        order: [], kOrder: opts.orderStrength,
    };

    // Order preservation: chain adjacent nodes along the profile's cross-axis so
    // the reference layout's left/right (or cyclic-band) order is kept. Only
    // clearly-ordered pairs are constrained; ties are left free.
    if (opts.orderAxis) {
        const nl = Math.hypot(opts.orderAxis.x, opts.orderAxis.y) || 1;
        const nx = opts.orderAxis.x / nl;
        const ny = opts.orderAxis.y / nl;
        const margin = opts.orderMarginFactor * l;
        const proj = (i: number): number => px0[i] * nx + py0[i] * ny;
        const order = [...Array(n).keys()].sort((a, b) => proj(a) - proj(b));
        for (let k = 0; k + 1 < order.length; k++) {
            const a = order[k];
            const b = order[k + 1];
            if (proj(b) - proj(a) > margin) {
                prob.order.push({i: a, j: b, nx, ny, margin});
            }
        }
    }

    regulariseCoincidences(prob, nodes);
    return prob;
}

/**
 * Nudge any pair of exactly coincident nodes apart by a tiny, deterministic
 * amount along a hash-derived direction. This is numeric regularisation, not
 * initialisation: it only touches nodes that share an identical position.
 *
 * @param prob The problem (mutated in place).
 * @param nodes The nodes, for their stable ids.
 * @returns void
 */
function regulariseCoincidences(prob: Problem, nodes: RefineNode[]): void {
    const {n, px, py, l, fixed} = prob;
    const nudge = EPS_REG_FACTOR * l * 10;
    for (let i = 0; i < n; i++) {
        for (let j = i + 1; j < n; j++) {
            if (px[i] === px[j] && py[i] === py[j]) {
                const ang = hashAngle(nodes[i].stableid, nodes[j].stableid);
                const dx = Math.cos(ang) * nudge;
                const dy = Math.sin(ang) * nudge;
                if (!fixed[j]) {
                    px[j] += dx; py[j] += dy;
                } else if (!fixed[i]) {
                    px[i] -= dx; py[i] -= dy;
                }
            }
        }
    }
}

/**
 * Evaluate the total energy and, when requested, the analytic gradient.
 *
 * @param prob The problem.
 * @param grad Optional output gradient arrays [gx, gy]; zeroed and filled when given.
 * @returns The total energy.
 */
export function energyAndGradient(prob: Problem, grad?: [Float64Array, Float64Array]): number {
    const {n, px, py, px0, py0, hw, hh, wstab, edges, l, p0, arep, padx, pady, epsReg, ux, uy, directed, dirFloor} = prob;
    let gx: Float64Array | null = null;
    let gy: Float64Array | null = null;
    if (grad) {
        gx = grad[0]; gy = grad[1];
        gx.fill(0); gy.fill(0);
    }
    let e = 0;

    // Repulsion over all pairs (anisotropic, short-range).
    for (let i = 0; i < n; i++) {
        for (let j = i + 1; j < n; j++) {
            const sx = hw[i] + hw[j] + padx;
            const sy = hh[i] + hh[j] + pady;
            const uxv = (px[j] - px[i]) / sx;
            const uyv = (py[j] - py[i]) / sy;
            const rho = Math.sqrt(uxv * uxv + uyv * uyv + EPS_REG_FACTOR * EPS_REG_FACTOR);
            if (rho >= 1) {
                continue;
            }
            e += arep * repShape(rho);
            if (gx && gy) {
                const dphi = arep * repShapeDeriv(rho);
                const grxx = dphi * (uxv / sx) / rho;
                const gryy = dphi * (uyv / sy) / rho;
                gx[j] += grxx; gy[j] += gryy;
                gx[i] -= grxx; gy[i] -= gryy;
            }
        }
    }

    // Edge length (and, for directed edges, direction).
    for (const {s, t, directed: ed} of edges) {
        const dx = px[t] - px[s];
        const dy = py[t] - py[s];
        const r = Math.sqrt(dx * dx + dy * dy + epsReg * epsReg);
        const ex = dx / r;
        const ey = dy / r;
        const pt = edgePotential(r, l, p0);
        const ptd = edgePotentialDeriv(r, l, p0);
        if (directed && ed) {
            const c = ex * ux + ey * uy;
            const g = dirFactor(c, dirFloor);
            const gp = dirFactorDeriv(dirFloor);
            e += pt * g;
            if (gx && gy) {
                const gjx = g * ptd * ex + pt * gp * (ux - c * ex) / r;
                const gjy = g * ptd * ey + pt * gp * (uy - c * ey) / r;
                gx[t] += gjx; gy[t] += gjy;
                gx[s] -= gjx; gy[s] -= gjy;
            }
        } else {
            e += pt;
            if (gx && gy) {
                gx[t] += ptd * ex; gy[t] += ptd * ey;
                gx[s] -= ptd * ex; gy[s] -= ptd * ey;
            }
        }
    }

    // Stability: anchor to the reference (human) layout.
    for (let i = 0; i < n; i++) {
        const ddx = px[i] - px0[i];
        const ddy = py[i] - py0[i];
        e += wstab[i] * (ddx * ddx + ddy * ddy);
        if (gx && gy) {
            gx[i] += 2 * wstab[i] * ddx;
            gy[i] += 2 * wstab[i] * ddy;
        }
    }

    // Containers: confine members inside (cosh well), push non-members outside
    // (domed super-Gaussian). Boxes are fixed here; they are re-fitted by a
    // separate projection, not by this gradient.
    const {containers, cIn, cOut, cFill, cDomeN, cCap, cPad} = prob;
    for (const c of containers) {
        for (let i = 0; i < n; i++) {
            if (c.members.has(i)) {
                // Interior: keep the node's box inside the wall with padding.
                const effhx = Math.max(c.hx - (hw[i] + cPad), 0.2 * c.hx);
                const effhy = Math.max(c.hy - (hh[i] + cPad), 0.2 * c.hy);
                const ax = cFill * effhx;
                const ay = cFill * effhy;
                const uxc = (px[i] - c.cx) / ax;
                const uyc = (py[i] - c.cy) / ay;
                e += cIn * (softCosh(uxc, cCap) + softCosh(uyc, cCap));
                if (gx && gy) {
                    gx[i] += cIn * softCoshDeriv(uxc, cCap) / ax;
                    gy[i] += cIn * softCoshDeriv(uyc, cCap) / ay;
                }
            } else {
                // Exterior: force-free beyond the margin, domed inside.
                const bx = c.hx + hw[i] + cPad;
                const by = c.hy + hh[i] + cPad;
                const qx = (px[i] - c.cx) / bx;
                const qy = (py[i] - c.cy) / by;
                const twoN = 2 * cDomeN;
                const qx2n = Math.pow(Math.abs(qx), twoN);
                const qy2n = Math.pow(Math.abs(qy), twoN);
                const arg = qx2n + qy2n;
                if (arg > 40) {
                    continue; // exp(-arg) is negligible; force-free far field.
                }
                const val = Math.exp(-arg);
                e += cOut * val;
                if (gx && gy) {
                    // d/dxi exp(-(|qx|^{2n}+...)) = exp(...)*(-2n)|qx|^{2n-1}sgn(qx)/bx
                    const dqx = -twoN * Math.pow(Math.abs(qx), twoN - 1) * Math.sign(qx) / bx;
                    const dqy = -twoN * Math.pow(Math.abs(qy), twoN - 1) * Math.sign(qy) / by;
                    gx[i] += cOut * val * dqx;
                    gy[i] += cOut * val * dqy;
                }
            }
        }
    }

    // Order preservation: a soft penalty when a pair gets closer than the margin
    // to swapping its reference side along the cross-axis. softplus^2 is ~0 while
    // the order holds and grows smoothly as it is violated.
    const {order, kOrder} = prob;
    for (const oc of order) {
        const proj = (px[oc.j] - px[oc.i]) * oc.nx + (py[oc.j] - py[oc.i]) * oc.ny;
        const gArg = oc.margin - proj;
        const sp = softplus(gArg);
        e += kOrder * sp * sp;
        if (gx && gy) {
            const dEdproj = -2 * kOrder * sp * sigmoid(gArg);
            gx[oc.j] += dEdproj * oc.nx; gy[oc.j] += dEdproj * oc.ny;
            gx[oc.i] -= dEdproj * oc.nx; gy[oc.i] -= dEdproj * oc.ny;
        }
    }
    return e;
}

/**
 * Minimal, damped container fit: adjust each box just enough to hold its members
 * with padding. Growth to contain members is immediate (a hard floor); shrinking
 * an oversized box is damped and slow. Non-members are never considered, so a
 * foreign node never shrinks a container — it is pushed out by the dome instead.
 *
 * @param prob The problem (container boxes are updated in place).
 * @param opts The resolved options.
 * @returns void
 */
export function fitContainers(prob: Problem, opts: RefineOptions): void {
    const {px, py, hw, hh, containers, cPad} = prob;
    const grow = opts.containerGrowRate;
    const shrink = opts.containerShrinkRate;

    for (const c of containers) {
        if (c.fixed || c.members.size === 0) {
            continue;
        }
        let reqL = Infinity;
        let reqR = -Infinity;
        let reqT = Infinity;
        let reqB = -Infinity;
        for (const i of c.members) {
            reqL = Math.min(reqL, px[i] - hw[i] - cPad);
            reqR = Math.max(reqR, px[i] + hw[i] + cPad);
            reqT = Math.min(reqT, py[i] - hh[i] - cPad);
            reqB = Math.max(reqB, py[i] + hh[i] + cPad);
        }

        const curL = c.cx - c.hx;
        const curR = c.cx + c.hx;
        const curT = c.cy - c.hy;
        const curB = c.cy + c.hy;

        const blend = (cur: number, req: number, outward: boolean): number => {
            const needOut = outward ? req < cur : req > cur;
            const rate = needOut ? grow : shrink;
            return cur + rate * (req - cur);
        };
        let newL = blend(curL, reqL, true);
        let newR = blend(curR, reqR, false);
        let newT = blend(curT, reqT, true);
        let newB = blend(curB, reqB, false);
        newL = Math.min(newL, reqL);
        newR = Math.max(newR, reqR);
        newT = Math.min(newT, reqT);
        newB = Math.max(newB, reqB);

        c.cx = (newL + newR) / 2;
        c.cy = (newT + newB) / 2;
        c.hx = Math.max(1, (newR - newL) / 2);
        c.hy = Math.max(1, (newB - newT) / 2);
    }
}

/** The refined geometry of a container after fitting (top-left x/y + w/h). */
export interface RefinedContainer {
    x: number;
    y: number;
    w: number;
    h: number;
}

/** 2D cross product of (u) and (v). */
function cross2(ux: number, uy: number, vx: number, vy: number): number {
    return ux * vy - uy * vx;
}

/**
 * Whether two open segments a-b and c-d properly cross (shared endpoints do not
 * count as a crossing).
 *
 * @param ax First segment start x.
 * @param ay First segment start y.
 * @param bx First segment end x.
 * @param by First segment end y.
 * @param cx Second segment start x.
 * @param cy Second segment start y.
 * @param dx Second segment end x.
 * @param dy Second segment end y.
 * @returns True if they properly cross.
 */
function segmentsCross(
    ax: number, ay: number, bx: number, by: number,
    cx: number, cy: number, dx: number, dy: number
): boolean {
    const d1 = cross2(dx - cx, dy - cy, ax - cx, ay - cy);
    const d2 = cross2(dx - cx, dy - cy, bx - cx, by - cy);
    const d3 = cross2(bx - ax, by - ay, cx - ax, cy - ay);
    const d4 = cross2(bx - ax, by - ay, dx - ax, dy - ay);
    return ((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) &&
        ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0));
}

/**
 * Count the properly-crossing edge pairs in the current layout.
 *
 * @param prob The problem.
 * @returns The number of crossings.
 */
export function countCrossings(prob: Problem): number {
    const {px, py, edges} = prob;
    let count = 0;
    for (let a = 0; a < edges.length; a++) {
        for (let b = a + 1; b < edges.length; b++) {
            const e1 = edges[a];
            const e2 = edges[b];
            if (e1.s === e2.s || e1.s === e2.t || e1.t === e2.s || e1.t === e2.t) {
                continue; // share an endpoint
            }
            if (segmentsCross(
                px[e1.s], py[e1.s], px[e1.t], py[e1.t],
                px[e2.s], py[e2.s], px[e2.t], py[e2.t]
            )) {
                count++;
            }
        }
    }
    return count;
}

/**
 * Restrictive swaps: a last-resort local repair. For each crossing, try swapping
 * two eligible nodes (not fixed, not directly connected, not already at the same
 * position) and keep the swap only if it strictly reduces crossings without
 * raising the energy beyond a small budget. This is deliberately conservative so
 * it never reshuffles a human layout for a marginal gain.
 *
 * @param prob The problem (positions are updated in place).
 * @param opts The resolved options.
 * @returns The number of swaps applied.
 */
export function restrictiveSwaps(prob: Problem, opts: RefineOptions): number {
    const {px, py, fixed, edges} = prob;
    const connected = new Set<string>();
    for (const e of edges) {
        connected.add(e.s + ':' + e.t);
        connected.add(e.t + ':' + e.s);
    }
    const eligible = (p: number, q: number): boolean =>
        p !== q && !fixed[p] && !fixed[q] && !connected.has(p + ':' + q);

    let applied = 0;
    for (let pass = 0; pass < opts.swapMaxPasses; pass++) {
        const baseCross = countCrossings(prob);
        if (baseCross === 0) {
            break;
        }
        const baseEnergy = energyAndGradient(prob);

        // Collect the endpoints of crossing edge pairs as swap candidates.
        const candidates = new Set<number>();
        for (let a = 0; a < edges.length; a++) {
            for (let b = a + 1; b < edges.length; b++) {
                const e1 = edges[a];
                const e2 = edges[b];
                if (e1.s === e2.s || e1.s === e2.t || e1.t === e2.s || e1.t === e2.t) {
                    continue;
                }
                if (segmentsCross(
                    px[e1.s], py[e1.s], px[e1.t], py[e1.t],
                    px[e2.s], py[e2.s], px[e2.t], py[e2.t]
                )) {
                    candidates.add(e1.s); candidates.add(e1.t);
                    candidates.add(e2.s); candidates.add(e2.t);
                }
            }
        }
        const list = [...candidates].sort((a, b) => a - b);

        let best: {p: number; q: number; cross: number; energy: number} | null = null;
        for (let ii = 0; ii < list.length; ii++) {
            for (let jj = ii + 1; jj < list.length; jj++) {
                const p = list[ii];
                const q = list[jj];
                if (!eligible(p, q)) {
                    continue;
                }
                const tpx = px[p];
                const tpy = py[p];
                px[p] = px[q]; py[p] = py[q];
                px[q] = tpx; py[q] = tpy;
                const nc = countCrossings(prob);
                const ne = energyAndGradient(prob);
                px[q] = px[p]; py[q] = py[p];
                px[p] = tpx; py[p] = tpy;
                if (nc < baseCross && ne <= baseEnergy + opts.swapEnergyBudget) {
                    if (best === null || nc < best.cross || (nc === best.cross && ne < best.energy)) {
                        best = {p, q, cross: nc, energy: ne};
                    }
                }
            }
        }

        if (!best) {
            break;
        }
        const tpx = px[best.p];
        const tpy = py[best.p];
        px[best.p] = px[best.q]; py[best.p] = py[best.q];
        px[best.q] = tpx; py[best.q] = tpy;
        applied++;
    }
    return applied;
}

/**
 * Run the preservation-first descent to convergence and return the refined
 * positions. Monotone: the energy never increases; the layout never moves
 * beyond the budget.
 *
 * @param nodes The nodes at their current (human) positions.
 * @param edges The edges.
 * @param options Optional overrides.
 * @returns The refined positions and run diagnostics.
 */
export function refineLayout(
    nodes: RefineNode[],
    edges: RefineEdge[],
    options: Partial<RefineOptions> = {},
    containers: RefineContainer[] = []
): RefineResult {
    const opts: RefineOptions = {...DEFAULTS, ...options};
    const prob = buildProblem(nodes, edges, opts, containers);
    const {n, px, py, px0, py0, wstab, fixed, l} = prob;

    const containerGeom = (): Record<string, RefinedContainer> => {
        const out: Record<string, RefinedContainer> = {};
        containers.forEach((c, i) => {
            const pc = prob.containers[i];
            out[c.stableid] = {x: pc.cx - pc.hx, y: pc.cy - pc.hy, w: pc.hx * 2, h: pc.hy * 2};
        });
        return out;
    };

    const emptyResult = (): RefineResult => ({
        positions: Object.fromEntries(nodes.map((nd, i) => [nd.stableid, {x: px[i], y: py[i]}])),
        containers: containerGeom(),
        iterations: 0, energyStart: 0, energyEnd: 0, movement: 0, scale: l,
    });
    if (n === 0) {
        return emptyResult();
    }

    const gx = new Float64Array(n);
    const gy = new Float64Array(n);
    const grad: [Float64Array, Float64Array] = [gx, gy];
    const trialX = new Float64Array(n);
    const trialY = new Float64Array(n);

    const stepCap = opts.stepCapFactor * l;
    const gradTolAbs = opts.gradTol / l; // gradient has units 1/L; compare per L.

    const energyStart = energyAndGradient(prob, grad);

    /** One bounded descent pass; returns the final energy and iteration count. */
    const descend = (maxIter: number): {energy: number; iters: number} => {
        let energy = energyAndGradient(prob, grad);
        let iters = 0;
        let alpha0 = stepCap;
        for (; iters < maxIter; iters++) {
            energyAndGradient(prob, grad);
            let gmax = 0;
            for (let i = 0; i < n; i++) {
                if (fixed[i]) {
                    continue;
                }
                gmax = Math.max(gmax, Math.hypot(gx[i], gy[i]));
            }
            if (gmax < gradTolAbs || gmax === 0) {
                break;
            }
            let alpha = alpha0 / gmax;
            let accepted = false;
            let newEnergy = energy;
            for (let ls = 0; ls < 30; ls++) {
                for (let i = 0; i < n; i++) {
                    if (fixed[i]) {
                        trialX[i] = px[i]; trialY[i] = py[i];
                        continue;
                    }
                    let sx = -alpha * gx[i];
                    let sy = -alpha * gy[i];
                    const sl = Math.hypot(sx, sy);
                    if (sl > stepCap) {
                        sx = (sx / sl) * stepCap;
                        sy = (sy / sl) * stepCap;
                    }
                    trialX[i] = px[i] + sx;
                    trialY[i] = py[i] + sy;
                }
                newEnergy = energyAt(prob, trialX, trialY);
                if (newEnergy < energy) {
                    accepted = true;
                    break;
                }
                alpha *= 0.5;
            }
            if (!accepted) {
                break;
            }
            px.set(trialX);
            py.set(trialY);
            let mv = 0;
            for (let i = 0; i < n; i++) {
                const ddx = px[i] - px0[i];
                const ddy = py[i] - py0[i];
                mv += wstab[i] * (ddx * ddx + ddy * ddy);
            }
            const relChange = Math.abs(energy - newEnergy) / (Math.abs(energy) + 1e-12);
            energy = newEnergy;
            if (mv >= opts.movementBudget || relChange < opts.energyTol) {
                iters++;
                break;
            }
            alpha0 = Math.min(stepCap, alpha0 * 1.1);
        }
        return {energy, iters};
    };

    const main = descend(opts.maxIterations);
    let energy = main.energy;
    let iterations = main.iters;

    // Optional last-resort crossing repair, then a short settle pass.
    if (opts.swaps && restrictiveSwaps(prob, opts) > 0) {
        const settle = descend(Math.max(20, Math.floor(opts.maxIterations / 4)));
        energy = settle.energy;
        iterations += settle.iters;
    }

    let movement = 0;
    for (let i = 0; i < n; i++) {
        const ddx = px[i] - px0[i];
        const ddy = py[i] - py0[i];
        movement += wstab[i] * (ddx * ddx + ddy * ddy);
    }

    // Project the container boxes onto a minimal fit around their final members.
    fitContainers(prob, opts);

    return {
        positions: Object.fromEntries(nodes.map((nd, i) => [nd.stableid, {x: px[i], y: py[i]}])),
        containers: containerGeom(),
        iterations, energyStart, energyEnd: energy, movement, scale: l,
    };
}

/**
 * Energy at a trial position set, without touching the problem's own arrays.
 *
 * @param prob The problem.
 * @param tx Trial x positions.
 * @param ty Trial y positions.
 * @returns The energy.
 */
function energyAt(prob: Problem, tx: Float64Array, ty: Float64Array): number {
    const savedX = prob.px;
    const savedY = prob.py;
    (prob as {px: Float64Array}).px = tx;
    (prob as {py: Float64Array}).py = ty;
    const e = energyAndGradient(prob);
    (prob as {px: Float64Array}).px = savedX;
    (prob as {py: Float64Array}).py = savedY;
    return e;
}
