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
 * Adaptive polling interval logic.
 *
 * Pure and side-effect free so it can be unit tested deterministically. The
 * client measures each poll's round-trip time and whether it returned changes,
 * then adjusts the next interval: quiet or slow responses lengthen it (up to
 * max), fresh activity shortens it (down to min). This keeps things lively when
 * collaborators are active and light on the server when they are not.
 *
 * @module     mod_vimipad/collab/adaptive
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Configuration for the adaptive interval, sourced from plugin settings. */
export interface AdaptiveConfig {
    /** Minimum interval in milliseconds. */
    min: number;
    /** Maximum interval in milliseconds. */
    max: number;
    /** Base/default interval in milliseconds. */
    base: number;
    /** Whether adaptation is enabled; if false, base is always used. */
    adaptive: boolean;
}

/** Outcome of a completed poll, feeding the next interval decision. */
export interface PollOutcome {
    /** Whether the poll returned any changes (operations or lease changes). */
    changed: boolean;
    /** Measured round-trip time of the poll request, in milliseconds. */
    rttMs: number;
}

/** Factor applied when lengthening the interval on a quiet poll. */
const GROWTH = 1.5;

/** Factor applied when shortening the interval on activity. */
const SHRINK = 0.5;

/** RTT above this (ms) is treated as server strain and lengthens the interval. */
const HIGH_RTT_MS = 1500;

/**
 * Clamp a value into the configured [min, max] range.
 *
 * @param value The value to clamp.
 * @param cfg The adaptive configuration.
 * @returns The clamped value.
 */
const clamp = (value: number, cfg: AdaptiveConfig): number =>
    Math.min(cfg.max, Math.max(cfg.min, value));

/**
 * Compute the next polling interval from the current one and the last outcome.
 *
 * @param cfg The adaptive configuration.
 * @param current The current interval in milliseconds.
 * @param outcome The outcome of the poll that just completed.
 * @returns The next interval in milliseconds.
 */
export function nextInterval(cfg: AdaptiveConfig, current: number, outcome: PollOutcome): number {
    if (!cfg.adaptive) {
        return cfg.base;
    }

    // High latency signals server strain: back off regardless of changes.
    if (outcome.rttMs >= HIGH_RTT_MS) {
        return clamp(current * GROWTH, cfg);
    }

    // Fresh activity: shorten toward min so collaborators feel responsive.
    if (outcome.changed) {
        return clamp(current * SHRINK, cfg);
    }

    // Quiet poll: lengthen toward max to spare the server.
    return clamp(current * GROWTH, cfg);
}
