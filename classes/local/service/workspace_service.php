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

use context_module;
use stdClass;

/**
 * Resolves and creates workspaces according to the activity collaboration mode.
 *
 * Internal service (\mod_vimipad\local): no stability guarantee for dependent
 * plugins. Collaboration modes: 0 = individual (one workspace per user),
 * 1 = group (one per Moodle group), 2 = course (one shared workspace).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workspace_service {
    /** @var int Individual mode: one workspace per user. */
    public const MODE_INDIVIDUAL = 0;

    /** @var int Group mode: one workspace per Moodle group. */
    public const MODE_GROUP = 1;

    /** @var int Course mode: one shared workspace for the activity. */
    public const MODE_COURSE = 2;

    /**
     * Resolve (and lazily create) the workspace the given user should edit.
     *
     * The caller is responsible for require_login on the course module. This
     * method enforces the edit capability and, in group mode, group membership.
     *
     * @param stdClass $instance The vimipad instance record.
     * @param context_module $context The course module context.
     * @param int $userid The user requesting access.
     * @param int|null $groupid Requested group id (group mode only).
     * @return stdClass The workspace record.
     * @throws \required_capability_exception If the user may not edit.
     * @throws \moodle_exception On invalid group access.
     */
    public function get_or_create_for_user(
        stdClass $instance,
        context_module $context,
        int $userid,
        ?int $groupid = null
    ): stdClass {
        global $DB;

        $mode = (int) $instance->collaborationmode;

        if ($mode === self::MODE_INDIVIDUAL) {
            require_capability('mod/vimipad:editown', $context, $userid);
            $criteria = ['vimipadid' => $instance->id, 'userid' => $userid, 'groupid' => null];
        } else if ($mode === self::MODE_GROUP) {
            require_capability('mod/vimipad:editgroup', $context, $userid);
            $groupid = $this->resolve_group($context, $userid, $groupid);
            $criteria = ['vimipadid' => $instance->id, 'userid' => null, 'groupid' => $groupid];
        } else {
            require_capability('mod/vimipad:editgroup', $context, $userid);
            $criteria = ['vimipadid' => $instance->id, 'userid' => null, 'groupid' => null];
        }

        $existing = $DB->get_record('vimipad_workspace', $criteria);
        if ($existing) {
            return $existing;
        }

        return $this->create_unique($instance->id, $criteria['userid'], $criteria['groupid']);
    }

    /**
     * Determine and validate the group to use in group mode.
     *
     * @param context_module $context The course module context.
     * @param int $userid The user requesting access.
     * @param int|null $groupid Requested group id, or null to auto-pick.
     * @return int The validated group id.
     * @throws \moodle_exception If the user is not a member of the group.
     */
    private function resolve_group(context_module $context, int $userid, ?int $groupid): int {
        $courseid = $context->get_course_context()->instanceid;
        $canaccessall = has_capability('moodle/site:accessallgroups', $context, $userid);

        if ($groupid === null || $groupid === 0) {
            // Auto-pick the user's own first group. Users who may access all
            // groups but belong to none (typically teachers) fall back to the
            // first group defined in the course.
            $usergroups = groups_get_all_groups($courseid, $userid);
            if (empty($usergroups) && $canaccessall) {
                $usergroups = groups_get_all_groups($courseid);
            }
            if (empty($usergroups)) {
                throw new \moodle_exception('error:nogroup', 'mod_vimipad');
            }
            $first = reset($usergroups);
            return (int) $first->id;
        }

        if (!$canaccessall && !groups_is_member($groupid, $userid)) {
            throw new \moodle_exception('error:notingroup', 'mod_vimipad');
        }

        return $groupid;
    }

    /**
     * Create a workspace for an owner, serialized so concurrent first-access
     * cannot produce duplicate workspaces.
     *
     * Uses the core lock API keyed per owner. If the lock cannot be obtained in
     * time, a best-effort create is performed (the race window is tiny).
     *
     * @param int $vimipadid The vimipad instance id.
     * @param int|null $userid Owner user id (individual mode) or null.
     * @param int|null $groupid Owner group id (group mode) or null.
     * @return stdClass The existing or newly created workspace record.
     */
    private function create_unique(int $vimipadid, ?int $userid, ?int $groupid): stdClass {
        global $DB;

        $criteria = ['vimipadid' => $vimipadid, 'userid' => $userid, 'groupid' => $groupid];

        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_vimipad_workspace');
        $lockkey = $vimipadid . ':u' . ($userid ?? 0) . ':g' . ($groupid ?? 0);
        $lock = $lockfactory->get_lock($lockkey, 10);

        if (!$lock) {
            return $this->create($vimipadid, $userid, $groupid);
        }

        try {
            // Another request may have created it while we waited for the lock.
            $existing = $DB->get_record('vimipad_workspace', $criteria);
            if ($existing) {
                return $existing;
            }
            return $this->create($vimipadid, $userid, $groupid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Reopen a submitted (locked) workspace so its owner can revise it.
     *
     * The existing snapshot is kept; the workspace is simply unlocked so further
     * edits and a fresh submission become possible.
     *
     * @param int $workspaceid The workspace id.
     * @return void
     */
    public function reopen(int $workspaceid): void {
        global $DB;

        $DB->update_record('vimipad_workspace', (object) [
            'id' => $workspaceid,
            'locked' => 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a new empty workspace record.
     *
     * @param int $vimipadid The vimipad instance id.
     * @param int|null $userid Owner user id (individual mode) or null.
     * @param int|null $groupid Owner group id (group mode) or null.
     * @return stdClass The created workspace record.
     */
    private function create(int $vimipadid, ?int $userid, ?int $groupid): stdClass {
        global $DB;

        $now = time();
        $record = (object) [
            'vimipadid' => $vimipadid,
            'userid' => $userid,
            'groupid' => $groupid,
            'name' => null,
            'currentrevision' => 0,
            'submittedsnapshotid' => null,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('vimipad_workspace', $record);

        return $record;
    }

    /**
     * Load the full editable state of a workspace.
     *
     * @param int $workspaceid The workspace id.
     * @return array{workspace: stdClass, nodes: array, relations: array, containers: array}
     */
    public function get_state(int $workspaceid): array {
        global $DB;

        $workspace = $DB->get_record('vimipad_workspace', ['id' => $workspaceid], '*', MUST_EXIST);
        $nodes = $DB->get_records('vimipad_node', ['workspaceid' => $workspaceid, 'deleted' => 0]);
        $relations = $DB->get_records('vimipad_relation', ['workspaceid' => $workspaceid, 'deleted' => 0]);
        $containers = $DB->get_records('vimipad_container', ['workspaceid' => $workspaceid, 'deleted' => 0]);

        return [
            'workspace' => $workspace,
            'nodes' => array_values($nodes),
            'relations' => array_values($relations),
            'containers' => array_values($containers),
        ];
    }
}
