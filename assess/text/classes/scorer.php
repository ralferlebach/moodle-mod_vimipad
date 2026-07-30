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

namespace vimipadassess_text;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\rouge;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;

/**
 * Scores the free-text descriptions students attach to their concepts.
 *
 * Concept labels say what a student named; descriptions say what they understood.
 * For each described concept in the reference solution this scorer locates the
 * corresponding concept in the submission (label matching through the injected
 * matcher, so fuzzy and word-overlap modes apply) and compares the two
 * description texts with ROUGE. Concepts whose description is absent or barely
 * overlapping are reported so a teacher can see where understanding is thin.
 *
 * @package    vimipadassess_text
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer {
    /** @var float Label weight above which two concepts are considered the same. */
    private const LABEL_THRESHOLD = 0.5;

    /** @var float Text overlap above which a description counts as reproduced. */
    private const TEXT_THRESHOLD = 0.4;

    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'text';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_text');
    }

    /**
     * Any profile may carry concept descriptions.
     *
     * @param string $profile The profile key.
     * @return bool
     */
    public function supports_profile(string $profile): bool {
        return true;
    }

    /**
     * A reference solution is required to compare against.
     *
     * @return bool
     */
    public function requires_reference(): bool {
        return true;
    }

    /**
     * Score the submission's descriptions against the (first) reference.
     *
     * @param submission $submission The submission to score.
     * @param submission[] $references The reference solution(s), may be empty.
     * @param matcher $matcher The label matcher to use.
     * @return result
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        $reference = $references ? reset($references) : null;
        $expected = $reference ? $reference->described_labels() : [];
        if ($reference === null || empty($expected)) {
            return new result(
                0.0,
                [],
                self::emptybreakdown(),
                self::emptybreakdown(),
                ['note' => get_string('nodescriptions', 'vimipadassess_text')],
                true
            );
        }

        $total = 0.0;
        $matched = [];
        $missing = [];
        foreach ($expected as $label) {
            $referencetext = $reference->description_for_label($label);
            $submittedtext = $this->description_for_similar_label($submission, $label, $matcher);
            $overlap = ($submittedtext === '') ? 0.0 : rouge::similarity($submittedtext, $referencetext);
            $total += $overlap;

            if ($overlap >= self::TEXT_THRESHOLD) {
                $matched[] = $label;
            } else if ($overlap > 0.0) {
                $missing[] = get_string('thin', 'vimipadassess_text', (object) [
                    'label' => $label,
                    'percent' => round($overlap * 100),
                ]);
            } else {
                $missing[] = $label;
            }
        }

        $score = $total / count($expected);

        // Described concepts in the submission with no counterpart in the reference.
        $extra = [];
        foreach ($submission->described_labels() as $label) {
            if ($this->best_label_weight($label, $expected, $matcher) < self::LABEL_THRESHOLD) {
                $extra[] = $label;
            }
        }

        return new result(
            $score,
            ['descriptions' => $score],
            self::emptybreakdown(),
            ['matched' => $matched, 'missing' => $missing, 'extra' => $extra]
        );
    }

    /**
     * The submission's description for the concept most like a reference label.
     *
     * @param submission $submission The submission.
     * @param string $label The reference concept label.
     * @param matcher $matcher The matcher.
     * @return string The description text, or '' when no concept matches.
     */
    private function description_for_similar_label(submission $submission, string $label, matcher $matcher): string {
        $best = 0.0;
        $bestlabel = '';
        foreach ($submission->concepts as $concept) {
            if ($concept === '') {
                continue;
            }
            $weight = $matcher->weight($label, $concept);
            if ($weight > $best) {
                $best = $weight;
                $bestlabel = $concept;
            }
        }
        if ($best < self::LABEL_THRESHOLD || $bestlabel === '') {
            return '';
        }
        return $submission->description_for_label($bestlabel);
    }

    /**
     * The best match weight of a label against a set of candidate labels.
     *
     * @param string $label The label.
     * @param array $candidates The candidate labels.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function best_label_weight(string $label, array $candidates, matcher $matcher): float {
        $best = 0.0;
        foreach ($candidates as $candidate) {
            $weight = $matcher->weight($label, $candidate);
            if ($weight > $best) {
                $best = $weight;
            }
        }
        return $best;
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
