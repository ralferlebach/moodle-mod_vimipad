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

use mod_vimipad\external\helper;
use mod_vimipad\local\service\import_service;
use mod_vimipad\local\service\layout_service;
use mod_vimipad\local\service\operation_service;
use mod_vimipad\local\service\workspace_service;

/**
 * Import hardening (format version gate, mode validation, complete replace)
 * and the mode-dependent read-access matrix for course workspaces.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\import_service
 * @covers     \mod_vimipad\external\helper
 */
final class import_hardening_and_read_access_test extends \advanced_testcase {
    /** @var \stdClass The activity instance. */
    private \stdClass $instance;

    /** @var int The workspace id. */
    private int $workspaceid;

    /**
     * Create a course, module and an empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $this->instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * A minimal valid envelope with the given extra top-level entries.
     *
     * @param array $extra Entries merged over the defaults (e.g. formatversion).
     * @return string The JSON document.
     */
    private function envelope(array $extra = []): string {
        return json_encode(array_merge([
            'generator' => 'mod_vimipad',
            'formatversion' => 1,
            'data' => [
                'nodes' => [['stableid' => 'node_x', 'type' => 'concept', 'label' => 'X']],
                'relations' => [],
            ],
        ], $extra));
    }

    /**
     * An unsupported format version is rejected, not silently misread.
     *
     * @return void
     */
    public function test_unsupported_format_version_is_rejected(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);

        try {
            (new import_service())->import_json($this->envelope(['formatversion' => 2]), $workspace, 1);
            $this->fail('Expected error:importversion was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:importversion', $e->errorcode);
        }

        // A missing version is read as 1 (legacy tolerance) and imports fine.
        $doc = json_decode($this->envelope(), true);
        unset($doc['formatversion']);
        $counts = (new import_service())->import_json(json_encode($doc), $workspace, 1);
        $this->assertSame(1, $counts['nodes']);
    }

    /**
     * An unknown import mode is rejected instead of falling back to append.
     *
     * @return void
     */
    public function test_unknown_mode_is_rejected(): void {
        global $DB;
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);

        $this->expectException(\invalid_parameter_exception::class);
        (new import_service())->import_json($this->envelope(), $workspace, 1, 'overwrite');
    }

    /**
     * Replace removes the entire previous map: nodes, relations, containers,
     * their membership rows, and the stored layout.
     *
     * @return void
     */
    public function test_replace_removes_the_whole_map(): void {
        global $DB;
        $service = new operation_service();
        $rev = 0;
        $r = $service->apply($this->workspaceid, $rev, 'node_create', ['type' => 'concept', 'label' => 'Old'], 1);
        $rev = (int) $r['revision'];
        $oldnode = $r['stableid'];
        $r = $service->apply($this->workspaceid, $rev, 'container_create', [
            'type' => 'group', 'label' => 'OldGroup',
            'geometryjson' => json_encode(['x' => 0, 'y' => 0, 'w' => 100, 'h' => 100]),
        ], 1);
        $rev = (int) $r['revision'];
        $oldcontainer = $r['stableid'];
        $service->apply($this->workspaceid, $rev, 'membership_add', [
            'containerstableid' => $oldcontainer, 'itemtype' => 'node', 'itemstableid' => $oldnode,
        ], 1);
        (new layout_service())->save($this->workspaceid, $this->instance->defaultprofile, json_encode([
            'v' => 1, 'pos' => [$oldnode => ['x' => 5, 'y' => 5]], 'size' => [],
        ]), '', 1);

        $workspace = $DB->get_record('vimipad_workspace', ['id' => $this->workspaceid], '*', MUST_EXIST);
        $counts = (new import_service())->import_json($this->envelope(), $workspace, 1, 'replace');
        $this->assertSame(1, $counts['nodes']);

        // Old node and container are gone (soft-deleted), memberships purged.
        $this->assertSame(0, (int) $DB->count_records('vimipad_node', [
            'workspaceid' => $this->workspaceid, 'stableid' => $oldnode, 'deleted' => 0,
        ]));
        $this->assertSame(0, (int) $DB->count_records('vimipad_container', [
            'workspaceid' => $this->workspaceid, 'stableid' => $oldcontainer, 'deleted' => 0,
        ]));
        $this->assertSame(0, (int) $DB->count_records('vimipad_membership'));

        // The stored layout no longer references the old node: the import had
        // no layout, so replace cleared it.
        $layoutjson = (new layout_service())->get_layout_json($this->workspaceid, $this->instance->defaultprofile);
        $this->assertStringNotContainsString($oldnode, (string) $layoutjson);

        // Exactly one live node remains: the imported one.
        $this->assertSame(1, (int) $DB->count_records('vimipad_node', [
            'workspaceid' => $this->workspaceid, 'deleted' => 0,
        ]));
    }

    /**
     * Read access matrix: a foreign individual workspace needs grade, while the
     * shared course workspace is readable by any enrolled user with view.
     *
     * @return void
     */
    public function test_read_access_matrix(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        // Individual mode: another learner's workspace requires grade.
        $individual = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => workspace_service::MODE_INDIVIDUAL,
        ]);
        $individualcm = get_coursemodule_from_instance('vimipad', $individual->id, 0, false, MUST_EXIST);
        $now = time();
        $foreignwsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $individual->id, 'userid' => $other->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $this->setUser($student);
        try {
            helper::validate_workspace_for_read((int) $individualcm->id, $foreignwsid, $i1, $w1);
            $this->fail('Reading a foreign individual workspace should require grade.');
        } catch (\required_capability_exception $e) {
            // Expected: the read helper demands the grade capability here.
            $this->assertSame('nopermissions', $e->errorcode);
        }

        // Course mode: the shared workspace is everyone's map; read allowed.
        $coursemode = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => workspace_service::MODE_COURSE,
        ]);
        $coursecm = get_coursemodule_from_instance('vimipad', $coursemode->id, 0, false, MUST_EXIST);
        $coursewsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $coursemode->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $context = helper::validate_workspace_for_read((int) $coursecm->id, $coursewsid, $i2, $w2);
        $this->assertInstanceOf(\context_module::class, $context);
        $this->assertSame((int) $coursemode->id, (int) $i2->id);
    }
}
