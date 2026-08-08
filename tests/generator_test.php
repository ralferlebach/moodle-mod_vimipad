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

/**
 * Tests for the test data generator.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad_generator
 */
final class generator_test extends \advanced_testcase {
    /**
     * create_workspace seeds nodes and a locked, submitted snapshot.
     *
     * @return void
     */
    public function test_create_workspace_with_submission(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        /** @var \mod_vimipad_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');
        $ws = $gen->create_workspace($instance, (int) $user->id, [
            ['stableid' => 'node_seedaaaaaaa', 'label' => 'Energy'],
        ], true);

        $this->assertNotEmpty($ws->id);
        $this->assertObjectHasProperty('snapshotid', $ws);
        $this->assertSame(1, $DB->count_records('vimipad_node', ['workspaceid' => $ws->id]));

        $reloaded = $DB->get_record('vimipad_workspace', ['id' => $ws->id]);
        $this->assertSame(1, (int) $reloaded->locked);
        $this->assertEquals($ws->snapshotid, $reloaded->submittedsnapshotid);
    }

    /**
     * Each granular creator inserts a row and returns it with an id.
     *
     * @return void
     */
    public function test_granular_creators(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $userid = (int) $user->id;
        /** @var \mod_vimipad_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $ws = $gen->create_workspace($instance, $userid);
        $a = $gen->create_node($ws, ['label' => 'A']);
        $b = $gen->create_node($ws, ['label' => 'B']);
        $this->assertNotEquals($a->stableid, $b->stableid);
        $this->assertSame(2, $DB->count_records('vimipad_node', ['workspaceid' => $ws->id]));

        $rel = $gen->create_relation($ws, ['sourceid' => $a->stableid, 'targetid' => $b->stableid]);
        $this->assertSame($a->stableid, $DB->get_field('vimipad_relation', 'sourceid', ['id' => $rel->id]));

        $c = $gen->create_container($ws, ['label' => 'Group']);
        $m = $gen->create_membership($c, ['itemstableid' => $a->stableid]);
        $this->assertSame($c->id, (int) $DB->get_field('vimipad_membership', 'containerid', ['id' => $m->id]));

        $rev = $gen->create_operations($ws, 5);
        $this->assertSame(5, $DB->count_records('vimipad_operation', ['workspaceid' => $ws->id]));
        $this->assertSame($rev, (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $ws->id]));

        $snap = $gen->create_snapshot($ws);
        $this->assertSame($rev, (int) $DB->get_field('vimipad_snapshot', 'revision', ['id' => $snap->id]));

        $grade = $gen->create_grade($instance, $userid, ['grade' => 88.5]);
        $this->assertEquals(88.5, (float) $DB->get_field('vimipad_grade', 'grade', ['id' => $grade->id]));

        $reviewer = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $pr = $gen->create_peer_review($snap, (int) $reviewer->id, ['score' => 0.75]);
        $this->assertEquals(0.75, (float) $DB->get_field('vimipad_peerreview', 'score', ['id' => $pr->id]));
    }

    /**
     * The small load profile seeds the documented node/relation/container counts
     * and every relation references real nodes in the workspace.
     *
     * @return void
     */
    public function test_small_map_profile(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_vimipad_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $ws = $gen->create_map_profile($instance, (int) $user->id, 'small');
        $this->assertSame(20, $DB->count_records('vimipad_node', ['workspaceid' => $ws->id]));
        $this->assertSame(30, $DB->count_records('vimipad_relation', ['workspaceid' => $ws->id]));
        $this->assertSame(5, $DB->count_records('vimipad_container', ['workspaceid' => $ws->id]));

        $nodeids = $DB->get_fieldset_select('vimipad_node', 'stableid', 'workspaceid = ?', [$ws->id]);
        foreach ($DB->get_records('vimipad_relation', ['workspaceid' => $ws->id]) as $rel) {
            $this->assertContains($rel->sourceid, $nodeids);
            $this->assertContains($rel->targetid, $nodeids);
        }
    }

    /**
     * A collaboration history advances the revision by the requested count.
     *
     * @return void
     */
    public function test_collaboration_history(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        /** @var \mod_vimipad_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_vimipad');

        $ws = $gen->create_workspace($instance, (int) $user->id);
        $rev = $gen->create_collaboration_history($ws, 100);
        $this->assertSame(100, $rev);
        $this->assertSame(100, $DB->count_records('vimipad_operation', ['workspaceid' => $ws->id]));
    }
}
