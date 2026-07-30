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

namespace mod_vimipad\local\output;

use cm_info;
use context_module;
use html_writer;
use html_table;
use moodle_url;
use stdClass;
use mod_vimipad\local\service\ai_feedback_service;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\service\assess_service;
use mod_vimipad\local\service\grading_service;
use mod_vimipad\local\service\journal_service;
use mod_vimipad\local\service\peer_review_service;
use mod_vimipad\local\service\workspace_service;
use mod_vimipad\form\grade_form;

/**
 * Renders the grading detail for one submission and processes its actions.
 *
 * Extracted so the grading UI can live inside the activity's Grading tab (and
 * the legacy grade.php can redirect to it) without duplicating logic. All
 * writes are capability-, context- and sesskey-checked by the caller/handler.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_panel {
    /** @var grade_form|null The advanced-grading form, built once per request. */
    private static $advancedform = null;

    /** @var array|null Cached [form, gradinginstance, itemid] for the advanced path. */
    private static $advancedcached = null;
    /**
     * The Grading-tab URL showing a single submission's detail.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param int $snapshotid The snapshot id.
     * @return moodle_url
     */
    public static function detail_url(cm_info|stdClass $cm, int $snapshotid): moodle_url {
        return new moodle_url('/mod/vimipad/view.php', [
            'id' => $cm->id, 'tab' => 'grade', 'snapshotid' => $snapshotid,
        ]);
    }

    /**
     * Process a grading POST action (if any) and redirect. No-op otherwise.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param stdClass $course The course.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @param stdClass $workspace The snapshot's workspace.
     * @return void
     */
    public static function handle_action(
        cm_info|stdClass $cm,
        stdClass $course,
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        stdClass $workspace
    ): void {
        global $DB, $USER;

        $snapshotid = (int) $snapshot->id;
        $pageurl = self::detail_url($cm, $snapshotid);

        if (optional_param('addannotation', 0, PARAM_BOOL) && confirm_sesskey()) {
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

        if (optional_param('setreference', 0, PARAM_BOOL) && confirm_sesskey()) {
            require_capability('mod/vimipad:grade', $context);
            $makeref = optional_param('makeref', 0, PARAM_BOOL);
            $DB->set_field('vimipad', 'referencesnapshotid', $makeref ? $snapshotid : null, ['id' => $instance->id]);
            redirect(
                $pageurl,
                get_string($makeref ? 'referenceset' : 'referencecleared', 'mod_vimipad')
            );
        }

        if (optional_param('genai', 0, PARAM_BOOL) && confirm_sesskey()) {
            require_capability('mod/vimipad:useai', $context);
            $notes = optional_param('ainotes', '', PARAM_TEXT);
            $points = optional_param('aipoints', '', PARAM_RAW_TRIMMED);
            $pointsval = ($points === '') ? null : (int) $points;

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
                (new ai_feedback_service())->generate_draft(
                    $context,
                    $instance,
                    $snapshot,
                    $notes,
                    $pointsval,
                    (int) $USER->id
                );
                redirect($pageurl, get_string('ai:draftgenerated', 'mod_vimipad'));
            } catch (\moodle_exception $e) {
                redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
            }
        }

        if (optional_param('acceptai', 0, PARAM_BOOL) && confirm_sesskey()) {
            require_capability('mod/vimipad:useai', $context);
            $aifeedbackid = required_param('aifeedbackid', PARAM_INT);
            $acceptedtext = required_param('acceptedtext', PARAM_TEXT);
            (new ai_feedback_service())->accept_draft($aifeedbackid, $snapshotid, $acceptedtext);
            redirect($pageurl, get_string('ai:draftaccepted', 'mod_vimipad'));
        }

        $advanced = self::resolve_advanced($cm, $context, $instance, $snapshot);
        if ($advanced !== null) {
            [$form, $gradinginstance, $itemid] = $advanced;
            if ($data = $form->get_data()) {
                $grade = $gradinginstance->submit_and_get_grade($data->advancedgrading, $itemid);
                self::store_instance($itemid, (int) $USER->id, (int) $gradinginstance->get_id());
                (new grading_service())->save_grade(
                    $instance,
                    $workspace,
                    $itemid,
                    ($grade < 0 ? null : (float) $grade),
                    $data->feedback,
                    FORMAT_PLAIN,
                    (int) $USER->id
                );
                redirect(
                    new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'grade']),
                    get_string('gradesaved', 'mod_vimipad')
                );
            }
        } else if (optional_param('savegrade', 0, PARAM_BOOL) && confirm_sesskey()) {
            $gradeval = optional_param('grade', '', PARAM_RAW_TRIMMED);
            $feedback = optional_param('feedback', '', PARAM_TEXT);
            $rawgrade = ($gradeval === '') ? null : (float) $gradeval;
            (new grading_service())->save_grade(
                $instance,
                $workspace,
                $snapshotid,
                $rawgrade,
                $feedback,
                FORMAT_PLAIN,
                (int) $USER->id
            );
            redirect(
                new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'grade']),
                get_string('gradesaved', 'mod_vimipad')
            );
        }

        if (optional_param('reopen', 0, PARAM_BOOL) && confirm_sesskey()) {
            (new workspace_service())->reopen((int) $workspace->id);
            redirect($pageurl, get_string('reopened', 'mod_vimipad'));
        }
    }

    /**
     * Render the grading detail for a snapshot.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @param stdClass $workspace The snapshot's workspace.
     * @return void
     */
    public static function render(
        cm_info|stdClass $cm,
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        stdClass $workspace
    ): void {
        global $DB, $USER, $OUTPUT;

        $snapshotid = (int) $snapshot->id;
        $pageurl = self::detail_url($cm, $snapshotid);

        // Back to the submissions list.
        echo html_writer::div(html_writer::link(
            new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'grade']),
            get_string('gradetab:back', 'mod_vimipad'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        ), 'mb-3');

        // Offer to reopen the workspace for revision while it is locked.
        if ((int) $workspace->locked === 1) {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'mb-3']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reopen', 'value' => 1]);
            echo html_writer::tag('p', get_string('reopen_help', 'mod_vimipad'), ['class' => 'text-muted small mb-1']);
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('reopen', 'mod_vimipad'),
                'class' => 'btn btn-outline-secondary',
            ]);
            echo html_writer::end_tag('form');
        }

        // Render the snapshot read-only.
        $data = json_decode($snapshot->snapshotjson, true);
        $labels = [];
        foreach (($data['nodes'] ?? []) as $node) {
            $labels[$node['stableid']] = $node['label'];
        }

        echo html_writer::tag('h3', get_string('profile_' . $data['profile'], 'mod_vimipad'));
        echo html_writer::tag('h4', get_string('editor:relations', 'mod_vimipad'));
        if (empty($data['relations'])) {
            echo html_writer::tag('p', get_string('editor:norelations', 'mod_vimipad'), ['class' => 'text-muted']);
        } else {
            $table = new html_table();
            $table->head = [
                get_string('editor:subject', 'mod_vimipad'),
                get_string('editor:relation', 'mod_vimipad'),
                get_string('editor:object', 'mod_vimipad'),
            ];
            foreach ($data['relations'] as $rel) {
                $table->data[] = [
                    s($labels[$rel['sourceid']] ?? $rel['sourceid']),
                    s($rel['label'] !== '' ? $rel['label'] : $rel['type']),
                    s($labels[$rel['targetid']] ?? $rel['targetid']),
                ];
            }
            echo html_writer::table($table);
        }

        // Existing annotations.
        $annotations = $DB->get_records('vimipad_annotation', ['snapshotid' => $snapshotid], 'timecreated ASC');
        if ($annotations) {
            echo html_writer::tag('h4', get_string('annotations', 'mod_vimipad'));
            $list = [];
            foreach ($annotations as $annotation) {
                $target = $annotation->targetstableid && isset($labels[$annotation->targetstableid])
                    ? $labels[$annotation->targetstableid] : $annotation->targettype;
                $list[] = html_writer::tag('li', s($target) . ': ' . s($annotation->commenttext));
            }
            echo html_writer::tag('ul', implode('', $list));
        }

        // Teacher-visible learner journal for this workspace.
        $journal = (new journal_service())->get_teacher_visible((int) $snapshot->workspaceid);
        if ($journal) {
            echo html_writer::tag('h4', get_string('journal:teacherheading', 'mod_vimipad'), ['class' => 'mt-4']);
            $authorids = [];
            foreach ($journal as $entry) {
                $authorids[(int) $entry->userid] = true;
            }
            $authors = empty($authorids) ? [] : $DB->get_records_list('user', 'id', array_keys($authorids));
            $jitems = [];
            foreach ($journal as $entry) {
                $author = $authors[$entry->userid] ?? null;
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

        self::render_annotation_form($pageurl, $data, $labels);
        $acceptedforfeedback = self::render_ai_assistance($context, $instance, $snapshot, $pageurl);
        self::render_assessment($cm, $context, $instance, $snapshot, $pageurl);
        self::render_peer_summary($instance, $snapshot);
        if (self::$advancedform !== null) {
            echo html_writer::tag('h4', get_string('gradingmethod', 'mod_vimipad'), ['class' => 'mt-4']);
            self::$advancedform->display();
        } else {
            self::render_grade_form($instance, $workspace, $pageurl, $acceptedforfeedback);
        }
    }

    /**
     * Render the aggregated peer reviews for this submission, if any exist.
     *
     * Reviewer identities are deliberately omitted: the teacher sees the spread of
     * peer judgements, not who said what.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @return void
     */
    private static function render_peer_summary(stdClass $instance, stdClass $snapshot): void {
        if (empty($instance->peerreviewmode)) {
            return;
        }

        $service = new peer_review_service();
        $aggregate = $service->aggregate((int) $snapshot->id);
        if ($aggregate['count'] === 0 && $aggregate['pending'] === 0) {
            return;
        }

        echo html_writer::tag('h4', get_string('peerreviewaggregate', 'mod_vimipad'), ['class' => 'mt-4']);
        echo html_writer::div(get_string('peerreviewaggregatedetail', 'mod_vimipad', (object) [
            'count' => $aggregate['count'],
            'mean' => ($aggregate['mean'] === null) ? '-' : round($aggregate['mean'] * 100),
            'median' => ($aggregate['median'] === null) ? '-' : round($aggregate['median'] * 100),
            'pending' => $aggregate['pending'],
        ]), 'alert alert-secondary');

        foreach ($service->for_snapshot((int) $snapshot->id, true) as $review) {
            if (trim((string) $review->reviewcomment) === '') {
                continue;
            }
            echo html_writer::div(s($review->reviewcomment), 'small border-left pl-2 mb-1');
        }
    }

    /**
     * Render the automatic-assessment aid: reference marking and the scorer's suggestion.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @param moodle_url $pageurl The detail page URL.
     * @return void
     */
    private static function render_assessment(
        cm_info|stdClass $cm,
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        moodle_url $pageurl
    ): void {
        global $OUTPUT;

        $snapshotid = (int) $snapshot->id;
        $referenceid = (int) ($instance->referencesnapshotid ?? 0);

        echo html_writer::tag('h4', get_string('assessment', 'mod_vimipad'), ['class' => 'mt-4']);

        if ($referenceid === $snapshotid) {
            echo $OUTPUT->notification(get_string('isreference', 'mod_vimipad'), 'info');
            echo self::reference_button($pageurl, false);
        } else {
            echo self::reference_button($pageurl, true);
            if ($referenceid === 0) {
                echo html_writer::div(get_string('noreference', 'mod_vimipad'), 'text-muted small mt-2');
            }
        }

        $results = (new assess_service())->score_all($instance, $snapshotid);
        foreach ($results as $entry) {
            echo html_writer::tag('h5', $entry['name'], ['class' => 'mt-3']);
            self::render_result($instance, $entry['result']);
        }

        self::render_ai_assessment($context, $instance, $snapshot, $pageurl);

        if (empty($results) && registry::get('llm') === null) {
            echo html_writer::div(get_string('scoreunavailable', 'mod_vimipad'), 'text-muted small mt-2');
        }
    }

    /**
     * Render the on-demand AI scorer: a button, and its suggestion once requested.
     *
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @param moodle_url $pageurl The detail page URL.
     * @return void
     */
    private static function render_ai_assessment(
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        moodle_url $pageurl
    ): void {
        global $USER, $OUTPUT;

        $scorer = registry::get('llm');
        if ($scorer === null || !$scorer->uses_ai()) {
            return;
        }

        echo html_writer::tag('h5', $scorer->get_name(), ['class' => 'mt-3']);

        if (!optional_param('runai', 0, PARAM_BOOL)) {
            $runurl = new moodle_url($pageurl, ['runai' => 1, 'sesskey' => sesskey()]);
            echo html_writer::link(
                $runurl,
                get_string('runai', 'mod_vimipad'),
                ['class' => 'btn btn-sm btn-outline-info']
            );
            echo html_writer::div(get_string('runaihint', 'mod_vimipad'), 'text-muted small mt-1');
            return;
        }

        if (!confirm_sesskey()) {
            return;
        }
        try {
            $result = (new assess_service())->score_ai($context, $instance, (int) $snapshot->id, (int) $USER->id);
        } catch (\moodle_exception $e) {
            echo $OUTPUT->notification($e->getMessage(), 'error');
            return;
        }
        if ($result === null) {
            echo html_writer::div(get_string('scoreunavailable', 'mod_vimipad'), 'text-muted small');
            return;
        }
        self::render_result($instance, $result);
    }

    /**
     * The reference mark/unmark button (a small self-posting form).
     *
     * @param moodle_url $pageurl The detail page URL.
     * @param bool $makeref True to offer marking as reference, false to offer removing it.
     * @return string
     */
    private static function reference_button(moodle_url $pageurl, bool $makeref): string {
        $label = get_string($makeref ? 'markreference' : 'unmarkreference', 'mod_vimipad');
        $class = 'btn btn-sm ' . ($makeref ? 'btn-outline-primary' : 'btn-outline-secondary');
        $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'setreference', 'value' => 1])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'makeref', 'value' => $makeref ? 1 : 0])
            . html_writer::empty_tag('input', ['type' => 'submit', 'value' => $label, 'class' => $class]);
        return html_writer::tag('form', $fields, ['method' => 'post', 'action' => $pageurl->out(false)]);
    }

    /**
     * Render a scorer result as a grading aid (a suggestion, never applied).
     *
     * @param stdClass $instance The activity instance.
     * @param result $result The scorer result.
     * @return void
     */
    private static function render_result(stdClass $instance, result $result): void {
        if ($result->informational) {
            foreach ($result->metrics as $label => $value) {
                echo html_writer::div(
                    html_writer::tag('span', s($label) . ': ', ['class' => 'font-weight-bold']) . s($value),
                    'small'
                );
            }
            echo html_writer::div(get_string('structuralnote', 'mod_vimipad'), 'text-muted small mt-1');
            return;
        }

        $max = (float) $instance->grade;
        if ($max <= 0) {
            $max = 100.0;
        }
        $data = (object) [
            'percent' => round($result->score * 100),
            'grade' => format_float($result->suggested_grade($max), 2),
            'max' => format_float($max, 2),
        ];
        echo html_writer::div(get_string('scoresuggestion', 'mod_vimipad', $data), 'alert alert-secondary mt-2');

        $hasbreakdown = !empty($result->concepts['matched']) || !empty($result->concepts['missing'])
            || !empty($result->concepts['extra']) || !empty($result->propositions['matched'])
            || !empty($result->propositions['missing']) || !empty($result->propositions['extra']);
        if ($hasbreakdown) {
            $partlines = [];
            foreach ($result->partscores as $dimension => $value) {
                $partlines[] = get_string('scorepart:' . $dimension, 'mod_vimipad') . ' ' . round($value * 100) . '%';
            }
            if (!empty($partlines)) {
                echo html_writer::tag('p', implode(', ', $partlines) . '.', ['class' => 'small']);
            }
            self::render_breakdown(get_string('concepts', 'mod_vimipad'), $result->concepts);
            self::render_breakdown(get_string('propositions', 'mod_vimipad'), $result->propositions);
        }

        if (!empty($result->metrics['rationale'])) {
            echo html_writer::div(s($result->metrics['rationale']), 'small mt-1');
        }
    }

    /**
     * Render one matched/missing/extra breakdown block.
     *
     * @param string $title The dimension title.
     * @param array $breakdown The matched/missing/extra lists.
     * @return void
     */
    private static function render_breakdown(string $title, array $breakdown): void {
        echo html_writer::tag('p', $title, ['class' => 'mb-0 mt-2 font-weight-bold']);
        foreach (['matched' => 'success', 'missing' => 'danger', 'extra' => 'warning'] as $key => $variant) {
            $items = $breakdown[$key] ?? [];
            if (empty($items)) {
                continue;
            }
            $line = get_string('breakdown_' . $key, 'mod_vimipad') . ': ' . implode(', ', $items);
            echo html_writer::div(s($line), 'small text-' . $variant);
        }
    }

    /**
     * Resolve the active advanced-grading form for this submission, if any.
     *
     * Builds the grade form (with the rubric / marking guide element) once per
     * request and stashes it for rendering. Returns null when no advanced method
     * is active, so the caller falls back to the numeric grade.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot being graded.
     * @return array|null [grade_form $form, \gradingform_instance $gradinginstance, int $itemid] or null.
     */
    private static function resolve_advanced(
        cm_info|stdClass $cm,
        context_module $context,
        stdClass $instance,
        stdClass $snapshot
    ): ?array {
        global $CFG, $DB, $USER;

        if (self::$advancedform !== null) {
            return self::$advancedcached;
        }

        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $manager = get_grading_manager($context, 'mod_vimipad', 'submissions');
        if (!$manager->get_active_method()) {
            return null;
        }
        $controller = $manager->get_active_controller();
        if (!$controller || !$controller->is_form_available()) {
            return null;
        }

        $itemid = (int) $snapshot->id;
        $storedid = (int) ($DB->get_field(
            'vimipad_gradeinstance',
            'instanceid',
            ['snapshotid' => $itemid, 'raterid' => (int) $USER->id]
        ) ?: 0);
        $gradinginstance = $controller->get_or_create_instance($storedid, (int) $USER->id, $itemid);

        $feedbackrecord = $DB->get_records('vimipad_grade', ['snapshotid' => $itemid], '', 'id, feedback', 0, 1);
        $feedback = $feedbackrecord ? (string) reset($feedbackrecord)->feedback : '';

        $form = new grade_form(self::detail_url($cm, $itemid)->out(false), [
            'cmid' => (int) $cm->id,
            'snapshotid' => $itemid,
            'maxgrade' => $instance->grade,
            'gradinginstance' => $gradinginstance,
            'feedback' => $feedback,
        ]);

        self::$advancedform = $form;
        self::$advancedcached = [$form, $gradinginstance, $itemid];
        return self::$advancedcached;
    }

    /**
     * Persist the advanced-grading instance id for this submission and grader.
     *
     * @param int $snapshotid The snapshot (itemid).
     * @param int $raterid The grader.
     * @param int $instanceid The gradingform instance id.
     * @return void
     */
    private static function store_instance(int $snapshotid, int $raterid, int $instanceid): void {
        global $DB;

        $existing = $DB->get_record('vimipad_gradeinstance', ['snapshotid' => $snapshotid, 'raterid' => $raterid]);
        $record = (object) [
            'snapshotid' => $snapshotid,
            'raterid' => $raterid,
            'instanceid' => $instanceid,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('vimipad_gradeinstance', $record);
        } else {
            $DB->insert_record('vimipad_gradeinstance', $record);
        }
    }

    /**
     * Render the add-annotation form.
     *
     * @param moodle_url $pageurl The form action URL.
     * @param array $data The decoded snapshot.
     * @param array $labels stableid => label map.
     * @return void
     */
    private static function render_annotation_form(moodle_url $pageurl, array $data, array $labels): void {
        echo html_writer::tag('h4', get_string('addannotation', 'mod_vimipad'));
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addannotation', 'value' => 1]);

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
    }

    /**
     * Render the AI feedback assistance block.
     *
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param stdClass $snapshot The snapshot.
     * @param moodle_url $pageurl The form action URL.
     * @return string The accepted AI text, for pre-filling feedback (or '').
     */
    private static function render_ai_assistance(
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        moodle_url $pageurl
    ): string {
        global $USER;

        if (!ai_feedback_service::is_available($context, $instance)) {
            return '';
        }
        $snapshotid = (int) $snapshot->id;
        $latest = (new ai_feedback_service())->get_latest($snapshotid);

        echo html_writer::tag('h4', get_string('ai:heading', 'mod_vimipad'), ['class' => 'mt-4']);
        echo html_writer::tag('p', get_string('ai:intro', 'mod_vimipad'), ['class' => 'text-muted']);
        if (!ai_feedback_service::policy_accepted((int) $USER->id)) {
            echo html_writer::div(get_string('ai:policyrequired', 'mod_vimipad'), 'alert alert-warning');
        }

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

        if ($latest && $latest->drafttext !== null && $latest->drafttext !== '') {
            echo html_writer::tag('h5', get_string('ai:draft', 'mod_vimipad'), ['class' => 'mt-3']);
            if (!empty($latest->providerinfo)) {
                echo html_writer::tag('p', s($latest->providerinfo), ['class' => 'text-muted small']);
            }
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'acceptai', 'value' => 1]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'aifeedbackid', 'value' => $latest->id]);
            echo html_writer::tag('label', get_string('ai:editaccept', 'mod_vimipad'), ['for' => 'vimipad-ai-accepted']);
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
            return (string) $latest->acceptedtext;
        }
        return '';
    }

    /**
     * Render the grade form.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The workspace.
     * @param moodle_url $pageurl The form action URL.
     * @param string $acceptedforfeedback Accepted AI text to pre-fill (or '').
     * @return void
     */
    private static function render_grade_form(
        stdClass $instance,
        stdClass $workspace,
        moodle_url $pageurl,
        string $acceptedforfeedback
    ): void {
        global $DB;

        echo html_writer::tag('h4', get_string('grade', 'mod_vimipad'), ['class' => 'mt-4']);
        $currentgrade = $DB->get_record(
            'vimipad_grade',
            ['vimipadid' => $instance->id, 'userid' => $workspace->userid]
        );

        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrade', 'value' => 1]);
        echo html_writer::tag('label', get_string('gradeoutof', 'mod_vimipad', $instance->grade), ['for' => 'vimipad-grade']);
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
    }
}
