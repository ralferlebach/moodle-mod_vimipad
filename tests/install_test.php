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
 * Tests for the install-time admin notification.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_vimipad_install
 */
final class install_test extends \advanced_testcase {
    /**
     * The install hook emails the site administrator once, outside the initial
     * site install.
     *
     * @return void
     */
    public function test_install_emails_admin(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/vimipad/db/install.php');
        $this->resetAfterTest();

        $sink = $this->redirectEmails();
        xmldb_vimipad_install();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertSame(get_admin()->email, $messages[0]->to);
        $this->assertStringContainsString('ViMi Pad', $messages[0]->subject);
    }
}
