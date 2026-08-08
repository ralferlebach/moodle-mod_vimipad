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

/**
 * Library of interface functions and constants for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return the features supported by this activity module.
 *
 * Grading/completion features are enabled incrementally as the corresponding
 * services land; the shell only declares what it actually implements.
 *
 * @param string $feature Constant for requested feature, e.g. FEATURE_MOD_INTRO.
 * @return mixed True if module supports feature, false if not, null if unknown.
 */
function vimipad_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}


/**
 * Normalise the multi-select scorer list into the stored comma-separated form.
 *
 * @param stdClass $data Form data, modified in place.
 * @return void
 */
function vimipad_prepare_scorer_fields(stdClass $data): void {
    if (!property_exists($data, 'activescorers')) {
        return;
    }
    if (is_array($data->activescorers)) {
        $keys = array_filter(array_map(static fn($key) => clean_param($key, PARAM_ALPHANUMEXT), $data->activescorers));
        $data->activescorers = implode(',', $keys);
    }
}

/**
 * Add a new vimipad instance.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_vimipad_mod_form|null $mform The form instance (unused).
 * @return int The id of the newly inserted record.
 */
function vimipad_add_instance(stdClass $data, ?mod_vimipad_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    vimipad_prepare_completion_fields($data);
    vimipad_prepare_scorer_fields($data);
    vimipad_enforce_group_consistency($data);
    vimipad_normalise_profile($data);

    $id = $DB->insert_record('vimipad', $data);

    $data->id = $id;
    vimipad_grade_item_update($data);

    return $id;
}

/**
 * Update an existing vimipad instance.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_vimipad_mod_form|null $mform The form instance (unused).
 * @return bool True on success.
 */
function vimipad_update_instance(stdClass $data, ?mod_vimipad_mod_form $mform = null): bool {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    vimipad_prepare_completion_fields($data);
    vimipad_prepare_scorer_fields($data);
    vimipad_enforce_group_consistency($data);
    vimipad_normalise_profile($data);

    $result = $DB->update_record('vimipad', $data);

    vimipad_grade_item_update($data);

    return $result;
}

/**
 * Normalise the default profile to a known one, non-breaking.
 *
 * The activity form only offers known profiles, but instances are also created
 * by backup/restore, course import and web services, which never run the form
 * and could carry a profile whose vimipadform subplugin is not installed. To
 * keep those paths working (the subplugins are separately installable), an
 * unknown profile is repaired to the safe default rather than rejected; the
 * editor still renders it via the fallback form definition.
 *
 * @param stdClass $data The instance data (modified in place).
 * @return void
 */
function vimipad_normalise_profile(stdClass $data): void {
    if (!isset($data->defaultprofile) || $data->defaultprofile === '') {
        $data->defaultprofile = 'conceptmap';
        return;
    }
    if (!\mod_vimipad\profile\profiles::exists((string) $data->defaultprofile)) {
        $data->defaultprofile = 'conceptmap';
    }
}

/**
 * Enforce the group-map / group-mode invariant server-side, non-breaking.
 *
 * The activity form validates this bidirectionally and blocks saving, but
 * instances are also created by backup/restore, course import and web
 * services, which never run the form. Here we therefore repair rather than
 * reject, so a restore cannot abort:
 *
 * - A group map without a course group mode gets separate groups.
 * - A non-group map that carries a group mode has the group mode cleared,
 *   unless the course forces a group mode — then the map is promoted to a
 *   group map instead, because the group mode cannot be removed.
 *
 * The course-module group mode lives on {course_modules}.groupmode, addressed
 * through the form's coursemodule id; the value is mirrored onto $data so the
 * caller and later hooks see a consistent record.
 *
 * @param stdClass $data The instance data (modified in place). Expects
 *      collaborationmode, and — when called from the module edit flow —
 *      coursemodule and groupmode.
 * @return void
 */
function vimipad_enforce_group_consistency(stdClass $data): void {
    global $DB, $COURSE;

    if (!property_exists($data, 'coursemodule') || empty($data->coursemodule)) {
        // No course module in scope (e.g. certain low-level test inserts):
        // nothing to reconcile against.
        return;
    }

    $cmid = (int) $data->coursemodule;
    $isgroupmap = (int) ($data->collaborationmode ?? 0)
        === \mod_vimipad\local\service\workspace_service::MODE_GROUP;
    $groupmode = isset($data->groupmode)
        ? (int) $data->groupmode
        : (int) $DB->get_field('course_modules', 'groupmode', ['id' => $cmid]);
    $forced = !empty($COURSE->groupmodeforce);

    if ($isgroupmap && $groupmode === NOGROUPS) {
        $groupmode = SEPARATEGROUPS;
    } else if (!$isgroupmap && $groupmode !== NOGROUPS) {
        if ($forced) {
            $data->collaborationmode = \mod_vimipad\local\service\workspace_service::MODE_GROUP;
        } else {
            $groupmode = NOGROUPS;
        }
    }

    // Persist the reconciled group mode onto the course module and mirror it
    // onto $data for any later hook in the same request.
    $DB->set_field('course_modules', 'groupmode', $groupmode, ['id' => $cmid]);
    $data->groupmode = $groupmode;
}

/**
 * Normalise the completion detail rule fields from the settings form.
 *
 * The "minimum concepts" rule is entered as an enable checkbox plus a number;
 * when the checkbox is off (or missing) the stored threshold is zeroed, which is
 * how the rule is treated as "off".
 *
 * @param stdClass $data Form data (modified in place).
 * @return void
 */
function vimipad_prepare_completion_fields(stdClass $data): void {
    if (empty($data->completionminnodesenabled)) {
        $data->completionminnodes = 0;
    } else {
        $data->completionminnodes = max(0, (int) ($data->completionminnodes ?? 0));
    }
}

/**
 * Provide course module info, registering the custom completion rules so Moodle
 * evaluates them.
 *
 * Without this, the completion subsystem does not know the activity's custom
 * rules and refuses to evaluate them ("rule not used by this activity").
 *
 * @param \stdClass $coursemodule The course module record.
 * @return \cached_cm_info|null The info, or null if the instance is missing.
 */
function vimipad_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit, completionminnodes, completiongraded';
    $instance = $DB->get_record('vimipad', ['id' => $coursemodule->instance], $fields);
    if (!$instance) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('vimipad', $instance, $coursemodule->id, false);
    }

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules'] = [
            'completionsubmit' => (int) $instance->completionsubmit,
            'completionminnodes' => (int) $instance->completionminnodes,
            'completiongraded' => (int) $instance->completiongraded,
        ];
    }

    return $info;
}

/**
 * Delete a vimipad instance and all dependent data.
 *
 * @param int $id Id of the vimipad instance.
 * @return bool True on success.
 */
function vimipad_delete_instance(int $id): bool {
    global $DB;

    if (!$instance = $DB->get_record('vimipad', ['id' => $id])) {
        return false;
    }

    $workspaceids = $DB->get_fieldset_select('vimipad_workspace', 'id', 'vimipadid = :vid', ['vid' => $id]);
    \mod_vimipad\local\cleanup::delete_workspaces($workspaceids);

    $DB->delete_records('vimipad_grade', ['vimipadid' => $id]);

    // Remove the gradebook item.
    vimipad_grade_item_delete($instance);

    $DB->delete_records('vimipad', ['id' => $id]);

    return true;
}

/**
 * Create, update or delete the gradebook item for a vimipad instance.
 *
 * @param stdClass $instance The vimipad instance record (must include id, course, name, grade).
 * @param array|null $grades Optional grades to push, keyed by user id, or 'reset'.
 * @return int GRADE_UPDATE_OK or a failure constant.
 */
function vimipad_grade_item_update(stdClass $instance, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => clean_param($instance->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
    ];

    $grade = isset($instance->grade) ? (int) $instance->grade : 100;
    if ($grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $grade;
        $item['grademin'] = 0;
    } else if ($grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/vimipad',
        $instance->course,
        'mod',
        'vimipad',
        $instance->id,
        0,
        $grades,
        $item
    );
}

/**
 * Delete the gradebook item for a vimipad instance.
 *
 * @param stdClass $instance The vimipad instance record.
 * @return int GRADE_UPDATE_OK or a failure constant.
 */
function vimipad_grade_item_delete(stdClass $instance): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/vimipad',
        $instance->course,
        'mod',
        'vimipad',
        $instance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Return the stored grades for a vimipad instance in gradebook shape.
 *
 * @param stdClass $instance The vimipad instance record.
 * @param int $userid A specific user id, or 0 for all users.
 * @return array<int,stdClass> Grade objects keyed by user id.
 */
function vimipad_get_user_grades(stdClass $instance, int $userid = 0): array {
    $service = new \mod_vimipad\local\service\grading_service();
    return $service->get_user_grades($instance, $userid);
}

/**
 * Update the gradebook from stored plugin grades.
 *
 * @param stdClass $instance The vimipad instance record.
 * @param int $userid A specific user id, or 0 for all users.
 * @param bool $nullifnone Whether to push a null grade if none is found for a user.
 * @return void
 */
function vimipad_update_grades(stdClass $instance, int $userid = 0, bool $nullifnone = true): void {
    $grades = vimipad_get_user_grades($instance, $userid);

    if (!empty($grades)) {
        vimipad_grade_item_update($instance, $grades);
    } else if ($userid && $nullifnone) {
        $null = (object) ['userid' => $userid, 'rawgrade' => null];
        vimipad_grade_item_update($instance, [$userid => $null]);
    } else {
        vimipad_grade_item_update($instance);
    }
}

/**
 * Status checks contributed to the site's Reports > Checks page.
 *
 * Surfaces operational health of mod_vimipad: workspace-data integrity, subplugin
 * registration and the size of the append-only histories.
 *
 * @return \core\check\check[] The check objects.
 */
function mod_vimipad_status_checks(): array {
    return [
        new \mod_vimipad\check\data_integrity(),
        new \mod_vimipad\check\subplugins(),
        new \mod_vimipad\check\history_size(),
    ];
}
