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

namespace mod_vimipad\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;
use mod_vimipad\local\access;
use mod_vimipad\local\service\consensus_service;
use mod_vimipad\local\service\push_service;
use mod_vimipad\local\service\workspace_service;

/**
 * Shared helpers for collaboration external functions.
 *
 * Keeps the repeated cmid → context → workspace → edit-access dance, the shared
 * element-lock parameter definition and the settings lookups in one place, so
 * each external function stays small and the behaviour is consistent.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * The parameter definition shared by all element-lock functions.
     *
     * @return external_function_parameters
     */
    public static function lock_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'workspaceid' => new external_value(PARAM_INT, 'Workspace id'),
            'targettype' => new external_value(PARAM_ALPHA, 'Element type: node or relation'),
            'targetstableid' => new external_value(PARAM_ALPHANUMEXT, 'Element stable id'),
        ]);
    }

    /**
     * The parameter definition shared by all consensus functions.
     *
     * @return external_function_parameters
     */
    public static function consensus_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'groupid' => new external_value(PARAM_INT, 'Group id (0 to auto-select)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The return structure shared by all consensus functions.
     *
     * @return external_single_structure
     */
    public static function consensus_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_INT, 'Consensus state: 0 open, 1 voting, 2 submitted'),
            'snapshotid' => new external_value(PARAM_INT, 'Snapshot id if just submitted, else 0'),
            'startedby' => new external_value(PARAM_INT, 'User id who started the process, or 0'),
            'timestarted' => new external_value(PARAM_INT, 'Unix time the process started, or 0'),
            'members' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Group member user id'),
                'confirmed' => new external_value(PARAM_BOOL, 'Whether the member has confirmed'),
            ])),
        ]);
    }

    /**
     * Build the consensus return payload from a service status array.
     *
     * @param array $status The status from consensus_service::get_status().
     * @param int $snapshotid The snapshot id if just submitted, else 0.
     * @return array
     */
    public static function consensus_payload(array $status, int $snapshotid): array {
        return [
            'state' => (int) $status['state'],
            'snapshotid' => $snapshotid,
            'startedby' => (int) $status['startedby'],
            'timestarted' => (int) $status['timestarted'],
            'members' => array_map(static fn($member) => [
                'userid' => (int) $member['userid'],
                'confirmed' => (bool) $member['confirmed'],
            ], $status['members']),
        ];
    }

    /**
     * Validate a consensus request and resolve the caller's group workspace.
     *
     * Shared by all consensus functions so the parameter, context, capability and
     * workspace resolution live in one place.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id (0 to auto-select).
     * @param string $capability The capability to require.
     * @return array [stdClass $instance, stdClass $workspace, \context_module $context]
     */
    public static function consensus_context(int $cmid, int $groupid, string $capability): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::consensus_parameters(), ['cmid' => $cmid, 'groupid' => $groupid]);
        [, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'vimipad');
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability($capability, $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = (new workspace_service())->get_or_create_for_user(
            $instance,
            $context,
            (int) $USER->id,
            $params['groupid'] ?: null
        );
        return [$instance, $workspace, $context];
    }

    /**
     * Re-read the workspace and build the consensus status payload.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The group workspace.
     * @param \context $context The module context.
     * @param int $snapshotid The snapshot id if just submitted, else 0.
     * @return array
     */
    public static function consensus_result(
        stdClass $instance,
        stdClass $workspace,
        \context $context,
        int $snapshotid
    ): array {
        global $DB;

        $fresh = $DB->get_record('vimipad_workspace', ['id' => (int) $workspace->id], '*', MUST_EXIST);
        $status = (new consensus_service())->get_status($instance, $fresh, $context);
        return self::consensus_payload($status, $snapshotid);
    }
    /**
     * Resolve and validate a workspace for editing by the current user.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param \stdClass|null $instance Out: the vimipad instance.
     * @param \stdClass|null $workspace Out: the workspace record.
     * @return \context_module The module context (already validated).
     */
    public static function validate_workspace_for_edit(int $cmid, int $workspaceid, &$instance, &$workspace): \context_module {
        global $USER, $DB;

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'vimipad');
        $context = \context_module::instance($cm->id);
        external_api::validate_context($context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = $DB->get_record(
            'vimipad_workspace',
            ['id' => $workspaceid, 'vimipadid' => $instance->id],
            '*',
            MUST_EXIST
        );

        access::require_edit($instance, $context, $workspace, (int) $USER->id);

        return $context;
    }

    /**
     * Resolve and validate a workspace for a read, enforcing view access and
     * the "own map, or grader for someone else's" rule. Mirrors
     * {@see validate_workspace_for_edit} for read-only external functions.
     *
     * @param int $cmid Course module id.
     * @param int $workspaceid Workspace id.
     * @param \stdClass|null $instance Out: the vimipad instance.
     * @param \stdClass|null $workspace Out: the workspace record.
     * @return \context_module The module context (already validated).
     */
    public static function validate_workspace_for_read(int $cmid, int $workspaceid, &$instance, &$workspace): \context_module {
        global $USER, $DB;

        [, $cm] = get_course_and_cm_from_cmid($cmid, 'vimipad');
        $context = \context_module::instance($cm->id);
        external_api::validate_context($context);
        // Deliberate access decision: reading any workspace — including the
        // shared course-mode workspace — requires mod/vimipad:view in this
        // module's context. Guests, unenrolled and suspended users therefore do
        // not get in (they lack the capability), because a course map is course
        // content, not public data. Course workspaces are shared among enrolled
        // participants only; cross-course/guest access is intentionally refused.
        require_capability('mod/vimipad:view', $context);

        $instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
        $workspace = $DB->get_record(
            'vimipad_workspace',
            ['id' => $workspaceid, 'vimipadid' => $instance->id],
            '*',
            MUST_EXIST
        );

        // A user may read their own map (individual: owner; group: member). In
        // course mode the single shared workspace is everyone's map, so any
        // enrolled user with view may read it. Inspecting another learner's map
        // needs grading.
        $iscourseworkspace = empty($workspace->userid) && empty($workspace->groupid)
            && (int) $instance->collaborationmode === \mod_vimipad\local\service\workspace_service::MODE_COURSE;
        $isown = $iscourseworkspace
            || ((int) $workspace->userid === (int) $USER->id)
            || (!empty($workspace->groupid) && groups_is_member((int) $workspace->groupid, (int) $USER->id));
        if (!$isown) {
            require_capability('mod/vimipad:grade', $context);
        }

        return $context;
    }

    /**
     * The configured lease time-to-live, in seconds (with a sane floor).
     *
     * @return int
     */
    public static function lease_ttl(): int {
        $ttl = (int) get_config('mod_vimipad', 'leasetimeout');
        return $ttl > 0 ? $ttl : 15;
    }

    /**
     * The collaboration client configuration derived from plugin settings.
     *
     * All poll intervals are returned in milliseconds for the client. Falls back
     * to sensible defaults when settings are unset.
     *
     * @param int|null $workspaceid The workspace, to derive the per-workspace push
     *                              topic and scoped subscriber token; null omits them.
     * @return array The collaboration settings bundle (all poll values in ms).
     */
    public static function collab_config(?int $workspaceid = null): array {
        $get = static function (string $name, int $default): int {
            $value = (int) get_config('mod_vimipad', $name);
            return $value > 0 ? $value : $default;
        };
        $pushtopic = '';
        $pushtoken = '';
        if ($workspaceid !== null && push_service::is_enabled()) {
            $pushtopic = push_service::topic($workspaceid);
            $pushtoken = push_service::subscriber_token($workspaceid);
        }
        return [
            'pollinterval' => $get('pollinterval', 1) * 1000,
            'polladaptive' => (int) get_config('mod_vimipad', 'polladaptive'),
            'pollmin' => $get('pollmin', 1) * 1000,
            'pollmax' => $get('pollmax', 10) * 1000,
            'leasetimeout' => self::lease_ttl(),
            'pushenabled' => (int) get_config('mod_vimipad', 'pushenabled'),
            'pushendpoint' => (string) get_config('mod_vimipad', 'pushendpoint'),
            'pushtopic' => $pushtopic,
            'pushtoken' => $pushtoken,
        ];
    }
}
