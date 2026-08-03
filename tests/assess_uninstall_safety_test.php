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

use mod_vimipad\local\service\assess_service;
use mod_vimipad\local\assess\registry;


/**
 * The assess (scorer) subplugins are separately installable/uninstallable.
 * Uninstalling a scorer must degrade gracefully: scoring against a missing
 * scorer returns no result rather than throwing, and the registry simply omits
 * it. This mirrors the form-subplugin uninstall-safety contract.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\assess_service
 * @covers     \mod_vimipad\local\assess\registry
 */
final class assess_uninstall_safety_test extends \advanced_testcase {
    /**
     * Requesting a scorer whose subplugin is absent returns null, not a fatal.
     *
     * @return void
     */
    public function test_absent_scorer_resolves_to_null(): void {
        $this->resetAfterTest();
        registry::reset_cache();
        $this->assertNull(registry::get('a_scorer_with_no_subplugin'));
    }

    /**
     * Scoring a snapshot with a missing scorer degrades to no result rather
     * than throwing, so an activity keeps working after a scorer is removed.
     *
     * @return void
     */
    public function test_scoring_with_missing_scorer_degrades(): void {
        global $DB;
        $this->resetAfterTest();
        registry::reset_cache();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $instance = $DB->get_record('vimipad', ['id' => $module->id], '*', MUST_EXIST);

        $ws = (object) [
            'vimipadid' => $instance->id, 'userid' => 0, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);
        $snapid = $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $ws->id, 'status' => 0, 'revision' => 0,
            'snapshotjson' => json_encode(['nodes' => [], 'relations' => [], 'containers' => []]),
            'cohortjson' => '', 'timecreated' => time(),
        ]);

        // Scoring against a scorer key that has no installed subplugin returns
        // null (graceful degradation), not an exception.
        $result = (new assess_service())->score($instance, (int) $snapid, 'a_scorer_with_no_subplugin');
        $this->assertNull($result);
    }

    /**
     * The registry only returns installed scorers; a missing key is simply
     * absent from the full list.
     *
     * @return void
     */
    public function test_registry_omits_absent_scorers(): void {
        $this->resetAfterTest();
        registry::reset_cache();
        $all = registry::all();
        $this->assertArrayNotHasKey('a_scorer_with_no_subplugin', $all);
    }
}
