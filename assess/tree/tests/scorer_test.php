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

namespace vimipadassess_tree;

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\registry;
use mod_vimipad\local\assess\submission;

/**
 * Tests for the hierarchy (tree) scorer.
 *
 * @package    vimipadassess_tree
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \vimipadassess_tree\scorer
 */
final class scorer_test extends \advanced_testcase {
    /**
     * Build a submission from parent/child link triples (relation label unused).
     *
     * @param string[] $concepts Concept labels.
     * @param array[] $links Links as [parent, child].
     * @return submission
     */
    private function make(array $concepts, array $links): submission {
        $keyed = [];
        foreach ($concepts as $i => $label) {
            $keyed['c' . $i] = $label;
        }
        $props = [];
        foreach ($links as $link) {
            $props[] = ['source' => $link[0], 'relation' => '', 'target' => $link[1]];
        }
        return new submission('tree', $keyed, $props);
    }

    /**
     * An identical hierarchy scores near the maximum with the root recognised.
     *
     * @return void
     */
    public function test_identical_tree(): void {
        $ref = $this->make(['Animal', 'Mammal', 'Bird'], [['Animal', 'Mammal'], ['Animal', 'Bird']]);
        $sub = $this->make(['Animal', 'Mammal', 'Bird'], [['Animal', 'Mammal'], ['Animal', 'Bird']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertEqualsWithDelta(1.0, $result->partscores['root'], 0.0001);
        $this->assertSame([], $result->propositions['missing']);
    }

    /**
     * A missing branch lowers the score and is reported.
     *
     * @return void
     */
    public function test_missing_link(): void {
        $ref = $this->make(['Animal', 'Mammal', 'Bird'], [['Animal', 'Mammal'], ['Animal', 'Bird']]);
        $sub = $this->make(['Animal', 'Mammal'], [['Animal', 'Mammal']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertLessThan(1.0, $result->score);
        $this->assertContains('Animal → Bird', $result->propositions['missing']);
    }

    /**
     * A different root scores zero on the root dimension.
     *
     * @return void
     */
    public function test_wrong_root(): void {
        $ref = $this->make(['Animal', 'Mammal'], [['Animal', 'Mammal']]);
        $sub = $this->make(['Plant', 'Leaf'], [['Plant', 'Leaf']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertSame(0.0, $result->partscores['root']);
    }

    /**
     * Superfluous links are reported as extra.
     *
     * @return void
     */
    public function test_extra_link(): void {
        $ref = $this->make(['Animal', 'Mammal'], [['Animal', 'Mammal']]);
        $sub = $this->make(['Animal', 'Mammal', 'Rock'], [['Animal', 'Mammal'], ['Animal', 'Rock']]);

        $result = (new scorer())->score($sub, [$ref], new exact_matcher());

        $this->assertContains('Animal → Rock', $result->propositions['extra']);
    }

    /**
     * The scorer supports hierarchical profiles and requires a reference.
     *
     * @return void
     */
    public function test_profiles_and_reference(): void {
        $scorer = new scorer();
        $this->assertTrue($scorer->supports_profile('tree'));
        $this->assertTrue($scorer->supports_profile('mindmap'));
        $this->assertTrue($scorer->requires_reference());
    }

    /**
     * The registry discovers the installed tree scorer.
     *
     * @return void
     */
    public function test_registry_discovers_scorer(): void {
        $this->resetAfterTest();
        registry::reset_cache();
        $this->assertInstanceOf(scorer::class, registry::get('tree'));
    }
}
