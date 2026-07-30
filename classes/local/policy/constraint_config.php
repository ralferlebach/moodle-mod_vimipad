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

use stdClass;

/**
 * The activity's map constraints, resolved from the instance settings.
 *
 * A plain, context-free value object so {@see constraint_policy} can be unit
 * tested with hand-built configurations. {@see from_instance()} reads the
 * qualitative constraint fields when they are present on the instance, so the
 * policy activates automatically once those settings are added to the form.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constraint_config {
    /** @var string[] Concept labels (lowercased) that must be present. */
    public array $requiredconcepts = [];

    /** @var string[] Concept labels (lowercased) that must NOT be present. */
    public array $forbiddenconcepts = [];

    /** @var string[] Allowed relation types; empty means every type is allowed. */
    public array $allowedrelationtypes = [];

    /** @var int Minimum number of concepts (0 = no minimum). */
    public int $minnodes = 0;

    /** @var int Minimum number of relations (0 = no minimum). */
    public int $minrelations = 0;

    /**
     * Build the config from an activity instance.
     *
     * @param stdClass $instance The vimipad instance record.
     * @return self
     */
    public static function from_instance(stdClass $instance): self {
        $config = new self();
        $config->requiredconcepts = self::split_terms($instance->requiredconcepts ?? '');
        $config->forbiddenconcepts = self::split_terms($instance->forbiddenconcepts ?? '');
        $config->allowedrelationtypes = self::split_terms($instance->allowedrelationtypes ?? '');
        $config->minnodes = (int) ($instance->minnodes ?? 0);
        $config->minrelations = (int) ($instance->minrelations ?? 0);
        return $config;
    }

    /**
     * Whether the config imposes any constraint at all.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return $this->requiredconcepts === []
            && $this->forbiddenconcepts === []
            && $this->allowedrelationtypes === []
            && $this->minnodes === 0
            && $this->minrelations === 0;
    }

    /**
     * Split a free-text list (newline- or comma-separated) into a normalized,
     * de-duplicated list of lowercased, trimmed terms.
     *
     * @param string $raw The raw field value.
     * @return string[]
     */
    public static function split_terms(string $raw): array {
        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $term = \core_text::strtolower(trim($part));
            if ($term !== '') {
                $out[$term] = true;
            }
        }
        return array_keys($out);
    }
}
