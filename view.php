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
$cansubmit = has_capability('mod/vimipad:submit', $context);

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
// A learner sees the feedback tab once their own submission is graded.
$hasfeedback = $canview && (new \mod_vimipad\local\service\grading_service())
    ->get_feedback_for_user($instance, (int) $USER->id) !== null;

$tabgates = [
    'canvas' => $canview,
    'list' => $canview,
    'journal' => $canview,
    'feedback' => $hasfeedback,
    'peer' => !empty($instance->peerreviewmode) && has_capability('mod/vimipad:peerreview', $context),
    'grade' => $cangrade,
    'tools' => $canedit,
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
$editortabs = ['canvas', 'list', 'tools'];
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
// Submitting requires mod/vimipad:submit in addition to edit access, matching
// the create_snapshot external function.
if (
    $tab === 'journal' && $canedit && $cansubmit && !$readonly
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

// Handle group-consensus actions (start / confirm / cancel) from the same tab.
$consensusaction = optional_param('consensus', '', PARAM_ALPHA);
if (
    $tab === 'journal' && $canedit && $cansubmit && !$readonly
        && in_array($consensusaction, ['start', 'confirm', 'cancel'], true) && confirm_sesskey()
) {
    $journalurl = new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => 'journal']);
    $ownws = (new \mod_vimipad\local\service\workspace_service())
        ->get_or_create_for_user($instance, $context, (int) $USER->id, $activegroupid ?: null);
    $service = new \mod_vimipad\local\service\consensus_service();
    $members = (new \mod_vimipad\local\service\snapshot_service())
        ->consensus_required_userids($ownws, $context);

    try {
        if ($consensusaction === 'start') {
            $service->start($instance, $ownws, $context, (int) $USER->id);
            \mod_vimipad\local\consensus_notifier::notify($cm, $instance, 'started', (int) $USER->id, $members);
            $notice = get_string('consensus:notice:started', 'mod_vimipad');
        } else if ($consensusaction === 'cancel') {
            $service->cancel($instance, $ownws, $context, (int) $USER->id);
            \mod_vimipad\local\consensus_notifier::notify($cm, $instance, 'cancelled', (int) $USER->id, $members);
            $notice = get_string('consensus:notice:cancelled', 'mod_vimipad');
        } else {
            if (!optional_param('agree', 0, PARAM_BOOL)) {
                redirect(
                    $journalurl,
                    get_string('consensus:mustagree', 'mod_vimipad'),
                    null,
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            $result = $service->confirm($instance, $ownws, $context, (int) $USER->id);
            if ((int) $result['state'] === \mod_vimipad\local\service\consensus_service::STATE_SUBMITTED) {
                \mod_vimipad\event\snapshot_submitted::create([
                    'context' => $context,
                    'objectid' => (int) $result['snapshotid'],
                    'other' => ['workspaceid' => (int) $ownws->id],
                ])->trigger();
                $completion = new completion_info($course);
                if ($completion->is_enabled($cm)) {
                    $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $USER->id);
                }
                \mod_vimipad\local\consensus_notifier::notify($cm, $instance, 'submitted', (int) $USER->id, $members);
                $notice = get_string('submitted', 'mod_vimipad');
            } else {
                $notice = get_string('consensus:notice:confirmed', 'mod_vimipad');
            }
        }
    } catch (\moodle_exception $e) {
        redirect($journalurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($journalurl, $notice);
}

// Handle grading actions (Grading tab with a submission selected). Runs before
// output so POST handlers can redirect; a GET is a no-op.
$gradesnapshot = null;
$gradeworkspace = null;
$gradesnapshotid = ($tab === 'grade') ? optional_param('snapshotid', 0, PARAM_INT) : 0;
if ($gradesnapshotid && $cangrade) {
    $gradesnapshot = $DB->get_record('vimipad_snapshot', ['id' => $gradesnapshotid], '*', MUST_EXIST);
    $gradeworkspace = $DB->get_record(
        'vimipad_workspace',
        ['id' => $gradesnapshot->workspaceid, 'vimipadid' => $instance->id],
        '*',
        MUST_EXIST
    );
    \mod_vimipad\local\output\grading_panel::handle_action(
        $cm,
        $course,
        $context,
        $instance,
        $gradesnapshot,
        $gradeworkspace
    );
}

// Peer review: a teacher allocating reviews for all submitted maps.
if ($tab === 'grade' && $cangrade && optional_param('allocatereviews', 0, PARAM_BOOL) && confirm_sesskey()) {
    $allocated = (new \mod_vimipad\local\service\peer_review_service())->allocate($instance);
    redirect(
        new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'grade']),
        get_string('peerreviewallocated', 'mod_vimipad', $allocated)
    );
}

// Peer review: save a review if one was posted.
if ($tab === 'peer' && !empty($instance->peerreviewmode)) {
    \mod_vimipad\local\output\peer_review_panel::handle_action($cm, $context, $instance, (int) $USER->id);
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
    case 'tools':
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
        if ($gradesnapshot !== null) {
            // Single-submission grading detail.
            echo $OUTPUT->heading(get_string('gradetitle', 'mod_vimipad'), 3);
            \mod_vimipad\local\output\grading_panel::render(
                $cm,
                $context,
                $instance,
                $gradesnapshot,
                $gradeworkspace
            );
            break;
        }

        // Submissions list.
        echo $OUTPUT->heading(get_string('submissions', 'mod_vimipad'), 3);
        echo html_writer::div(html_writer::link(
            new moodle_url('/mod/vimipad/report.php', ['cmid' => $cm->id]),
            get_string('report:link', 'mod_vimipad'),
            ['class' => 'btn btn-secondary']
        ), 'mb-3');

        if (!empty($instance->peerreviewmode)) {
            $allocateurl = new moodle_url('/mod/vimipad/view.php', [
                'id' => $cm->id, 'tab' => 'grade', 'allocatereviews' => 1, 'sesskey' => sesskey(),
            ]);
            echo html_writer::div(html_writer::link(
                $allocateurl,
                get_string('peerreviewallocate', 'mod_vimipad'),
                ['class' => 'btn btn-outline-secondary']
            ), 'mb-3');
        }

        $sql = "SELECT s.id AS snapshotid, s.status, s.timecreated, ws.id AS workspaceid, ws.userid, ws.groupid
                  FROM {vimipad_snapshot} s
                  JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                 WHERE ws.vimipadid = :vid AND s.id = ws.submittedsnapshotid
              ORDER BY s.timecreated DESC";
        $submissions = $DB->get_records_sql($sql, ['vid' => $instance->id]);

        // Structure metrics (nodes/relations per workspace) as a grading aid,
        // batched in two grouped queries. The workspace is locked on submission,
        // so the live tables reflect the submitted state.
        $nodecounts = [];
        $relationcounts = [];
        if (!empty($submissions)) {
            $wsids = array_map(static fn($sub) => (int) $sub->workspaceid, $submissions);
            [$insql, $inparams] = $DB->get_in_or_equal($wsids, SQL_PARAMS_NAMED);
            $nodecounts = $DB->get_records_sql_menu(
                "SELECT workspaceid, COUNT(*) FROM {vimipad_node}
                  WHERE workspaceid $insql AND deleted = 0 GROUP BY workspaceid",
                $inparams
            );
            $relationcounts = $DB->get_records_sql_menu(
                "SELECT workspaceid, COUNT(*) FROM {vimipad_relation}
                  WHERE workspaceid $insql AND deleted = 0 GROUP BY workspaceid",
                $inparams
            );
        }

        if (empty($submissions)) {
            echo html_writer::tag('p', get_string('nosubmissions', 'mod_vimipad'), ['class' => 'text-muted']);
        } else {
            $table = new html_table();
            $table->head = [
                get_string('participant', 'mod_vimipad'),
                get_string('gradetab:size', 'mod_vimipad'),
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
                $gradeurl = \mod_vimipad\local\output\grading_panel::detail_url($cm, (int) $sub->snapshotid);
                $size = (int) ($nodecounts[$sub->workspaceid] ?? 0) . ' / '
                    . (int) ($relationcounts[$sub->workspaceid] ?? 0);
                $table->data[] = [
                    s($who),
                    $size,
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

    case 'feedback':
        echo $OUTPUT->heading(get_string('tab:feedback', 'mod_vimipad'), 3);
        echo \mod_vimipad\local\output\feedback_panel::render($context, $instance, (int) $USER->id);
        break;

    case 'peer':
        \mod_vimipad\local\output\peer_review_panel::render($cm, $context, $instance, (int) $USER->id);
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

        // Submission block (own map only, and only for users allowed to submit).
        if (!$readonly && $canedit && $cansubmit && $ws !== null) {
            echo $OUTPUT->heading(get_string('submission', 'mod_vimipad'), 4);
            $formaction = (new moodle_url('/mod/vimipad/view.php', $baseparams + ['tab' => 'journal']))->out(false);
            $isconsensus = $isgroup && (int) $instance->requireallteamsubmit === 1 && !empty($ws->groupid);

            // Small POST form for a single consensus action (optional agree box).
            $actionform = function (string $action, string $label, string $class, bool $agree = false) use ($formaction) {
                $html = html_writer::start_tag('form', ['method' => 'post', 'action' => $formaction, 'class' => 'd-inline']);
                $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'consensus', 'value' => $action]);
                if ($agree) {
                    $html .= html_writer::tag('label', html_writer::empty_tag('input', [
                        'type' => 'checkbox', 'name' => 'agree', 'value' => 1, 'required' => 'required',
                    ]) . ' ' . get_string('consensus:agree', 'mod_vimipad'), ['class' => 'd-block mb-2']);
                }
                $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => $label, 'class' => $class]);
                $html .= html_writer::end_tag('form');
                return $html;
            };

            if ((int) $ws->locked === 1) {
                echo html_writer::div(get_string('editor:submitted', 'mod_vimipad'), 'alert alert-success');
            } else if ($isconsensus) {
                $status = (new \mod_vimipad\local\service\consensus_service())->get_status($instance, $ws, $context);
                if ((int) $status['state'] === \mod_vimipad\local\service\consensus_service::STATE_OPEN) {
                    echo html_writer::tag('p', get_string('consensus:openhint', 'mod_vimipad'), ['class' => 'text-muted']);
                    echo $actionform('start', get_string('consensus:start', 'mod_vimipad'), 'btn btn-primary');
                } else {
                    // Voting: member overview with confirmation status.
                    $memberids = array_map(static fn($member) => (int) $member['userid'], $status['members']);
                    $memberusers = empty($memberids) ? [] : $DB->get_records_list('user', 'id', $memberids);
                    $confirmedcount = 0;
                    $iconfirmed = false;
                    echo html_writer::start_tag('ul', ['class' => 'vimipad-consensus-members list-unstyled mb-3']);
                    foreach ($status['members'] as $member) {
                        $memberuser = $memberusers[$member['userid']] ?? null;
                        if ($member['confirmed']) {
                            $confirmedcount++;
                            if ((int) $member['userid'] === (int) $USER->id) {
                                $iconfirmed = true;
                            }
                        }
                        echo html_writer::start_tag('li', ['class' => 'vimipad-consensus-member']);
                        if ($memberuser) {
                            echo $OUTPUT->user_picture($memberuser, ['size' => 28, 'link' => true, 'includefullname' => true]);
                            echo ' ' . html_writer::link(
                                new moodle_url('/message/index.php', ['id' => $memberuser->id]),
                                get_string('journal:message', 'mod_vimipad'),
                                ['class' => 'small']
                            );
                        }
                        $badgetext = $member['confirmed']
                            ? get_string('consensus:confirmed', 'mod_vimipad')
                            : get_string('consensus:pending', 'mod_vimipad');
                        $badgeclass = $member['confirmed'] ? 'badge badge-success' : 'badge badge-secondary';
                        echo ' ' . html_writer::tag('span', $badgetext, ['class' => $badgeclass]);
                        echo html_writer::end_tag('li');
                    }
                    echo html_writer::end_tag('ul');

                    if ($iconfirmed) {
                        echo html_writer::div(get_string('consensus:youconfirmed', 'mod_vimipad'), 'alert alert-info');
                    } else {
                        $islast = (count($status['members']) - $confirmedcount) === 1;
                        $label = $islast
                            ? get_string('consensus:submitfinal', 'mod_vimipad')
                            : get_string('consensus:confirm', 'mod_vimipad');
                        echo $actionform('confirm', $label, 'btn btn-primary', true);
                    }
                    echo ' ' . $actionform('cancel', get_string('consensus:cancel', 'mod_vimipad'), 'btn btn-outline-danger');
                }
            } else {
                // Direct submission (no consensus).
                echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formaction, 'class' => 'mb-4']);
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
        $hasrevisionbuttons = false;

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
                if (!empty($entry->revisionref)) {
                    echo html_writer::tag(
                        'span',
                        ' · ' . get_string('journal:atrevision', 'mod_vimipad', (int) $entry->revisionref),
                        ['class' => 'text-muted small']
                    );
                }
                echo html_writer::div(format_text($entry->entrytext, FORMAT_PLAIN), 'vimipad-journal-text');
                if (!empty($entry->revisionref) && $ws !== null) {
                    echo html_writer::tag('button', get_string('journal:showstate', 'mod_vimipad'), [
                        'type' => 'button',
                        'class' => 'btn btn-sm btn-outline-secondary vimipad-showstate',
                        'data-vimipad-revision' => (int) $entry->revisionref,
                        'data-workspaceid' => (int) $ws->id,
                    ]);
                    echo ' ' . html_writer::tag('button', get_string('revision:playtitle', 'mod_vimipad'), [
                        'type' => 'button',
                        'class' => 'btn btn-sm btn-outline-secondary vimipad-playstate',
                        'data-vimipad-play-revision' => (int) $entry->revisionref,
                        'data-workspaceid' => (int) $ws->id,
                    ]);
                    $hasrevisionbuttons = true;
                }
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('details');
        }

        if ($hasrevisionbuttons) {
            echo html_writer::div('', '', ['id' => 'vimipad-revision-viewer', 'class' => 'vimipad-revision-viewer-host mt-3']);
            $PAGE->requires->js_call_amd('mod_vimipad/revision', 'init', [$cm->id]);
        }
        break;

    default:
        // Unknown tab parameter: nothing to render (the navigation only offers
        // implemented tabs; direct URLs with stale tab names land here).
        break;
}

echo $OUTPUT->footer();
