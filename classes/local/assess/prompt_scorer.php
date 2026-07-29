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
 * A scorer that works through a language-model prompt.
 *
 * Splits the AI interaction into two testable, deterministic halves: building
 * the prompt from the map, and interpreting the model's reply into a result.
 * The actual model call is performed by the caller (the assess service), so the
 * prompt scorer itself stays free of context and infrastructure.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface prompt_scorer {
    /**
     * Build the prompt sent to the language model.
     *
     * @param submission $submission The submission to assess.
     * @param submission[] $references The reference solution(s), may be empty.
     * @return string
     */
    public function build_prompt(submission $submission, array $references): string;

    /**
     * Interpret the model's reply into a result.
     *
     * @param string $airesponse The raw model reply.
     * @param submission $submission The submission that was assessed.
     * @param submission[] $references The reference solution(s).
     * @return result
     */
    public function interpret(string $airesponse, submission $submission, array $references): result;
}
