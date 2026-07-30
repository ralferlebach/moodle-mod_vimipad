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

namespace mod_vimipad\local\output;

/**
 * Groups journal entries into growing time buckets for display.
 *
 * Pure logic (no output), so the bucketing is independently testable while the
 * rendering (avatars, links) stays with the page.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class journal_buckets {
    /** @var string[] Bucket keys in display order (most recent first). */
    public const ORDER = ['thisweek', 'lastweek', 'thismonth', 'thisyear', 'older'];

    /**
     * Assign each entry to a time bucket, preserving input order within buckets.
     *
     * Entries are expected to be pre-sorted (newest first). Empty buckets are
     * omitted from the result.
     *
     * @param array $entries Entry records, each with a numeric timecreated.
     * @param int $now The reference "now" timestamp.
     * @return array<string, array> Ordered map of bucket key => entries.
     */
    public static function bucketise(array $entries, int $now): array {
        $startofday = strtotime('midnight', $now);
        $dow = (int) date('N', $now);
        $startofweek = strtotime('-' . ($dow - 1) . ' days', $startofday);
        $startoflastweek = strtotime('-7 days', $startofweek);
        $startofmonth = strtotime('first day of this month midnight', $now);
        $startofyear = strtotime('first day of january this year midnight', $now);

        $buckets = array_fill_keys(self::ORDER, []);
        foreach ($entries as $entry) {
            $time = (int) $entry->timecreated;
            if ($time >= $startofweek) {
                $buckets['thisweek'][] = $entry;
            } else if ($time >= $startoflastweek) {
                $buckets['lastweek'][] = $entry;
            } else if ($time >= $startofmonth) {
                $buckets['thismonth'][] = $entry;
            } else if ($time >= $startofyear) {
                $buckets['thisyear'][] = $entry;
            } else {
                $buckets['older'][] = $entry;
            }
        }

        return array_filter($buckets, static fn($group) => !empty($group));
    }
}
