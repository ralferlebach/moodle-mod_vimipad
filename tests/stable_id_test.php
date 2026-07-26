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

use mod_vimipad\local\id\stable_id;
use mod_vimipad\api\ids;

/**
 * Tests for stable id generation and validation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\id\stable_id
 * @covers     \mod_vimipad\api\ids
 */
final class stable_id_test extends \advanced_testcase {
    /**
     * Generated ids carry the correct prefix and validate against their kind.
     *
     * @return void
     */
    public function test_generate_produces_valid_ids(): void {
        $node = stable_id::generate('node');
        $relation = stable_id::generate('relation');
        $container = stable_id::generate('container');

        $this->assertStringStartsWith('node_', $node);
        $this->assertStringStartsWith('rel_', $relation);
        $this->assertStringStartsWith('cont_', $container);

        $this->assertTrue(stable_id::is_valid($node, 'node'));
        $this->assertTrue(stable_id::is_valid($relation, 'relation'));
        $this->assertTrue(stable_id::is_valid($container, 'container'));
    }

    /**
     * Generated ids are unique across many draws.
     *
     * @return void
     */
    public function test_generate_is_unique(): void {
        $seen = [];
        for ($i = 0; $i < 500; $i++) {
            $id = stable_id::generate('node');
            $this->assertArrayNotHasKey($id, $seen);
            $seen[$id] = true;
        }
    }

    /**
     * An unknown kind throws a coding exception.
     *
     * @return void
     */
    public function test_generate_rejects_unknown_kind(): void {
        $this->expectException(\coding_exception::class);
        stable_id::generate('bogus');
    }

    /**
     * Validation rejects malformed values and cross-kind mismatches.
     *
     * @return void
     */
    public function test_is_valid_rejects_bad_values(): void {
        $this->assertFalse(stable_id::is_valid('node_XYZ'));
        $this->assertFalse(stable_id::is_valid('node_'));
        $this->assertFalse(stable_id::is_valid('123'));
        $this->assertFalse(stable_id::is_valid(''));

        $node = stable_id::generate('node');
        $this->assertFalse(stable_id::is_valid($node, 'relation'));
        $this->assertFalse(stable_id::is_valid($node, 'bogus'));
    }

    /**
     * The public API facade delegates correctly to the internal generator.
     *
     * @return void
     */
    public function test_public_api_facade(): void {
        $node = ids::new_node_id();
        $relation = ids::new_relation_id();
        $container = ids::new_container_id();

        $this->assertTrue(ids::is_valid($node, 'node'));
        $this->assertTrue(ids::is_valid($relation, 'relation'));
        $this->assertTrue(ids::is_valid($container, 'container'));
        $this->assertTrue(ids::is_valid($node));
        $this->assertFalse(ids::is_valid('nope'));
    }
}
