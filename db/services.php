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
 * External function (web service) definitions for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_vimipad_get_workspace' => [
        'classname' => 'mod_vimipad\external\get_workspace',
        'methodname' => 'execute',
        'description' => 'Resolve and return the current user\'s workspace with its state.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_apply_operation' => [
        'classname' => 'mod_vimipad\external\apply_operation',
        'methodname' => 'execute',
        'description' => 'Apply a single validated operation to a workspace.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],
    'mod_vimipad_save_layout' => [
        'classname' => 'mod_vimipad\external\save_layout',
        'methodname' => 'execute',
        'description' => 'Persist the non-revisioned layout state of a workspace.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],

    'mod_vimipad_poll_changes' => [
        'classname' => 'mod_vimipad\external\poll_changes',
        'methodname' => 'execute',
        'description' => 'Poll for operations since a revision, the current layout and active element leases.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],

    'mod_vimipad_acquire_lock' => [
        'classname' => 'mod_vimipad\external\acquire_lock',
        'methodname' => 'execute',
        'description' => 'Acquire a short-lived editing lease on a node or relation.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],

    'mod_vimipad_renew_lock' => [
        'classname' => 'mod_vimipad\external\renew_lock',
        'methodname' => 'execute',
        'description' => 'Renew (heartbeat) an editing lease held by the caller.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],

    'mod_vimipad_release_lock' => [
        'classname' => 'mod_vimipad\external\release_lock',
        'methodname' => 'execute',
        'description' => 'Release an editing lease held by the caller.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],
    'mod_vimipad_create_snapshot' => [
        'classname' => 'mod_vimipad\external\create_snapshot',
        'methodname' => 'execute',
        'description' => 'Submit the current workspace as an immutable snapshot.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:submit',
    ],
];
