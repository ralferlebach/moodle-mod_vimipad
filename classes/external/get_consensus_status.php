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
use core_external\external_single_structure;

/**
 * External function: read the current group-consensus status.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_consensus_status extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return helper::consensus_parameters();
    }

    /**
     * Resolve the group workspace and return its consensus status.
     *
     * @param int $cmid Course module id.
     * @param int $groupid Group id (0 to auto-select).
     * @return array
     */
    public static function execute(int $cmid, int $groupid = 0): array {
        [$instance, $workspace, $context] = helper::consensus_context($cmid, $groupid, 'mod/vimipad:view');
        return helper::consensus_result($instance, $workspace, $context, 0);
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return helper::consensus_returns();
    }
}
