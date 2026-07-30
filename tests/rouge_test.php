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

use mod_vimipad\local\assess\rouge;

/**
 * Tests for the ROUGE-style text overlap helper.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\assess\rouge
 */
final class rouge_test extends \advanced_testcase {
    /**
     * Identical text scores 1.0; unrelated text scores 0.0.
     *
     * @return void
     */
    public function test_extremes(): void {
        $text = 'Plants convert sunlight into chemical energy.';
        $this->assertEqualsWithDelta(1.0, rouge::similarity($text, $text), 0.0001);
        $this->assertSame(0.0, rouge::similarity('Bicycles need tyres', 'Plants convert sunlight'));
        $this->assertSame(0.0, rouge::similarity('', $text));
    }

    /**
     * Partial rewording scores between the extremes and beats a weaker answer.
     *
     * @return void
     */
    public function test_partial_overlap_is_ordered(): void {
        $reference = 'Plants convert sunlight into chemical energy stored as sugar.';
        $good = 'Plants convert sunlight into chemical energy stored as sugar molecules.';
        $weak = 'Plants need light.';

        $goodscore = rouge::similarity($good, $reference);
        $weakscore = rouge::similarity($weak, $reference);

        $this->assertGreaterThan($weakscore, $goodscore);
        $this->assertGreaterThan(0.0, $weakscore);
        $this->assertLessThan(1.0, $goodscore);
    }

    /**
     * HTML markup and casing are ignored when tokenising.
     *
     * @return void
     */
    public function test_html_and_case_ignored(): void {
        $plain = 'Sunlight drives photosynthesis';
        $html = '<p><strong>SUNLIGHT</strong> drives photosynthesis</p>';
        $this->assertEqualsWithDelta(1.0, rouge::similarity($html, $plain), 0.0001);
    }

    /**
     * ROUGE-L rewards preserved word order that ROUGE-2 misses.
     *
     * @return void
     */
    public function test_rouge_l_handles_gaps(): void {
        $reference = rouge::tokens('the cell membrane controls transport');
        $candidate = rouge::tokens('the membrane controls transport');

        $this->assertGreaterThan(0.7, rouge::rouge_l($candidate, $reference));
        $this->assertGreaterThan(0.0, rouge::rouge_n($candidate, $reference, 1));
    }
}
