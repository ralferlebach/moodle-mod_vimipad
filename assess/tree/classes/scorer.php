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

namespace vimipadassess_tree;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;

/**
 * Scores a submission by its hierarchy against a reference solution.
 *
 * Where the reference scorer weighs named propositions, this scorer looks only
 * at structure: it reads each directed relation as a parent → child link, finds
 * the root (a concept that is never a child) and measures how well the
 * submission reproduces the reference's root and parent–child links (precision /
 * recall F1). Relation labels are ignored, which suits tree and mind-map
 * profiles where the shape carries the meaning. The matcher is injected, so
 * fuzzy or word-overlap matching applies here too.
 *
 * @package    vimipadassess_tree
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer {
    /** @var float Best-weight threshold above which a link counts as matched. */
    private const MATCH_THRESHOLD = 0.5;

    /** @var float Weight of the root agreement in the overall score. */
    private const WEIGHT_ROOT = 0.3;

    /** @var float Weight of the parent-child link agreement in the overall score. */
    private const WEIGHT_LINKS = 0.7;

    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'tree';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_tree');
    }

    /**
     * Handles hierarchical profiles.
     *
     * @param string $profile The profile key.
     * @return bool
     */
    public function supports_profile(string $profile): bool {
        return in_array($profile, ['tree', 'mindmap', 'conceptmap'], true);
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
     * Score the submission's hierarchy against the (first) reference.
     *
     * @param submission $submission The submission to score.
     * @param submission[] $references The reference solution(s), may be empty.
     * @param matcher $matcher The label matcher to use.
     * @return result
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        if (empty($references)) {
            return new result(0.0, [], self::emptybreakdown(), self::emptybreakdown());
        }
        $reference = reset($references);

        $rootmatch = $this->root_match($submission, $reference, $matcher);
        [$linkf1, $matched, $missing, $extra] =
            $this->compare_links(self::links($submission), self::links($reference), $matcher);

        $score = self::WEIGHT_ROOT * $rootmatch + self::WEIGHT_LINKS * $linkf1;

        return new result(
            $score,
            ['root' => $rootmatch, 'hierarchy' => $linkf1],
            self::emptybreakdown(),
            ['matched' => $matched, 'missing' => $missing, 'extra' => $extra]
        );
    }

    /**
     * The matcher weight between the two trees' roots (0.0 if either is unclear).
     *
     * @param submission $submission The submission.
     * @param submission $reference The reference.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function root_match(submission $submission, submission $reference, matcher $matcher): float {
        $subroot = self::root($submission);
        $refroot = self::root($reference);
        if ($subroot === '' || $refroot === '') {
            return 0.0;
        }
        $weight = $matcher->weight($subroot, $refroot);
        return $weight >= self::MATCH_THRESHOLD ? $weight : 0.0;
    }

    /**
     * Parent-child links (ignoring the relation label) as [parent, child] pairs.
     *
     * @param submission $submission The submission.
     * @return array[]
     */
    private static function links(submission $submission): array {
        $links = [];
        foreach ($submission->propositions as $proposition) {
            if ($proposition['source'] !== '' && $proposition['target'] !== '') {
                $links[] = ['parent' => $proposition['source'], 'child' => $proposition['target']];
            }
        }
        return $links;
    }

    /**
     * The root label: a concept that is a parent but never a child. Empty if unclear.
     *
     * @param submission $submission The submission.
     * @return string
     */
    private static function root(submission $submission): string {
        $parents = [];
        $children = [];
        foreach ($submission->propositions as $proposition) {
            if ($proposition['source'] !== '' && $proposition['target'] !== '') {
                $parents[$proposition['source']] = true;
                $children[$proposition['target']] = true;
            }
        }
        $roots = array_keys(array_diff_key($parents, $children));
        return count($roots) === 1 ? $roots[0] : '';
    }

    /**
     * Compare two link sets, returning [f1, matched, missing, extra].
     *
     * @param array[] $sub Submission links.
     * @param array[] $ref Reference links.
     * @param matcher $matcher The matcher.
     * @return array{0: float, 1: string[], 2: string[], 3: string[]}
     */
    private function compare_links(array $sub, array $ref, matcher $matcher): array {
        $recall = $this->coverage($ref, $sub, $matcher);
        $precision = $this->coverage($sub, $ref, $matcher);
        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        $matched = [];
        $missing = [];
        foreach ($ref as $link) {
            if ($this->best_weight($link, $sub, $matcher) >= self::MATCH_THRESHOLD) {
                $matched[] = self::linkstr($link);
            } else {
                $missing[] = self::linkstr($link);
            }
        }
        $extra = [];
        foreach ($sub as $link) {
            if ($this->best_weight($link, $ref, $matcher) < self::MATCH_THRESHOLD) {
                $extra[] = self::linkstr($link);
            }
        }
        return [$f1, $matched, $missing, $extra];
    }

    /**
     * Average best link-match weight of each needle against the haystack.
     *
     * @param array[] $needles The links to cover.
     * @param array[] $haystack The links to cover them with.
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
     * The best directional match weight of a link against candidates.
     *
     * @param array $link The link ['parent','child'].
     * @param array[] $candidates The candidate links.
     * @param matcher $matcher The matcher.
     * @return float
     */
    private function best_weight(array $link, array $candidates, matcher $matcher): float {
        $best = 0.0;
        foreach ($candidates as $candidate) {
            $parentweight = $matcher->weight($link['parent'], $candidate['parent']);
            $childweight = $matcher->weight($link['child'], $candidate['child']);
            $weight = $parentweight * $childweight;
            if ($weight > $best) {
                $best = $weight;
            }
        }
        return $best;
    }

    /**
     * Render a link as "parent → child".
     *
     * @param array $link The link.
     * @return string
     */
    private static function linkstr(array $link): string {
        return $link['parent'] . ' → ' . $link['child'];
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
