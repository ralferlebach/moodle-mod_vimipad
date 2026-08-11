<?php
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

namespace mod_vimipad\local;

use mod_vimipad\local\service\snapshot_service;

/**
 * The submission lifecycle as a validated state machine.
 *
 * A snapshot moves through draft, submitted, in review, graded and returned,
 * and can be reopened from the submitted state onward. This class is the single
 * source of truth for which moves are legal and for the human-readable phase
 * label; it holds no state itself, so it is safe to use from any context.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class snapshot_phase {
    /**
     * The phases in lifecycle order.
     *
     * @return int[] The status constants, ordered from draft to returned.
     */
    public static function all(): array {
        return [
            snapshot_service::STATUS_DRAFT,
            snapshot_service::STATUS_SUBMITTED,
            snapshot_service::STATUS_INREVIEW,
            snapshot_service::STATUS_GRADED,
            snapshot_service::STATUS_RETURNED,
        ];
    }

    /**
     * The legal target phases reachable in one step from a given phase.
     *
     * @param int $from A status constant.
     * @return int[] The status constants reachable directly from $from.
     */
    public static function next_phases(int $from): array {
        $graph = [
            snapshot_service::STATUS_DRAFT => [
                snapshot_service::STATUS_SUBMITTED,
            ],
            snapshot_service::STATUS_SUBMITTED => [
                snapshot_service::STATUS_INREVIEW,
                snapshot_service::STATUS_GRADED,
                snapshot_service::STATUS_REOPENED,
            ],
            snapshot_service::STATUS_INREVIEW => [
                snapshot_service::STATUS_GRADED,
                snapshot_service::STATUS_REOPENED,
            ],
            snapshot_service::STATUS_GRADED => [
                snapshot_service::STATUS_RETURNED,
                snapshot_service::STATUS_REOPENED,
            ],
            snapshot_service::STATUS_RETURNED => [
                snapshot_service::STATUS_REOPENED,
            ],
            snapshot_service::STATUS_REOPENED => [
                snapshot_service::STATUS_SUBMITTED,
            ],
        ];
        return $graph[$from] ?? [];
    }

    /**
     * Whether moving from one phase to another in one step is legal.
     *
     * A no-op move to the same phase is not a transition and returns false.
     *
     * @param int $from The current status constant.
     * @param int $to The desired status constant.
     * @return bool True if the move is allowed.
     */
    public static function can_transition(int $from, int $to): bool {
        return in_array($to, self::next_phases($from), true);
    }

    /**
     * Whether a status value is one this model knows.
     *
     * @param int $status A candidate status constant.
     * @return bool True if it is a known phase (including reopened).
     */
    public static function is_valid(int $status): bool {
        return in_array($status, self::all(), true)
            || $status === snapshot_service::STATUS_REOPENED;
    }

    /**
     * Whether a phase has no onward moves except reopening.
     *
     * @param int $status A status constant.
     * @return bool True when the only way out is a reopen.
     */
    public static function is_terminal(int $status): bool {
        $onward = self::next_phases($status);
        return $onward === [snapshot_service::STATUS_REOPENED];
    }

    /**
     * The localised label for a phase.
     *
     * @param int $status A status constant.
     * @return string The human-readable phase name.
     */
    public static function label(int $status): string {
        $keys = [
            snapshot_service::STATUS_REOPENED => 'phase_reopened',
            snapshot_service::STATUS_DRAFT => 'phase_draft',
            snapshot_service::STATUS_SUBMITTED => 'phase_submitted',
            snapshot_service::STATUS_INREVIEW => 'phase_inreview',
            snapshot_service::STATUS_GRADED => 'phase_graded',
            snapshot_service::STATUS_RETURNED => 'phase_returned',
        ];
        $key = $keys[$status] ?? null;
        return $key === null ? (string) $status : get_string($key, 'mod_vimipad');
    }
}
