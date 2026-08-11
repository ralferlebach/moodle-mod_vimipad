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

namespace mod_vimipad\api;

use mod_vimipad\local\assess\matcher_factory;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\submission;

/**
 * Stable, context-free entry point for scoring one ViMi Pad map against another.
 *
 * This is the public seam over the activity's internal assessment engine
 * (the vimipadassess_* subplugins). A derived plugin - a question type, a
 * peer-review tool - can grade a learner map against a reference map through
 * this facade and get exactly the same result the activity would, without
 * reaching into internal classes and without duplicating scoring logic.
 *
 * Both maps are passed as the serialised snapshot JSON that the ViMi Pad editor
 * exports (nodes with stableid/label/content, relations with sourceid/targetid/
 * label, optional containers and memberships). No Moodle context, activity
 * instance or database access is required.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class score {
    /** @var int Exact (normalised) label matching. */
    public const MATCH_EXACT = matcher_factory::MODE_EXACT;

    /** @var int Fuzzy (edit-distance) label matching. */
    public const MATCH_FUZZY = matcher_factory::MODE_LEVENSHTEIN;

    /** @var int Word-overlap label matching. */
    public const MATCH_TOKEN = matcher_factory::MODE_TOKEN;

    /** Aggregate several peer fractions by their arithmetic mean. */
    public const AGG_MEAN = 'mean';

    /** Aggregate several peer fractions by their median (robust to one outlier). */
    public const AGG_MEDIAN = 'median';

    /** Aggregate by trimmed mean: drop one lowest and one highest, then mean. */
    public const AGG_TRIMMED = 'trimmedmean';

    /**
     * Score a response map against a reference map.
     *
     * @param string $responsejson Serialised snapshot JSON of the learner map.
     * @param string $referencejson Serialised snapshot JSON of the reference map.
     * @param string $scorerkey The vimipadassess scorer key to use (default 'reference').
     * @param int $matchmode One of the MATCH_* constants.
     * @return array|null Result keys: score, partscores, concepts, propositions,
     *                    metrics, informational; or null when the inputs are
     *                    unusable (invalid JSON, or the scorer is not installed).
     */
    public static function against_reference(
        string $responsejson,
        string $referencejson,
        string $scorerkey = 'reference',
        int $matchmode = self::MATCH_EXACT
    ): ?array {
        $responsedata = json_decode($responsejson, true);
        $referencedata = json_decode($referencejson, true);
        if (!is_array($responsedata) || !is_array($referencedata)) {
            return null;
        }

        $scorer = registry::get($scorerkey);
        if ($scorer === null) {
            return null;
        }

        $submission = submission::from_snapshot_data($responsedata);
        $reference = submission::from_snapshot_data($referencedata);
        $matcher = matcher_factory::create($matchmode);

        return self::result_to_array($scorer->score($submission, [$reference], $matcher));
    }

    /**
     * The overall fraction (0.0-1.0) of scoring a response against a reference.
     *
     * A convenience wrapper around {@see self::against_reference()} for callers
     * that only need the number, such as a question type's grade_response().
     *
     * @param string $responsejson Serialised snapshot JSON of the learner map.
     * @param string $referencejson Serialised snapshot JSON of the reference map.
     * @param string $scorerkey The vimipadassess scorer key to use (default 'reference').
     * @param int $matchmode One of the MATCH_* constants.
     * @return float|null The fraction, or null when the inputs are unusable.
     */
    public static function fraction(
        string $responsejson,
        string $referencejson,
        string $scorerkey = 'reference',
        int $matchmode = self::MATCH_EXACT
    ): ?float {
        $result = self::against_reference($responsejson, $referencejson, $scorerkey, $matchmode);
        return $result === null ? null : (float) $result['score'];
    }

    /**
     * Combine several peer fractions into one final fraction.
     *
     * Peer review compares each reviewer's map against the author's map with
     * {@see self::fraction()}, which yields one fraction per reviewer. This
     * aggregates those fractions into a single grade fraction. Values are
     * clamped to 0.0-1.0 and non-numeric entries are ignored.
     *
     * @param float[] $fractions Peer fractions, each expected in 0.0-1.0.
     * @param string $method One of the AGG_* constants (default AGG_MEAN).
     * @return float|null The aggregated fraction, or null when there is no
     *     usable value to aggregate.
     */
    public static function aggregate_fractions(array $fractions, string $method = self::AGG_MEAN): ?float {
        $values = [];
        foreach ($fractions as $fraction) {
            if (is_numeric($fraction)) {
                $values[] = min(1.0, max(0.0, (float) $fraction));
            }
        }
        if (empty($values)) {
            return null;
        }
        sort($values, SORT_NUMERIC);
        $count = count($values);

        switch ($method) {
            case self::AGG_MEDIAN:
                $mid = intdiv($count, 2);
                if ($count % 2 === 1) {
                    return $values[$mid];
                }
                return ($values[$mid - 1] + $values[$mid]) / 2.0;

            case self::AGG_TRIMMED:
                if ($count >= 3) {
                    $values = array_slice($values, 1, $count - 2);
                }
                return array_sum($values) / count($values);

            case self::AGG_MEAN:
            default:
                return array_sum($values) / $count;
        }
    }

    /**
     * The keys of the reference-based scorers that are installed and usable.
     *
     * Excludes reference-free scorers and scorers that call the AI subsystem, so
     * a caller can offer only the scorers that fit this facade's map-vs-map use.
     *
     * @return string[] Scorer keys.
     */
    public static function reference_scorers(): array {
        $keys = [];
        foreach (registry::all() as $key => $scorer) {
            if ($scorer->requires_reference() && !$scorer->uses_ai()) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Convert an internal result value object into a plain, stable array.
     *
     * @param result $result The scorer result.
     * @return array
     */
    private static function result_to_array(result $result): array {
        return [
            'score' => $result->score,
            'partscores' => $result->partscores,
            'concepts' => $result->concepts,
            'propositions' => $result->propositions,
            'metrics' => $result->metrics,
            'informational' => $result->informational,
        ];
    }
}
