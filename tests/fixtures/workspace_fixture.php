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

use mod_vimipad\local\service\operation_service;

/**
 * Shared setup for tests that operate on a single empty workspace.
 *
 * Several suites (element locks, membership integrity, reconstruction
 * round-trips) create the same course + module + empty workspace and drive the
 * operation service the same way. This trait holds that common fixture so the
 * setup is written once. Using it does not change any test's behaviour — it is
 * the exact same course/module/workspace creation and the same op() helper.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait workspace_fixture {
    /** @var int The workspace id created by {@see set_up_workspace()}. */
    private int $workspaceid;

    /** @var int|null The owning user id, set by {@see set_up_owned_workspace()}. */
    private ?int $userid = null;

    /**
     * Create a course, a vimipad module and an empty workspace.
     *
     * Call from a test's setUp() after parent::setUp() and resetAfterTest().
     *
     * @return void
     */
    protected function set_up_workspace(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Create a course, a vimipad module, an enrolled student and a workspace
     * owned by that student. Sets both $this->userid and $this->workspaceid.
     *
     * Call from a test's setUp() after parent::setUp() and resetAfterTest().
     *
     * @return void
     */
    protected function set_up_owned_workspace(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userid = (int) $user->id;
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $this->userid, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Apply an operation to the fixture workspace and return [revision, stableid].
     *
     * @param operation_service $service The operation service.
     * @param int $rev The current revision.
     * @param string $type The operation type.
     * @param array $payload The operation payload.
     * @return array{0: int, 1: ?string} [new revision, server-assigned stable id or null]
     */
    private function op(operation_service $service, int $rev, string $type, array $payload): array {
        $r = $service->apply($this->workspaceid, $rev, $type, $payload, 1);
        return [(int) $r['revision'], $r['stableid'] ?? null];
    }
}
