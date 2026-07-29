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

namespace mod_vimipad\local\service;

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\submission;
use stdClass;

/**
 * Bridges snapshots and the automatic scorers.
 *
 * Turns a stored snapshot into a scorer-ready submission and runs the activity's
 * chosen scorer against the reference solution the teacher has marked. The result
 * is always a suggestion for the grader, never a stored grade.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assess_service {
    /**
     * Build a scorer submission from a stored snapshot.
     *
     * @param int $snapshotid The snapshot id.
     * @return submission|null The submission, or null if the snapshot has no usable map.
     */
    public function submission_from_snapshot(int $snapshotid): ?submission {
        global $DB;

        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => $snapshotid]);
        if (!$snapshot || $snapshot->snapshotjson === null) {
            return null;
        }
        $data = json_decode($snapshot->snapshotjson, true);
        if (!is_array($data)) {
            return null;
        }
        return submission::from_snapshot_data($data);
    }

    /**
     * Score a snapshot against the activity's marked reference solution.
     *
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The snapshot to score.
     * @param string $scorerkey The scorer subplugin key.
     * @return result|null The suggestion, or null if scoring is not possible.
     */
    public function score(stdClass $instance, int $snapshotid, string $scorerkey = 'reference'): ?result {
        if (empty($instance->referencesnapshotid)) {
            return null;
        }
        if ((int) $instance->referencesnapshotid === $snapshotid) {
            // The reference is not scored against itself.
            return null;
        }
        $scorer = registry::get($scorerkey);
        if ($scorer === null) {
            return null;
        }
        $submission = $this->submission_from_snapshot($snapshotid);
        $reference = $this->submission_from_snapshot((int) $instance->referencesnapshotid);
        if ($submission === null || $reference === null) {
            return null;
        }
        if (!$scorer->supports_profile($submission->profile)) {
            return null;
        }
        return $scorer->score($submission, [$reference], new exact_matcher());
    }
}
