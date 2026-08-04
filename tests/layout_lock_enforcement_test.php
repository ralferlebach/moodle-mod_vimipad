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
 * Tests that move-locked nodes cannot be repositioned via the layout channel.
 *
 * These cover the authoritative server enforcement: whatever a client sends,
 * a move-locked node keeps its stored position and size, in both merge and
 * replace mode, while unlocked nodes still move freely. The endpoint decides
 * (from capability + the lock-mode preview toggle) which stable ids to pin;
 * here we drive the service directly with an explicit pinned set.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\layout_service
 */
final class layout_lock_enforcement_test extends \advanced_testcase {
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
     * Insert a live node with the given stable id and metadata.
     *
     * @param string $stableid The stable id.
     * @param string $metadatajson The metadata JSON (lock state).
     * @return void
     */
    private function make_node(string $stableid, string $metadatajson = ''): void {
        global $DB;
        $now = time();
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $this->workspaceid,
            'stableid' => $stableid,
            'type' => 'concept',
            'label' => $stableid,
            'content' => '',
            'contentformat' => 0,
            'metadatajson' => $metadatajson,
            'deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * The move-lock metadata for a node.
     *
     * @return string
     */
    private function movelock(): string {
        return json_encode(['locked' => true, 'locks' => ['move' => true, 'color' => false, 'text' => false]]);
    }

    /**
     * move_locked_node_stableids returns only nodes with the move group locked.
     *
     * @return void
     */
    public function test_move_locked_ids_discovery(): void {
        $this->make_node('locked_move', $this->movelock());
        $this->make_node('locked_text', json_encode([
            'locked' => true, 'locks' => ['move' => false, 'color' => false, 'text' => true],
        ]));
        $this->make_node('legacy_global', json_encode(['locked' => true])); // No locks map = all locked.
        $this->make_node('free', '');

        $ids = (new layout_service())->move_locked_node_stableids($this->workspaceid);
        sort($ids);
        $this->assertSame(['legacy_global', 'locked_move'], $ids);
    }

    /**
     * A move-locked node keeps its stored position when a later merge patch
     * tries to move it; an unlocked node in the same patch moves.
     *
     * @return void
     */
    public function test_pinned_node_not_moved_in_merge(): void {
        $this->make_node('a', $this->movelock());
        $this->make_node('b');
        $service = new layout_service();

        // Seed stored positions for both.
        $seed = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 10, 'y' => 10], 'b' => ['x' => 20, 'y' => 20]]]);
        $service->save($this->workspaceid, 'conceptmap', $seed, '', $this->userid, 'replace');

        // A patch tries to move both; only a is pinned.
        $patch = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 999, 'y' => 999], 'b' => ['x' => 30, 'y' => 30]]]);
        $service->save($this->workspaceid, 'conceptmap', $patch, '', $this->userid, 'merge', ['a']);

        $stored = json_decode($service->get_layout_json($this->workspaceid, 'conceptmap'), true);
        $this->assertSame(['x' => 10, 'y' => 10], $stored['pos']['a'], 'locked node stays put');
        $this->assertSame(['x' => 30, 'y' => 30], $stored['pos']['b'], 'unlocked node moves');
    }

    /**
     * A full replace payload cannot move a pinned node either: its stored
     * geometry is restored after the replace.
     *
     * @return void
     */
    public function test_pinned_node_not_moved_in_replace(): void {
        $this->make_node('a', $this->movelock());
        $service = new layout_service();

        $seed = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 5, 'y' => 6]], 'size' => ['a' => ['w' => 100, 'h' => 40]]]);
        $service->save($this->workspaceid, 'conceptmap', $seed, '', $this->userid, 'replace');

        $replace = json_encode([
            'v' => 1,
            'pos' => ['a' => ['x' => 500, 'y' => 600]],
            'size' => ['a' => ['w' => 999, 'h' => 999]],
        ]);
        $service->save($this->workspaceid, 'conceptmap', $replace, '', $this->userid, 'replace', ['a']);

        $stored = json_decode($service->get_layout_json($this->workspaceid, 'conceptmap'), true);
        $this->assertSame(['x' => 5, 'y' => 6], $stored['pos']['a'], 'position pinned');
        $this->assertSame(['w' => 100, 'h' => 40], $stored['size']['a'], 'size pinned');
    }

    /**
     * A pinned node with no stored position yet (first placement) is allowed
     * to be positioned once — pinning only prevents *changing* a stored value.
     *
     * @return void
     */
    public function test_first_placement_of_locked_node_allowed(): void {
        $this->make_node('a', $this->movelock());
        $service = new layout_service();

        // No prior layout stored. First placement of the locked node lands.
        $first = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 7, 'y' => 8]]]);
        $service->save($this->workspaceid, 'conceptmap', $first, '', $this->userid, 'replace', ['a']);

        $stored = json_decode($service->get_layout_json($this->workspaceid, 'conceptmap'), true);
        $this->assertSame(['x' => 7, 'y' => 8], $stored['pos']['a']);
    }

    /**
     * With an empty pinned set (bypassing manager or import), a move-locked
     * node moves freely — enforcement is opt-in at the endpoint.
     *
     * @return void
     */
    public function test_empty_pin_set_moves_everything(): void {
        $this->make_node('a', $this->movelock());
        $service = new layout_service();

        $seed = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 1, 'y' => 1]]]);
        $service->save($this->workspaceid, 'conceptmap', $seed, '', $this->userid, 'replace');

        $move = json_encode(['v' => 1, 'pos' => ['a' => ['x' => 2, 'y' => 2]]]);
        $service->save($this->workspaceid, 'conceptmap', $move, '', $this->userid, 'merge', []);

        $stored = json_decode($service->get_layout_json($this->workspaceid, 'conceptmap'), true);
        $this->assertSame(['x' => 2, 'y' => 2], $stored['pos']['a']);
    }
}
