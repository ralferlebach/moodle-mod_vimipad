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

// Individual mode: teachers may inspect a specific learner's map read-only via a
// user selector. The choice travels in the URL (two typed params, no separator).
$isindividual = (int) $instance->collaborationmode === \mod_vimipad\local\service\workspace_service::MODE_INDIVIDUAL;
$showuserselector = $isindividual && $cangrade;
$targetuserid = 0;
if ($showuserselector) {
    $targettype = optional_param('targettype', 'self', PARAM_ALPHA);
    $targetid = optional_param('targetid', 0, PARAM_INT);
    if ($targettype === 'user' && $targetid > 0) {
        $targetuserid = $targetid;
        $readonly = $readonly || ($targetuserid !== (int) $USER->id);
    }
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
if ($targetuserid) {
    $baseparams['targettype'] = 'user';
    $baseparams['targetid'] = $targetuserid;
}

// Handle a submission started from the Journal & submission tab (own map).
if (
    $tab === 'journal' && $canedit && !$readonly
        && optional_param('dosubmit', 0, PARAM_BOOL) && confirm_sesskey()
) {
    $journalurl = new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => 'journal']);
    $ownws = (new \mod_vimipad\local\service\workspace_service())
        ->get_or_create_for_user($instance, $context, (int) $USER->id, $activegroupid ?: null);
    try {
        $result = (new \mod_vimipad\local\service\snapshot_service())
            ->create_submission($instance, $ownws, $context, (int) $USER->id);
    } catch (\moodle_exception $e) {
        redirect($journalurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($result['snapshot'] !== null) {
        \mod_vimipad\event\snapshot_submitted::create([
            'context' => $context,
            'objectid' => (int) $result['snapshot']->id,
            'other' => ['workspaceid' => (int) $ownws->id],
        ])->trigger();
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $USER->id);
        }
        $notice = get_string('submitted', 'mod_vimipad');
    } else {
        $notice = get_string('submitpendingcount', 'mod_vimipad', $result['pending']);
    }
    redirect($journalurl, $notice);
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

        // User selector (individual mode, teachers): inspect a learner's map.
        if ($showuserselector) {
            $enrolled = get_enrolled_users(
                $context,
                'mod/vimipad:editown',
                0,
                'u.*',
                'u.lastname, u.firstname'
            );
            $options = [];
            $selfurl = new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => $tab]);
            $options[$selfurl->out(false)] = get_string('yourmap', 'mod_vimipad');
            foreach ($enrolled as $enrolleduser) {
                $userurl = new moodle_url('/mod/vimipad/view.php', [
                    'id' => $cm->id, 'tab' => $tab, 'targettype' => 'user', 'targetid' => $enrolleduser->id,
                ]);
                $options[$userurl->out(false)] = fullname($enrolleduser);
            }
            $selected = $targetuserid
                ? (new moodle_url('/mod/vimipad/view.php', [
                    'id' => $cm->id, 'tab' => $tab, 'targettype' => 'user', 'targetid' => $targetuserid,
                ]))->out(false)
                : $selfurl->out(false);
            $userselect = new url_select($options, $selected, null, 'vimipaduserselect');
            $userselect->set_label(get_string('viewmap', 'mod_vimipad'));
            echo $OUTPUT->render($userselect);
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
                    'data-targetuserid' => $targetuserid,
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

    case 'journal':
        $wsservice = new \mod_vimipad\local\service\workspace_service();
        $journalservice = new \mod_vimipad\local\service\journal_service();

        // Resolve the workspace to display (own, or the inspected foreign one).
        if ($readonly && $targetuserid) {
            $ws = $wsservice->find_for_user($instance, $targetuserid);
        } else if ($readonly && $isgroup && $activegroupid) {
            $ws = $DB->get_record(
                'vimipad_workspace',
                ['vimipadid' => $instance->id, 'groupid' => $activegroupid]
            ) ?: null;
        } else {
            $ws = $wsservice->get_or_create_for_user($instance, $context, (int) $USER->id, $activegroupid ?: null);
        }

        // Submission block (own map only): the submit button now lives here.
        if (!$readonly && $canedit && $ws !== null) {
            echo $OUTPUT->heading(get_string('submission', 'mod_vimipad'), 4);
            if ((int) $ws->locked === 1) {
                echo html_writer::div(
                    get_string('editor:submitted', 'mod_vimipad'),
                    'alert alert-success'
                );
            } else {
                echo html_writer::start_tag('form', [
                    'method' => 'post',
                    'action' => (new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => 'journal']))->out(false),
                    'class' => 'mb-4',
                ]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dosubmit', 'value' => 1]);
                echo html_writer::empty_tag('input', [
                    'type' => 'submit',
                    'value' => get_string('editor:submit', 'mod_vimipad'),
                    'class' => 'btn btn-primary',
                ]);
                echo html_writer::end_tag('form');
            }
        }

        // Journal display: entries in growing, collapsible time buckets.
        echo $OUTPUT->heading(get_string('tab:journal', 'mod_vimipad'), 4);
        $entries = ($ws === null) ? [] : ($readonly
            ? $journalservice->get_teacher_visible((int) $ws->id)
            : $journalservice->get_entries_for_user((int) $ws->id, (int) $USER->id));

        if (empty($entries)) {
            echo html_writer::tag('p', get_string('journal:none', 'mod_vimipad'), ['class' => 'text-muted']);
            break;
        }

        $authorids = [];
        foreach ($entries as $entry) {
            $authorids[(int) $entry->userid] = true;
        }
        $authors = $DB->get_records_list('user', 'id', array_keys($authorids));
        $buckets = \mod_vimipad\local\output\journal_buckets::bucketise($entries, time());

        foreach ($buckets as $bucketkey => $group) {
            echo html_writer::start_tag('details', ['class' => 'vimipad-journal-bucket', 'open' => 'open']);
            echo html_writer::tag(
                'summary',
                get_string('journal:bucket:' . $bucketkey, 'mod_vimipad') . ' (' . count($group) . ')'
            );
            foreach ($group as $entry) {
                $author = $authors[$entry->userid] ?? null;
                echo html_writer::start_tag('div', ['class' => 'vimipad-journal-entry']);
                if ($author) {
                    echo $OUTPUT->user_picture($author, ['size' => 35, 'link' => true, 'includefullname' => true]);
                    echo ' ' . html_writer::link(
                        new moodle_url('/message/index.php', ['id' => $author->id]),
                        get_string('journal:message', 'mod_vimipad'),
                        ['class' => 'small']
                    );
                }
                echo html_writer::tag('span', ' · ' . userdate($entry->timecreated), ['class' => 'text-muted small']);
                echo html_writer::div(format_text($entry->entrytext, FORMAT_PLAIN), 'vimipad-journal-text');
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('details');
        }
        break;

    default:
        // Feedback and tools content follows in later steps.
        echo html_writer::tag('p', get_string('tab:comingsoon', 'mod_vimipad'), ['class' => 'text-muted']);
        break;
}

echo $OUTPUT->footer();
