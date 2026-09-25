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
 * The AMD editor-strings module lists the editor string keys. If a key is requested but
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
    /**
     * Every typed relation label the UI can ask for is actually requested.
     *
     * The relation menu and list view resolve labels as editor:reltype_<key>
     * from the strings the editor bootstrap loaded. A label defined in lang but
     * missing from STRING_KEYS is never fetched, so the UI silently shows the
     * raw key instead of the label - which is what happened to every typed
     * relation until this test existed.
     *
     * @return void
     */
    public function test_every_relation_label_is_requested(): void {
        global $CFG;
        $this->resetAfterTest();

        $lang = file_get_contents($CFG->dirroot . '/mod/vimipad/lang/en/vimipad.php');
        $amd = file_get_contents($CFG->dirroot . '/mod/vimipad/amd/src/editor_strings.js');

        preg_match_all("/editor:reltype_[a-z]+/", $lang, $defined);
        $defined = array_unique($defined[0]);
        $this->assertNotEmpty($defined, 'The plugin must define relation type labels.');

        foreach ($defined as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $amd,
                "The label {$key} is defined but never requested, so the UI would show the raw key."
            );
        }
    }

    public function test_amd_string_keys_exist_in_lang(): void {
        global $CFG;

        $string = [];
        require($CFG->dirroot . '/mod/vimipad/lang/en/vimipad.php');
        $available = array_keys($string);

        foreach (['amd/src/editor_strings.js', 'amd/src/revision.js'] as $module) {
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
