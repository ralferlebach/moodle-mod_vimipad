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
 * Renders a submission as readable text (tuple-to-text).
 *
 * A shared core service: turns a concept map's concepts and propositions into
 * plain sentences a language model (or a human) can read. This is the bridge
 * between the structured map and prompt-based scorers.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tuple_text {
    /**
     * Render a submission as a concept list plus one sentence per proposition.
     *
     * @param submission $submission The submission.
     * @return string
     */
    public static function render(submission $submission): string {
        $concepts = $submission->concept_labels();
        $lines = [];
        if (!empty($concepts)) {
            $lines[] = get_string('tuple:concepts', 'mod_vimipad', implode(', ', $concepts));
        }
        foreach ($submission->propositions as $proposition) {
            $lines[] = self::sentence($proposition);
        }
        if (empty($lines)) {
            return get_string('tuple:empty', 'mod_vimipad');
        }
        return implode("\n", $lines);
    }

    /**
     * Render a single proposition as a sentence.
     *
     * @param array $proposition The proposition ['source','relation','target'].
     * @return string
     */
    public static function sentence(array $proposition): string {
        $relation = trim((string) $proposition['relation']);
        if ($relation === '') {
            $relation = get_string('tuple:relates', 'mod_vimipad');
        }
        return trim($proposition['source'] . ' ' . $relation . ' ' . $proposition['target']) . '.';
    }
}
