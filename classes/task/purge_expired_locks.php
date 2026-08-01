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

namespace mod_vimipad\task;

use mod_vimipad\local\service\lock_service;

/**
 * Scheduled task: delete expired collaboration lock leases.
 *
 * Replaces the probabilistic cleanup that used to run inside the poll request,
 * so housekeeping is deterministic and adds no write load to the read path.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_expired_locks extends \core\task\scheduled_task {
    /**
     * The task name shown in the admin scheduled-task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:purgeexpiredlocks', 'mod_vimipad');
    }

    /**
     * Delete all expired lock leases.
     *
     * @return void
     */
    public function execute(): void {
        (new lock_service())->purge_all_expired();
    }
}
