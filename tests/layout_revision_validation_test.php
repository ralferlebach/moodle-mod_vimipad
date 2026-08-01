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
use mod_vimipad\external\save_layout;
use mod_vimipad\external\get_revision_state;
use mod_vimipad\api\map;
use mod_vimipad\local\policy\limits;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Input-validation hardening for layout and revision endpoints.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\external\save_layout
 * @covers     \mod_vimipad\external\get_revision_state
 * @covers     \mod_vimipad\api\map
 */
final class layout_revision_validation_test extends externallib_advanced_testcase {
    /** @var \stdClass The vimipad instance. */
    private \stdClass $instance;
    /** @var \stdClass The course module. */
    private \stdClass $cm;
    /** @var int The owner's workspace id. */
    private int $workspaceid;

    /**
     * An individual-mode activity with a workspace owned by an enrolled user.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id, 'collaborationmode' => 0,
        ]);
        $this->cm = get_coursemodule_from_instance('vimipad', $this->instance->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);
        $ws = (object) [
            'vimipadid' => $this->instance->id, 'userid' => $user->id, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $this->workspaceid = (int) $DB->insert_record('vimipad_workspace', $ws);
    }

    /**
     * save_layout rejects an unknown mode instead of silently replacing.
     *
     * @return void
     */
    public function test_save_layout_rejects_unknown_mode(): void {
        $this->expectException(\invalid_parameter_exception::class);
        save_layout::execute($this->cm->id, $this->workspaceid, '{}', '', 'bogusmode');
    }

    /**
     * save_layout accepts the known modes.
     *
     * @return void
     */
    public function test_save_layout_accepts_known_modes(): void {
        $r1 = save_layout::execute($this->cm->id, $this->workspaceid, '{}', '', 'replace');
        $this->assertTrue($r1['status']);
        $r2 = save_layout::execute($this->cm->id, $this->workspaceid, '{}', '', 'merge');
        $this->assertTrue($r2['status']);
    }

    /**
     * save_layout rejects an over-sized viewport payload.
     *
     * @return void
     */
    public function test_save_layout_rejects_oversized_viewport(): void {
        // Valid JSON string just over the byte limit.
        $big = '"' . str_repeat('a', limits::MAX_LAYOUT_BYTES + 10) . '"';
        $this->expectException(\moodle_exception::class);
        save_layout::execute($this->cm->id, $this->workspaceid, '{}', $big, 'replace');
    }

    /**
     * get_revision_state rejects a negative revision.
     *
     * @return void
     */
    public function test_get_revision_state_rejects_negative(): void {
        $this->expectException(\invalid_parameter_exception::class);
        get_revision_state::execute($this->cm->id, $this->workspaceid, -1);
    }

    /**
     * get_revision_state rejects a revision beyond the current one.
     *
     * @return void
     */
    public function test_get_revision_state_rejects_out_of_range(): void {
        // The currentrevision is 0, so 5 is out of range.
        $this->expectException(\invalid_parameter_exception::class);
        get_revision_state::execute($this->cm->id, $this->workspaceid, 5);
    }

    /**
     * The public map facade rejects a negative revision.
     *
     * @return void
     */
    public function test_public_map_rejects_negative_revision(): void {
        $this->expectException(\invalid_parameter_exception::class);
        map::state_at($this->workspaceid, -1);
    }
}
