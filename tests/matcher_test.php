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

use mod_vimipad\local\assess\exact_matcher;
use mod_vimipad\local\assess\levenshtein_matcher;
use mod_vimipad\local\assess\matcher_factory;
use mod_vimipad\local\assess\token_matcher;

/**
 * Tests for the label matchers and their factory.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\assess\matcher_factory
 */
final class matcher_test extends \advanced_testcase {
    /**
     * Exact matching is case- and accent-insensitive but otherwise strict.
     *
     * @return void
     */
    public function test_exact(): void {
        $matcher = new exact_matcher();
        $this->assertEqualsWithDelta(1.0, $matcher->weight('Photosynthese', ' photosynthese '), 0.0001);
        $this->assertSame(0.0, $matcher->weight('Plant', 'Plnt'));
    }

    /**
     * Fuzzy matching credits typos but not unrelated words.
     *
     * @return void
     */
    public function test_levenshtein(): void {
        $matcher = new levenshtein_matcher();
        $this->assertEqualsWithDelta(1.0, $matcher->weight('Plant', 'plant'), 0.0001);
        $this->assertGreaterThan(0.7, $matcher->weight('Photosynthesis', 'Photosynthese'));
        $this->assertSame(0.0, $matcher->weight('Plant', 'Aeroplane hangar'));
    }

    /**
     * Word-overlap matching ignores order and filler words.
     *
     * @return void
     */
    public function test_token(): void {
        $matcher = new token_matcher();
        $this->assertEqualsWithDelta(1.0, $matcher->weight('cell membrane', 'membrane cell'), 0.0001);
        $this->assertGreaterThan(0.6, $matcher->weight('cell membrane', 'membrane of cell'));
        $this->assertEqualsWithDelta(0.5, $matcher->weight('cell membrane', 'the membrane of the cell'), 0.0001);
        $this->assertSame(0.0, $matcher->weight('nucleus', 'ribosome'));
    }

    /**
     * The factory maps modes to the right matcher and defaults to exact.
     *
     * @return void
     */
    public function test_factory(): void {
        $this->assertInstanceOf(exact_matcher::class, matcher_factory::create(matcher_factory::MODE_EXACT));
        $this->assertInstanceOf(levenshtein_matcher::class, matcher_factory::create(matcher_factory::MODE_LEVENSHTEIN));
        $this->assertInstanceOf(token_matcher::class, matcher_factory::create(matcher_factory::MODE_TOKEN));
        $this->assertInstanceOf(exact_matcher::class, matcher_factory::create(99));
    }
}
