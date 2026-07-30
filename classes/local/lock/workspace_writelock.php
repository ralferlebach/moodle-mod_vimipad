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

namespace mod_vimipad\local\lock;

/**
 * Shared per-workspace write lock.
 *
 * Every semantic mutation of a workspace and every snapshot creation must be
 * serialized on the SAME lock so they cannot interleave. A single operation, a
 * whole import, a reopen and a submission/finalisation therefore all acquire
 * this one key ('write_<workspaceid>'), which guarantees that a snapshot is
 * built from a consistent revision rather than a torn read across the node,
 * relation, container and membership tables.
 *
 * The lock is advisory in the concurrency sense (it coordinates writers); it is
 * distinct from the per-element collaboration lease in {@see lock_service} and
 * from the workspace's own `locked` flag (which marks a submitted map).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workspace_writelock {
    /** @var string The lock factory type used for all workspace-level locks. */
    public const FACTORY = 'mod_vimipad_workspace';

    /**
     * Acquire the write lock for a workspace.
     *
     * The caller owns the returned lock and MUST release it (use try/finally).
     * Do NOT re-acquire it for the same workspace within the same request: the
     * lock is not guaranteed to be reentrant. Services that fan out into many
     * operations (e.g. import) acquire it once and then call the lock-free
     * variants (e.g. operation_service::apply_locked).
     *
     * @param int $workspaceid The workspace id.
     * @param int $timeout Seconds to wait for the lock before giving up.
     * @return \core\lock\lock The acquired lock.
     * @throws \moodle_exception If the lock cannot be acquired (concurrency).
     */
    public static function acquire(int $workspaceid, int $timeout = 10): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::FACTORY);
        $lock = $factory->get_lock('write_' . $workspaceid, $timeout);
        if (!$lock) {
            throw new \moodle_exception('error:workspacelocked', 'mod_vimipad');
        }
        return $lock;
    }
}
