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
 * Creates the label matcher an activity has chosen.
 *
 * Maps the stored match mode to a concrete matcher, so scorers stay agnostic of
 * which matching strategy is in force. Unknown modes fall back to exact matching.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matcher_factory {
    /** @var int Exact (normalised) matching. */
    public const MODE_EXACT = 0;

    /** @var int Fuzzy (edit-distance) matching. */
    public const MODE_LEVENSHTEIN = 1;

    /** @var int Word-overlap matching. */
    public const MODE_TOKEN = 2;

    /**
     * Create the matcher for a match mode.
     *
     * @param int $mode One of the MODE_* constants.
     * @return matcher
     */
    public static function create(int $mode): matcher {
        switch ($mode) {
            case self::MODE_LEVENSHTEIN:
                return new levenshtein_matcher();
            case self::MODE_TOKEN:
                return new token_matcher();
            default:
                return new exact_matcher();
        }
    }

    /**
     * Match mode => localised label, for a settings menu.
     *
     * @return array<int,string>
     */
    public static function menu(): array {
        return [
            self::MODE_EXACT => get_string('matchmode:exact', 'mod_vimipad'),
            self::MODE_LEVENSHTEIN => get_string('matchmode:levenshtein', 'mod_vimipad'),
            self::MODE_TOKEN => get_string('matchmode:token', 'mod_vimipad'),
        ];
    }
}
