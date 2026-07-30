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
 * Group-consensus submission as an explicit state machine.
 *
 * The state is derived from existing data, so no extra schema is needed:
 *  - submitted: the workspace is locked;
 *  - voting:    at least one member has confirmed (a submit intent exists);
 *  - open:      no confirmations yet.
 *
 * Starting records the initiator's confirmation (open -> voting); confirming
 * records a member's confirmation and, once every submitting member has
 * confirmed, finalises the snapshot (voting -> submitted); cancelling clears all
 * confirmations (voting -> open).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consensus_service {
    /** @var int No submission process is under way. */
    public const STATE_OPEN = 0;

    /** @var int A submission process is collecting confirmations. */
    public const STATE_VOTING = 1;

    /** @var int The map has been submitted (workspace locked). */
    public const STATE_SUBMITTED = 2;

    /**
     * Derive the consensus state of a workspace.
     *
     * @param stdClass $workspace The group workspace record.
     * @return int One of the STATE_* constants.
     */
    public function state(stdClass $workspace): int {
        global $DB;

        if ((int) $workspace->locked === 1) {
            return self::STATE_SUBMITTED;
        }
        $hasintent = $DB->record_exists('vimipad_submissionintent', ['workspaceid' => (int) $workspace->id]);
        return $hasintent ? self::STATE_VOTING : self::STATE_OPEN;
    }

    /**
     * Start the submission process: records the initiator's confirmation.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $userid The initiating user id.
     * @return array The status after starting (see get_status()).
     * @throws \moodle_exception If consensus does not apply or is already under way.
     */
    public function start(stdClass $instance, stdClass $workspace, \context $context, int $userid): array {
        $this->require_consensus($instance, $workspace);
        $this->require_member($workspace, $context, $userid);
        if ($this->state($workspace) !== self::STATE_OPEN) {
            throw new \moodle_exception('error:consensusnotopen', 'mod_vimipad');
        }
        return $this->record_and_maybe_finalize($instance, $workspace, $context, $userid);
    }

    /**
     * Confirm the submission for the acting member; finalises once all have.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $userid The confirming user id.
     * @return array The status after confirming (see get_status()).
     * @throws \moodle_exception If no process is under way.
     */
    public function confirm(stdClass $instance, stdClass $workspace, \context $context, int $userid): array {
        $this->require_consensus($instance, $workspace);
        $this->require_member($workspace, $context, $userid);
        if ($this->state($workspace) !== self::STATE_VOTING) {
            throw new \moodle_exception('error:consensusnotvoting', 'mod_vimipad');
        }
        return $this->record_and_maybe_finalize($instance, $workspace, $context, $userid);
    }

    /**
     * Cancel the submission process, clearing all confirmations.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $userid The acting user id.
     * @return void
     * @throws \moodle_exception If no process is under way.
     */
    public function cancel(stdClass $instance, stdClass $workspace, \context $context, int $userid): void {
        global $DB;

        $this->require_consensus($instance, $workspace);
        $this->require_member($workspace, $context, $userid);
        if ($this->state($workspace) !== self::STATE_VOTING) {
            throw new \moodle_exception('error:consensusnotvoting', 'mod_vimipad');
        }
        $DB->delete_records('vimipad_submissionintent', ['workspaceid' => (int) $workspace->id]);
    }

    /**
     * The full consensus status: state and per-member confirmation.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @return array The status: state, members (userid + confirmed), startedby, timestarted.
     */
    public function get_status(stdClass $instance, stdClass $workspace, \context $context): array {
        global $DB;

        $required = (new snapshot_service())->consensus_required_userids($workspace, $context);
        $intents = $DB->get_records(
            'vimipad_submissionintent',
            ['workspaceid' => (int) $workspace->id],
            'timecreated ASC'
        );
        $confirmedids = array_map(static fn($intent) => (int) $intent->userid, array_values($intents));

        $members = [];
        foreach ($required as $memberid) {
            $members[] = ['userid' => $memberid, 'confirmed' => in_array($memberid, $confirmedids, true)];
        }

        $startedby = 0;
        $timestarted = 0;
        if (!empty($intents)) {
            $first = reset($intents);
            $startedby = (int) $first->userid;
            $timestarted = (int) $first->timecreated;
        }

        return [
            'state' => $this->state($workspace),
            'members' => $members,
            'startedby' => $startedby,
            'timestarted' => $timestarted,
        ];
    }

    /**
     * Record the acting member's confirmation and finalise if everyone has.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $userid The acting user id.
     * @return array The status after recording.
     * @throws \moodle_exception On a closed cut-off or an already-submitted map.
     */
    private function record_and_maybe_finalize(
        stdClass $instance,
        stdClass $workspace,
        \context $context,
        int $userid
    ): array {
        global $DB;

        $snapshotservice = new snapshot_service();
        [$lock, $fresh] = $snapshotservice->begin_submission($instance, $workspace);

        try {
            if (
                !$DB->record_exists('vimipad_submissionintent', [
                'workspaceid' => (int) $fresh->id, 'userid' => $userid,
                ])
            ) {
                $DB->insert_record('vimipad_submissionintent', (object) [
                    'workspaceid' => (int) $fresh->id, 'userid' => $userid, 'timecreated' => time(),
                ]);
            }

            $required = $snapshotservice->consensus_required_userids($fresh, $context);
            $have = $DB->get_fieldset_select(
                'vimipad_submissionintent',
                'userid',
                'workspaceid = :wid',
                ['wid' => (int) $fresh->id]
            );
            $remaining = array_diff($required, array_map('intval', $have));

            if (empty($remaining)) {
                $snapshot = $snapshotservice->finalize($instance, $fresh, $userid);
                return ['state' => self::STATE_SUBMITTED, 'snapshotid' => (int) $snapshot->id, 'pending' => 0];
            }

            return ['state' => self::STATE_VOTING, 'snapshotid' => 0, 'pending' => count($remaining)];
        } finally {
            $lock->release();
        }
    }

    /**
     * Ensure the activity actually uses group-consensus submission.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The workspace.
     * @return void
     * @throws \moodle_exception If consensus does not apply.
     */
    private function require_consensus(stdClass $instance, stdClass $workspace): void {
        if (
            (int) $instance->collaborationmode !== workspace_service::MODE_GROUP
                || (int) $instance->requireallteamsubmit !== 1
                || empty($workspace->groupid)
        ) {
            throw new \moodle_exception('error:noconsensus', 'mod_vimipad');
        }
    }

    /**
     * Ensure the acting user is a submitting member of the group.
     *
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $userid The acting user id.
     * @return void
     * @throws \moodle_exception If the user may not take part.
     */
    private function require_member(stdClass $workspace, \context $context, int $userid): void {
        $required = (new snapshot_service())->consensus_required_userids($workspace, $context);
        if (!in_array($userid, $required, true)) {
            throw new \moodle_exception('error:notgroupmember', 'mod_vimipad');
        }
    }
}
