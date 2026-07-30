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

use mod_vimipad\local\lock\workspace_writelock;

/**
 * Tests for the shared per-workspace write lock.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\lock\workspace_writelock
 */
final class workspace_writelock_test extends \advanced_testcase {
    /**
     * Acquiring the lock returns a lock object that can be released.
     *
     * @return void
     */
    public function test_acquire_and_release(): void {
        $this->resetAfterTest();

        $lock = workspace_writelock::acquire(4242);
        $this->assertInstanceOf(\core\lock\lock::class, $lock);
        $lock->release();

        // After release the same key can be acquired again.
        $again = workspace_writelock::acquire(4242);
        $this->assertInstanceOf(\core\lock\lock::class, $again);
        $again->release();
    }

    /**
     * Different workspaces use independent keys, so each can be acquired and
     * released in turn. (The lock is only ever held for one workspace at a time
     * within a single request; serialisation across requests is the lock
     * factory's guarantee, exercised by core.)
     *
     * @return void
     */
    public function test_distinct_keys_per_workspace(): void {
        $this->resetAfterTest();

        foreach ([1, 2, 1000000] as $wsid) {
            $lock = workspace_writelock::acquire($wsid);
            $this->assertInstanceOf(\core\lock\lock::class, $lock);
            $lock->release();
        }
    }
}
