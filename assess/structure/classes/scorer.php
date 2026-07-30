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

namespace vimipadassess_structure;

use mod_vimipad\local\assess\matcher;
use mod_vimipad\local\assess\result;
use mod_vimipad\local\assess\scorer as base_scorer;
use mod_vimipad\local\assess\submission;

/**
 * Reference-free structural overview of a submission.
 *
 * Reports graph metrics — concept and proposition counts, links per concept,
 * isolated concepts and well-connected hubs — as an aid, never a grade. Rich
 * structure alone does not mean a correct map (structure can reward nonsense),
 * so this scorer is explicitly informational: it supplies context a grader can
 * weigh, not an automatic mark.
 *
 * @package    vimipadassess_structure
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorer extends base_scorer {
    /** @var float Links-per-concept treated as a well-developed map (for the connectedness part score). */
    private const CONNECTEDNESS_TARGET = 1.5;

    /** @var int Degree at or above which a concept counts as a hub. */
    private const HUB_DEGREE = 3;

    /**
     * The subplugin key.
     *
     * @return string
     */
    public function get_key(): string {
        return 'structure';
    }

    /**
     * The localised display name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'vimipadassess_structure');
    }

    /**
     * Whether this scorer can handle the given diagram profile.
     *
     * @param string $profile The profile key.
     * @return bool
     */
    public function supports_profile(string $profile): bool {
        return true;
    }

    /**
     * This scorer is reference-free.
     *
     * @return bool
     */
    public function requires_reference(): bool {
        return false;
    }

    /**
     * Compute structural metrics for the submission.
     *
     * @param submission $submission The submission to describe.
     * @param submission[] $references Ignored; this scorer is reference-free.
     * @param matcher $matcher Ignored; structure needs no label matching.
     * @return result An informational result carrying the metrics.
     */
    public function score(submission $submission, array $references, matcher $matcher): result {
        $concepts = $submission->concept_labels();
        $conceptcount = count($concepts);
        $propositioncount = count($submission->propositions);

        $degree = array_fill_keys($concepts, 0);
        foreach ($submission->propositions as $proposition) {
            foreach (['source', 'target'] as $end) {
                if (isset($degree[$proposition[$end]])) {
                    $degree[$proposition[$end]]++;
                }
            }
        }
        $isolated = 0;
        $hubs = 0;
        foreach ($degree as $count) {
            if ($count === 0) {
                $isolated++;
            }
            if ($count >= self::HUB_DEGREE) {
                $hubs++;
            }
        }

        $connectedness = $conceptcount > 0 ? $propositioncount / $conceptcount : 0.0;
        $connectednessscore = min(1.0, $connectedness / self::CONNECTEDNESS_TARGET);
        $isolationpenalty = $conceptcount > 0 ? $isolated / $conceptcount : 1.0;
        $integrationscore = $conceptcount > 0 ? min(1.0, ($hubs / $conceptcount) * 3) : 0.0;
        $score = max(0.0, min(1.0, 0.6 * $connectednessscore + 0.4 * (1 - $isolationpenalty)));

        $metrics = [
            get_string('metric_concepts', 'vimipadassess_structure') => (string) $conceptcount,
            get_string('metric_propositions', 'vimipadassess_structure') => (string) $propositioncount,
            get_string('metric_connectedness', 'vimipadassess_structure') => format_float($connectedness, 2),
            get_string('metric_isolated', 'vimipadassess_structure') => (string) $isolated,
            get_string('metric_hubs', 'vimipadassess_structure') => (string) $hubs,
        ];

        return new result(
            $score,
            ['connectedness' => $connectednessscore, 'integration' => $integrationscore],
            ['matched' => [], 'missing' => [], 'extra' => []],
            ['matched' => [], 'missing' => [], 'extra' => []],
            $metrics,
            true
        );
    }
}
