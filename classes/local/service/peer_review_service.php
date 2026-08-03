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

namespace mod_vimipad\local\service;

use stdClass;

/**
 * Peer review: allocating submissions to reviewers, collecting and aggregating reviews.
 *
 * Only submitted snapshots take part, and nobody reviews their own map. Allocation
 * is round-robin over the submitting students, so the load is even and repeatable:
 * running it again only fills gaps rather than reshuffling existing allocations.
 *
 * Reviewers can be shown the automatic scorers' comparison as guidance (see
 * {@see self::guidance()}), which runs through the activity's chosen matcher — so
 * fuzzy or word-overlap matching applies to peer review exactly as it does to
 * teacher grading. Peer scores are advisory: they are aggregated for the teacher,
 * never written to the gradebook by this service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_review_service {
    /** @var int The review has been allocated but not yet submitted. */
    public const STATUS_ALLOCATED = 0;

    /** @var int The reviewer has submitted their review. */
    public const STATUS_SUBMITTED = 1;

    /**
     * Allocate reviewers to every submitted snapshot in the activity.
     *
     * Idempotent: existing allocations are kept and only missing ones are added,
     * so it is safe to call whenever new submissions arrive.
     *
     * @param stdClass $instance The activity instance.
     * @return int The number of allocations created.
     */
    public function allocate(stdClass $instance): int {
        global $DB;

        $perreview = max(0, (int) ($instance->peerreviewcount ?? 0));
        if ($perreview === 0) {
            return 0;
        }

        $submissions = $this->submitted_snapshots($instance);
        if (count($submissions) < 2) {
            // With a single submission there is nobody else to review it.
            return 0;
        }

        $owners = array_values($submissions);
        $snapshotids = array_keys($submissions);
        $reviewers = array_values(array_unique($owners));
        if (count($reviewers) < 2) {
            return 0;
        }

        $created = 0;
        $now = time();

        // Load all existing (snapshotid, reviewerid) pairs for these snapshots
        // once into a lookup set, instead of a record_exists() per candidate
        // pair inside the loop (which was O(snapshots x reviewers) queries).
        $existing = [];
        [$insql, $inparams] = $DB->get_in_or_equal($snapshotids, SQL_PARAMS_NAMED, 'sn');
        $rows = $DB->get_records_select(
            'vimipad_peerreview',
            "snapshotid $insql",
            $inparams,
            '',
            'id, snapshotid, reviewerid'
        );
        foreach ($rows as $row) {
            $existing[(int) $row->snapshotid . ':' . (int) $row->reviewerid] = true;
        }

        foreach ($snapshotids as $index => $snapshotid) {
            $ownerid = (int) $submissions[$snapshotid];
            $assigned = 0;
            $offset = 1;
            $limit = count($reviewers);
            // Walk the reviewer ring starting just after this submission's owner.
            while ($assigned < $perreview && $offset <= $limit) {
                $reviewerid = (int) $reviewers[($index + $offset) % $limit];
                $offset++;
                if ($reviewerid === $ownerid) {
                    continue;
                }
                $key = (int) $snapshotid . ':' . $reviewerid;
                if (isset($existing[$key])) {
                    $assigned++;
                    continue;
                }
                $DB->insert_record('vimipad_peerreview', (object) [
                    'snapshotid' => $snapshotid,
                    'reviewerid' => $reviewerid,
                    'status' => self::STATUS_ALLOCATED,
                    'score' => null,
                    'reviewcomment' => null,
                    'commentformat' => FORMAT_PLAIN,
                    'timeallocated' => $now,
                    'timemodified' => $now,
                ]);
                $existing[$key] = true;
                $assigned++;
                $created++;
            }
        }

        return $created;
    }

    /**
     * The reviews allocated to one reviewer in this activity.
     *
     * @param stdClass $instance The activity instance.
     * @param int $reviewerid The reviewing user.
     * @return stdClass[] Review records, keyed by id.
     */
    public function for_reviewer(stdClass $instance, int $reviewerid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT pr.*
               FROM {vimipad_peerreview} pr
               JOIN {vimipad_snapshot} s ON s.id = pr.snapshotid
               JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
              WHERE ws.vimipadid = :vid AND pr.reviewerid = :reviewerid
           ORDER BY pr.timeallocated ASC, pr.id ASC",
            ['vid' => (int) $instance->id, 'reviewerid' => $reviewerid]
        );
    }

    /**
     * The reviews written about one submission.
     *
     * @param int $snapshotid The reviewed snapshot.
     * @param bool $submittedonly Whether to return only completed reviews.
     * @return stdClass[] Review records, keyed by id.
     */
    public function for_snapshot(int $snapshotid, bool $submittedonly = false): array {
        global $DB;

        $conditions = ['snapshotid' => $snapshotid];
        if ($submittedonly) {
            $conditions['status'] = self::STATUS_SUBMITTED;
        }
        return $DB->get_records('vimipad_peerreview', $conditions, 'id ASC');
    }

    /**
     * Record a reviewer's verdict on a submission they were allocated.
     *
     * @param int $snapshotid The reviewed snapshot.
     * @param int $reviewerid The reviewing user.
     * @param float|null $score The peer score from 0.0 to 1.0, or null for comment only.
     * @param string $comment The reviewer's written feedback.
     * @return void
     * @throws \moodle_exception If the reviewer has no allocation for this submission.
     */
    public function save_review(int $snapshotid, int $reviewerid, ?float $score, string $comment): void {
        global $DB;

        \mod_vimipad\local\policy\limits::check_text(
            $comment,
            \mod_vimipad\local\policy\limits::MAX_TEXT,
            'reviewcomment'
        );

        $review = $DB->get_record('vimipad_peerreview', [
            'snapshotid' => $snapshotid, 'reviewerid' => $reviewerid,
        ]);
        if (!$review) {
            throw new \moodle_exception('error:notallocated', 'mod_vimipad');
        }

        $review->score = ($score === null) ? null : max(0.0, min(1.0, $score));
        $review->reviewcomment = $comment;
        $review->commentformat = FORMAT_PLAIN;
        $review->status = self::STATUS_SUBMITTED;
        $review->timemodified = time();
        $DB->update_record('vimipad_peerreview', $review);
    }

    /**
     * Aggregate the completed peer scores for a submission.
     *
     * The median is reported alongside the mean because a single outlying review
     * should not decide a suggestion.
     *
     * @param int $snapshotid The reviewed snapshot.
     * @return array{count: int, mean: float|null, median: float|null, pending: int}
     */
    public function aggregate(int $snapshotid): array {
        $scores = [];
        $pending = 0;
        foreach ($this->for_snapshot($snapshotid) as $review) {
            if ((int) $review->status !== self::STATUS_SUBMITTED) {
                $pending++;
                continue;
            }
            if ($review->score !== null) {
                $scores[] = (float) $review->score;
            }
        }

        if (empty($scores)) {
            return ['count' => 0, 'mean' => null, 'median' => null, 'pending' => $pending];
        }

        sort($scores);
        $count = count($scores);
        $middle = (int) floor(($count - 1) / 2);
        $median = ($count % 2) ? $scores[$middle] : (($scores[$middle] + $scores[$middle + 1]) / 2);

        return [
            'count' => $count,
            'mean' => array_sum($scores) / $count,
            'median' => $median,
            'pending' => $pending,
        ];
    }

    /**
     * Automatic guidance for a reviewer: the scorers' view of this submission.
     *
     * Runs the same synchronous scorers a teacher sees, through the activity's
     * configured matcher, so a peer reviewer gets fuzzy-tolerant hints about what
     * is present or missing instead of judging unaided.
     *
     * @param stdClass $instance The activity instance.
     * @param int $snapshotid The reviewed snapshot.
     * @return array Scorer key => ['name' => string, 'result' => \mod_vimipad\local\assess\result].
     */
    public function guidance(stdClass $instance, int $snapshotid): array {
        $assessservice = new assess_service();
        if (!$assessservice->has_reference($instance)) {
            return [];
        }
        return $assessservice->score_all($instance, $snapshotid);
    }

    /**
     * Submitted snapshots in this activity: snapshot id => owning user id.
     *
     * @param stdClass $instance The activity instance.
     * @return array<int,int>
     */
    private function submitted_snapshots(stdClass $instance): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT s.id AS snapshotid, ws.userid
               FROM {vimipad_snapshot} s
               JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
              WHERE ws.vimipadid = :vid AND s.id = ws.submittedsnapshotid AND ws.userid IS NOT NULL
           ORDER BY s.id ASC",
            ['vid' => (int) $instance->id]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->snapshotid] = (int) $row->userid;
        }
        return $map;
    }
}
