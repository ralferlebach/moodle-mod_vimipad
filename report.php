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
 * Edit-activity report for a ViMi Pad activity (teacher view).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$workspaceid = optional_param('workspaceid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'vimipad');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vimipad:grade', $context);

$instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
$stats = new \mod_vimipad\local\service\statistics_service();

$PAGE->set_url(new moodle_url('/mod/vimipad/report.php', ['cmid' => $cm->id]));
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

/**
 * Human label for a workspace owner (user, group or course-wide).
 *
 * @param array $row The overview row (with userid, groupid keys).
 * @param array $users Prefetched user records keyed by id.
 * @param array $groups Prefetched group records keyed by id (id, name).
 * @return string The owner label.
 */
function vimipad_owner_label(array $row, array $users, array $groups = []): string {
    if (!empty($row['userid'])) {
        return isset($users[$row['userid']])
            ? fullname($users[$row['userid']])
            : get_string('mode_individual', 'mod_vimipad');
    }
    if (!empty($row['groupid'])) {
        return isset($groups[$row['groupid']])
            ? $groups[$row['groupid']]->name
            : get_string('mode_group', 'mod_vimipad');
    }
    return get_string('mode_course', 'mod_vimipad');
}

/**
 * Human label for an operation type, falling back to the raw key.
 *
 * @param string $type The operation type.
 * @return string The label.
 */
function vimipad_optype_label(string $type): string {
    if (get_string_manager()->string_exists('optype_' . $type, 'mod_vimipad')) {
        return get_string('optype_' . $type, 'mod_vimipad');
    }
    return $type;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:heading', 'mod_vimipad'));

if ($workspaceid) {
    // Detailed view for a single workspace.
    $DB->get_record(
        'vimipad_workspace',
        ['id' => $workspaceid, 'vimipadid' => $instance->id],
        'id',
        MUST_EXIST
    );
    $summary = $stats->workspace_summary($workspaceid);

    echo html_writer::div(html_writer::link(
        new moodle_url('/mod/vimipad/report.php', ['cmid' => $cm->id]),
        get_string('report:backtooverview', 'mod_vimipad')
    ), 'mb-3');

    $totals = new html_table();
    $totals->data[] = [get_string('report:coledits', 'mod_vimipad'), $summary['total']];
    $totals->data[] = [get_string('report:colcontributors', 'mod_vimipad'), $summary['contributors']];
    $totals->data[] = [
        get_string('report:collastactivity', 'mod_vimipad'),
        $summary['lastactivity'] ? userdate($summary['lastactivity']) : '-',
    ];
    echo html_writer::table($totals);

    echo $OUTPUT->heading(get_string('report:bytype', 'mod_vimipad'), 4);
    $typetable = new html_table();
    $typetable->head = [get_string('report:type', 'mod_vimipad'), get_string('report:count', 'mod_vimipad')];
    foreach ($summary['bytype'] as $type => $count) {
        $typetable->data[] = [s(vimipad_optype_label($type)), $count];
    }
    echo html_writer::table($typetable);

    echo $OUTPUT->heading(get_string('report:byuser', 'mod_vimipad'), 4);
    $usertable = new html_table();
    $usertable->head = [get_string('participant', 'mod_vimipad'), get_string('report:count', 'mod_vimipad')];
    $byuserids = array_keys($summary['byuser']);
    $byusers = empty($byuserids) ? [] : $DB->get_records_list('user', 'id', $byuserids);
    foreach ($summary['byuser'] as $uid => $count) {
        $usertable->data[] = [isset($byusers[$uid]) ? fullname($byusers[$uid]) : (string) $uid, $count];
    }
    echo html_writer::table($usertable);
} else {
    // Overview across every workspace of the instance.
    $overview = $stats->instance_overview($instance->id);

    if (empty($overview)) {
        echo html_writer::tag('p', get_string('report:noactivity', 'mod_vimipad'), ['class' => 'text-muted']);
    } else {
        $table = new html_table();
        $table->head = [
            get_string('report:workspace', 'mod_vimipad'),
            get_string('report:coledits', 'mod_vimipad'),
            get_string('report:colcontributors', 'mod_vimipad'),
            get_string('report:collastactivity', 'mod_vimipad'),
        ];
        // Pre-fetch owner records in one query for the whole overview.
        $ownerids = [];
        $ownergroupids = [];
        foreach ($overview as $row) {
            if (!empty($row['userid'])) {
                $ownerids[(int) $row['userid']] = true;
            } else if (!empty($row['groupid'])) {
                $ownergroupids[(int) $row['groupid']] = true;
            }
        }
        $owners = empty($ownerids) ? [] : $DB->get_records_list('user', 'id', array_keys($ownerids));
        $ownergroups = empty($ownergroupids)
            ? []
            : $DB->get_records_list('groups', 'id', array_keys($ownergroupids), '', 'id, name');
        foreach ($overview as $row) {
            $detailurl = new moodle_url(
                '/mod/vimipad/report.php',
                ['cmid' => $cm->id, 'workspaceid' => $row['workspaceid']]
            );
            $table->data[] = [
                html_writer::link($detailurl, s(vimipad_owner_label($row, $owners, $ownergroups))),
                $row['total'],
                $row['contributors'],
                $row['lastactivity'] ? userdate($row['lastactivity']) : '-',
            ];
        }
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
