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

/**
 * Install-time hook for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Notify the site administrator by email that ViMi Pad has been installed.
 *
 * Runs only when an administrator installs the plugin into a running site; it is
 * skipped during the initial site install (when there is no real administrator
 * yet and the notice would only add noise to an unattended or CI setup). The
 * email is best-effort and never blocks installation.
 *
 * @return bool Always true.
 */
function xmldb_vimipad_install() {
    global $CFG, $SITE;

    if (during_initial_install()) {
        return true;
    }

    $admin = get_admin();
    if (!$admin) {
        return true;
    }

    $data = (object) [
        'site' => format_string($SITE->fullname ?? ''),
        'url' => $CFG->wwwroot,
    ];
    $subject = get_string('installnotify:subject', 'mod_vimipad');
    $body = get_string('installnotify:body', 'mod_vimipad', $data);

    try {
        email_to_user($admin, \core_user::get_noreply_user(), $subject, $body);
    } catch (\Throwable $e) {
        // The notification is a courtesy, not a requirement: never let a mail
        // problem interfere with a successful installation.
        return true;
    }

    return true;
}
