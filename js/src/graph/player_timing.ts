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
 * Timing helpers for the revision player. Kept dependency-free so the speed
 * arithmetic can be unit-tested without React or a browser.
 *
 * @module mod_vimipad/graph/player_timing
 */

/** The playback speeds the player offers, as multipliers of real time. */
export const PLAYBACK_SPEEDS = [1, 10, 100] as const;

/** A supported playback speed. */
export type PlaybackSpeed = (typeof PLAYBACK_SPEEDS)[number];

/**
 * The delay, in milliseconds, between advancing one revision at a given speed.
 * Higher speeds shorten the delay proportionally, with a 1 ms floor so the
 * timer always makes forward progress.
 *
 * @param baseMs The delay at 1x speed.
 * @param speed The speed multiplier (e.g. 1, 10, 100).
 * @returns The per-step delay in milliseconds, at least 1.
 */
export function stepDelayMs(baseMs: number, speed: number): number {
    const safespeed = speed > 0 ? speed : 1;
    return Math.max(1, Math.round(baseMs / safespeed));
}

/**
 * Normalise an arbitrary number to the nearest supported playback speed,
 * defaulting to 1x when the value is not one of the offered speeds.
 *
 * @param speed A candidate speed.
 * @returns A supported playback speed.
 */
export function normaliseSpeed(speed: number): PlaybackSpeed {
    return (PLAYBACK_SPEEDS as readonly number[]).includes(speed) ? (speed as PlaybackSpeed) : 1;
}
