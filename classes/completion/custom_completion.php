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

        switch ($rule) {
            case 'completionsubmit':
                return $this->state_submit($instance);
            case 'completionminnodes':
                return $this->state_min_nodes($instance);
            case 'completiongraded':
                return $this->state_graded($instance);
            default:
                return COMPLETION_INCOMPLETE;
        }
    }

    /**
     * The workspace ids that count towards this user's completion, by mode.
     *
     * @param \stdClass $instance The activity instance.
     * @return int[] The applicable workspace ids.
     */
    private function applicable_workspace_ids(\stdClass $instance): array {
        global $DB;

        $mode = (int) $instance->collaborationmode;

        if ($mode === 1) {
            // Group: the user's group workspace(s).
            return $DB->get_fieldset_sql(
                "SELECT ws.id
                   FROM {vimipad_workspace} ws
                  WHERE ws.vimipadid = :vid
                    AND ws.groupid IN (SELECT gm.groupid FROM {groups_members} gm WHERE gm.userid = :userid)",
                ['vid' => $instance->id, 'userid' => $this->userid]
            );
        }
        if ($mode === 2) {
            // Course: the single shared workspace.
            return $DB->get_fieldset_select(
                'vimipad_workspace',
                'id',
                'vimipadid = :vid AND userid IS NULL AND groupid IS NULL',
                ['vid' => $instance->id]
            );
        }
        // Individual: the user's own workspace.
        return $DB->get_fieldset_select(
            'vimipad_workspace',
            'id',
            'vimipadid = :vid AND userid = :userid',
            ['vid' => $instance->id, 'userid' => $this->userid]
        );
    }

    /**
     * State for the "submit a snapshot" rule. Complete once any workspace that
     * counts for the user has a submitted snapshot (regardless of who submitted).
     *
     * @param \stdClass $instance The activity instance.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    private function state_submit(\stdClass $instance): int {
        global $DB;

        if ((int) $instance->completionsubmit === 0) {
            return COMPLETION_INCOMPLETE;
        }

        $wsids = $this->applicable_workspace_ids($instance);
        if (empty($wsids)) {
            return COMPLETION_INCOMPLETE;
        }

        [$insql, $params] = $DB->get_in_or_equal($wsids, SQL_PARAMS_NAMED);
        $params['submitted'] = \mod_vimipad\local\service\snapshot_service::STATUS_SUBMITTED;
        $exists = $DB->record_exists_sql(
            "SELECT 1 FROM {vimipad_snapshot} s WHERE s.workspaceid $insql AND s.status >= :submitted",
            $params
        );

        return $exists ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * State for the "minimum concepts" rule.
     *
     * @param \stdClass $instance The activity instance.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    private function state_min_nodes(\stdClass $instance): int {
        global $DB;

        $required = (int) $instance->completionminnodes;
        if ($required <= 0) {
            return COMPLETION_INCOMPLETE;
        }

        $wsids = $this->applicable_workspace_ids($instance);
        if (empty($wsids)) {
            return COMPLETION_INCOMPLETE;
        }

        [$insql, $params] = $DB->get_in_or_equal($wsids, SQL_PARAMS_NAMED);
        $count = (int) $DB->count_records_select('vimipad_node', "workspaceid $insql AND deleted = 0", $params);

        return $count >= $required ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * State for the "submission graded" rule.
     *
     * @param \stdClass $instance The activity instance.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    private function state_graded(\stdClass $instance): int {
        global $DB;

        if ((int) $instance->completiongraded === 0) {
            return COMPLETION_INCOMPLETE;
        }

        $wsids = $this->applicable_workspace_ids($instance);
        if (empty($wsids)) {
            return COMPLETION_INCOMPLETE;
        }

        [$insql, $params] = $DB->get_in_or_equal($wsids, SQL_PARAMS_NAMED);
        $params['graded'] = \mod_vimipad\local\service\snapshot_service::STATUS_GRADED;
        $exists = $DB->record_exists_sql(
            "SELECT 1 FROM {vimipad_snapshot} s WHERE s.workspaceid $insql AND s.status >= :graded",
            $params
        );

        return $exists ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Rules defined by this activity.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit', 'completionminnodes', 'completiongraded'];
    }

    /**
     * Descriptions of the custom rules for display.
     *
     * @return array<string,string>
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $min = (int) $DB->get_field('vimipad', 'completionminnodes', ['id' => $this->cm->instance]);
        return [
            'completionsubmit' => get_string('completionsubmit_desc', 'mod_vimipad'),
            'completionminnodes' => get_string('completionminnodes_desc', 'mod_vimipad', $min),
            'completiongraded' => get_string('completiongraded_desc', 'mod_vimipad'),
        ];
    }

    /**
     * Sort order of the completion conditions.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionminnodes',
            'completionsubmit',
            'completiongraded',
            'completionusegrade',
        ];
    }
}
