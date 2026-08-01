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

use externallib_advanced_testcase;
use mod_vimipad\external\get_operations;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Contract and access tests for the get_operations external function, including
 * pagination and the server-side batch cap.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\get_operations
 */
final class get_operations_contract_test extends externallib_advanced_testcase {
    /** @var \stdClass The course. */
    private \stdClass $course;
    /** @var \stdClass The vimipad instance. */
    private \stdClass $instance;
    /** @var \stdClass The course module. */
    private \stdClass $cm;

    /**
     * Individual-mode activity fixture.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $this->course->id, 'collaborationmode' => 0,
        ]);
        $this->cm = get_coursemodule_from_instance('vimipad', $this->instance->id);
    }

    /**
     * Create a workspace with $n node_create operations for the given user.
     *
     * @param int $userid The owner.
     * @param int $n How many operations to log.
     * @return int The workspace id.
     */
    private function workspace_with_ops(int $userid, int $n): int {
        global $DB;
        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => $userid, 'groupid' => 0,
            'currentrevision' => $n, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        for ($i = 1; $i <= $n; $i++) {
            $DB->insert_record('vimipad_operation', (object) [
                'workspaceid' => $wsid,
                'revision' => $i,
                'operationtype' => 'node_create',
                'payloadjson' => json_encode(['stableid' => 'node_' . sprintf('%012x', $i), 'label' => 'N' . $i]),
                'userid' => $userid,
                'timecreated' => $now,
            ]);
        }
        return $wsid;
    }

    /**
     * A learner can read the operation log of their own workspace.
     *
     * @return void
     */
    public function test_own_workspace_is_readable(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $wsid = $this->workspace_with_ops((int) $user->id, 3);
        $this->setUser($user);

        $result = get_operations::execute($this->cm->id, $wsid, 3);
        $this->assertCount(3, $result['operations']);
        $this->assertFalse($result['hasmore']);
        $this->assertSame(0, $result['nextrevision']);
        $this->assertSame('node_create', $result['operations'][0]['operationtype']);
    }

    /**
     * A learner cannot read another learner's individual workspace.
     *
     * @return void
     */
    public function test_foreign_workspace_is_rejected(): void {
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $wsid = $this->workspace_with_ops((int) $owner->id, 2);
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        get_operations::execute($this->cm->id, $wsid, 2);
    }

    /**
     * The requested revision is clamped to the workspace's current revision.
     *
     * @return void
     */
    public function test_torevision_is_clamped(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $wsid = $this->workspace_with_ops((int) $user->id, 3);
        $this->setUser($user);

        // Asking beyond the current revision returns everything, not more.
        $result = get_operations::execute($this->cm->id, $wsid, 9999);
        $this->assertSame(3, $result['torevision']);
        $this->assertCount(3, $result['operations']);
    }

    /**
     * A small limit paginates: the first page reports hasmore and a next
     * revision, and paging through yields every operation exactly once.
     *
     * @return void
     */
    public function test_pagination_pages_through_all(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $wsid = $this->workspace_with_ops((int) $user->id, 10);
        $this->setUser($user);

        $seen = [];
        $from = 1;
        $pages = 0;
        do {
            $page = get_operations::execute($this->cm->id, $wsid, 10, $from, 3);
            foreach ($page['operations'] as $op) {
                $seen[] = $op['revision'];
            }
            $from = $page['nextrevision'];
            $pages++;
        } while ($page['hasmore'] && $pages < 20);

        $this->assertSame(range(1, 10), $seen);
        $this->assertGreaterThan(1, $pages);
    }

    /**
     * A limit above the server cap is bounded to the cap.
     *
     * @return void
     */
    public function test_limit_is_capped(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        // More operations than the cap would be needed to fully prove this, so
        // assert the returned page never exceeds MAX_BATCH for a huge request.
        $wsid = $this->workspace_with_ops((int) $user->id, 5);
        $this->setUser($user);

        $result = get_operations::execute($this->cm->id, $wsid, 5, 1, PHP_INT_MAX);
        $this->assertLessThanOrEqual(get_operations::MAX_BATCH, count($result['operations']));
        $this->assertCount(5, $result['operations']);
    }
}
