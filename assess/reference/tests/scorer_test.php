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

namespace vimipadassess_reference;

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;

/**
 * Tests for the reference-comparison scorer.
 *
 * @package    vimipadassess_reference
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_reference\scorer
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
     * An identical map scores 1.0 with nothing missing or extra.
     *
     * @return void
     */
    public function test_perfect_match(): void {
        $ref = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $sub = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertSame([], $result->concepts['missing']);
        $this->assertSame([], $result->concepts['extra']);
        $this->assertSame([], $result->propositions['missing']);
    }

    /**
     * A missing concept and proposition lower the score and are reported.
     *
     * @return void
     */
    public function test_missing_content(): void {
        $ref = $this->make(['Plant', 'Oxygen', 'Sunlight'], [
            ['Plant', 'produces', 'Oxygen'],
            ['Sunlight', 'drives', 'Plant'],
        ]);
        $sub = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertLessThan(1.0, $result->score);
        $this->assertContains('Sunlight', $result->concepts['missing']);
        $this->assertContains('Sunlight → drives → Plant', $result->propositions['missing']);
        $this->assertSame([], $result->concepts['extra']);
    }

    /**
     * Superfluous concepts are reported as extra and reduce precision.
     *
     * @return void
     */
    public function test_extra_content(): void {
        $ref = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $sub = $this->make(['Plant', 'Oxygen', 'Banana'], [['Plant', 'produces', 'Oxygen']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertContains('Banana', $result->concepts['extra']);
        $this->assertLessThan(1.0, $result->partscores['concepts']);
    }

    /**
     * A reversed proposition does not match: direction matters.
     *
     * @return void
     */
    public function test_direction_matters(): void {
        $ref = $this->make(['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $sub = $this->make(['Plant', 'Oxygen'], [['Oxygen', 'produces', 'Plant']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertContains('Plant → produces → Oxygen', $result->propositions['missing']);
        $this->assertContains('Oxygen → produces → Plant', $result->propositions['extra']);
    }

    /**
     * Matching is case-, space- and diacritic-insensitive via the exact matcher.
     *
     * @return void
     */
    public function test_normalised_matching(): void {
        $ref = $this->make(['Photosynthese'], []);
        $sub = $this->make(['  photosynthese '], []);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertEqualsWithDelta(1.0, $result->partscores['concepts'], 0.0001);
        $this->assertSame([], $result->concepts['missing']);
    }

    /**
     * With no reference, the score is zero and all content is extra.
     *
     * @return void
     */
    public function test_no_reference(): void {
        $sub = $this->make(['Plant'], [['Plant', 'is', 'Green']]);

        $result = (new scorer())->score($sub, [], new exact_matcher());

        $this->assertSame(0.0, $result->score);
        $this->assertContains('Plant', $result->concepts['extra']);
        $this->assertContains('Plant → is → Green', $result->propositions['extra']);
    }

    /**
     * The registry discovers the installed reference scorer.
     *
     * @return void
     */
    public function test_registry_discovers_scorer(): void {
        $this->resetAfterTest();
        registry::reset_cache();

        $scorer = registry::get('reference');
        $this->assertInstanceOf(scorer::class, $scorer);
        $this->assertTrue($scorer->supports_profile('conceptmap'));
        $this->assertArrayHasKey('reference', registry::for_profile('conceptmap'));
    }
}
