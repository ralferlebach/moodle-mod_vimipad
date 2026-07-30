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

namespace mod_vimipad\local\assess;

/**
 * ROUGE-style text overlap, for comparing free-text node descriptions.
 *
 * Summarisation metrics suit this job better than label matching: a student's
 * description of a concept is judged by how much of the reference wording it
 * recovers, not by exact equality. Provides ROUGE-N (n-gram overlap) and ROUGE-L
 * (longest common subsequence, so word order counts without demanding contiguity),
 * both as F-measures, and a combined similarity used by the text scorer.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rouge {
    /** @var float Weight of unigram overlap in the combined similarity. */
    private const WEIGHT_R1 = 0.5;

    /** @var float Weight of bigram overlap in the combined similarity. */
    private const WEIGHT_R2 = 0.2;

    /** @var float Weight of the longest-common-subsequence measure. */
    private const WEIGHT_RL = 0.3;

    /**
     * Combined ROUGE similarity of two texts, from 0.0 to 1.0.
     *
     * @param string $candidate The submitted text.
     * @param string $reference The reference text.
     * @return float
     */
    public static function similarity(string $candidate, string $reference): float {
        $cand = self::tokens($candidate);
        $ref = self::tokens($reference);
        if (empty($cand) || empty($ref)) {
            return 0.0;
        }
        $score = self::WEIGHT_R1 * self::rouge_n($cand, $ref, 1)
            + self::WEIGHT_R2 * self::rouge_n($cand, $ref, 2)
            + self::WEIGHT_RL * self::rouge_l($cand, $ref);
        return max(0.0, min(1.0, $score));
    }

    /**
     * Split a text into comparable word tokens (HTML stripped, lower-cased).
     *
     * @param string $text The raw text, possibly HTML.
     * @return string[]
     */
    public static function tokens(string $text): array {
        $plain = html_to_text($text, 0, false);
        $plain = \core_text::strtolower($plain);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
        return $parts ?: [];
    }

    /**
     * ROUGE-N F-measure: overlap of n-grams between candidate and reference.
     *
     * @param array $candidate Candidate tokens.
     * @param array $reference Reference tokens.
     * @param int $n The n-gram size.
     * @return float
     */
    public static function rouge_n(array $candidate, array $reference, int $n): float {
        $cgrams = self::ngrams($candidate, $n);
        $rgrams = self::ngrams($reference, $n);
        if (empty($cgrams) || empty($rgrams)) {
            return 0.0;
        }

        // Clipped overlap: each reference n-gram can be matched only as often as it occurs.
        $ccounts = array_count_values($cgrams);
        $rcounts = array_count_values($rgrams);
        $overlap = 0;
        foreach ($rcounts as $gram => $rcount) {
            if (isset($ccounts[$gram])) {
                $overlap += min($rcount, $ccounts[$gram]);
            }
        }

        $recall = $overlap / count($rgrams);
        $precision = $overlap / count($cgrams);
        return ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;
    }

    /**
     * ROUGE-L F-measure, based on the longest common subsequence.
     *
     * @param array $candidate Candidate tokens.
     * @param array $reference Reference tokens.
     * @return float
     */
    public static function rouge_l(array $candidate, array $reference): float {
        $lcs = self::lcs_length($candidate, $reference);
        if ($lcs === 0) {
            return 0.0;
        }
        $recall = $lcs / count($reference);
        $precision = $lcs / count($candidate);
        return ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;
    }

    /**
     * Build the list of n-grams for a token list.
     *
     * @param array $tokens The tokens.
     * @param int $n The n-gram size.
     * @return string[]
     */
    private static function ngrams(array $tokens, int $n): array {
        if ($n < 1 || count($tokens) < $n) {
            return [];
        }
        $grams = [];
        $limit = count($tokens) - $n;
        for ($i = 0; $i <= $limit; $i++) {
            $grams[] = implode(' ', array_slice($tokens, $i, $n));
        }
        return $grams;
    }

    /**
     * Length of the longest common subsequence of two token lists.
     *
     * Uses a rolling row so long descriptions stay cheap in memory. Very long
     * texts are truncated, since description fields are not essays.
     *
     * @param array $a First token list.
     * @param array $b Second token list.
     * @return int
     */
    private static function lcs_length(array $a, array $b): int {
        $limit = 600;
        $a = array_slice($a, 0, $limit);
        $b = array_slice($b, 0, $limit);
        $rows = count($a);
        $cols = count($b);
        if ($rows === 0 || $cols === 0) {
            return 0;
        }

        $previous = array_fill(0, $cols + 1, 0);
        for ($i = 1; $i <= $rows; $i++) {
            $current = array_fill(0, $cols + 1, 0);
            for ($j = 1; $j <= $cols; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $current[$j] = $previous[$j - 1] + 1;
                } else {
                    $current[$j] = max($previous[$j], $current[$j - 1]);
                }
            }
            $previous = $current;
        }
        return (int) $previous[$cols];
    }
}
