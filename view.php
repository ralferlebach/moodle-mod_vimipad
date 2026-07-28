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
 * View page for a ViMi Pad activity instance.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'vimipad');
$instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vimipad:view', $context);

$event = \mod_vimipad\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('vimipad', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/vimipad/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$cangrade = has_capability('mod/vimipad:grade', $context);
$canedit = has_capability('mod/vimipad:editown', $context)
    || has_capability('mod/vimipad:editgroup', $context);

// In group collaboration mode, respect the activity's Moodle group mode
// (separate/visible). groups_get_activity_group() reflects the group-menu
// selection and validates it for the user; the editor loads that group's map.
$activegroupid = 0;
$showgroupmenu = false;
if ((int) $instance->collaborationmode === \mod_vimipad\local\service\workspace_service::MODE_GROUP) {
    $activegroupid = (int) groups_get_activity_group($cm, true);
    $showgroupmenu = groups_get_activity_groupmode($cm) != NOGROUPS;
}

// Load the editor through the idiomatic AMD entry point. The thin ES6 module
// resolves strings (core/str) and an AJAX transport (core/ajax), then loads and
// mounts the separately bundled React editor. Strings are still registered for
// JS so the module can resolve them without extra round-trips.
if ($canedit) {
    $PAGE->requires->js_call_amd('mod_vimipad/init', 'init', [$cm->id]);
}

echo $OUTPUT->header();

if (!empty($instance->intro)) {
    echo $OUTPUT->box(format_module_intro('vimipad', $instance, $cm->id), 'generalbox', 'intro');
}

// Group switcher (group collaboration mode with a Moodle group mode set).
if ($showgroupmenu) {
    groups_print_activity_menu($cm, new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id]));
}

// Teacher view: list submissions to grade.
if ($cangrade) {
    echo $OUTPUT->heading(get_string('submissions', 'mod_vimipad'), 3);

    $sql = "SELECT s.id AS snapshotid, s.status, s.timecreated, ws.userid, ws.groupid
              FROM {vimipad_snapshot} s
              JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
             WHERE ws.vimipadid = :vid AND s.id = ws.submittedsnapshotid
          ORDER BY s.timecreated DESC";
    $submissions = $DB->get_records_sql($sql, ['vid' => $instance->id]);

    if (empty($submissions)) {
        echo html_writer::tag('p', get_string('nosubmissions', 'mod_vimipad'), ['class' => 'text-muted']);
    } else {
        $table = new html_table();
        $table->head = [
            get_string('participant', 'mod_vimipad'),
            get_string('status', 'mod_vimipad'),
            get_string('actions', 'mod_vimipad'),
        ];
        foreach ($submissions as $sub) {
            if (!empty($sub->userid)) {
                $who = fullname($DB->get_record('user', ['id' => $sub->userid], '*', MUST_EXIST));
            } else if (!empty($sub->groupid)) {
                $who = groups_get_group_name($sub->groupid);
            } else {
                $who = get_string('mode_course', 'mod_vimipad');
            }
            $gradeurl = new moodle_url(
                '/mod/vimipad/grade.php',
                ['id' => $cm->id, 'snapshotid' => $sub->snapshotid]
            );
            $table->data[] = [
                s($who),
                get_string('snapshotstatus_' . (int) $sub->status, 'mod_vimipad'),
                html_writer::link(
                    $gradeurl,
                    get_string('viewandgrade', 'mod_vimipad'),
                    ['class' => 'btn btn-sm btn-primary']
                ),
            ];
        }
        echo html_writer::table($table);
    }
}

// Editor: shown to anyone who can edit. Teachers see it below the submissions
// list as a live preview; learners see it as their working surface.
if ($canedit) {
    if ($cangrade) {
        echo $OUTPUT->heading(get_string('editorpreview', 'mod_vimipad'), 3);
    }
    echo html_writer::div(
        get_string('editorloading', 'mod_vimipad'),
        'vimipad-editor-placeholder',
        [
            'id' => 'vimipad-editor-root',
            'data-instanceid' => $instance->id,
            'data-cmid' => $cm->id,
            'data-groupid' => $activegroupid,
        ]
    );
} else if (!$cangrade) {
    echo html_writer::tag('p', get_string('noaccess', 'mod_vimipad'), ['class' => 'text-muted']);
}

echo $OUTPUT->footer();
