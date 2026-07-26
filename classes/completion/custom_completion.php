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

namespace mod_vimipad\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Get the completion state for a given rule.
     *
     * @param string $rule The completion rule.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $instance = $DB->get_record('vimipad', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ((int) $instance->completionsubmit === 0) {
            return COMPLETION_INCOMPLETE;
        }

        // Complete once the user has submitted a snapshot in any of their workspaces.
        $sql = "SELECT 1
                  FROM {vimipad_snapshot} s
                  JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                 WHERE ws.vimipadid = :vid AND s.submittedby = :userid AND s.status >= :submitted";
        $params = [
            'vid' => $instance->id,
            'userid' => $this->userid,
            'submitted' => \mod_vimipad\local\service\snapshot_service::STATUS_SUBMITTED,
        ];
        $submitted = $DB->record_exists_sql($sql, $params);

        return $submitted ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules defined by this activity.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * Descriptions of the custom rules for display.
     *
     * @return array<string,string>
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionsubmit' => get_string('completionsubmit_desc', 'mod_vimipad'),
        ];
    }

    /**
     * Sort order of the completion conditions.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionsubmit', 'completionusegrade'];
    }
}
