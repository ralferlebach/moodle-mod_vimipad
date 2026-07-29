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

use mod_vimipad\local\output\journal_buckets;

/**
 * Tests for the journal time-bucket helper.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\output\journal_buckets
 */
final class journal_buckets_test extends \basic_testcase {
    /**
     * Entries land in the expected growing time buckets.
     *
     * @return void
     */
    public function test_bucketise_assigns_growing_windows(): void {
        // A fixed Wednesday, mid-month, mid-year.
        $now = strtotime('2026-06-17 12:00:00');

        $entry = static function (int $time): \stdClass {
            return (object) ['timecreated' => $time];
        };

        $entries = [
            'week' => $entry($now - 3600),
            'lastweek' => $entry(strtotime('2026-06-10 12:00:00')),
            'month' => $entry(strtotime('2026-06-03 12:00:00')),
            'year' => $entry(strtotime('2026-03-01 12:00:00')),
            'older' => $entry(strtotime('2025-11-01 12:00:00')),
        ];

        $buckets = journal_buckets::bucketise(array_values($entries), $now);

        $this->assertSame(['thisweek', 'lastweek', 'thismonth', 'thisyear', 'older'], array_keys($buckets));
        $this->assertSame($entries['week'], $buckets['thisweek'][0]);
        $this->assertSame($entries['lastweek'], $buckets['lastweek'][0]);
        $this->assertSame($entries['month'], $buckets['thismonth'][0]);
        $this->assertSame($entries['year'], $buckets['thisyear'][0]);
        $this->assertSame($entries['older'], $buckets['older'][0]);
    }

    /**
     * Empty buckets are omitted from the result.
     *
     * @return void
     */
    public function test_bucketise_omits_empty_buckets(): void {
        $now = strtotime('2026-06-17 12:00:00');
        $entries = [(object) ['timecreated' => $now - 3600]];

        $buckets = journal_buckets::bucketise($entries, $now);

        $this->assertSame(['thisweek'], array_keys($buckets));
    }
}
