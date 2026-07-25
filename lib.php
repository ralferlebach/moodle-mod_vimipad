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

defined('MOODLE_INTERNAL') || die();

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
        case FEATURE_GRADE_HAS_GRADE:
            return false; // Enabled with the snapshot grading service (MVP milestone M4).
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
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

    return $DB->insert_record('vimipad', $data);
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

    return $DB->update_record('vimipad', $data);
}

/**
 * Delete a vimipad instance and all dependent data.
 *
 * @param int $id Id of the vimipad instance.
 * @return bool True on success.
 */
function vimipad_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('vimipad', ['id' => $id])) {
        return false;
    }

    // Dependent domain tables (workspaces, nodes, relations, snapshots, ...)
    // will be cleaned up here as they are introduced.
    $DB->delete_records('vimipad', ['id' => $id]);

    return true;
}
