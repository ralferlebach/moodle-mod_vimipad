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

namespace vimipadassess_llm;

use mod_vimipad\local\assess\prompt_scorer;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;
use mod_vimipad\local\assess\tuple_text;

/**
 * Tests for the LLM scorer's deterministic halves.
 *
 * @package    vimipadassess_llm
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_llm\scorer
 */
final class scorer_test extends \advanced_testcase {
    /**
     * Build a submission from labels and [source, relation, target] triples.
     *
     * @param string[] $concepts Concept labels.
     * @param array[] $triples Propositions.
     * @return submission
     */
    private function make(array $concepts, array $triples): submission {
        $keyed = [];
        foreach ($concepts as $i => $label) {
            $keyed['c' . $i] = $label;
        }
        $props = [];
        foreach ($triples as $t) {
            $props[] = ['source' => $t[0], 'relation' => $t[1], 'target' => $t[2]];
        }
        return new submission('conceptmap', $keyed, $props);
    }

    /**
     * Tuple-to-text renders concepts and one sentence per proposition.
     *
     * @return void
     */
    public function test_tuple_text(): void {
        $submission = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $text = tuple_text::render($submission);
        $this->assertStringContainsString('Plant', $text);
        $this->assertStringContainsString('Plant produces Oxygen.', $text);
    }

    /**
     * The prompt contains the map text and, when present, the reference.
     *
     * @return void
     */
    public function test_build_prompt(): void {
        $sub = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $ref = $this->make(['Plant', 'Oxygen', 'Sunlight'], [['Sunlight', 'drives', 'Plant']]);

        $withref = (new scorer())->build_prompt($sub, [$ref]);
        $this->assertStringContainsString('Plant produces Oxygen.', $withref);
        $this->assertStringContainsString('Sunlight drives Plant.', $withref);
        $this->assertStringContainsString('SCORE:', $withref);

        $withoutref = (new scorer())->build_prompt($sub, []);
        $this->assertStringNotContainsString('Sunlight', $withoutref);
    }

    /**
     * The reply is parsed into a normalised score with the rationale kept.
     *
     * @return void
     */
    public function test_interpret(): void {
        $sub = $this->make(['Plant'], []);
        $reply = "SCORE: 80\nThe map captures the key idea but misses the energy source.";

        $result = (new scorer())->interpret($reply, $sub, []);

        $this->assertEqualsWithDelta(0.8, $result->score, 0.0001);
        $this->assertFalse($result->informational);
        $this->assertStringContainsString('energy source', $result->metrics['rationale']);
        $this->assertStringNotContainsString('SCORE:', $result->metrics['rationale']);
    }

    /**
     * A reply without a score line yields a zero score but keeps the text.
     *
     * @return void
     */
    public function test_interpret_without_score(): void {
        $result = (new scorer())->interpret('I cannot assess this map.', $this->make(['A'], []), []);
        $this->assertSame(0.0, $result->score);
        $this->assertStringContainsString('cannot assess', $result->metrics['rationale']);
    }

    /**
     * The scorer declares itself AI-driven and reference-optional.
     *
     * @return void
     */
    public function test_flags(): void {
        $scorer = new scorer();
        $this->assertTrue($scorer->uses_ai());
        $this->assertFalse($scorer->requires_reference());
        $this->assertInstanceOf(prompt_scorer::class, $scorer);
    }

    /**
     * The registry discovers the installed LLM scorer.
     *
     * @return void
     */
    public function test_registry_discovers_scorer(): void {
        $this->resetAfterTest();
        registry::reset_cache();
        $this->assertInstanceOf(scorer::class, registry::get('llm'));
    }
}
