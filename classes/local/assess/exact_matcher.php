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
 * Normalised exact matcher: case-, space- and diacritic-insensitive equality.
 *
 * The default matcher. Returns 1.0 when the normalised labels are identical and
 * 0.0 otherwise, so scorers built on it behave as a strict concept/proposition
 * overlap until a fuzzier matcher is supplied.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exact_matcher implements matcher {
    /**
     * 1.0 if the normalised labels are equal, else 0.0.
     *
     * @param string $a The first label.
     * @param string $b The second label.
     * @return float
     */
    public function weight(string $a, string $b): float {
        $na = self::normalise($a);
        $nb = self::normalise($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        return $na === $nb ? 1.0 : 0.0;
    }

    /**
     * Lower-case, collapse whitespace and strip diacritics for comparison.
     *
     * @param string $value The raw label.
     * @return string
     */
    public static function normalise(string $value): string {
        $value = \core_text::strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
        return trim($value);
    }
}
