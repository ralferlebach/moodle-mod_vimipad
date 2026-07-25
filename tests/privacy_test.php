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

use mod_vimipad\privacy\provider;

/**
 * Privacy provider tests for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\privacy\provider
 */
final class privacy_test extends \advanced_testcase {

    /**
     * The null provider declares a valid reason string.
     *
     * @return void
     */
    public function test_get_reason(): void {
        $reason = provider::get_reason();
        $this->assertSame('privacy:metadata', $reason);
        $this->assertNotEmpty(get_string($reason, 'mod_vimipad'));
    }
}
