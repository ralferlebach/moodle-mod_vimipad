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
 * A concept map reduced to the parts a scorer works on: concepts and propositions.
 *
 * Built from a snapshot's normalized JSON. A proposition is the didactically
 * meaningful triple source concept – relation – target concept.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission {
    /** @var string $profile The diagram profile (conceptmap, tree, …). */
    public string $profile;

    /** @var string[] Concept labels, keyed by stable id. */
    public array $concepts;

    /** @var array[] Propositions: each ['source' => string, 'relation' => string, 'target' => string]. */
    public array $propositions;

    /** @var array[] Sub-maps (containers): each ['label' => string, 'concepts' => string[]]. */
    public array $submaps;

    /**
     * Constructor.
     *
     * @param string $profile The diagram profile.
     * @param array $concepts Concept labels keyed by stable id.
     * @param array $propositions Proposition triples.
     * @param array $submaps Sub-maps: each ['label' => string, 'concepts' => string[]].
     */
    public function __construct(string $profile, array $concepts, array $propositions, array $submaps = []) {
        $this->profile = $profile;
        $this->concepts = $concepts;
        $this->propositions = $propositions;
        $this->submaps = $submaps;
    }

    /**
     * Build a submission from a snapshot's decoded JSON (nodes + relations).
     *
     * @param array $data The decoded snapshot json.
     * @return self
     */
    public static function from_snapshot_data(array $data): self {
        $concepts = [];
        foreach (($data['nodes'] ?? []) as $node) {
            $concepts[$node['stableid']] = trim((string) ($node['label'] ?? ''));
        }

        $propositions = [];
        foreach (($data['relations'] ?? []) as $relation) {
            $source = $concepts[$relation['sourceid']] ?? '';
            $target = $concepts[$relation['targetid']] ?? '';
            if ($source === '' || $target === '') {
                continue;
            }
            $propositions[] = [
                'source' => $source,
                'relation' => trim((string) ($relation['label'] ?? '')),
                'target' => $target,
            ];
        }

        $submaps = [];
        foreach (($data['containers'] ?? []) as $container) {
            $submaps[$container['stableid']] = [
                'label' => trim((string) ($container['label'] ?? '')),
                'concepts' => [],
            ];
        }
        foreach (($data['memberships'] ?? []) as $membership) {
            if (($membership['itemtype'] ?? '') !== 'node') {
                continue;
            }
            $containerid = $membership['containerstableid'] ?? '';
            $label = $concepts[$membership['itemstableid'] ?? ''] ?? '';
            if (isset($submaps[$containerid]) && $label !== '') {
                $submaps[$containerid]['concepts'][] = $label;
            }
        }

        return new self(
            (string) ($data['profile'] ?? 'conceptmap'),
            $concepts,
            $propositions,
            array_values($submaps)
        );
    }

    /**
     * The distinct, non-empty concept labels.
     *
     * @return string[]
     */
    public function concept_labels(): array {
        return array_values(array_unique(array_filter($this->concepts, static fn($label) => $label !== '')));
    }
}
