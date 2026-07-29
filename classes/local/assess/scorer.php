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
 * Base class for an automatic scorer (a vimipadassess_* subplugin).
 *
 * A scorer compares a submission against zero or more reference solutions and
 * produces a result: a suggested score plus a breakdown. The matcher is
 * injected, so the same scorer works with exact or fuzzy/semantic matching.
 * Scorers declare which diagram profiles they can handle, so the registry can
 * offer only the ones that fit the activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class scorer {
    /**
     * The subplugin key (e.g. 'reference').
     *
     * @return string
     */
    abstract public function get_key(): string;

    /**
     * The localised display name.
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Whether this scorer can handle the given diagram profile.
     *
     * @param string $profile The profile key (e.g. 'conceptmap').
     * @return bool
     */
    abstract public function supports_profile(string $profile): bool;

    /**
     * Whether this scorer calls the AI subsystem and must therefore run on demand
     * (not automatically on page load).
     *
     * @return bool
     */
    public function uses_ai(): bool {
        return false;
    }

    /**
     * Whether this scorer needs at least one reference solution to run.
     *
     * @return bool
     */
    public function requires_reference(): bool {
        return true;
    }

    /**
     * Score a submission against reference solutions.
     *
     * @param submission $submission The submission to score.
     * @param submission[] $references The reference solution(s), may be empty.
     * @param matcher $matcher The label matcher to use.
     * @return result The suggested score and breakdown.
     */
    abstract public function score(submission $submission, array $references, matcher $matcher): result;
}
