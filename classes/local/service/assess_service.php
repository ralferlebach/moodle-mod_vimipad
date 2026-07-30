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

namespace mod_vimipad\local\service;

use mod_vimipad\local\assess\matcher_factory;
use mod_vimipad\local\assess\prompt_scorer;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\submission;
use mod_vimipad\local\service\ai_feedback_service;
use stdClass;

/**
 * Bridges snapshots and the automatic scorers.
 *
 * Turns a stored snapshot into a scorer-ready submission and runs the activity's
 * chosen scorer against the reference solution the teacher has marked. The result
 * is always a suggestion for the grader, never a stored grade.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assess_service {
    /**
     * Build a scorer submission from a stored snapshot.
     *
     * @param int $snapshotid The snapshot id.
     * @return submission|null The submission, or null if the snapshot has no usable map.
     */
    public function submission_from_snapshot(int $snapshotid): ?submission {
        global $DB;

        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => $snapshotid]);
        if (!$snapshot || $snapshot->snapshotjson === null) {
            return null;
        }
        $data = json_decode($snapshot->snapshotjson, true);
        if (!is_array($data)) {
            return null;
        }
        return submission::from_snapshot_data($data);
    }

    /**
     * Score a snapshot against the activity's marked reference solution.
     *
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The snapshot to score.
     * @param string $scorerkey The scorer subplugin key.
     * @return result|null The suggestion, or null if scoring is not possible.
     */
    public function score(stdClass $instance, int $snapshotid, string $scorerkey = 'reference'): ?result {
        if (empty($instance->referencesnapshotid)) {
            return null;
        }
        if ((int) $instance->referencesnapshotid === $snapshotid) {
            // The reference is not scored against itself.
            return null;
        }
        $scorer = registry::get($scorerkey);
        if ($scorer === null || !$this->scorer_enabled($instance, $scorerkey)) {
            return null;
        }
        $submission = $this->submission_from_snapshot($snapshotid);
        $reference = $this->submission_from_snapshot((int) $instance->referencesnapshotid);
        if ($submission === null || $reference === null) {
            return null;
        }
        if (!$scorer->supports_profile($submission->profile)) {
            return null;
        }
        $matcher = matcher_factory::create((int) ($instance->matchmode ?? 0));
        return $scorer->score($submission, [$reference], $matcher);
    }

    /**
     * Run every scorer that applies to the submission's profile.
     *
     * Reference-free scorers always run; reference-based scorers run only when a
     * reference is marked and the snapshot is not the reference itself.
     *
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The snapshot to assess.
     * @return array<string,array{name: string, result: result}> Keyed by scorer key.
     */
    public function score_all(stdClass $instance, int $snapshotid): array {
        $submission = $this->submission_from_snapshot($snapshotid);
        if ($submission === null) {
            return [];
        }
        $referenceid = (int) ($instance->referencesnapshotid ?? 0);
        $reference = ($referenceid > 0 && $referenceid !== $snapshotid)
            ? $this->submission_from_snapshot($referenceid)
            : null;
        $matcher = matcher_factory::create((int) ($instance->matchmode ?? 0));

        $results = [];
        foreach (registry::for_profile($submission->profile) as $key => $scorer) {
            if ($scorer->uses_ai()) {
                // AI scorers are slow and run on demand, not automatically.
                continue;
            }
            if (!$this->scorer_enabled($instance, $key)) {
                continue;
            }
            if ($scorer->requires_reference()) {
                if ($reference === null) {
                    continue;
                }
                $result = $scorer->score($submission, [$reference], $matcher);
            } else {
                $result = $scorer->score($submission, [], $matcher);
            }
            $results[$key] = ['name' => $scorer->get_name(), 'result' => $result];
        }
        return $results;
    }

    /**
     * Whether a scorer key is enabled for this activity.
     *
     * An empty selection means every installed scorer runs, so activities created
     * before the setting existed keep their previous behaviour.
     *
     * @param stdClass $instance The activity instance.
     * @param string $key The scorer key.
     * @return bool
     */
    public function scorer_enabled(stdClass $instance, string $key): bool {
        $selected = array_filter(array_map('trim', explode(',', (string) ($instance->activescorers ?? ''))));
        return empty($selected) || in_array($key, $selected, true);
    }

    /**
     * Run an AI (prompt-based) scorer on demand, calling the AI subsystem.
     *
     * @param \context $context The module context.
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The snapshot to assess.
     * @param int $userid The acting teacher's user id.
     * @param string $scorerkey The AI scorer key.
     * @return result|null The suggestion, or null if the scorer or map is unavailable.
     * @throws \moodle_exception If the AI subsystem is unavailable or the call fails.
     */
    public function score_ai(
        \context $context,
        stdClass $instance,
        int $snapshotid,
        int $userid,
        string $scorerkey = 'llm'
    ): ?result {
        $scorer = registry::get($scorerkey);
        if (!$scorer instanceof prompt_scorer || !$this->scorer_enabled($instance, $scorerkey)) {
            return null;
        }
        $submission = $this->submission_from_snapshot($snapshotid);
        if ($submission === null) {
            return null;
        }
        $referenceid = (int) ($instance->referencesnapshotid ?? 0);
        $references = ($referenceid > 0 && $referenceid !== $snapshotid)
            ? array_filter([$this->submission_from_snapshot($referenceid)])
            : [];

        $prompt = $scorer->build_prompt($submission, $references);
        $airesponse = (new ai_feedback_service())->generate_text($context, $userid, $prompt);
        return $scorer->interpret($airesponse['text'], $submission, $references);
    }
}
