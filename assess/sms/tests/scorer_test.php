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

namespace vimipadassess_sms;

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;

/**
 * Tests for the sub-map (SMS) scorer.
 *
 * @package    vimipadassess_sms
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_sms\scorer
 */
final class scorer_test extends \advanced_testcase {
    /**
     * Build a submission from named sub-maps (each a list of concept labels).
     *
     * @param array $submaps Label => concept labels.
     * @return submission
     */
    private function make(array $submaps): submission {
        $concepts = [];
        $maps = [];
        $i = 0;
        foreach ($submaps as $label => $labels) {
            foreach ($labels as $concept) {
                $concepts['c' . ($i++)] = $concept;
            }
            $maps[] = ['label' => (string) $label, 'concepts' => $labels];
        }
        return new submission('conceptmap', $concepts, [], $maps);
    }

    /**
     * Identical grouping scores near the maximum with nothing missing.
     *
     * @return void
     */
    public function test_identical_grouping(): void {
        $ref = $this->make(['Cell' => ['Nucleus', 'Membrane'], 'Energy' => ['ATP', 'Mitochondria']]);
        $sub = $this->make(['Cell' => ['Nucleus', 'Membrane'], 'Energy' => ['ATP', 'Mitochondria']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertSame([], $result->propositions['missing']);
        $this->assertFalse($result->informational);
    }

    /**
     * A missing grouping lowers the score and is reported.
     *
     * @return void
     */
    public function test_missing_submap(): void {
        $ref = $this->make(['Cell' => ['Nucleus', 'Membrane'], 'Energy' => ['ATP', 'Mitochondria']]);
        $sub = $this->make(['Cell' => ['Nucleus', 'Membrane']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertLessThan(1.0, $result->score);
        $this->assertContains('Sub-map "Energy"', $result->propositions['missing']);
    }

    /**
     * A superfluous grouping is reported as extra.
     *
     * @return void
     */
    public function test_extra_submap(): void {
        $ref = $this->make(['Cell' => ['Nucleus', 'Membrane']]);
        $sub = $this->make(['Cell' => ['Nucleus', 'Membrane'], 'Noise' => ['Banana', 'Car']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertContains('Sub-map "Noise"', $result->propositions['extra']);
    }

    /**
     * With no reference sub-maps, the result is an informational note.
     *
     * @return void
     */
    public function test_no_submaps_is_informational(): void {
        $ref = new submission('conceptmap', ['c0' => 'Nucleus'], [], []);
        $sub = $this->make(['Cell' => ['Nucleus']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertTrue($result->informational);
        $this->assertArrayHasKey('note', $result->metrics);
    }

    /**
     * The registry discovers the installed sub-map scorer.
     *
     * @return void
     */
    public function test_registry_discovers_scorer(): void {
        $this->resetAfterTest();
        registry::reset_cache();
        $this->assertInstanceOf(scorer::class, registry::get('sms'));
    }

    /**
     * Sub-maps are rebuilt from a snapshot's containers and node memberships.
     *
     * @return void
     */
    public function test_from_snapshot_builds_submaps(): void {
        $data = [
            'profile' => 'conceptmap',
            'nodes' => [
                ['stableid' => 'node_1', 'label' => 'Nucleus'],
                ['stableid' => 'node_2', 'label' => 'Membrane'],
            ],
            'relations' => [],
            'containers' => [
                ['stableid' => 'cont_1', 'label' => 'Cell'],
            ],
            'memberships' => [
                ['containerstableid' => 'cont_1', 'itemtype' => 'node', 'itemstableid' => 'node_1'],
                ['containerstableid' => 'cont_1', 'itemtype' => 'node', 'itemstableid' => 'node_2'],
            ],
        ];

        $submission = submission::from_snapshot_data($data);

        $this->assertCount(1, $submission->submaps);
        $this->assertSame('Cell', $submission->submaps[0]['label']);
        $this->assertContains('Nucleus', $submission->submaps[0]['concepts']);
        $this->assertContains('Membrane', $submission->submaps[0]['concepts']);
    }
}
