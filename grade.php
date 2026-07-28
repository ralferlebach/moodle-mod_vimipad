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
 * Teacher snapshot viewer and grading page.
 *
 * Renders a submitted snapshot read-only (server-side, no JS dependency),
 * lets a grader add annotations and record a grade with feedback. All writes
 * are capability-, context- and sesskey-checked.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_vimipad\local\service\ai_feedback_service;
use mod_vimipad\local\service\grading_service;
use mod_vimipad\local\service\snapshot_service;

$id = required_param('id', PARAM_INT);
$snapshotid = required_param('snapshotid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'vimipad');
$instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/vimipad:grade', $context);

$snapshot = $DB->get_record('vimipad_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);
$workspace = $DB->get_record(
    'vimipad_workspace',
    ['id' => $snapshot->workspaceid, 'vimipadid' => $instance->id],
    '*',
    MUST_EXIST
);

$pageurl = new moodle_url('/mod/vimipad/grade.php', ['id' => $cm->id, 'snapshotid' => $snapshotid]);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('gradetitle', 'mod_vimipad'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle annotation submission.
$addannotation = optional_param('addannotation', 0, PARAM_BOOL);
if ($addannotation && confirm_sesskey()) {
    // The target is a combined value: "map", "node:<stableid>" or "relation:<stableid>".
    $rawtarget = optional_param('annotationtarget', 'map', PARAM_RAW);
    $targettype = 'map';
    $targetstableid = '';
    if (strpos($rawtarget, ':') !== false) {
        [$ttype, $tid] = explode(':', $rawtarget, 2);
        if (in_array($ttype, ['node', 'relation'], true)) {
            $targettype = $ttype;
            $targetstableid = clean_param($tid, PARAM_ALPHANUMEXT);
        }
    }
    $commenttext = required_param('commenttext', PARAM_TEXT);

    if (trim($commenttext) !== '') {
        $DB->insert_record('vimipad_annotation', (object) [
            'snapshotid' => $snapshotid,
            'targettype' => $targettype,
            'targetstableid' => $targetstableid !== '' ? $targetstableid : null,
            'commenttext' => $commenttext,
            'commentformat' => FORMAT_PLAIN,
            'userid' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
    redirect($pageurl, get_string('annotationadded', 'mod_vimipad'));
}

// Handle AI feedback draft generation.
$genai = optional_param('genai', 0, PARAM_BOOL);
if ($genai && confirm_sesskey()) {
    require_capability('mod/vimipad:useai', $context);
    $notes = optional_param('ainotes', '', PARAM_TEXT);
    $points = optional_param('aipoints', '', PARAM_RAW_TRIMMED);
    $pointsval = ($points === '') ? null : (int) $points;

    $aiservice = new ai_feedback_service();
    if (!ai_feedback_service::is_available($context, $instance)) {
        redirect(
            $pageurl,
            get_string('error:aiunavailable', 'mod_vimipad'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if (!ai_feedback_service::policy_accepted((int) $USER->id)) {
        redirect(
            $pageurl,
            get_string('ai:policyrequired', 'mod_vimipad'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    try {
        $aiservice->generate_draft($context, $instance, $snapshot, $notes, $pointsval, (int) $USER->id);
        redirect($pageurl, get_string('ai:draftgenerated', 'mod_vimipad'));
    } catch (\moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Handle AI draft acceptance.
$acceptai = optional_param('acceptai', 0, PARAM_BOOL);
if ($acceptai && confirm_sesskey()) {
    require_capability('mod/vimipad:useai', $context);
    $aifeedbackid = required_param('aifeedbackid', PARAM_INT);
    $acceptedtext = required_param('acceptedtext', PARAM_TEXT);

    $aiservice = new ai_feedback_service();
    $aiservice->accept_draft($aifeedbackid, $snapshotid, $acceptedtext);
    redirect($pageurl, get_string('ai:draftaccepted', 'mod_vimipad'));
}

// Handle grade submission.
$savegrade = optional_param('savegrade', 0, PARAM_BOOL);
if ($savegrade && confirm_sesskey()) {
    $gradeval = optional_param('grade', '', PARAM_RAW_TRIMMED);
    $feedback = optional_param('feedback', '', PARAM_TEXT);

    $rawgrade = ($gradeval === '') ? null : (float) $gradeval;
    $service = new grading_service();
    $service->save_grade(
        $instance,
        $workspace,
        $snapshotid,
        $rawgrade,
        $feedback,
        FORMAT_PLAIN,
        (int) $USER->id
    );

    redirect(
        new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id]),
        get_string('gradesaved', 'mod_vimipad')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradetitle', 'mod_vimipad'));

// Render the snapshot read-only.
$data = json_decode($snapshot->snapshotjson, true);
$labels = [];
if (!empty($data['nodes'])) {
    foreach ($data['nodes'] as $node) {
        $labels[$node['stableid']] = $node['label'];
    }
}

echo html_writer::tag('h3', get_string('profile_' . $data['profile'], 'mod_vimipad'));

echo html_writer::tag('h4', get_string('editor:relations', 'mod_vimipad'));
if (empty($data['relations'])) {
    echo html_writer::tag(
        'p',
        get_string('editor:norelations', 'mod_vimipad'),
        ['class' => 'text-muted']
    );
} else {
    $table = new html_table();
    $table->head = [
        get_string('editor:subject', 'mod_vimipad'),
        get_string('editor:relation', 'mod_vimipad'),
        get_string('editor:object', 'mod_vimipad'),
    ];
    foreach ($data['relations'] as $rel) {
        $source = $labels[$rel['sourceid']] ?? $rel['sourceid'];
        $target = $labels[$rel['targetid']] ?? $rel['targetid'];
        $table->data[] = [
            s($source),
            s($rel['label'] !== '' ? $rel['label'] : $rel['type']),
            s($target),
        ];
    }
    echo html_writer::table($table);
}

// Existing annotations.
$annotations = $DB->get_records('vimipad_annotation', ['snapshotid' => $snapshotid], 'timecreated ASC');
if ($annotations) {
    echo html_writer::tag('h4', get_string('annotations', 'mod_vimipad'));
    $list = [];
    foreach ($annotations as $a) {
        $target = $a->targetstableid && isset($labels[$a->targetstableid])
            ? $labels[$a->targetstableid] : $a->targettype;
        $list[] = html_writer::tag('li', s($target) . ': ' . s($a->commenttext));
    }
    echo html_writer::tag('ul', implode('', $list));
}

// Teacher-visible learner journal for this workspace.
$journal = (new \mod_vimipad\local\service\journal_service())->get_teacher_visible((int) $snapshot->workspaceid);
if ($journal) {
    echo html_writer::tag('h4', get_string('journal:teacherheading', 'mod_vimipad'), ['class' => 'mt-4']);
    $jitems = [];
    foreach ($journal as $entry) {
        $author = $DB->get_record('user', ['id' => $entry->userid]);
        $meta = ($author ? fullname($author) : (string) $entry->userid) . ' · ' . userdate($entry->timecreated);
        $jitems[] = html_writer::tag(
            'li',
            html_writer::tag('div', s($meta), ['class' => 'text-muted small']) .
            html_writer::tag('div', s($entry->entrytext)),
            ['class' => 'mb-2']
        );
    }
    echo html_writer::tag('ul', implode('', $jitems), ['class' => 'list-unstyled']);
}

// Add annotation form.
echo html_writer::tag('h4', get_string('addannotation', 'mod_vimipad'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addannotation', 'value' => 1]);

// Target selector: whole map, or a specific node or relation of the snapshot.
$targetoptions = ['map' => get_string('annotationtarget_map', 'mod_vimipad')];
foreach (($data['nodes'] ?? []) as $node) {
    $targetoptions['node:' . $node['stableid']] =
        get_string('annotationtarget_node', 'mod_vimipad', $node['label']);
}
foreach (($data['relations'] ?? []) as $rel) {
    if (empty($rel['stableid'])) {
        continue;
    }
    $rlabel = $rel['label'] !== ''
        ? $rel['label']
        : (($labels[$rel['sourceid']] ?? '?') . ' → ' . ($labels[$rel['targetid']] ?? '?'));
    $targetoptions['relation:' . $rel['stableid']] =
        get_string('annotationtarget_relation', 'mod_vimipad', $rlabel);
}
echo html_writer::tag(
    'label',
    get_string('annotationtarget', 'mod_vimipad'),
    ['for' => 'vimipad-annotation-target']
);
echo html_writer::select(
    $targetoptions,
    'annotationtarget',
    'map',
    false,
    ['id' => 'vimipad-annotation-target', 'class' => 'form-select mb-2']
);
echo html_writer::tag(
    'label',
    get_string('annotationtext', 'mod_vimipad'),
    ['for' => 'vimipad-annotation-text']
);
echo html_writer::tag(
    'textarea',
    '',
    ['id' => 'vimipad-annotation-text', 'name' => 'commenttext', 'rows' => 3, 'class' => 'form-control']
);
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'value' => get_string('add', 'mod_vimipad'), 'class' => 'btn btn-secondary mt-2']
);
echo html_writer::end_tag('form');

// AI feedback assistance (teacher-in-the-loop).
$acceptedforfeedback = '';
if (ai_feedback_service::is_available($context, $instance)) {
    $aiservice = new ai_feedback_service();
    $latest = $aiservice->get_latest($snapshotid);

    echo html_writer::tag('h4', get_string('ai:heading', 'mod_vimipad'), ['class' => 'mt-4']);
    echo html_writer::tag('p', get_string('ai:intro', 'mod_vimipad'), ['class' => 'text-muted']);

    if (!ai_feedback_service::policy_accepted((int) $USER->id)) {
        echo html_writer::div(get_string('ai:policyrequired', 'mod_vimipad'), 'alert alert-warning');
    }

    // Generate form.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'genai', 'value' => 1]);
    echo html_writer::tag('label', get_string('ai:notes', 'mod_vimipad'), ['for' => 'vimipad-ai-notes']);
    echo html_writer::tag(
        'textarea',
        '',
        ['id' => 'vimipad-ai-notes', 'name' => 'ainotes', 'rows' => 2, 'class' => 'form-control']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('ai:generate', 'mod_vimipad'),
        'class' => 'btn btn-outline-primary mt-2',
    ]);
    echo html_writer::end_tag('form');

    // Show the latest draft for editing and acceptance.
    if ($latest && $latest->drafttext !== null && $latest->drafttext !== '') {
        echo html_writer::tag('h5', get_string('ai:draft', 'mod_vimipad'), ['class' => 'mt-3']);
        if (!empty($latest->providerinfo)) {
            echo html_writer::tag('p', s($latest->providerinfo), ['class' => 'text-muted small']);
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'acceptai', 'value' => 1]);
        echo html_writer::empty_tag(
            'input',
            ['type' => 'hidden', 'name' => 'aifeedbackid', 'value' => $latest->id]
        );
        echo html_writer::tag(
            'label',
            get_string('ai:editaccept', 'mod_vimipad'),
            ['for' => 'vimipad-ai-accepted']
        );
        $draftshown = $latest->acceptedtext !== null && $latest->acceptedtext !== ''
            ? $latest->acceptedtext : $latest->drafttext;
        echo html_writer::tag(
            'textarea',
            s($draftshown),
            ['id' => 'vimipad-ai-accepted', 'name' => 'acceptedtext', 'rows' => 6, 'class' => 'form-control']
        );
        echo html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => get_string('ai:accept', 'mod_vimipad'),
            'class' => 'btn btn-primary mt-2',
        ]);
        echo html_writer::end_tag('form');
    }

    if ($latest && $latest->acceptedtext !== null && $latest->acceptedtext !== '') {
        $acceptedforfeedback = $latest->acceptedtext;
    }
}

// Grade form.
echo html_writer::tag('h4', get_string('grade', 'mod_vimipad'), ['class' => 'mt-4']);
$currentgrade = $DB->get_record(
    'vimipad_grade',
    ['vimipadid' => $instance->id, 'userid' => $workspace->userid]
);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrade', 'value' => 1]);
echo html_writer::tag(
    'label',
    get_string('gradeoutof', 'mod_vimipad', $instance->grade),
    ['for' => 'vimipad-grade']
);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'step' => 'any', 'min' => 0, 'max' => $instance->grade,
    'id' => 'vimipad-grade', 'name' => 'grade', 'class' => 'form-control',
    'value' => $currentgrade && $currentgrade->grade !== null ? $currentgrade->grade : '',
]);
echo html_writer::tag(
    'label',
    get_string('feedback', 'mod_vimipad'),
    ['for' => 'vimipad-feedback', 'class' => 'mt-2']
);
$feedbackvalue = '';
if ($currentgrade && $currentgrade->feedback !== null && $currentgrade->feedback !== '') {
    $feedbackvalue = $currentgrade->feedback;
} else if ($acceptedforfeedback !== '') {
    $feedbackvalue = $acceptedforfeedback;
}
echo html_writer::tag(
    'textarea',
    s($feedbackvalue),
    ['id' => 'vimipad-feedback', 'name' => 'feedback', 'rows' => 3, 'class' => 'form-control']
);
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'value' => get_string('savegrade', 'mod_vimipad'), 'class' => 'btn btn-primary mt-2']
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
