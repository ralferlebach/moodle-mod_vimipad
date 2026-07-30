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

namespace vimipadassess_sms;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;

/**
 * Scores how a submission groups concepts into sub-maps, against a reference.
 *
 * Where the reference and structural scorers look at the whole map, this scorer
 * looks at its partitioning: each container is a sub-map (a set of concepts). It
 * matches every reference sub-map to the submission's best-overlapping sub-map
 * (concept-set F1 through the injected matcher) and reports which expected
 * groupings were reproduced, missed or added. When no sub-maps are defined it
 * returns an informational note rather than a misleading zero.
 *
 * @package    vimipadassess_sms
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer {
    /** @var float Overlap above which a sub-map counts as reproduced. */
    private const MATCH_THRESHOLD = 0.5;

    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'sms';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_sms');
    }

    /**
     * Any profile may group concepts into sub-maps.
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
     * Score the submission's sub-map grouping against the (first) reference.
     *
     * @param submission $submission The submission to score.
     * @param submission[] $references The reference solution(s), may be empty.
     * @param matcher $matcher The label matcher to use.
     * @return result
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        $reference = $references ? reset($references) : null;
        if ($reference === null || empty($reference->submaps)) {
            return new result(
                0.0,
                [],
                self::emptybreakdown(),
                self::emptybreakdown(),
                ['note' => get_string('nosubmaps', 'vimipadassess_sms')],
                true
            );
        }

        $recall = $this->coverage($reference->submaps, $submission->submaps, $matcher);
        $precision = $this->coverage($submission->submaps, $reference->submaps, $matcher);
        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        $matched = [];
        $missing = [];
        foreach ($reference->submaps as $submap) {
            if ($this->best_overlap($submap, $submission->submaps, $matcher) >= self::MATCH_THRESHOLD) {
                $matched[] = self::submapstr($submap);
            } else {
                $missing[] = self::submapstr($submap);
            }
        }
        $extra = [];
        foreach ($submission->submaps as $submap) {
            if ($this->best_overlap($submap, $reference->submaps, $matcher) < self::MATCH_THRESHOLD) {
                $extra[] = self::submapstr($submap);
            }
        }

        return new result(
            $f1,
            ['submaps' => $f1],
            self::emptybreakdown(),
            ['matched' => $matched, 'missing' => $missing, 'extra' => $extra]
        );
    }

    /**
     * Average best sub-map overlap of each needle against the haystack.
     *
     * @param array[] $needles The sub-maps to cover.
     * @param array[] $haystack The sub-maps to cover them with.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function coverage(array $needles, array $haystack, matcher $matcher): float {
        if (empty($needles)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($needles as $needle) {
            $sum += $this->best_overlap($needle, $haystack, $matcher);
        }
        return $sum / count($needles);
    }

    /**
     * The best concept-set overlap of a sub-map against candidate sub-maps.
     *
     * @param array $submap The sub-map ['label','concepts'].
     * @param array[] $candidates The candidate sub-maps.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function best_overlap(array $submap, array $candidates, matcher $matcher): float {
        $best = 0.0;
        foreach ($candidates as $candidate) {
            $overlap = $this->set_similarity($submap['concepts'], $candidate['concepts'], $matcher);
            if ($overlap > $best) {
                $best = $overlap;
            }
        }
        return $best;
    }

    /**
     * Concept-set similarity (F1) between two sub-maps' concept lists.
     *
     * @param string[] $a The first concept list.
     * @param string[] $b The second concept list.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function set_similarity(array $a, array $b, matcher $matcher): float {
        if (empty($a) || empty($b)) {
            return 0.0;
        }
        $recall = $this->concept_coverage($a, $b, $matcher);
        $precision = $this->concept_coverage($b, $a, $matcher);
        return ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;
    }

    /**
     * Average best concept match of each needle against the haystack.
     *
     * @param string[] $needles The concepts to cover.
     * @param string[] $haystack The concepts to cover them with.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function concept_coverage(array $needles, array $haystack, matcher $matcher): float {
        $sum = 0.0;
        foreach ($needles as $needle) {
            $best = 0.0;
            foreach ($haystack as $candidate) {
                $weight = $matcher->weight($needle, $candidate);
                if ($weight > $best) {
                    $best = $weight;
                }
            }
            $sum += $best;
        }
        return count($needles) > 0 ? $sum / count($needles) : 0.0;
    }

    /**
     * Render a sub-map for the breakdown: its label, or its concepts if unnamed.
     *
     * @param array $submap The sub-map.
     * @return string
     */
    private static function submapstr(array $submap): string {
        $label = trim((string) $submap['label']);
        if ($label !== '') {
            return get_string('submap', 'vimipadassess_sms', $label);
        }
        return get_string('submapunnamed', 'vimipadassess_sms', implode(', ', $submap['concepts']));
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
