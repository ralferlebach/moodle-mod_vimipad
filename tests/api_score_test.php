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

namespace mod_vimipad;

use mod_vimipad\api\score;

/**
 * Tests for the public scoring facade.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\api\score
 */
final class api_score_test extends \advanced_testcase {
    /**
     * Build a serialised snapshot map from concept labels and relation triples.
     *
     * @param array $concepts Concept labels.
     * @param array $relations Triples: each [sourcelabel, relationlabel, targetlabel].
     * @return string JSON snapshot.
     */
    private function map(array $concepts, array $relations = []): string {
        $ids = [];
        $nodes = [];
        foreach ($concepts as $i => $label) {
            $id = 'n' . $i;
            $ids[$label] = $id;
            $nodes[] = ['stableid' => $id, 'label' => $label];
        }
        $rels = [];
        foreach ($relations as $rel) {
            [$source, $label, $target] = $rel;
            $rels[] = [
                'sourceid' => $ids[$source] ?? $source,
                'targetid' => $ids[$target] ?? $target,
                'label' => $label,
            ];
        }
        return json_encode(['profile' => 'conceptmap', 'nodes' => $nodes, 'relations' => $rels]);
    }

    /**
     * A response identical to the reference scores a perfect mark.
     *
     * @return void
     */
    public function test_full_match_scores_one(): void {
        $this->resetAfterTest();
        $map = $this->map(['Water', 'Ice'], [['Water', 'freezes to', 'Ice']]);

        $result = score::against_reference($map, $map);
        $this->assertIsArray($result);
        $this->assertEqualsWithDelta(1.0, $result['score'], 0.0001);
        $this->assertSame(1.0, score::fraction($map, $map));
    }

    /**
     * A weaker response scores below a perfect response.
     *
     * @return void
     */
    public function test_partial_match_scores_lower(): void {
        $this->resetAfterTest();
        $reference = $this->map(
            ['Water', 'Ice', 'Steam'],
            [['Water', 'freezes to', 'Ice'], ['Water', 'boils to', 'Steam']]
        );
        $weak = $this->map(['Water', 'Ice'], [['Water', 'freezes to', 'Ice']]);

        $full = score::fraction($reference, $reference);
        $partial = score::fraction($weak, $reference);

        $this->assertNotNull($partial);
        $this->assertGreaterThan(0.0, $partial);
        $this->assertLessThan($full, $partial);
    }

    /**
     * The breakdown lists a missing proposition the response did not reproduce.
     *
     * @return void
     */
    public function test_breakdown_reports_missing(): void {
        $this->resetAfterTest();
        $reference = $this->map(
            ['Water', 'Ice', 'Steam'],
            [['Water', 'freezes to', 'Ice'], ['Water', 'boils to', 'Steam']]
        );
        $weak = $this->map(['Water', 'Ice'], [['Water', 'freezes to', 'Ice']]);

        $result = score::against_reference($weak, $reference);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('missing', $result['concepts']);
        $this->assertContains('Steam', $result['concepts']['missing']);
    }

    /**
     * Invalid JSON on either side yields null rather than an error.
     *
     * @return void
     */
    public function test_invalid_json_returns_null(): void {
        $this->resetAfterTest();
        $map = $this->map(['A']);
        $this->assertNull(score::against_reference('not json', $map));
        $this->assertNull(score::against_reference($map, 'not json'));
        $this->assertNull(score::fraction('', $map));
    }

    /**
     * An unknown scorer key yields null.
     *
     * @return void
     */
    public function test_unknown_scorer_returns_null(): void {
        $this->resetAfterTest();
        $map = $this->map(['A']);
        $this->assertNull(score::against_reference($map, $map, 'no_such_scorer'));
    }

    /**
     * The reference-scorer list includes the reference scorer and excludes AI scorers.
     *
     * @return void
     */
    public function test_reference_scorers_list(): void {
        $this->resetAfterTest();
        $keys = score::reference_scorers();
        $this->assertContains('reference', $keys);
        $this->assertNotContains('llm', $keys);
    }
}
