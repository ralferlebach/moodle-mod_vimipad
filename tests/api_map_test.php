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

use mod_vimipad\api\map;
use mod_vimipad\api\ids;
use mod_vimipad\local\service\operation_service;

/**
 * Tests for the public map-reconstruction API (\mod_vimipad\api\map).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\api\map
 */
final class api_map_test extends \advanced_testcase {
    /**
     * The facade reconstructs the surviving state built from operations,
     * returning nodes, relations and containers.
     *
     * @return void
     */
    public function test_state_at_reconstructs_all_kinds(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id,
            'userid' => 0,
            'groupid' => 0,
            'currentrevision' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        // Build a small map through the operation service. The public ids
        // facade supplies the stable ids — exercising two API surfaces at once.
        $svc = new operation_service();
        $na = ids::new_node_id();
        $nb = ids::new_node_id();
        $rel = ids::new_relation_id();
        $cont = ids::new_container_id();
        $rev = $svc->apply($ws->id, 0, 'node_create', ['stableid' => $na, 'type' => 'concept', 'label' => 'A'], 1)['revision'];
        $rev = $svc->apply($ws->id, $rev, 'node_create', ['stableid' => $nb, 'type' => 'concept', 'label' => 'B'], 1)['revision'];
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'relation_create',
            ['stableid' => $rel, 'sourceid' => $na, 'targetid' => $nb, 'type' => 'link', 'label' => 'r', 'direction' => 1],
            1
        )['revision'];
        $rev = $svc->apply(
            $ws->id,
            $rev,
            'container_create',
            ['stableid' => $cont, 'type' => 'group', 'label' => 'C', 'geometryjson' => '{"x":0,"y":0,"w":100,"h":100}'],
            1
        )['revision'];

        $state = map::state_at($ws->id, $rev);

        $this->assertArrayHasKey('nodes', $state);
        $this->assertArrayHasKey('relations', $state);
        $this->assertArrayHasKey('containers', $state);
        $this->assertCount(2, $state['nodes']);
        $this->assertCount(1, $state['relations']);
        $this->assertCount(1, $state['containers']);
    }

    /**
     * Reconstructing at an earlier revision yields the earlier state.
     *
     * @return void
     */
    public function test_state_at_is_revision_scoped(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id, 'userid' => 0, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        $svc = new operation_service();
        $na = ids::new_node_id();
        $nb = ids::new_node_id();
        $rev1 = $svc->apply($ws->id, 0, 'node_create', ['stableid' => $na, 'type' => 'concept', 'label' => 'A'], 1)['revision'];
        $rev2 = $svc->apply($ws->id, $rev1, 'node_create', ['stableid' => $nb, 'type' => 'concept', 'label' => 'B'], 1)['revision'];

        // At the first revision only one node existed.
        $this->assertCount(1, map::state_at($ws->id, $rev1)['nodes']);
        // At the second, both.
        $this->assertCount(2, map::state_at($ws->id, $rev2)['nodes']);
    }
}
