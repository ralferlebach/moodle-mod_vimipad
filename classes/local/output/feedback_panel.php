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

use context_module;
use html_writer;
use stdClass;
use mod_vimipad\local\service\grading_service;

/**
 * The learner-facing feedback view.
 *
 * Shows a learner the grade and written feedback for their own graded
 * submission — the in-plugin counterpart to the gradebook, so the feedback
 * text (which the gradebook does not surface well) is visible where the work
 * was done. Renders nothing beyond a notice until the submission is graded.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_panel {
    /**
     * Whether the feedback view for this learner embeds the assessed-map viewer
     * and therefore needs the revision viewer's JavaScript initialised.
     *
     * @param stdClass $instance The activity instance.
     * @param int $userid The learner viewing their feedback.
     * @return bool True if the viewer is present.
     */
    public static function needs_viewer(stdClass $instance, int $userid): bool {
        $feedback = (new grading_service())->get_feedback_for_user($instance, $userid);
        return $feedback !== null
            && !empty($feedback->snapshotrevision)
            && !empty($feedback->snapshotworkspaceid);
    }

    /**
     * Render the feedback view for the given learner.
     *
     * @param context_module $context The activity context.
     * @param stdClass $instance The activity instance.
     * @param int $userid The learner viewing their feedback.
     * @return string The rendered HTML.
     */
    public static function render(context_module $context, stdClass $instance, int $userid): string {
        $feedback = (new grading_service())->get_feedback_for_user($instance, $userid);

        if ($feedback === null) {
            return html_writer::div(
                get_string('feedback:none', 'mod_vimipad'),
                'vimipad-feedback-none text-muted'
            );
        }

        $out = html_writer::start_div('vimipad-feedback');

        // Grade.
        $gradetext = format_float($feedback->grade, 2) . ' / ' . format_float($feedback->grademax, 2);
        $out .= html_writer::tag('h3', get_string('feedback:gradeheading', 'mod_vimipad'), ['class' => 'h5']);
        $out .= html_writer::div($gradetext, 'vimipad-feedback-grade lead');

        // Written feedback.
        if (trim($feedback->feedback) !== '') {
            $out .= html_writer::tag('h3', get_string('feedback:textheading', 'mod_vimipad'), ['class' => 'h5 mt-3']);
            $out .= html_writer::div(
                format_text($feedback->feedback, $feedback->feedbackformat, ['context' => $context]),
                'vimipad-feedback-text'
            );
        }

        // When it was graded.
        if ($feedback->dategraded > 0) {
            $out .= html_writer::div(
                get_string('feedback:dategraded', 'mod_vimipad', userdate($feedback->dategraded)),
                'vimipad-feedback-date text-muted small mt-3'
            );
        }

        // The assessed map: a read-only view of the exact snapshot that was
        // graded, so the learner sees the feedback in the context of their work.
        // Rendered only when the graded snapshot's revision could be resolved.
        if (!empty($feedback->snapshotrevision) && !empty($feedback->snapshotworkspaceid)) {
            $out .= html_writer::tag('h3', get_string('feedback:mapheading', 'mod_vimipad'), ['class' => 'h5 mt-3']);
            $out .= html_writer::tag('button', get_string('feedback:showmap', 'mod_vimipad'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary vimipad-showstate',
                'data-vimipad-revision' => (int) $feedback->snapshotrevision,
                'data-workspaceid' => (int) $feedback->snapshotworkspaceid,
            ]);
            $out .= html_writer::div('', '', [
                'id' => 'vimipad-revision-viewer',
                'class' => 'vimipad-revision-viewer-host mt-3',
            ]);
        }

        $out .= html_writer::end_div();
        return $out;
    }
}
