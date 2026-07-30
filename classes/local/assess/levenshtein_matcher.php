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
 * Fuzzy matcher based on normalised edit distance.
 *
 * Tolerates typos and minor spelling variation: the weight is the Levenshtein
 * similarity (1 - distance / longer length) of the normalised labels, so close
 * spellings score high and unrelated labels score near zero. Below a small floor
 * the weight is clamped to zero to avoid crediting coincidental overlap.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class levenshtein_matcher implements matcher {
    /** @var float Similarities below this floor count as no match. */
    private const FLOOR = 0.5;

    /**
     * The normalised edit-distance similarity between two labels.
     *
     * @param string $a The first label.
     * @param string $b The second label.
     * @return float
     */
    public function weight(string $a, string $b): float {
        $na = exact_matcher::normalise($a);
        $nb = exact_matcher::normalise($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }
        // PHP's levenshtein() only handles strings up to 255 bytes.
        if (strlen($na) > 255 || strlen($nb) > 255) {
            return 0.0;
        }
        $longest = max(strlen($na), strlen($nb));
        $similarity = 1.0 - (levenshtein($na, $nb) / $longest);
        return $similarity >= self::FLOOR ? $similarity : 0.0;
    }
}
