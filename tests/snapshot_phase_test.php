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

use mod_vimipad\local\snapshot_phase;
use mod_vimipad\local\service\snapshot_service;

/**
 * Tests for the submission phase state machine.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\snapshot_phase
 */
final class snapshot_phase_test extends \advanced_testcase {
    /**
     * Legal single-step moves are allowed and illegal jumps are not.
     *
     * @return void
     */
    public function test_legal_and_illegal_transitions(): void {
        // Legal lifecycle steps.
        $this->assertTrue(snapshot_phase::can_transition(
            snapshot_service::STATUS_DRAFT,
            snapshot_service::STATUS_SUBMITTED
        ));
        $this->assertTrue(snapshot_phase::can_transition(
            snapshot_service::STATUS_SUBMITTED,
            snapshot_service::STATUS_GRADED
        ));
        $this->assertTrue(snapshot_phase::can_transition(
            snapshot_service::STATUS_GRADED,
            snapshot_service::STATUS_RETURNED
        ));
        $this->assertTrue(snapshot_phase::can_transition(
            snapshot_service::STATUS_GRADED,
            snapshot_service::STATUS_REOPENED
        ));
        $this->assertTrue(snapshot_phase::can_transition(
            snapshot_service::STATUS_REOPENED,
            snapshot_service::STATUS_SUBMITTED
        ));

        // Illegal jumps.
        $this->assertFalse(snapshot_phase::can_transition(
            snapshot_service::STATUS_DRAFT,
            snapshot_service::STATUS_GRADED
        ));
        $this->assertFalse(snapshot_phase::can_transition(
            snapshot_service::STATUS_RETURNED,
            snapshot_service::STATUS_GRADED
        ));
        // A no-op is not a transition.
        $this->assertFalse(snapshot_phase::can_transition(
            snapshot_service::STATUS_GRADED,
            snapshot_service::STATUS_GRADED
        ));
    }

    /**
     * Returned is terminal apart from reopening; submitted is not.
     *
     * @return void
     */
    public function test_terminal_and_validity(): void {
        $this->assertTrue(snapshot_phase::is_terminal(snapshot_service::STATUS_RETURNED));
        $this->assertFalse(snapshot_phase::is_terminal(snapshot_service::STATUS_SUBMITTED));

        $this->assertTrue(snapshot_phase::is_valid(snapshot_service::STATUS_DRAFT));
        $this->assertTrue(snapshot_phase::is_valid(snapshot_service::STATUS_REOPENED));
        $this->assertFalse(snapshot_phase::is_valid(99));
    }

    /**
     * Each phase has a non-numeric localised label.
     *
     * @return void
     */
    public function test_labels_resolve(): void {
        $this->resetAfterTest();
        foreach (snapshot_phase::all() as $status) {
            $label = snapshot_phase::label($status);
            $this->assertIsString($label);
            $this->assertNotSame((string) $status, $label);
        }
    }

    /**
     * The service transition() writes legal moves and refuses illegal ones.
     *
     * @return void
     */
    public function test_service_transition_enforces_graph(): void {
        global $DB;
        $this->resetAfterTest();

        $insert = function (int $status): int {
            global $DB;
            return (int) $DB->insert_record('vimipad_snapshot', (object) [
                'workspaceid' => 1,
                'revision' => 1,
                'snapshotjson' => '{}',
                'submittedby' => 2,
                'status' => $status,
                'cohortjson' => '',
                'timecreated' => time(),
            ]);
        };
        $service = new snapshot_service();

        // Legal: submitted -> graded.
        $id = $insert(snapshot_service::STATUS_SUBMITTED);
        $service->transition($id, snapshot_service::STATUS_GRADED);
        $this->assertSame(
            snapshot_service::STATUS_GRADED,
            (int) $DB->get_field('vimipad_snapshot', 'status', ['id' => $id])
        );

        // Illegal: draft -> graded throws and leaves the phase unchanged.
        $draftid = $insert(snapshot_service::STATUS_DRAFT);
        try {
            $service->transition($draftid, snapshot_service::STATUS_GRADED);
            $this->fail('Expected an exception for an illegal transition.');
        } catch (\moodle_exception $e) {
            $this->assertSame(
                snapshot_service::STATUS_DRAFT,
                (int) $DB->get_field('vimipad_snapshot', 'status', ['id' => $draftid])
            );
        }
    }
}
