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

use core\check\result;
use mod_vimipad\check\data_integrity;
use mod_vimipad\check\subplugins;
use mod_vimipad\check\history_size;

/**
 * Tests for the operational status checks (Reports > Checks).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\check\data_integrity
 * @covers     \mod_vimipad\check\subplugins
 * @covers     \mod_vimipad\check\history_size
 */
final class check_test extends \advanced_testcase {
    /**
     * lib.php contributes the three checks to the status report.
     *
     * @return void
     */
    public function test_status_checks_callback(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vimipad/lib.php');
        $checks = mod_vimipad_status_checks();
        $this->assertCount(3, $checks);
        foreach ($checks as $check) {
            $this->assertInstanceOf(\core\check\check::class, $check);
            // Names resolve from lang (no missing string debugging).
            $this->assertNotEmpty($check->get_name());
        }
    }

    /**
     * A clean database reports OK integrity; an orphaned row warns.
     *
     * @return void
     */
    public function test_data_integrity(): void {
        global $DB;
        $this->resetAfterTest();

        $check = new data_integrity();
        $this->assertSame(result::OK, $check->get_result()->get_status());

        // Insert a node with a non-existent workspace: an orphan.
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => 999999, 'stableid' => 'orphan_node', 'type' => 'concept',
            'label' => 'Orphan', 'contentformat' => 0, 'deleted' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $result = $check->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertStringContainsString('vimipad_node', $result->get_details());
    }

    /**
     * The subplugin check reports OK when the bundled subplugins are installed.
     *
     * @return void
     */
    public function test_subplugins(): void {
        $this->resetAfterTest();
        $result = (new subplugins())->get_result();
        // The shipped assess/form subplugins are installed in the test env.
        $this->assertSame(result::OK, $result->get_status());
    }

    /**
     * The history-size check reports OK for a small history and includes the
     * measured counts in its details.
     *
     * @return void
     */
    public function test_history_size(): void {
        $this->resetAfterTest();
        $result = (new history_size())->get_result();
        $this->assertSame(result::OK, $result->get_status());
        $this->assertStringContainsString('0', $result->get_details());
    }
}
