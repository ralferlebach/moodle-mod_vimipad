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
 * Tests for the layout service (non-revisioned presentation state).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\layout_service
 */
final class layout_service_test extends \advanced_testcase {
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
        $this->workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $this->userid, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Saving inserts a layout row and get returns it.
     *
     * @return void
     */
    public function test_save_and_get(): void {
        global $DB;
        $service = new layout_service();

        $this->assertSame('', $service->get_layout_json($this->workspaceid, 'conceptmap'));

        $layout = '{"node_a":{"x":10,"y":20}}';
        $service->save($this->workspaceid, 'conceptmap', $layout, '{"zoom":1}', $this->userid);

        $this->assertSame($layout, $service->get_layout_json($this->workspaceid, 'conceptmap'));
        $this->assertSame(1, $DB->count_records(
            'vimipad_layout',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        ));
    }

    /**
     * Saving again updates in place rather than inserting a duplicate.
     *
     * @return void
     */
    public function test_save_is_upsert(): void {
        global $DB;
        $service = new layout_service();

        $first = '{"v":1,"pos":{"n1":{"x":1,"y":1}}}';
        $second = '{"v":1,"pos":{"n1":{"x":2,"y":2}}}';
        $service->save($this->workspaceid, 'conceptmap', $first, '', $this->userid);
        $service->save($this->workspaceid, 'conceptmap', $second, '', $this->userid);

        $this->assertSame(1, $DB->count_records(
            'vimipad_layout',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        ));
        $this->assertSame($second, $service->get_layout_json($this->workspaceid, 'conceptmap'));
    }

    /**
     * The layout schema is enforced at the service boundary, so an invalid
     * payload is rejected even when it reaches save() directly (e.g. via the
     * import path), not only through the external endpoint.
     *
     * @return void
     */
    public function test_service_rejects_invalid_layout(): void {
        $service = new layout_service();
        $this->expectException(\moodle_exception::class);
        // A scalar root is not a valid layout; save() must reject it.
        $service->save($this->workspaceid, 'conceptmap', '42', '', $this->userid);
    }

    /**
     * Layouts are stored independently per profile.
     *
     * @return void
     */
    public function test_layout_is_per_profile(): void {
        $service = new layout_service();

        $cmap = '{"v":1,"pos":{"c":{"x":1,"y":1}}}';
        $mmap = '{"v":1,"pos":{"m":{"x":2,"y":2}}}';
        $service->save($this->workspaceid, 'conceptmap', $cmap, '', $this->userid);
        $service->save($this->workspaceid, 'mindmap', $mmap, '', $this->userid);

        $this->assertSame($cmap, $service->get_layout_json($this->workspaceid, 'conceptmap'));
        $this->assertSame($mmap, $service->get_layout_json($this->workspaceid, 'mindmap'));
    }

    /**
     * Merge mode updates only the patched nodes, preserving the others.
     *
     * @return void
     */
    public function test_merge_preserves_other_nodes(): void {
        $service = new layout_service();

        $full = json_encode([
            'v' => 1,
            'pos' => ['n1' => ['x' => 1, 'y' => 1], 'n2' => ['x' => 2, 'y' => 2]],
            'size' => [],
        ]);
        $service->save($this->workspaceid, 'conceptmap', $full, '', $this->userid, 'replace');

        // Merge only n1's new position.
        $patch = json_encode(['v' => 1, 'pos' => ['n1' => ['x' => 9, 'y' => 9]], 'size' => []]);
        $service->save($this->workspaceid, 'conceptmap', $patch, '', $this->userid, 'merge');

        $stored = json_decode($service->get_layout_json($this->workspaceid, 'conceptmap'), true);
        $this->assertSame(9, $stored['pos']['n1']['x']);
        $this->assertSame(2, $stored['pos']['n2']['x']);
    }

    /**
     * get_layout_since returns the layout only when newer than the given token,
     * and the token is strictly monotonic: two saves in the same second yield
     * distinguishable revisions, so no save can be missed by a polling client.
     *
     * @return void
     */
    public function test_get_layout_since_only_when_changed(): void {
        global $DB;
        $service = new layout_service();
        $service->save($this->workspaceid, 'conceptmap', '{"v":1}', '', $this->userid);
        $revision = (int) $DB->get_field(
            'vimipad_layout',
            'layoutrevision',
            ['workspaceid' => $this->workspaceid, 'profile' => 'conceptmap']
        );

        $before = $service->get_layout_since($this->workspaceid, 'conceptmap', $revision - 1);
        $this->assertTrue($before['changed']);
        $this->assertSame('{"v":1}', $before['layoutjson']);
        $this->assertSame($revision, $before['revision']);

        $same = $service->get_layout_since($this->workspaceid, 'conceptmap', $revision);
        $this->assertFalse($same['changed']);
        $this->assertSame('', $same['layoutjson']);
        $this->assertSame($revision, $same['revision']);

        // A second save within the same wall-clock second must still advance
        // the token, so a client holding the previous token sees the change.
        $service->save($this->workspaceid, 'conceptmap', '{"v":1,"pos":{}}', '', $this->userid);
        $after = $service->get_layout_since($this->workspaceid, 'conceptmap', $revision);
        $this->assertTrue($after['changed']);
        $this->assertGreaterThan($revision, $after['revision']);

        $none = $service->get_layout_since($this->workspaceid, 'tree', 0);
        $this->assertFalse($none['changed']);
        $this->assertSame(0, $none['revision']);
    }
}
