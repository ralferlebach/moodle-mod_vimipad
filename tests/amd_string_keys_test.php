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
     * Every string key requested by the AMD sources exists in lang/en.
     *
     * @return void
     */
    public function test_amd_string_keys_exist_in_lang(): void {
        global $CFG;

        $string = [];
        require($CFG->dirroot . '/mod/vimipad/lang/en/vimipad.php');
        $available = array_keys($string);

        foreach (['amd/src/init.js', 'amd/src/revision.js'] as $module) {
            $requested = $this->requested_keys($module);
            $this->assertNotEmpty($requested, "No STRING_KEYS parsed from $module");
            $missing = array_values(array_diff($requested, $available));
            $this->assertSame([], $missing, "$module requests string keys missing from lang/en: "
                . implode(', ', $missing));
        }
    }

    /**
     * Parse the STRING_KEYS array literal out of an AMD source file.
     *
     * @param string $module The module path relative to the plugin root.
     * @return string[] The requested string keys.
     */
    private function requested_keys(string $module): array {
        global $CFG;

        $source = file_get_contents($CFG->dirroot . '/mod/vimipad/' . $module);
        $this->assertNotFalse($source, "$module is not readable");

        $start = strpos($source, 'const STRING_KEYS = [');
        $this->assertNotFalse($start, "STRING_KEYS not found in $module");
        $end = strpos($source, '];', $start);
        $this->assertNotFalse($end, 'STRING_KEYS array is not terminated');

        $literal = substr($source, $start, $end - $start);
        preg_match_all("/'([^']+)'/", $literal, $matches);

        return array_values(array_unique($matches[1]));
    }
}
