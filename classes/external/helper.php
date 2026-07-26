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
use mod_vimipad\local\access;

/**
 * Shared helpers for collaboration external functions.
 *
 * Keeps the repeated cmid → context → workspace → edit-access dance and the
 * settings lookups in one place, so each external function stays small and the
 * behaviour is consistent.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
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
     * The configured lease time-to-live, in seconds (with a sane floor).
     *
     * @return int
     */
    public static function lease_ttl(): int {
        $ttl = (int) get_config('mod_vimipad', 'leasetimeout');
        return $ttl > 0 ? $ttl : 15;
    }
}
