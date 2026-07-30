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
 * Word-overlap matcher for multi-word labels.
 *
 * Compares labels as sets of words (Jaccard similarity), so word order and
 * filler words matter less: "cell membrane" and "membrane of the cell" score
 * highly. Suited to activities where phrasing varies but the key terms recur.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_matcher implements matcher {
    /**
     * The Jaccard word-overlap of two labels.
     *
     * @param string $a The first label.
     * @param string $b The second label.
     * @return float
     */
    public function weight(string $a, string $b): float {
        $ta = self::tokens($a);
        $tb = self::tokens($b);
        if (empty($ta) || empty($tb)) {
            return 0.0;
        }
        $intersection = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Split a normalised label into distinct word tokens.
     *
     * @param string $value The raw label.
     * @return string[]
     */
    private static function tokens(string $value): array {
        $normalised = exact_matcher::normalise($value);
        $parts = preg_split('/[^a-z0-9]+/', $normalised, -1, PREG_SPLIT_NO_EMPTY);
        return $parts ? array_values(array_unique($parts)) : [];
    }
}
