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

namespace mod_vimipad\local\policy;

/**
 * The structured result of evaluating a map against a {@see constraint_config}.
 *
 * The same report drives the hard submission gate (via {@see is_satisfied()})
 * and the soft, edit-time hints (via {@see messages()}), so both surfaces stay
 * in sync.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constraint_report {
    /** @var string[] Required concepts that are missing. */
    public array $requiredmissing = [];

    /** @var string[] Forbidden concepts that are present. */
    public array $forbiddenpresent = [];

    /** @var string[] Relation types used but not allowed. */
    public array $typeviolations = [];

    /** @var array|null [min, actual] when below the concept minimum, else null. */
    public ?array $belowminnodes = null;

    /** @var array|null [min, actual] when below the relation minimum, else null. */
    public ?array $belowminrelations = null;

    /**
     * Whether the map satisfies every constraint.
     *
     * @return bool
     */
    public function is_satisfied(): bool {
        return $this->requiredmissing === []
            && $this->forbiddenpresent === []
            && $this->typeviolations === []
            && $this->belowminnodes === null
            && $this->belowminrelations === null;
    }

    /**
     * Localized, human-readable messages for each violation.
     *
     * @return string[]
     */
    public function messages(): array {
        $messages = [];
        if ($this->requiredmissing !== []) {
            $messages[] = get_string('constraint:requiredmissing', 'mod_vimipad', implode(', ', $this->requiredmissing));
        }
        if ($this->forbiddenpresent !== []) {
            $messages[] = get_string('constraint:forbiddenpresent', 'mod_vimipad', implode(', ', $this->forbiddenpresent));
        }
        if ($this->typeviolations !== []) {
            $messages[] = get_string('constraint:typenotallowed', 'mod_vimipad', implode(', ', $this->typeviolations));
        }
        if ($this->belowminnodes !== null) {
            $messages[] = get_string('constraint:belowminnodes', 'mod_vimipad', (object) [
                'min' => $this->belowminnodes[0], 'actual' => $this->belowminnodes[1],
            ]);
        }
        if ($this->belowminrelations !== null) {
            $messages[] = get_string('constraint:belowminrelations', 'mod_vimipad', (object) [
                'min' => $this->belowminrelations[0], 'actual' => $this->belowminrelations[1],
            ]);
        }
        return $messages;
    }

    /**
     * A single-line summary of all violations (empty string if satisfied).
     *
     * @return string
     */
    public function summary(): string {
        return implode('; ', $this->messages());
    }
}
