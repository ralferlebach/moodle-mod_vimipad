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
use html_table;
use html_writer;
use mod_vimipad\local\policy\request_policy;
use mod_vimipad\local\service\peer_review_service;
use moodle_url;
use stdClass;

/**
 * The peer review tab: a reviewer's allocations and the review form.
 *
 * Reviews are anonymous in both directions: a reviewer sees "Submission 1", never
 * the author's name, and authors see aggregated peer scores without reviewer
 * identities. The automatic scorers' guidance is offered alongside the map so a
 * reviewer has something concrete to react to, but the peer verdict stays theirs.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_review_panel {
    /**
     * The URL of one review's detail page.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param int $reviewid The review record id.
     * @return moodle_url
     */
    public static function detail_url(cm_info|stdClass $cm, int $reviewid): moodle_url {
        return new moodle_url('/mod/vimipad/view.php', [
            'id' => $cm->id, 'tab' => 'peer', 'reviewid' => $reviewid,
        ]);
    }

    /**
     * Process a review submission (if posted) and redirect. No-op otherwise.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param int $userid The acting user.
     * @return void
     */
    public static function handle_action(
        cm_info|stdClass $cm,
        context_module $context,
        stdClass $instance,
        int $userid
    ): void {
        // Saving a review changes state, so only a POST may trigger it.
        if (!request_policy::is_mutating_request()) {
            return;
        }
        if (!optional_param('savereview', 0, PARAM_BOOL) || !confirm_sesskey()) {
            return;
        }
        require_capability('mod/vimipad:peerreview', $context);

        $reviewid = required_param('reviewid', PARAM_INT);
        $service = new peer_review_service();
        $review = self::own_review($service, $instance, $userid, $reviewid);
        if ($review === null) {
            throw new \moodle_exception('error:notallocated', 'mod_vimipad');
        }

        $rawscore = optional_param('peerscore', '', PARAM_RAW_TRIMMED);
        $score = ($rawscore === '') ? null : ((float) $rawscore) / 100.0;
        $comment = optional_param('peercomment', '', PARAM_TEXT);

        $service->save_review((int) $review->snapshotid, $userid, $score, $comment);
        redirect(
            new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'peer']),
            get_string('peerreviewsaved', 'mod_vimipad')
        );
    }

    /**
     * Render the tab: either the reviewer's list or one review's detail.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param context_module $context The module context.
     * @param stdClass $instance The activity instance.
     * @param int $userid The acting user.
     * @return void
     */
    public static function render(
        cm_info|stdClass $cm,
        context_module $context,
        stdClass $instance,
        int $userid
    ): void {
        global $OUTPUT;

        if (empty($instance->peerreviewmode)) {
            echo $OUTPUT->notification(get_string('peerreviewdisabled', 'mod_vimipad'), 'info');
            return;
        }
        if (!has_capability('mod/vimipad:peerreview', $context)) {
            echo $OUTPUT->notification(get_string('peerreviewnoaccess', 'mod_vimipad'), 'info');
            return;
        }

        $service = new peer_review_service();
        $reviewid = optional_param('reviewid', 0, PARAM_INT);
        if ($reviewid) {
            $review = self::own_review($service, $instance, $userid, $reviewid);
            if ($review !== null) {
                self::render_detail($cm, $instance, $review);
                return;
            }
        }
        self::render_list($cm, $service, $instance, $userid);
    }

    /**
     * Render the reviewer's allocated submissions.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param peer_review_service $service The peer review service.
     * @param stdClass $instance The activity instance.
     * @param int $userid The acting user.
     * @return void
     */
    private static function render_list(
        cm_info|stdClass $cm,
        peer_review_service $service,
        stdClass $instance,
        int $userid
    ): void {
        $reviews = $service->for_reviewer($instance, $userid);
        if (empty($reviews)) {
            echo html_writer::div(get_string('peerreviewnone', 'mod_vimipad'), 'text-muted');
            return;
        }

        $table = new html_table();
        $table->head = [
            get_string('peerreviewsubmission', 'mod_vimipad'),
            get_string('peerreviewstatus', 'mod_vimipad'),
            '',
        ];

        $index = 0;
        foreach ($reviews as $review) {
            $index++;
            $done = (int) $review->status === peer_review_service::STATUS_SUBMITTED;
            $status = $done
                ? get_string('peerreviewdone', 'mod_vimipad')
                : get_string('peerreviewpending', 'mod_vimipad');
            $action = html_writer::link(
                self::detail_url($cm, (int) $review->id),
                get_string($done ? 'peerreviewedit' : 'peerreviewopen', 'mod_vimipad'),
                ['class' => 'btn btn-sm btn-outline-primary']
            );
            $table->data[] = [
                get_string('peerreviewsubmissionn', 'mod_vimipad', $index),
                $status,
                $action,
            ];
        }
        echo html_writer::table($table);
    }

    /**
     * Render one review: the anonymous map, scorer guidance and the review form.
     *
     * @param cm_info|stdClass $cm The course module.
     * @param stdClass $instance The activity instance.
     * @param stdClass $review The review record.
     * @return void
     */
    private static function render_detail(cm_info|stdClass $cm, stdClass $instance, stdClass $review): void {
        global $DB;

        echo html_writer::div(html_writer::link(
            new moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'peer']),
            get_string('peerreviewback', 'mod_vimipad')
        ), 'mb-3');

        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => (int) $review->snapshotid], '*', MUST_EXIST);
        self::render_map($snapshot);
        self::render_guidance($instance, (int) $review->snapshotid);
        self::render_form($cm, $review);
    }

    /**
     * Render a submitted map read-only, without revealing its author.
     *
     * @param stdClass $snapshot The snapshot record.
     * @return void
     */
    private static function render_map(stdClass $snapshot): void {
        $data = json_decode((string) $snapshot->snapshotjson, true);
        if (!is_array($data)) {
            return;
        }

        $labels = [];
        foreach (($data['nodes'] ?? []) as $node) {
            $labels[$node['stableid']] = (string) ($node['label'] ?? '');
        }

        echo html_writer::tag('h4', get_string('concepts', 'mod_vimipad'));
        echo html_writer::div(s(implode(', ', array_filter($labels))), 'mb-3');

        if (!empty($data['relations'])) {
            echo html_writer::tag('h4', get_string('propositions', 'mod_vimipad'));
            $table = new html_table();
            $table->head = ['', '', ''];
            foreach ($data['relations'] as $relation) {
                $table->data[] = [
                    s($labels[$relation['sourceid']] ?? $relation['sourceid']),
                    s((string) ($relation['label'] ?? '')),
                    s($labels[$relation['targetid']] ?? $relation['targetid']),
                ];
            }
            echo html_writer::table($table);
        }
    }

    /**
     * Render the automatic scorers' hints for the reviewer.
     *
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The reviewed snapshot.
     * @return void
     */
    private static function render_guidance(stdClass $instance, int $snapshotid): void {
        $guidance = (new peer_review_service())->guidance($instance, $snapshotid);
        if (empty($guidance)) {
            return;
        }

        echo html_writer::tag('h4', get_string('peerreviewguidance', 'mod_vimipad'), ['class' => 'mt-4']);
        echo html_writer::div(get_string('peerreviewguidancehint', 'mod_vimipad'), 'text-muted small mb-2');
        foreach ($guidance as $entry) {
            $result = $entry['result'];
            $line = $entry['name'];
            if (empty($result->informational)) {
                $line .= ': ' . round($result->score * 100) . '%';
            }
            echo html_writer::div(s($line), 'small');
        }
    }

    /**
     * Render the review form (advisory score plus written feedback).
     *
     * @param cm_info|stdClass $cm The course module.
     * @param stdClass $review The review record.
     * @return void
     */
    private static function render_form(cm_info|stdClass $cm, stdClass $review): void {
        $current = ($review->score === null) ? '' : (string) round((float) $review->score * 100);

        $fields = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'peer'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reviewid', 'value' => (int) $review->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savereview', 'value' => 1]);

        $fields .= html_writer::div(
            html_writer::label(get_string('peerreviewscore', 'mod_vimipad'), 'vimipad-peerscore')
            . html_writer::empty_tag('input', [
                'type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1,
                'id' => 'vimipad-peerscore', 'name' => 'peerscore', 'value' => $current,
                'class' => 'form-control', 'style' => 'max-width:8rem;',
            ]),
            'mb-2'
        );
        $fields .= html_writer::div(
            html_writer::label(get_string('peerreviewcomment', 'mod_vimipad'), 'vimipad-peercomment')
            . html_writer::tag('textarea', s((string) $review->reviewcomment), [
                'id' => 'vimipad-peercomment', 'name' => 'peercomment', 'rows' => 4, 'class' => 'form-control',
            ]),
            'mb-2'
        );
        $fields .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('peerreviewsave', 'mod_vimipad'),
            'class' => 'btn btn-primary',
        ]);

        echo html_writer::tag('h4', get_string('peerreviewyours', 'mod_vimipad'), ['class' => 'mt-4']);
        echo html_writer::tag('form', $fields, [
            'method' => 'post',
            'action' => new moodle_url('/mod/vimipad/view.php'),
        ]);
    }

    /**
     * Fetch a review record only if it belongs to this user and activity.
     *
     * @param peer_review_service $service The peer review service.
     * @param stdClass $instance The activity instance.
     * @param int $userid The acting user.
     * @param int $reviewid The review record id.
     * @return stdClass|null The review, or null if it is not this user's.
     */
    private static function own_review(
        peer_review_service $service,
        stdClass $instance,
        int $userid,
        int $reviewid
    ): ?stdClass {
        $reviews = $service->for_reviewer($instance, $userid);
        return $reviews[$reviewid] ?? null;
    }
}
