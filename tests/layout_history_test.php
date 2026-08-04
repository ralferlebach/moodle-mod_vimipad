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

use mod_vimipad\local\service\layout_service;

/**
 * Tests for the append-only layout history that lets past revisions replay with
 * their real topology (R10).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\layout_service
 */
final class layout_history_test extends \advanced_testcase {
    /** @var int Workspace id under test. */
    private $workspaceid;

    /** @var int Acting user id. */
    private $userid;

    /**
     * Create a course, activity, user and empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->userid = (int) $user->id;

        global $DB;
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $this->userid, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Set the workspace's semantic revision (the value history rows are tagged
     * with at save time).
     *
     * @param int $revision The revision to set.
     * @return void
     */
    private function set_revision(int $revision): void {
        global $DB;
        $DB->set_field('vimipad_workspace', 'currentrevision', $revision, ['id' => $this->workspaceid]);
    }

    /**
     * A layout JSON placing node 'a' at the given coordinates.
     *
     * @param int $x The x coordinate.
     * @param int $y The y coordinate.
     * @return string
     */
    private function layout(int $x, int $y): string {
        return json_encode(['v' => 1, 'pos' => ['a' => ['x' => $x, 'y' => $y]]]);
    }

    /**
     * Each save appends a history row tagged with the current revision.
     *
     * @return void
     */
    public function test_save_appends_history_tagged_with_revision(): void {
        global $DB;
        $service = new layout_service();

        $this->set_revision(3);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(10, 10), '', $this->userid);
        $this->set_revision(7);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(20, 20), '', $this->userid);

        $rows = $DB->get_records(
            'vimipad_layouthist',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap'],
            'revision ASC'
        );
        $this->assertCount(2, $rows);
        $revisions = array_values(array_map(fn($r) => (int) $r->revision, $rows));
        $this->assertSame([3, 7], $revisions);
    }

    /**
     * An identical consecutive layout is de-duplicated (no new row).
     *
     * @return void
     */
    public function test_identical_layout_is_deduplicated(): void {
        global $DB;
        $service = new layout_service();

        $this->set_revision(2);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(10, 10), '', $this->userid);
        // Same layout again (idempotent save) at a later revision: no new row.
        $this->set_revision(4);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(10, 10), '', $this->userid);

        $this->assertSame(1, $DB->count_records(
            'vimipad_layouthist',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        ));
    }

    /**
     * layout_at_revision returns the newest layout at or before the target
     * revision, and empty when nothing was recorded that early.
     *
     * @return void
     */
    public function test_layout_at_revision_picks_newest_not_after(): void {
        $service = new layout_service();

        $this->set_revision(2);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(10, 10), '', $this->userid);
        $this->set_revision(5);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(50, 50), '', $this->userid);

        // Before any recorded layout.
        $this->assertSame('', $service->layout_at_revision($this->workspaceid, 'conceptmap', 1));
        // Between the two saves: the revision-2 layout applies.
        $this->assertSame(
            $this->layout(10, 10),
            $service->layout_at_revision($this->workspaceid, 'conceptmap', 4)
        );
        // At/after the second save: the revision-5 layout applies.
        $this->assertSame(
            $this->layout(50, 50),
            $service->layout_at_revision($this->workspaceid, 'conceptmap', 9)
        );
    }

    /**
     * layout_history returns all entries in ascending revision order.
     *
     * @return void
     */
    public function test_layout_history_ordered(): void {
        $service = new layout_service();
        $this->set_revision(1);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(1, 1), '', $this->userid);
        $this->set_revision(2);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(2, 2), '', $this->userid);

        $history = $service->layout_history($this->workspaceid, 'conceptmap');
        $this->assertCount(2, $history);
        $this->assertSame(1, $history[0]['revision']);
        $this->assertSame(2, $history[1]['revision']);
        $this->assertSame($this->layout(2, 2), $history[1]['layoutjson']);
    }

    /**
     * History for a different profile is kept separate.
     *
     * @return void
     */
    public function test_history_is_per_profile(): void {
        $service = new layout_service();
        $this->set_revision(1);
        $service->save($this->workspaceid, 'conceptmap', $this->layout(1, 1), '', $this->userid);
        $service->save($this->workspaceid, 'mindmap', $this->layout(9, 9), '', $this->userid);

        $this->assertCount(1, $service->layout_history($this->workspaceid, 'conceptmap'));
        $this->assertCount(1, $service->layout_history($this->workspaceid, 'mindmap'));
    }
}
