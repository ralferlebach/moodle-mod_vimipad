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

/**
 * The AMD module requests its editor strings by key. If a key is requested but
 * missing from the language file, Moodle's get_strings returns a placeholder and
 * the editor shows a broken label, so guard the two lists against drift.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\amd_string_keys_test
 */
final class amd_string_keys_test extends \advanced_testcase {
    /**
     * Every string key requested by amd/src/init.js exists in lang/en.
     *
     * @return void
     */
    public function test_amd_string_keys_exist_in_lang(): void {
        global $CFG;

        $requested = $this->requested_keys();
        $this->assertNotEmpty($requested, 'No STRING_KEYS parsed from amd/src/init.js');

        $string = [];
        require($CFG->dirroot . '/mod/vimipad/lang/en/vimipad.php');

        $missing = array_values(array_diff($requested, array_keys($string)));
        $this->assertSame([], $missing, 'AMD requests string keys missing from lang/en: '
            . implode(', ', $missing));
    }

    /**
     * Parse the STRING_KEYS array literal out of the AMD source.
     *
     * @return string[] The requested string keys.
     */
    private function requested_keys(): array {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/mod/vimipad/amd/src/init.js');
        $this->assertNotFalse($source, 'amd/src/init.js is not readable');

        $start = strpos($source, 'const STRING_KEYS = [');
        $this->assertNotFalse($start, 'STRING_KEYS not found in amd/src/init.js');
        $end = strpos($source, '];', $start);
        $this->assertNotFalse($end, 'STRING_KEYS array is not terminated');

        $literal = substr($source, $start, $end - $start);
        preg_match_all("/'([^']+)'/", $literal, $matches);

        return array_values(array_unique($matches[1]));
    }
}
