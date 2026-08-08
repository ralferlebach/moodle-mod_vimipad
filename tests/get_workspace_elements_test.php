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
use mod_vimipad\external\get_workspace;
use mod_vimipad\external\get_workspace_elements;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for paginated element loading: get_workspace(includeelements=false)
 * returns metadata + counts, and get_workspace_elements returns validated pages.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\get_workspace_elements
 */
final class get_workspace_elements_test extends externallib_advanced_testcase {
    /**
     * Insert $count live nodes into a workspace.
     *
     * @param int $workspaceid The workspace id.
     * @param int $count How many nodes to insert.
     * @return void
     */
    private function seed_nodes(int $workspaceid, int $count): void {
        global $DB;
        $now = time();
        for ($i = 0; $i < $count; $i++) {
            $DB->insert_record('vimipad_node', (object) [
                'workspaceid' => $workspaceid,
                'stableid' => 'node_' . $i,
                'type' => 'concept',
                'label' => 'N' . $i,
                'content' => '',
                'contentformat' => FORMAT_HTML,
                'metadatajson' => '{}',
                'createdby' => 1,
                'modifiedby' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
                'deleted' => 0,
            ]);
        }
    }

    /**
     * includeelements=false returns empty arrays plus matching counts, while the
     * default call still returns the full arrays.
     *
     * @return void
     */
    public function test_includeelements_false_returns_counts_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $meta = get_workspace::execute($instance->cmid);
        $meta = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $meta);
        $this->seed_nodes((int) $meta['workspaceid'], 5);

        // Default (full) still returns the array.
        $full = get_workspace::execute($instance->cmid);
        $full = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $full);
        $this->assertCount(5, $full['nodes']);

        // With includeelements=false: empty arrays, but counts present and correct.
        $lite = get_workspace::execute($instance->cmid, 0, 0, false);
        $lite = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $lite);
        $this->assertSame([], $lite['nodes']);
        $this->assertArrayHasKey('counts', $lite);
        $this->assertSame(5, $lite['counts']['nodes']);
    }

    /**
     * get_workspace_elements returns validated pages, with correct total/hasmore,
     * and paging through assembles every node exactly once.
     *
     * @return void
     */
    public function test_element_pages_cover_all_rows(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $meta = get_workspace::execute($instance->cmid, 0, 0, false);
        $meta = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $meta);
        $wsid = (int) $meta['workspaceid'];
        $this->seed_nodes($wsid, 3);

        // First page of 2.
        $page1 = get_workspace_elements::execute($instance->cmid, $wsid, 'nodes', 0, 2);
        $page1 = \core_external\external_api::clean_returnvalue(get_workspace_elements::execute_returns(), $page1);
        $this->assertSame(3, $page1['total']);
        $this->assertTrue($page1['hasmore']);
        $this->assertCount(2, $page1['nodes']);

        // Second page of 2 → 1 row, no more.
        $page2 = get_workspace_elements::execute($instance->cmid, $wsid, 'nodes', 2, 2);
        $page2 = \core_external\external_api::clean_returnvalue(get_workspace_elements::execute_returns(), $page2);
        $this->assertFalse($page2['hasmore']);
        $this->assertCount(1, $page2['nodes']);

        // The two pages together cover all three stable ids, each once.
        $ids = array_merge(
            array_column($page1['nodes'], 'stableid'),
            array_column($page2['nodes'], 'stableid')
        );
        sort($ids);
        $this->assertSame(['node_0', 'node_1', 'node_2'], $ids);
    }

    /**
     * An unknown element kind is rejected.
     *
     * @return void
     */
    public function test_unknown_kind_is_rejected(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $meta = get_workspace::execute($instance->cmid, 0, 0, false);
        $meta = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $meta);

        $this->expectException(\invalid_parameter_exception::class);
        get_workspace_elements::execute($instance->cmid, (int) $meta['workspaceid'], 'widgets', 0, 10);
    }

    /**
     * A grader viewing an enrolled learner who has never opened the activity gets
     * a well-formed empty state: no workspace, no elements, and a collab block
     * without a push topic/token — and crucially no warning or exception.
     *
     * @return void
     */
    public function test_grader_viewing_learner_without_workspace(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $learner = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = get_workspace::execute($instance->cmid, 0, (int) $learner->id);
        $result = \core_external\external_api::clean_returnvalue(get_workspace::execute_returns(), $result);

        $this->assertSame(0, $result['workspaceid']);
        $this->assertSame(0, $result['revision']);
        $this->assertSame([], $result['nodes']);
        $this->assertSame([], $result['relations']);
        $this->assertSame([], $result['containers']);
        $this->assertSame('', $result['collab']['pushtopic']);
        $this->assertSame('', $result['collab']['pushtoken']);
    }
}
