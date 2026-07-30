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
 * The outcome of scoring a submission: a suggestion, never a set grade.
 *
 * Carries an overall score (0.0–1.0), per-dimension part scores and a
 * human-readable breakdown of what was matched, missed and superfluous, so a
 * teacher can see why a suggestion was made and adjust the grade themselves.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /** @var float Overall score from 0.0 to 1.0. */
    public float $score;

    /** @var array<string,float> Part scores per dimension, e.g. ['concepts' => 0.8]. */
    public array $partscores;

    /** @var array<string,string[]> Concept breakdown: matched, missing, extra. */
    public array $concepts;

    /** @var array<string,string[]> Proposition breakdown: matched, missing, extra. */
    public array $propositions;

    /** @var array<string,string> Named informational metrics (label => value), for reference-free scorers. */
    public array $metrics;

    /** @var bool True when the result is a structural overview, not a grade suggestion. */
    public bool $informational;

    /**
     * Constructor.
     *
     * @param float $score Overall score (0.0–1.0).
     * @param array $partscores Part scores per dimension.
     * @param array $concepts Concept breakdown (matched/missing/extra).
     * @param array $propositions Proposition breakdown (matched/missing/extra).
     * @param array $metrics Named metrics (label => value) for informational scorers.
     * @param bool $informational True to present as an overview rather than a grade suggestion.
     */
    public function __construct(
        float $score,
        array $partscores,
        array $concepts,
        array $propositions,
        array $metrics = [],
        bool $informational = false
    ) {
        $this->score = max(0.0, min(1.0, $score));
        $this->partscores = $partscores;
        $this->concepts = $concepts;
        $this->propositions = $propositions;
        $this->metrics = $metrics;
        $this->informational = $informational;
    }

    /**
     * The suggested grade for a given maximum.
     *
     * @param float $maxgrade The activity's maximum grade.
     * @return float
     */
    public function suggested_grade(float $maxgrade): float {
        return round($this->score * $maxgrade, 2);
    }
}
