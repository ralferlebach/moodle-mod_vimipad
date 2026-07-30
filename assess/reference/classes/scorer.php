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

namespace vimipadassess_reference;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;

/**
 * Scores a submission by comparing its concepts and propositions to a reference.
 *
 * Concepts and propositions (source–relation–target triples) are matched against
 * the reference solution through the supplied matcher, yielding precision/recall
 * F1 per dimension. Proposition endpoints must match in the same direction; the
 * relation label contributes but weighs less than the endpoints. The overall
 * score favours propositions, the didactically richer signal. Everything is a
 * suggestion with a full matched/missing/extra breakdown.
 *
 * @package    vimipadassess_reference
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer {
    /** @var float Best-weight threshold above which an item counts as matched (for the breakdown). */
    private const MATCH_THRESHOLD = 0.5;

    /** @var float Weight of the concept dimension in the overall score. */
    private const WEIGHT_CONCEPTS = 0.4;

    /** @var float Weight of the proposition dimension in the overall score. */
    private const WEIGHT_PROPOSITIONS = 0.6;

    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'reference';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_reference');
    }

    /**
     * Whether this scorer can handle the given diagram profile.
     *
     * @param string $profile The profile key.
     * @return bool
     */
    public function supports_profile(string $profile): bool {
        // Every profile has concepts and (possibly unlabelled) relations.
        return true;
    }

    /**
     * Score a submission against the (first) reference solution.
     *
     * @param submission $submission The submission to score.
     * @param submission[] $references The reference solution(s), may be empty.
     * @param matcher $matcher The label matcher to use.
     * @return result The suggested score and breakdown.
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        if (empty($references)) {
            return new result(
                0.0,
                ['concepts' => 0.0, 'propositions' => 0.0],
                ['matched' => [], 'missing' => [], 'extra' => $submission->concept_labels()],
                ['matched' => [], 'missing' => [], 'extra' => array_map([self::class, 'propstr'], $submission->propositions)]
            );
        }

        $reference = reset($references);

        [$conceptf1, $cmatched, $cmissing, $cextra] =
            $this->compare_labels($submission->concept_labels(), $reference->concept_labels(), $matcher);
        [$propf1, $pmatched, $pmissing, $pextra] =
            $this->compare_propositions($submission->propositions, $reference->propositions, $matcher);

        $score = self::WEIGHT_CONCEPTS * $conceptf1 + self::WEIGHT_PROPOSITIONS * $propf1;

        return new result(
            $score,
            ['concepts' => $conceptf1, 'propositions' => $propf1],
            ['matched' => $cmatched, 'missing' => $cmissing, 'extra' => $cextra],
            ['matched' => $pmatched, 'missing' => $pmissing, 'extra' => $pextra]
        );
    }

    /**
     * Compare two label sets, returning [f1, matched, missing, extra].
     *
     * @param string[] $sub Submission labels.
     * @param string[] $ref Reference labels.
     * @param matcher $matcher The matcher.
     * @return array{0: float, 1: string[], 2: string[], 3: string[]}
     */
    private function compare_labels(array $sub, array $ref, matcher $matcher): array {
        $recall = $this->coverage($ref, $sub, $matcher);
        $precision = $this->coverage($sub, $ref, $matcher);
        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        $matched = [];
        $missing = [];
        foreach ($ref as $label) {
            if ($this->best_weight($label, $sub, $matcher) >= self::MATCH_THRESHOLD) {
                $matched[] = $label;
            } else {
                $missing[] = $label;
            }
        }
        $extra = [];
        foreach ($sub as $label) {
            if ($this->best_weight($label, $ref, $matcher) < self::MATCH_THRESHOLD) {
                $extra[] = $label;
            }
        }
        return [$f1, $matched, $missing, $extra];
    }

    /**
     * Average best match weight of each needle against the haystack.
     *
     * @param string[] $needles The labels to cover.
     * @param string[] $haystack The labels to cover them with.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function coverage(array $needles, array $haystack, matcher $matcher): float {
        if (empty($needles)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($needles as $needle) {
            $sum += $this->best_weight($needle, $haystack, $matcher);
        }
        return $sum / count($needles);
    }

    /**
     * The best match weight of a label against a set of candidates.
     *
     * @param string $label The label.
     * @param string[] $candidates The candidate labels.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function best_weight(string $label, array $candidates, matcher $matcher): float {
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
     * Compare two proposition sets, returning [f1, matched, missing, extra].
     *
     * @param array[] $sub Submission propositions.
     * @param array[] $ref Reference propositions.
     * @param matcher $matcher The matcher.
     * @return array{0: float, 1: string[], 2: string[], 3: string[]}
     */
    private function compare_propositions(array $sub, array $ref, matcher $matcher): array {
        $recall = $this->proposition_coverage($ref, $sub, $matcher);
        $precision = $this->proposition_coverage($sub, $ref, $matcher);
        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        $matched = [];
        $missing = [];
        foreach ($ref as $proposition) {
            if ($this->best_proposition_weight($proposition, $sub, $matcher) >= self::MATCH_THRESHOLD) {
                $matched[] = self::propstr($proposition);
            } else {
                $missing[] = self::propstr($proposition);
            }
        }
        $extra = [];
        foreach ($sub as $proposition) {
            if ($this->best_proposition_weight($proposition, $ref, $matcher) < self::MATCH_THRESHOLD) {
                $extra[] = self::propstr($proposition);
            }
        }
        return [$f1, $matched, $missing, $extra];
    }

    /**
     * Average best proposition match weight of each needle against the haystack.
     *
     * @param array[] $needles The propositions to cover.
     * @param array[] $haystack The propositions to cover them with.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function proposition_coverage(array $needles, array $haystack, matcher $matcher): float {
        if (empty($needles)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($needles as $needle) {
            $sum += $this->best_proposition_weight($needle, $haystack, $matcher);
        }
        return $sum / count($needles);
    }

    /**
     * The best directional match weight of a proposition against candidates.
     *
     * Endpoints must both match in the same direction; the relation label then
     * modulates the weight (unlabelled on both sides counts as a match).
     *
     * @param array $proposition The proposition ['source','relation','target'].
     * @param array[] $candidates The candidate propositions.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function best_proposition_weight(array $proposition, array $candidates, matcher $matcher): float {
        $best = 0.0;
        foreach ($candidates as $candidate) {
            $sourceweight = $matcher->weight($proposition['source'], $candidate['source']);
            $targetweight = $matcher->weight($proposition['target'], $candidate['target']);
            if ($sourceweight <= 0.0 || $targetweight <= 0.0) {
                continue;
            }
            $relationweight = $this->relation_weight($proposition['relation'], $candidate['relation'], $matcher);
            $weight = $sourceweight * $targetweight * (0.5 + 0.5 * $relationweight);
            if ($weight > $best) {
                $best = $weight;
            }
        }
        return $best;
    }

    /**
     * The relation-label contribution: unlabelled↔unlabelled = 1.0, mixed = 0.5.
     *
     * @param string $a One relation label.
     * @param string $b The other relation label.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function relation_weight(string $a, string $b, matcher $matcher): float {
        if ($a === '' && $b === '') {
            return 1.0;
        }
        if ($a === '' || $b === '') {
            return 0.5;
        }
        return $matcher->weight($a, $b);
    }

    /**
     * Render a proposition as "source → relation → target" for the breakdown.
     *
     * @param array $proposition The proposition.
     * @return string
     */
    public static function propstr(array $proposition): string {
        $relation = $proposition['relation'] !== '' ? $proposition['relation'] : '—';
        return $proposition['source'] . ' → ' . $relation . ' → ' . $proposition['target'];
    }
}
