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

use mod_vimipad\local\policy\layout_policy;

/**
 * Schema validation for layout and viewport payloads.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\policy\layout_policy
 */
final class layout_policy_test extends \advanced_testcase {
    /**
     * Valid layouts (versioned envelope, legacy bare map, empty) are accepted.
     *
     * @return void
     */
    public function test_valid_layouts_pass(): void {
        // Versioned envelope.
        layout_policy::validate_layout(json_encode([
            'v' => 1,
            'pos' => ['node_a' => ['x' => 10, 'y' => 20], 'node_b' => ['x' => -5.5, 'y' => 0]],
            'size' => ['node_a' => ['w' => 100, 'h' => 40]],
        ]));
        // Legacy bare position map.
        layout_policy::validate_layout(json_encode(['node_a' => ['x' => 1, 'y' => 2]]));
        // Empty / null clears the layout.
        layout_policy::validate_layout('');
        layout_policy::validate_layout('null');
        $this->assertTrue(true);
    }

    /**
     * A non-object root (scalar or list) is rejected.
     *
     * @return void
     */
    public function test_scalar_root_is_rejected(): void {
        foreach (['42', 'true', '"hello"', '[1,2,3]'] as $bad) {
            try {
                layout_policy::validate_layout($bad);
                $this->fail("expected rejection for: $bad");
            } catch (\moodle_exception $e) {
                $this->assertStringContainsStringIgnoringCase('format', $e->getMessage());
            }
        }
    }

    /**
     * Unknown top-level fields are rejected.
     *
     * @return void
     */
    public function test_unknown_fields_rejected(): void {
        $this->expectException(\moodle_exception::class);
        layout_policy::validate_layout(json_encode(['v' => 1, 'evil' => true]));
    }

    /**
     * Non-numeric or non-finite coordinates are rejected.
     *
     * @return void
     */
    public function test_bad_coordinates_rejected(): void {
        // Non-numeric x.
        try {
            layout_policy::validate_layout(json_encode(['pos' => ['n' => ['x' => 'abc', 'y' => 1]]]));
            $this->fail('expected rejection for non-numeric coordinate');
        } catch (\moodle_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        // Out-of-range coordinate.
        $this->expectException(\moodle_exception::class);
        layout_policy::validate_layout(json_encode(['pos' => ['n' => ['x' => 1, 'y' => 1e12]]]));
    }

    /**
     * Non-positive sizes are rejected.
     *
     * @return void
     */
    public function test_nonpositive_size_rejected(): void {
        $this->expectException(\moodle_exception::class);
        layout_policy::validate_layout(json_encode(['size' => ['n' => ['w' => 0, 'h' => 10]]]));
    }

    /**
     * Too many layout objects are rejected.
     *
     * @return void
     */
    public function test_object_count_capped(): void {
        $pos = [];
        for ($i = 0; $i <= layout_policy::MAX_LAYOUT_OBJECTS + 1; $i++) {
            $pos['node_' . $i] = ['x' => 0, 'y' => 0];
        }
        $this->expectException(\moodle_exception::class);
        layout_policy::validate_layout(json_encode(['pos' => $pos]));
    }

    /**
     * Viewport validation: valid numbers pass, unknown fields and bad values
     * are rejected.
     *
     * @return void
     */
    public function test_viewport_schema(): void {
        layout_policy::validate_viewport(json_encode(['x' => 10, 'y' => -5, 'zoom' => 1.5]));
        layout_policy::validate_viewport('');
        $this->assertTrue(true);

        $this->expectException(\moodle_exception::class);
        layout_policy::validate_viewport(json_encode(['x' => 'nan', 'evil' => 1]));
    }
}
