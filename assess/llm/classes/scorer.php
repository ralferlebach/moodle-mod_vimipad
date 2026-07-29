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

namespace vimipadassess_llm;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\prompt_scorer;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;
use mod_vimipad\local\assess\tuple_text;

/**
 * Scores a submission with a language model via Moodle's AI subsystem.
 *
 * The map is rendered to text (tuple-to-text) and, with any reference solution,
 * placed in a prompt that asks the model for a 0–100 match score and a short
 * justification. Because it calls the AI subsystem it runs on demand, never
 * automatically, and its output is explicitly a suggestion for the teacher.
 * Prompt building and reply interpretation are kept deterministic and testable;
 * the model call itself is performed by the assess service.
 *
 * @package    vimipadassess_llm
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer implements prompt_scorer {
    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'llm';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_llm');
    }

    /**
     * Whether this scorer can handle the given diagram profile.
     *
     * @param string $profile The profile key.
     * @return bool
     */
    public function supports_profile(string $profile): bool {
        return true;
    }

    /**
     * A reference is helpful but not required; the model can judge on its own.
     *
     * @return bool
     */
    public function requires_reference(): bool {
        return false;
    }

    /**
     * This scorer calls the AI subsystem and therefore runs on demand.
     *
     * @return bool
     */
    public function uses_ai(): bool {
        return true;
    }

    /**
     * Not used directly: AI scorers run through the assess service on demand.
     *
     * @param submission $submission The submission.
     * @param submission[] $references The reference solution(s).
     * @param matcher $matcher The matcher (unused).
     * @return result An empty informational result.
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        return new result(0.0, [], self::emptybreakdown(), self::emptybreakdown(), [], true);
    }

    /**
     * Build the prompt sent to the language model.
     *
     * @param submission $submission The submission to assess.
     * @param submission[] $references The reference solution(s), may be empty.
     * @return string
     */
    public function build_prompt(submission $submission, array $references): string {
        $parts = [
            get_string('prompt:intro', 'vimipadassess_llm'),
            get_string('prompt:studentmap', 'vimipadassess_llm') . "\n" . tuple_text::render($submission),
        ];
        if (!empty($references)) {
            $parts[] = get_string('prompt:reference', 'vimipadassess_llm') . "\n"
                . tuple_text::render(reset($references));
        }
        $parts[] = get_string('prompt:instruction', 'vimipadassess_llm');
        return implode("\n\n", $parts);
    }

    /**
     * Interpret the model's reply into a result.
     *
     * @param string $airesponse The raw model reply.
     * @param submission $submission The submission that was assessed.
     * @param submission[] $references The reference solution(s).
     * @return result
     */
    public function interpret(string $airesponse, submission $submission, array $references): result {
        $score = 0.0;
        if (preg_match('/SCORE:\s*(\d{1,3})/i', $airesponse, $matches)) {
            $score = min(100, max(0, (int) $matches[1])) / 100.0;
        }
        $rationale = trim(preg_replace('/SCORE:\s*\d{1,3}/i', '', $airesponse, 1));
        if ($rationale === '') {
            $rationale = trim($airesponse);
        }
        return new result(
            $score,
            ['ai' => $score],
            self::emptybreakdown(),
            self::emptybreakdown(),
            ['rationale' => $rationale],
            false
        );
    }

    /**
     * An empty matched/missing/extra breakdown.
     *
     * @return array
     */
    private static function emptybreakdown(): array {
        return ['matched' => [], 'missing' => [], 'extra' => []];
    }
}
