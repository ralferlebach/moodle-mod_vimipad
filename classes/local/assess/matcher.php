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
 * Decides how similar two labels are, as a weight from 0.0 to 1.0.
 *
 * The exchangeable part of every content scorer: exact/normalised today,
 * Levenshtein or embedding-based later, behind the same interface. A weight of
 * 1.0 is a perfect match, 0.0 no match; values between express fuzzy matches.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface matcher {
    /**
     * The match weight between two labels, from 0.0 (no match) to 1.0 (perfect).
     *
     * @param string $a The first label.
     * @param string $b The second label.
     * @return float
     */
    public function weight(string $a, string $b): float;
}
