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
$canview = has_capability('mod/vimipad:view', $context);
$canedit = has_capability('mod/vimipad:editown', $context)
    || has_capability('mod/vimipad:editgroup', $context);

$isgroup = (int) $instance->collaborationmode === \mod_vimipad\local\service\workspace_service::MODE_GROUP;

// Group selector: teachers always (when a group mode is set); learners only with
// visible groups; never for a course-wide map. In group collaboration the native
// Moodle group menu drives the target and its 'group' param travels in the tab
// URLs, so the selection persists across tabs without a custom separator.
$activegroupid = 0;
$showgroupmenu = false;
if ($isgroup) {
    $activegroupid = (int) groups_get_activity_group($cm, true);
    $groupmode = groups_get_activity_groupmode($cm);
    $showgroupmenu = ($groupmode == VISIBLEGROUPS) || ($cangrade && $groupmode != NOGROUPS);
}

// Foreign maps are viewed live but never edited. In group mode a user editing a
// group they do not belong to (e.g. a teacher browsing groups) is read-only.
$readonly = false;
if ($isgroup && $activegroupid) {
    $readonly = !groups_is_member($activegroupid, $USER->id);
}

// Server-rendered, role-gated tabs. The active tab travels in the URL so it is
// shareable and persists alongside the group selection.
$tabgates = [
    'canvas' => $canview,
    'list' => $canview,
    'journal' => $canview,
    'grade' => $cangrade,
    'feedback' => $canview,
    'tools' => $cangrade,
];
$availabletabs = array_keys(array_filter($tabgates));

// Auto-open: teachers default to the canvas (monitoring/preview); learners too
// for now. State-based routing (submitted -> journal, graded -> feedback) is
// added together with those tabs.
$defaulttab = 'canvas';
$tab = optional_param('tab', $defaulttab, PARAM_ALPHA);
if (!in_array($tab, $availabletabs, true)) {
    $tab = in_array($defaulttab, $availabletabs, true) ? $defaulttab : reset($availabletabs);
}

// Load the editor bundle only on the tabs that render it.
$editortabs = ['canvas', 'list'];
if ($canedit && in_array($tab, $editortabs, true)) {
    $PAGE->requires->js_call_amd('mod_vimipad/init', 'init', [$cm->id]);
}

$baseparams = ['id' => $cm->id];
if ($activegroupid) {
    $baseparams['group'] = $activegroupid;
}
$tabtree = [];
foreach ($availabletabs as $key) {
    $taburl = new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => $key]);
    $tabtree[] = new tabobject($key, $taburl, get_string('tab:' . $key, 'mod_vimipad'));
}

echo $OUTPUT->header();

if (!empty($instance->intro)) {
    echo $OUTPUT->box(format_module_intro('vimipad', $instance, $cm->id), 'generalbox', 'intro');
}

echo $OUTPUT->tabtree($tabtree, $tab);

switch ($tab) {
    case 'canvas':
    case 'list':
        // Companion channel (optional forum/chat/BBB link).
        if (!empty($instance->channelurl)) {
            echo html_writer::div(html_writer::link(
                $instance->channelurl,
                get_string('channel:open', 'mod_vimipad'),
                ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener noreferrer']
            ), 'mb-3');
        }

        // Group selector.
        if ($showgroupmenu) {
            groups_print_activity_menu($cm, new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => $tab]));
        }

        if ($canedit) {
            echo html_writer::div(
                get_string('editorloading', 'mod_vimipad'),
                'vimipad-editor-placeholder',
                [
                    'id' => 'vimipad-editor-root',
                    'data-instanceid' => $instance->id,
                    'data-cmid' => $cm->id,
                    'data-groupid' => $activegroupid,
                    'data-view' => $tab,
                    'data-readonly' => $readonly ? 1 : 0,
                ]
            );
        } else {
            echo html_writer::tag('p', get_string('noaccess', 'mod_vimipad'), ['class' => 'text-muted']);
        }
        break;

    case 'grade':
        // Submissions to grade (moves into a full grading tab in a later step).
        echo html_writer::div(html_writer::link(
            new moodle_url('/mod/vimipad/report.php', ['cmid' => $cm->id]),
            get_string('report:link', 'mod_vimipad'),
            ['class' => 'btn btn-secondary']
        ), 'mb-3');

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
            $userids = [];
            foreach ($submissions as $sub) {
                if (!empty($sub->userid)) {
                    $userids[(int) $sub->userid] = true;
                }
            }
            $users = empty($userids) ? [] : $DB->get_records_list('user', 'id', array_keys($userids));
            foreach ($submissions as $sub) {
                if (!empty($sub->userid)) {
                    $who = isset($users[$sub->userid]) ? fullname($users[$sub->userid]) : '';
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
        break;

    default:
        // Journal, feedback and tools content follows in later steps.
        echo html_writer::tag('p', get_string('tab:comingsoon', 'mod_vimipad'), ['class' => 'text-muted']);
        break;
}

echo $OUTPUT->footer();
