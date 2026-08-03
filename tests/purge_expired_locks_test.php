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

use mod_vimipad\task\purge_expired_locks;

/**
 * The scheduled lock-purge task deletes expired leases across all workspaces.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\task\purge_expired_locks
 * @covers     \mod_vimipad\local\service\lock_service
 */
final class purge_expired_locks_test extends \advanced_testcase {
    /**
     * Expired leases are removed; live leases survive.
     *
     * @return void
     */
    public function test_task_purges_only_expired(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        // An expired lease and a live one, in two different workspaces.
        $DB->insert_record('vimipad_lock', (object) [
            'workspaceid' => 11, 'targettype' => 'node', 'targetstableid' => 'node_aaaaaaaaaaaa',
            'userid' => 1, 'timeacquired' => $now - 100, 'timeexpires' => $now - 10,
        ]);
        $DB->insert_record('vimipad_lock', (object) [
            'workspaceid' => 22, 'targettype' => 'node', 'targetstableid' => 'node_bbbbbbbbbbbb',
            'userid' => 2, 'timeacquired' => $now, 'timeexpires' => $now + 3600,
        ]);

        $this->assertSame(2, $DB->count_records('vimipad_lock'));

        (new purge_expired_locks())->execute();

        $this->assertSame(1, $DB->count_records('vimipad_lock'));
        $this->assertTrue($DB->record_exists('vimipad_lock', ['workspaceid' => 22]));
        $this->assertFalse($DB->record_exists('vimipad_lock', ['workspaceid' => 11]));
    }
}
