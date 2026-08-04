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
    'mod_vimipad_get_constraint_status' => [
        'classname' => 'mod_vimipad\\external\\get_constraint_status',
        'methodname' => 'execute',
        'description' => 'Report the current map constraint status for non-blocking edit-time hints.',
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
    'mod_vimipad_start_consensus' => [
        'classname' => 'mod_vimipad\external\start_consensus',
        'methodname' => 'execute',
        'description' => 'Start the group-consensus submission process.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:submit',
    ],
    'mod_vimipad_confirm_consensus' => [
        'classname' => 'mod_vimipad\external\confirm_consensus',
        'methodname' => 'execute',
        'description' => 'Confirm the group-consensus submission for the current member.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:submit',
    ],
    'mod_vimipad_cancel_consensus' => [
        'classname' => 'mod_vimipad\external\cancel_consensus',
        'methodname' => 'execute',
        'description' => 'Cancel the group-consensus submission process.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:submit',
    ],
    'mod_vimipad_get_consensus_status' => [
        'classname' => 'mod_vimipad\external\get_consensus_status',
        'methodname' => 'execute',
        'description' => 'Read the current group-consensus status.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_get_revision_state' => [
        'classname' => 'mod_vimipad\external\get_revision_state',
        'methodname' => 'execute',
        'description' => 'Reconstruct a workspace state at a past revision (read-only).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_get_operations' => [
        'classname' => 'mod_vimipad\external\get_operations',
        'methodname' => 'execute',
        'description' => 'Return a workspace operation log up to a revision (read-only).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_get_layout_history' => [
        'classname' => 'mod_vimipad\external\get_layout_history',
        'methodname' => 'execute',
        'description' => 'Return a workspace node-layout history for replay (read-only).',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_get_journal_entries' => [
        'classname' => 'mod_vimipad\external\get_journal_entries',
        'methodname' => 'execute',
        'description' => 'Return the current user\'s own journal entries for a workspace.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:view',
    ],
    'mod_vimipad_add_journal_entry' => [
        'classname' => 'mod_vimipad\external\add_journal_entry',
        'methodname' => 'execute',
        'description' => 'Add a journal entry to the current user\'s own journal.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:comment',
    ],
    'mod_vimipad_import_map' => [
        'classname' => 'mod_vimipad\external\import_map',
        'methodname' => 'execute',
        'description' => 'Import a JSON export into a workspace.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/vimipad:editown, mod/vimipad:editgroup',
    ],
];
