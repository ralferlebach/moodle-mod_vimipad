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

use mod_vimipad\local\lock\element_lock;

/**
 * The differentiated element-lock group model.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\lock\element_lock
 */
final class element_lock_model_test extends \advanced_testcase {
    /**
     * A legacy global lock (no locks map) locks every group.
     *
     * @return void
     */
    public function test_legacy_lock_locks_all_groups(): void {
        $meta = ['locked' => true];
        $this->assertTrue(element_lock::is_locked($meta));
        foreach (element_lock::GROUPS as $group) {
            $this->assertTrue(element_lock::is_group_locked($meta, $group), "group $group should be locked");
        }
    }

    /**
     * A locks map locks only the named groups.
     *
     * @return void
     */
    public function test_locks_map_is_per_group(): void {
        $meta = ['locked' => true, 'locks' => ['move' => true, 'color' => false, 'text' => true]];
        $this->assertTrue(element_lock::is_group_locked($meta, element_lock::GROUP_MOVE));
        $this->assertFalse(element_lock::is_group_locked($meta, element_lock::GROUP_COLOR));
        $this->assertTrue(element_lock::is_group_locked($meta, element_lock::GROUP_TEXT));
    }

    /**
     * An unlocked element locks nothing.
     *
     * @return void
     */
    public function test_unlocked_locks_nothing(): void {
        $this->assertFalse(element_lock::is_locked(null));
        $this->assertFalse(element_lock::is_locked([]));
        foreach (element_lock::GROUPS as $group) {
            $this->assertFalse(element_lock::is_group_locked(['locks' => [$group => true]], $group));
        }
    }

    /**
     * Database fields map to the expected groups; ungated fields map to null.
     *
     * @return void
     */
    public function test_field_grouping(): void {
        $this->assertSame(element_lock::GROUP_MOVE, element_lock::group_for_field('geometryjson'));
        $this->assertSame(element_lock::GROUP_MOVE, element_lock::group_for_field('newsource'));
        $this->assertSame(element_lock::GROUP_MOVE, element_lock::group_for_field('newtarget'));
        $this->assertSame(element_lock::GROUP_TEXT, element_lock::group_for_field('label'));
        $this->assertSame(element_lock::GROUP_TEXT, element_lock::group_for_field('content'));
        // The 'type' and 'direction' fields are not gated by any group.
        $this->assertNull(element_lock::group_for_field('type'));
        $this->assertNull(element_lock::group_for_field('direction'));
    }

    /**
     * Metadata style keys map to groups; unknown keys fall back to text.
     *
     * @return void
     */
    public function test_meta_key_grouping(): void {
        $this->assertSame(element_lock::GROUP_MOVE, element_lock::group_for_meta_key('shape'));
        $this->assertSame(element_lock::GROUP_COLOR, element_lock::group_for_meta_key('fill'));
        $this->assertSame(element_lock::GROUP_TEXT, element_lock::group_for_meta_key('text'));
        // A future/unknown key is treated as text so it cannot bypass a text lock.
        $this->assertSame(element_lock::GROUP_TEXT, element_lock::group_for_meta_key('somethingnew'));
    }

    /**
     * changed_meta_groups reports exactly the groups whose content changed and
     * ignores lock bookkeeping keys.
     *
     * @return void
     */
    public function test_changed_meta_groups(): void {
        $old = ['shape' => 'ellipse', 'fill' => '#ffffff', 'text' => ['bold' => false]];

        // Change only the fill: colour group.
        $this->assertSame(
            [element_lock::GROUP_COLOR],
            element_lock::changed_meta_groups($old, ['shape' => 'ellipse', 'fill' => '#ff0000', 'text' => ['bold' => false]])
        );

        // Change shape and text: move and text.
        $groups = element_lock::changed_meta_groups(
            $old,
            ['shape' => 'rect', 'fill' => '#ffffff', 'text' => ['bold' => true]]
        );
        sort($groups);
        $this->assertSame([element_lock::GROUP_MOVE, element_lock::GROUP_TEXT], $groups);

        // Toggling the lock bookkeeping keys is not a styling change.
        $this->assertSame(
            [],
            element_lock::changed_meta_groups(
                $old,
                $old + ['locked' => true, 'locks' => ['move' => true]]
            )
        );
    }
}
