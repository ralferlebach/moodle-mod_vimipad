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

        // Server-side grade domain: 0 <= grade <= activity maximum (points
        // grading only). UI limits are not a contract.
        if ($grade !== null) {
            $max = (float) $instance->grade;
            if ($max <= 0 || $grade < 0 || $grade > $max) {
                throw new \moodle_exception('error:gradeoutofrange', 'mod_vimipad', '', format_float($max, 2));
            }
        }

        $cm = get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // The grade goes to the cohort frozen at submission time; membership
        // changes after submitting do not shift it. Legacy snapshots without a
        // stored cohort fall back to resolving the current membership.
        $recipients = null;
        $cohortjson = $DB->get_field('vimipad_snapshot', 'cohortjson', ['id' => $snapshotid]);
        if ($cohortjson !== false && $cohortjson !== null && $cohortjson !== '') {
            $decoded = json_decode($cohortjson, true);
            if (is_array($decoded) && $decoded !== []) {
                $recipients = array_map('intval', array_values($decoded));
            }
        }
        if ($recipients === null) {
            $recipients = $this->resolve_recipients($workspace, $context);
        }
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

        $eventdata = ['context' => $context, 'objectid' => (int) $snapshotid, 'userid' => $graderid];
        if (!empty($workspace->userid)) {
            $eventdata['relateduserid'] = (int) $workspace->userid;
        }
        \mod_vimipad\event\snapshot_graded::create($eventdata)->trigger();
    }

    /**
     * Resolve which users a grade applies to for a workspace.
     *
     * @param stdClass $workspace The workspace record.
     * @param \context_module $context The activity context.
     * @return int[] The recipient user ids.
     */
    public function resolve_recipients(stdClass $workspace, \context_module $context): array {
        if (!empty($workspace->userid)) {
            return [(int) $workspace->userid];
        }
        if (!empty($workspace->groupid)) {
            $members = groups_get_members((int) $workspace->groupid, 'u.id');
            return array_map('intval', array_keys($members));
        }
        // Course-wide shared workspace: the grade applies to every participant
        // who may submit to the activity.
        $users = get_enrolled_users($context, 'mod/vimipad:submit', 0, 'u.id');
        return array_map('intval', array_keys($users));
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

    /**
     * The grade and feedback shown to a learner for their own submission.
     *
     * Returns the learner's grade row (grade, feedback text, the graded
     * snapshot id — with its revision and workspace so the assessed map can be
     * shown — and when it was graded), or null when the learner has no graded
     * submission yet. In group mode the grade is stored per member, so the
     * member's own row is returned.
     *
     * @param stdClass $instance The activity instance.
     * @param int $userid The learner.
     * @return stdClass|null The feedback record, or null if not graded.
     */
    public function get_feedback_for_user(stdClass $instance, int $userid): ?stdClass {
        global $DB;

        $row = $DB->get_record('vimipad_grade', ['vimipadid' => $instance->id, 'userid' => $userid]);
        if (!$row || $row->grade === null) {
            return null;
        }
        // If the graded snapshot is known, resolve its revision and workspace so
        // the learner can view the exact map that was assessed.
        $snapshotrevision = null;
        $snapshotworkspaceid = null;
        if ($row->snapshotid !== null) {
            $snap = $DB->get_record(
                'vimipad_snapshot',
                ['id' => (int) $row->snapshotid],
                'id, revision, workspaceid',
                IGNORE_MISSING
            );
            if ($snap) {
                $snapshotrevision = (int) $snap->revision;
                $snapshotworkspaceid = (int) $snap->workspaceid;
            }
        }
        return (object) [
            'grade' => (float) $row->grade,
            'grademax' => (float) $instance->grade,
            'feedback' => (string) $row->feedback,
            'feedbackformat' => (int) $row->feedbackformat,
            'snapshotid' => $row->snapshotid !== null ? (int) $row->snapshotid : null,
            'snapshotrevision' => $snapshotrevision,
            'snapshotworkspaceid' => $snapshotworkspaceid,
            'dategraded' => (int) $row->timemodified,
        ];
    }
}
