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

use mod_vimipad\local\operation\operation_type;

/**
 * Tests for operation payload contract validation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\operation\operation_type
 */
final class operation_type_test extends \advanced_testcase {
    /**
     * Well-formed payloads validate without error.
     *
     * @return void
     */
    public function test_valid_payloads_pass(): void {
        operation_type::validate_payload('node_create', ['type' => 'concept', 'label' => 'Energy']);
        operation_type::validate_payload('node_update', ['stableid' => 'node_aaaaaaaaaaaa', 'label' => 'New']);
        operation_type::validate_payload('relation_create', [
            'sourceid' => 'node_aaaaaaaaaaaa', 'targetid' => 'node_bbbbbbbbbbbb',
            'type' => 'link', 'direction' => -1,
        ]);
        operation_type::validate_payload('relation_update', ['stableid' => 'rel_cccccccccccc', 'direction' => 2]);
        $this->assertTrue(true);
    }

    /**
     * A direction outside the enum domain is rejected.
     *
     * @return void
     */
    public function test_invalid_direction_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload('relation_update', ['stableid' => 'rel_cccccccccccc', 'direction' => 5]);
    }

    /**
     * A non-string label on a node update is rejected.
     *
     * @return void
     */
    public function test_wrong_field_type_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload('node_update', ['stableid' => 'node_aaaaaaaaaaaa', 'label' => ['x']]);
    }

    /**
     * Unknown payload keys are rejected.
     *
     * @return void
     */
    public function test_unknown_field_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload('node_delete', ['stableid' => 'node_aaaaaaaaaaaa', 'sneaky' => 1]);
    }

    /**
     * Malformed relation metadata JSON is rejected.
     *
     * @return void
     */
    public function test_invalid_relation_metadata_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload('relation_update', [
            'stableid' => 'rel_cccccccccccc', 'metadatajson' => '{not valid',
        ]);
    }
}
