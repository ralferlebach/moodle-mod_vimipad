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

namespace vimipadassess_text;

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\levenshtein_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;

/**
 * Tests for the description (ROUGE) scorer.
 *
 * @package    vimipadassess_text
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_text\scorer
 */
final class scorer_test extends \advanced_testcase {
    /**
     * Build a submission from label => description text pairs.
     *
     * @param array $described Label => description text.
     * @return submission
     */
    private function make(array $described): submission {
        $concepts = [];
        $descriptions = [];
        $i = 0;
        foreach ($described as $label => $text) {
            $stableid = 'node_' . sprintf('%012x', ++$i);
            $concepts[$stableid] = (string) $label;
            if ($text !== '') {
                $descriptions[$stableid] = $text;
            }
        }
        return new submission('conceptmap', $concepts, [], [], $descriptions);
    }

    /**
     * Matching descriptions score near the maximum.
     *
     * @return void
     */
    public function test_matching_descriptions(): void {
        $text = 'Chloroplasts capture sunlight and turn it into chemical energy.';
        $ref = $this->make(['Chloroplast' => $text]);
        $sub = $this->make(['Chloroplast' => $text]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertContains('Chloroplast', $result->propositions['matched']);
        $this->assertFalse($result->informational);
    }

    /**
     * An absent description is reported as missing and scores zero.
     *
     * @return void
     */
    public function test_missing_description(): void {
        $ref = $this->make(['Chloroplast' => 'Captures sunlight and stores chemical energy.']);
        $sub = $this->make(['Chloroplast' => '']);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertSame(0.0, $result->score);
        $this->assertContains('Chloroplast', $result->propositions['missing']);
    }

    /**
     * A thin description scores partially and is flagged with its overlap.
     *
     * @return void
     */
    public function test_thin_description_is_flagged(): void {
        $ref = $this->make(['Chloroplast' => 'Chloroplasts capture sunlight and turn it into chemical energy.']);
        $sub = $this->make(['Chloroplast' => 'It is green.']);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertLessThan(1.0, $result->score);
        $this->assertNotEmpty($result->propositions['missing']);
        $this->assertSame([], $result->propositions['matched']);
    }

    /**
     * With a fuzzy matcher a mistyped concept label still finds its description.
     *
     * @return void
     */
    public function test_fuzzy_label_still_pairs_descriptions(): void {
        $text = 'Chloroplasts capture sunlight and turn it into chemical energy.';
        $ref = $this->make(['Chloroplast' => $text]);
        $sub = $this->make(['Chloropast' => $text]);

        $strict = (new scorer())->score($sub, [$ref], new exact_matcher());
        $fuzzy = (new scorer())->score($sub, [$ref], new levenshtein_matcher());

        $this->assertSame(0.0, $strict->score);
        $this->assertGreaterThan(0.9, $fuzzy->score);
    }

    /**
     * A reference without descriptions yields an informational note.
     *
     * @return void
     */
    public function test_no_reference_descriptions_is_informational(): void {
        $ref = $this->make(['Chloroplast' => '']);
        $sub = $this->make(['Chloroplast' => 'Something.']);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertTrue($result->informational);
        $this->assertArrayHasKey('note', $result->metrics);
    }

    /**
     * Descriptions rebuilt from snapshot node content, and registry discovery.
     *
     * @return void
     */
    public function test_snapshot_parsing_and_registry(): void {
        $this->resetAfterTest();

        $submission = submission::from_snapshot_data([
            'profile' => 'conceptmap',
            'nodes' => [
                ['stableid' => 'node_1', 'label' => 'Chloroplast', 'content' => '<p>Captures light.</p>'],
                ['stableid' => 'node_2', 'label' => 'Sugar'],
            ],
            'relations' => [],
        ]);

        $this->assertSame(['Chloroplast'], $submission->described_labels());
        $this->assertStringContainsString('Captures light', $submission->description_for_label('Chloroplast'));
        $this->assertSame('', $submission->description_for_label('Sugar'));

        registry::reset_cache();
        $this->assertInstanceOf(scorer::class, registry::get('text'));
    }
}
