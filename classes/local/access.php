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

use context_module;
use stdClass;

/**
 * Shared edit-access enforcement for external functions.
 *
 * Centralises the capability and group checks required before any write to a
 * workspace, so apply_operation and save_layout stay consistent. Internal
 * (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Enforce edit capability and, in group mode, group membership.
     *
     * @param stdClass $instance The vimipad instance.
     * @param context_module $context The module context.
     * @param stdClass $workspace The workspace record.
     * @param int $userid The acting user id.
     * @return void
     * @throws \required_capability_exception|\moodle_exception
     */
    public static function require_edit(
        stdClass $instance,
        context_module $context,
        stdClass $workspace,
        int $userid
    ): void {
        $mode = (int) $instance->collaborationmode;

        if ($mode === 0) {
            require_capability('mod/vimipad:editown', $context);
            if ((int) $workspace->userid !== $userid) {
                throw new \moodle_exception('error:notownworkspace', 'mod_vimipad');
            }
            return;
        }

        require_capability('mod/vimipad:editgroup', $context);
        if ($mode === 1 && !empty($workspace->groupid)) {
            $canaccessall = has_capability('moodle/site:accessallgroups', $context);
            if (!$canaccessall && !groups_is_member((int) $workspace->groupid, $userid)) {
                throw new \moodle_exception('error:notingroup', 'mod_vimipad');
            }
        }
    }
}
