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

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;

/**
 * Tests for the structural-overview scorer.
 *
 * @package    vimipadassess_structure
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_structure\scorer
 */
final class scorer_test extends \advanced_testcase {
    /**
     * Build a submission from concept labels and [source, relation, target] triples.
     *
     * @param string[] $concepts Concept labels.
     * @param array[] $propositions Triples as [source, relation, target].
     * @return submission
     */
    private function make(array $concepts, array $propositions): submission {
        $keyed = [];
        foreach ($concepts as $i => $label) {
            $keyed['c' . $i] = $label;
        }
        $props = [];
        foreach ($propositions as $triple) {
            $props[] = ['source' => $triple[0], 'relation' => $triple[1], 'target' => $triple[2]];
        }
        return new submission('conceptmap', $keyed, $props);
    }

    /**
     * The scorer reports concept and proposition counts.
     *
     * @return void
     */
    public function test_counts(): void {
        $sub = $this->make(['A', 'B', 'C'], [['A', 'r', 'B'], ['B', 'r', 'C']]);

        $result = (new scorer())->score($sub, [], new exact_matcher());

        $this->assertTrue($result->informational);
        $labels = $result->metrics;
        $this->assertSame('3', $labels[get_string('metric_concepts', 'vimipadassess_structure')]);
        $this->assertSame('2', $labels[get_string('metric_propositions', 'vimipadassess_structure')]);
    }

    /**
     * A concept in no proposition is reported as isolated.
     *
     * @return void
     */
    public function test_isolated_concept(): void {
        $sub = $this->make(['A', 'B', 'Lonely'], [['A', 'r', 'B']]);

        $result = (new scorer())->score($sub, [], new exact_matcher());

        $this->assertSame('1', $result->metrics[get_string('metric_isolated', 'vimipadassess_structure')]);
    }

    /**
     * A concept linked three or more times counts as a hub.
     *
     * @return void
     */
    public function test_hub_detection(): void {
        $sub = $this->make(['Hub', 'A', 'B', 'C'], [
            ['Hub', 'r', 'A'],
            ['Hub', 'r', 'B'],
            ['Hub', 'r', 'C'],
        ]);

        $result = (new scorer())->score($sub, [], new exact_matcher());

        $this->assertSame('1', $result->metrics[get_string('metric_hubs', 'vimipadassess_structure')]);
    }

    /**
     * The scorer is reference-free.
     *
     * @return void
     */
    public function test_reference_free(): void {
        $this->assertFalse((new scorer())->requires_reference());
    }

    /**
     * An empty map does not error and scores zero.
     *
     * @return void
     */
    public function test_empty_map(): void {
        $result = (new scorer())->score($this->make([], []), [], new exact_matcher());

        $this->assertSame(0.0, $result->score);
        $this->assertSame('0', $result->metrics[get_string('metric_concepts', 'vimipadassess_structure')]);
    }

    /**
     * The registry discovers the structure scorer as reference-free.
     *
     * @return void
     */
    public function test_registry_discovers_scorer(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $scorer = registry::get('structure');
        $this->assertInstanceOf(scorer::class, $scorer);
        $this->assertFalse($scorer->requires_reference());
    }
}
