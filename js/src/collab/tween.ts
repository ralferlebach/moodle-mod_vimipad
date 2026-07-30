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
 * Position tweening.
 *
 * When a collaborator's node moves, the poll delivers its new (final) position.
 * Instead of snapping, the client eases the node from its current position to
 * the new one over slightly more than one poll period, so movement reads as
 * gliding rather than jumping. Pure and deterministic for unit testing.
 *
 * @module     mod_vimipad/collab/tween
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Point} from '../types';

/** An in-progress tween between two points. */
export interface Tween {
    from: Point;
    to: Point;
    /** Duration in milliseconds. */
    durationMs: number;
    /** Start timestamp in milliseconds (same clock passed to sampleTween). */
    startMs: number;
}

/**
 * Ease-in-out cubic. Maps progress 0..1 to eased 0..1 with a smooth start/stop.
 *
 * @param p Linear progress in [0, 1].
 * @returns Eased progress in [0, 1].
 */
const easeInOut = (p: number): number =>
    p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;

/**
 * Create a tween description.
 *
 * @param from Start position.
 * @param to End position.
 * @param durationMs Duration in milliseconds.
 * @param startMs Start timestamp.
 * @returns The tween.
 */
export function createTween(from: Point, to: Point, durationMs: number, startMs: number): Tween {
    return {from, to, durationMs, startMs};
}

/**
 * Eased interpolation of a scalar.
 *
 * @param from Start value.
 * @param to End value.
 * @param progress Linear progress in [0, 1].
 * @returns The eased value.
 */
export function tweenValue(from: number, to: number, progress: number): number {
    const clamped = Math.min(1, Math.max(0, progress));
    return from + (to - from) * easeInOut(clamped);
}

/**
 * Sample a tween at a given time.
 *
 * @param tween The tween.
 * @param nowMs The current timestamp (same clock as startMs).
 * @returns The interpolated position, clamped to [from, to].
 */
export function sampleTween(tween: Tween, nowMs: number): Point {
    if (tween.durationMs <= 0) {
        return {x: tween.to.x, y: tween.to.y};
    }
    const progress = (nowMs - tween.startMs) / tween.durationMs;
    return {
        x: tweenValue(tween.from.x, tween.to.x, progress),
        y: tweenValue(tween.from.y, tween.to.y, progress),
    };
}
