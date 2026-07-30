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
 * Evaluates a normalized map against an activity's constraints.
 *
 * Pure and deterministic: it takes the same normalized structure that
 * {@see \mod_vimipad\local\service\snapshot_service::build_normalized()} yields
 * plus a {@see constraint_config}, and returns a {@see constraint_report}. It
 * performs no I/O, so it is fully unit testable and can back both the hard
 * submission gate and soft edit-time hints.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class constraint_policy {
    /**
     * Evaluate a normalized map against the config.
     *
     * @param array $normalized The normalized map (with 'nodes' and 'relations').
     * @param constraint_config $config The activity constraints.
     * @return constraint_report The structured findings.
     */
    public static function evaluate(array $normalized, constraint_config $config): constraint_report {
        $report = new constraint_report();
        if ($config->is_empty()) {
            return $report;
        }

        $nodes = is_array($normalized['nodes'] ?? null) ? $normalized['nodes'] : [];
        $relations = is_array($normalized['relations'] ?? null) ? $normalized['relations'] : [];

        // Concept labels present, lowercased, for set membership tests.
        $present = [];
        foreach ($nodes as $node) {
            $label = \core_text::strtolower(trim((string) ($node['label'] ?? '')));
            if ($label !== '') {
                $present[$label] = true;
            }
        }

        foreach ($config->requiredconcepts as $term) {
            if (!isset($present[$term])) {
                $report->requiredmissing[] = $term;
            }
        }
        foreach ($config->forbiddenconcepts as $term) {
            if (isset($present[$term])) {
                $report->forbiddenpresent[] = $term;
            }
        }

        if ($config->allowedrelationtypes !== []) {
            $allowed = array_flip($config->allowedrelationtypes);
            $seen = [];
            foreach ($relations as $relation) {
                $type = \core_text::strtolower(trim((string) ($relation['type'] ?? '')));
                if ($type !== '' && !isset($allowed[$type]) && !isset($seen[$type])) {
                    $seen[$type] = true;
                    $report->typeviolations[] = $type;
                }
            }
        }

        if ($config->minnodes > 0 && count($nodes) < $config->minnodes) {
            $report->belowminnodes = [$config->minnodes, count($nodes)];
        }
        if ($config->minrelations > 0 && count($relations) < $config->minrelations) {
            $report->belowminrelations = [$config->minrelations, count($relations)];
        }

        return $report;
    }
}
