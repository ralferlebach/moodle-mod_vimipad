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

use mod_vimipad\local\style\node_style;
use mod_vimipad\local\operation\operation_type;

/**
 * Tests for node style validation and its gating in the operation validator.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\style\node_style
 * @covers     \mod_vimipad\local\operation\operation_type
 */
final class node_style_test extends \advanced_testcase {
    /**
     * Absent, empty and well-formed styles validate without error.
     *
     * @return void
     */
    public function test_valid_metadata_passes(): void {
        node_style::validate_metadata(null);
        node_style::validate_metadata('');
        node_style::validate_metadata(json_encode(['shape' => 'ellipse', 'fill' => '#aabbcc']));
        node_style::validate_metadata(json_encode([
            'text' => ['font' => 'serif', 'size' => 2, 'color' => '#112233', 'background' => '#ffffff'],
        ]));
        // Unknown keys are tolerated (reserved for profile metadata).
        node_style::validate_metadata(json_encode(['shape' => 'rect', 'future' => ['x' => 1]]));
        $this->assertTrue(true);
    }

    /**
     * A non-object payload is rejected.
     *
     * @return void
     */
    public function test_non_object_metadata_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata('"just a string"');
    }

    /**
     * An unknown shape is rejected.
     *
     * @return void
     */
    public function test_invalid_shape_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata(json_encode(['shape' => 'triangle']));
    }

    /**
     * A malformed colour is rejected.
     *
     * @return void
     */
    public function test_invalid_colour_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata(json_encode(['fill' => 'red']));
    }

    /**
     * A font size step beyond the accepted range is rejected.
     *
     * @return void
     */
    public function test_font_size_out_of_range_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        node_style::validate_metadata(json_encode(['text' => ['size' => 99]]));
    }

    /**
     * node_create tolerates a valid style and content payload.
     *
     * @return void
     */
    public function test_operation_type_accepts_node_style(): void {
        operation_type::validate_payload(operation_type::NODE_CREATE, [
            'type' => 'concept',
            'label' => 'X',
            'content' => '<p>hi</p>',
            'metadatajson' => json_encode(['shape' => 'ellipse', 'fill' => '#ff0000']),
        ]);
        $this->assertTrue(true);
    }

    /**
     * node_update rejects a malformed style payload.
     *
     * @return void
     */
    public function test_operation_type_rejects_bad_style(): void {
        $this->expectException(\invalid_parameter_exception::class);
        operation_type::validate_payload(operation_type::NODE_UPDATE, [
            'stableid' => 'node_abc',
            'metadatajson' => json_encode(['shape' => 'hexagon']),
        ]);
    }
}
