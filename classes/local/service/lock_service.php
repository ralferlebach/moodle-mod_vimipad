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

/**
 * Manages short-lived per-element editing leases (collaborative locking + presence).
 *
 * These leases are ADVISORY (collaboration/UX): they coordinate concurrent
 * editors and drive presence, but operation_service does NOT reject a mutation
 * from a user who lacks the lease — concurrency correctness is instead
 * guaranteed by the shared workspace write lock plus optimistic revision
 * checks. Do not build hard restrictions on top of these leases.
 *
 * A lease is acquired when a user starts editing/dragging an element and is
 * renewed by a heartbeat while they hold it. If the client disconnects the
 * lease simply expires (server-enforced via timeexpires), so nothing stays
 * locked forever. Only one live lease can exist per element (DB unique index).
 *
 * Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_service {
    /**
     * Try to acquire a lease on an element.
     *
     * Succeeds if the element is free, the caller already holds it (idempotent
     * extend), or the existing lease has expired. Otherwise it fails and reports
     * the current holder so the caller can show presence ("edited by …").
     *
     * @param int $workspaceid The workspace id.
     * @param string $targettype 'node' or 'relation'.
     * @param string $stableid The element stable id.
     * @param int $userid The requesting user id.
     * @param int $ttl Lease lifetime in seconds.
     * @return \stdClass {acquired: bool, userid: int, timeexpires: int}
     */
    public function acquire(int $workspaceid, string $targettype, string $stableid, int $userid, int $ttl): \stdClass {
        global $DB;

        $now = time();
        $expires = $now + max(1, $ttl);

        $existing = $DB->get_record('vimipad_lock', [
            'workspaceid' => $workspaceid,
            'targettype' => $targettype,
            'targetstableid' => $stableid,
        ]);

        if ($existing) {
            $held = (int) $existing->userid === $userid;
            $expired = (int) $existing->timeexpires <= $now;
            if ($held && !$expired) {
                // Caller owns a still-valid lease: extend it, but only if the
                // row still matches what we read (compare-and-swap), so a
                // concurrent takeover between our read and write is not clobbered
                // by an unconditional update.
                $DB->execute(
                    'UPDATE {vimipad_lock}
                        SET timeexpires = :exp
                      WHERE id = :id AND userid = :olduser AND timeexpires = :oldexp',
                    [
                        'exp' => $expires, 'id' => $existing->id,
                        'olduser' => $userid, 'oldexp' => (int) $existing->timeexpires,
                    ]
                );
                $current = $DB->get_record('vimipad_lock', ['id' => $existing->id]);
                if ($current && (int) $current->userid === $userid) {
                    return $this->result(true, $userid, (int) $current->timeexpires);
                }
                if ($current) {
                    // Someone took over in the gap: report the real holder.
                    return $this->result(false, (int) $current->userid, (int) $current->timeexpires);
                }
                // Row vanished: fall through to insert.
            } else if ($expired) {
                // A lapsed lease (whether previously ours or someone else's):
                // take it over, but only if it is still exactly the row we read
                // (compare-and-swap on the old holder + expiry). Routing the
                // owner's own expired lease through the CAS path too closes the
                // owner-renewal race where a concurrent takeover would otherwise
                // be overwritten by an unconditional owner update.
                $DB->execute(
                    'UPDATE {vimipad_lock}
                        SET userid = :newuser, timeacquired = :acq, timeexpires = :exp
                      WHERE id = :id AND userid = :olduser AND timeexpires = :oldexp',
                    [
                        'newuser' => $userid, 'acq' => $now, 'exp' => $expires,
                        'id' => $existing->id, 'olduser' => (int) $existing->userid,
                        'oldexp' => (int) $existing->timeexpires,
                    ]
                );
                // The execute() call returns true; a follow-up read learns who
                // actually holds the lease now (the CAS winner).
                $current = $DB->get_record('vimipad_lock', ['id' => $existing->id]);
                if ($current && (int) $current->userid === $userid) {
                    return $this->result(true, $userid, (int) $current->timeexpires);
                }
                if ($current) {
                    return $this->result(false, (int) $current->userid, (int) $current->timeexpires);
                }
                // The row vanished (deleted concurrently): fall through to insert.
            } else {
                // Held by someone else and still valid: refuse, report holder.
                return $this->result(false, (int) $existing->userid, (int) $existing->timeexpires);
            }
        }

        // Free: create the lease. Guard against a race on the unique index.
        try {
            $DB->insert_record('vimipad_lock', (object) [
                'workspaceid' => $workspaceid,
                'targettype' => $targettype,
                'targetstableid' => $stableid,
                'userid' => $userid,
                'timeacquired' => $now,
                'timeexpires' => $expires,
            ]);
        } catch (\dml_exception $e) {
            // Someone inserted first; re-read and report the holder.
            $winner = $DB->get_record('vimipad_lock', [
                'workspaceid' => $workspaceid,
                'targettype' => $targettype,
                'targetstableid' => $stableid,
            ]);
            if ($winner && (int) $winner->userid !== $userid && (int) $winner->timeexpires > $now) {
                return $this->result(false, (int) $winner->userid, (int) $winner->timeexpires);
            }
            return $this->result(true, $userid, $expires);
        }

        return $this->result(true, $userid, $expires);
    }

    /**
     * Renew (heartbeat) a lease the caller holds.
     *
     * @param int $workspaceid The workspace id.
     * @param string $targettype 'node' or 'relation'.
     * @param string $stableid The element stable id.
     * @param int $userid The requesting user id.
     * @param int $ttl Lease lifetime in seconds.
     * @return \stdClass {acquired: bool, userid: int, timeexpires: int}
     */
    public function renew(int $workspaceid, string $targettype, string $stableid, int $userid, int $ttl): \stdClass {
        global $DB;

        $now = time();
        $existing = $DB->get_record('vimipad_lock', [
            'workspaceid' => $workspaceid,
            'targettype' => $targettype,
            'targetstableid' => $stableid,
        ]);

        if (!$existing || (int) $existing->userid !== $userid || (int) $existing->timeexpires <= $now) {
            // Not held by caller (or already expired): renewal fails.
            $holder = $existing ? (int) $existing->userid : 0;
            return $this->result(false, $holder, $existing ? (int) $existing->timeexpires : 0);
        }

        $expires = $now + max(1, $ttl);
        $DB->set_field('vimipad_lock', 'timeexpires', $expires, ['id' => $existing->id]);
        return $this->result(true, $userid, $expires);
    }

    /**
     * Release a lease the caller holds. No-op if not held by the caller.
     *
     * @param int $workspaceid The workspace id.
     * @param string $targettype 'node' or 'relation'.
     * @param string $stableid The element stable id.
     * @param int $userid The requesting user id.
     * @return void
     */
    public function release(int $workspaceid, string $targettype, string $stableid, int $userid): void {
        global $DB;

        $DB->delete_records('vimipad_lock', [
            'workspaceid' => $workspaceid,
            'targettype' => $targettype,
            'targetstableid' => $stableid,
            'userid' => $userid,
        ]);
    }

    /**
     * Return the currently active (non-expired) leases for a workspace (presence).
     *
     * @param int $workspaceid The workspace id.
     * @return \stdClass[] Active lease records.
     */
    public function get_active_leases(int $workspaceid): array {
        global $DB;

        return $DB->get_records_select(
            'vimipad_lock',
            'workspaceid = :wid AND timeexpires > :now',
            ['wid' => $workspaceid, 'now' => time()],
            'timeacquired ASC'
        );
    }

    /**
     * Delete expired leases for a workspace (housekeeping).
     *
     * @param int $workspaceid The workspace id.
     * @return void
     */
    public function purge_expired(int $workspaceid): void {
        global $DB;

        $DB->delete_records_select(
            'vimipad_lock',
            'workspaceid = :wid AND timeexpires <= :now',
            ['wid' => $workspaceid, 'now' => time()]
        );
    }

    /**
     * Delete all expired leases across every workspace (scheduled housekeeping).
     *
     * Run from a scheduled task rather than the hot poll path, so cleanup is
     * deterministic and does not add write load to reads.
     *
     * @return void
     */
    public function purge_all_expired(): void {
        global $DB;

        $DB->delete_records_select('vimipad_lock', 'timeexpires <= :now', ['now' => time()]);
    }

    /**
     * Build a uniform result object.
     *
     * @param bool $acquired Whether the caller holds the lease after this call.
     * @param int $userid The current holder's user id.
     * @param int $timeexpires The lease expiry timestamp.
     * @return \stdClass
     */
    private function result(bool $acquired, int $userid, int $timeexpires): \stdClass {
        return (object) [
            'acquired' => $acquired,
            'userid' => $userid,
            'timeexpires' => $timeexpires,
        ];
    }
}
