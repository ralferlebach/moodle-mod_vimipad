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

use mod_vimipad\local\policy\limits;
use mod_vimipad\local\service\journal_service;

/**
 * Free-text length limits are enforced at the service boundary, multibyte-safe.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\policy\limits
 * @covers     \mod_vimipad\local\service\journal_service
 */
final class text_limit_test extends \advanced_testcase {
    /**
     * check_text passes at exactly the maximum and rejects one over, counting
     * characters (not bytes) so multibyte text is measured correctly.
     *
     * @return void
     */
    public function test_check_text_boundary_is_multibyte_safe(): void {
        $this->resetAfterTest();

        // Exactly MAX_TEXT ASCII characters: allowed.
        $atlimit = str_repeat('a', limits::MAX_TEXT);
        limits::check_text($atlimit, limits::MAX_TEXT, 'test');
        $this->assertSame(limits::MAX_TEXT, \core_text::strlen($atlimit));

        // Exactly MAX_TEXT multibyte characters (each 1 char, multiple bytes):
        // allowed, proving the check counts characters, not bytes.
        $mb = str_repeat("\u{00e4}", limits::MAX_TEXT);
        $this->assertSame(limits::MAX_TEXT, \core_text::strlen($mb));
        $this->assertGreaterThan(limits::MAX_TEXT, strlen($mb));
        limits::check_text($mb, limits::MAX_TEXT, 'test');

        // One character over the limit: rejected.
        $over = str_repeat('a', limits::MAX_TEXT + 1);
        try {
            limits::check_text($over, limits::MAX_TEXT, 'test');
            $this->fail('expected a text limit exception');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsStringIgnoringCase('maximum length', $e->getMessage());
        }

        // Null is always fine.
        limits::check_text(null, limits::MAX_TEXT, 'test');
    }

    /**
     * check_bytes counts bytes, not characters: a multibyte string within the
     * character count but over the byte limit is rejected.
     *
     * @return void
     */
    public function test_check_bytes_counts_bytes_not_characters(): void {
        $this->resetAfterTest();

        // 100 multibyte chars = 100 chars but 200+ bytes.
        $mb = str_repeat("\u{00e4}", 100);
        $this->assertSame(100, \core_text::strlen($mb));
        $this->assertGreaterThan(150, strlen($mb));

        // Under the char count but over a 150-byte cap: rejected by check_bytes.
        try {
            limits::check_bytes($mb, 150, 'payload');
            $this->fail('expected a byte limit exception');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsStringIgnoringCase('bytes', $e->getMessage());
        }

        // Exactly at the byte limit: allowed.
        $atlimit = str_repeat('a', 150);
        limits::check_bytes($atlimit, 150, 'payload');
        // One byte over: rejected.
        $this->expectException(\moodle_exception::class);
        limits::check_bytes(str_repeat('a', 151), 150, 'payload');
    }

    /**
     * The journal service enforces the limit: an over-long entry is rejected
     * before it reaches the database.
     *
     * @return void
     */
    public function test_journal_entry_over_limit_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id, 'userid' => 0, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        $service = new journal_service();
        $over = str_repeat('x', limits::MAX_TEXT + 1);

        $this->expectException(\moodle_exception::class);
        $service->add_entry($ws->id, 1, $over, FORMAT_PLAIN, false, false, 0);
    }

    /**
     * An entry exactly at the limit is stored.
     *
     * @return void
     */
    public function test_journal_entry_at_limit_is_stored(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $vimipad = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) [
            'vimipadid' => $vimipad->id, 'userid' => 0, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);

        $service = new journal_service();
        $atlimit = str_repeat('x', limits::MAX_TEXT);
        $id = $service->add_entry($ws->id, 1, $atlimit, FORMAT_PLAIN, false, false, 0);
        $this->assertGreaterThan(0, $id);
    }
}
