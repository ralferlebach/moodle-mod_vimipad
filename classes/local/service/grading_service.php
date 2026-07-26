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

use stdClass;

/**
 * Stores per-user grades and feedback and pushes them to the gradebook.
 *
 * The vimipad_grade table is the plugin's source of truth; vimipad_update_grades
 * reads it and calls the gradebook. In group mode the grade applies to every
 * member of the owning group. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_service {
    /**
     * Save a grade for the recipients of a submitted snapshot and update the
     * gradebook and snapshot status.
     *
     * @param stdClass $instance The vimipad instance.
     * @param stdClass $workspace The graded workspace.
     * @param int $snapshotid The graded snapshot id.
     * @param float|null $grade The raw grade, or null to clear.
     * @param string $feedback Overall feedback text.
     * @param int $feedbackformat The feedback text format.
     * @param int $graderid The grading user id.
     * @return void
     */
    public function save_grade(
        stdClass $instance,
        stdClass $workspace,
        int $snapshotid,
        ?float $grade,
        string $feedback,
        int $feedbackformat,
        int $graderid
    ): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/vimipad/lib.php');

        $recipients = $this->resolve_recipients($workspace);
        $now = time();

        foreach ($recipients as $userid) {
            $existing = $DB->get_record(
                'vimipad_grade',
                ['vimipadid' => $instance->id, 'userid' => $userid]
            );
            $record = (object) [
                'vimipadid' => $instance->id,
                'userid' => $userid,
                'grade' => $grade,
                'feedback' => $feedback,
                'feedbackformat' => $feedbackformat,
                'snapshotid' => $snapshotid,
                'grader' => $graderid,
                'timemodified' => $now,
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('vimipad_grade', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('vimipad_grade', $record);
            }

            vimipad_update_grades($instance, $userid);
        }

        $snapshotservice = new snapshot_service();
        $snapshotservice->set_status($snapshotid, snapshot_service::STATUS_GRADED);
    }

    /**
     * Resolve which users a grade applies to for a workspace.
     *
     * @param stdClass $workspace The workspace record.
     * @return int[] The recipient user ids.
     */
    private function resolve_recipients(stdClass $workspace): array {
        if (!empty($workspace->userid)) {
            return [(int) $workspace->userid];
        }
        if (!empty($workspace->groupid)) {
            $members = groups_get_members((int) $workspace->groupid, 'u.id');
            return array_map('intval', array_keys($members));
        }
        // Course-wide workspace: no automatic grade recipient in this milestone.
        return [];
    }

    /**
     * Read the stored grade for a user, in the gradebook shape.
     *
     * @param stdClass $instance The vimipad instance.
     * @param int $userid The user id, or 0 for all users.
     * @return array<int,stdClass> Grade objects keyed by user id.
     */
    public function get_user_grades(stdClass $instance, int $userid = 0): array {
        global $DB;

        $params = ['vimipadid' => $instance->id];
        if ($userid) {
            $params['userid'] = $userid;
        }
        $rows = $DB->get_records('vimipad_grade', $params);

        $grades = [];
        foreach ($rows as $row) {
            if ($row->grade === null) {
                continue;
            }
            $grades[(int) $row->userid] = (object) [
                'userid' => (int) $row->userid,
                'rawgrade' => (float) $row->grade,
                'feedback' => $row->feedback,
                'feedbackformat' => (int) $row->feedbackformat,
                'dategraded' => (int) $row->timemodified,
            ];
        }
        return $grades;
    }
}
