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
 * CLI seed for the mod_vimipad JMeter/k6 load test.
 *
 * Creates a course with an individual-mode ViMi Pad activity and a student,
 * fills that student's workspace with a large map (1000 nodes / 2000 relations /
 * 200 containers) plus a long operation log, then enables the REST web service,
 * adds the read functions and mints a token. Finally it prints the shell exports
 * the load run needs (TOKEN, WORKSPACEID, CMID, BASE_URL).
 *
 * Usage: php mod/vimipad/tests/load/seed_large.php [oplog_count]
 *   oplog_count  number of operation-log rows to insert (default 5000)
 *
 * Intended for a disposable dev/staging site — never point it at production.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/externallib.php');

$oplog = isset($argv[1]) ? max(0, (int) $argv[1]) : 5000;
$nodes = 1000;
$relations = 2000;
$containers = 200;
$now = time();

// Course.
$course = create_course((object) [
    'fullname' => 'ViMi Pad load ' . $now,
    'shortname' => 'vimiload' . $now,
    'category' => 1,
    'format' => 'topics',
    'numsections' => 1,
]);

// Student user, enrolled.
$username = 'vimi_load_' . $now;
$user = (object) [
    'username' => $username,
    'firstname' => 'Load',
    'lastname' => 'Tester',
    'email' => $username . '@example.invalid',
    'confirmed' => 1,
    'mnethostid' => $CFG->mnet_localhost_id,
    'auth' => 'manual',
];
$user->id = user_create_user($user, false, false);
$password = 'Vimi!load_1';
update_internal_user_password($DB->get_record('user', ['id' => $user->id]), $password);

$context = context_course::instance($course->id);
$studentrole = $DB->get_record('role', ['archetype' => 'student'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$enrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual->enrol_user($enrol, $user->id, $studentrole->id);

// Individual-mode ViMi Pad activity (each user gets their own workspace).
$module = $DB->get_record('modules', ['name' => 'vimipad'], '*', MUST_EXIST);
$moduleinfo = (object) [
    'modulename' => 'vimipad',
    'module' => $module->id,
    'course' => $course->id,
    'section' => 1,
    'visible' => 1,
    'name' => 'Load map',
    'intro' => 'Load-test fixture',
    'introformat' => FORMAT_HTML,
    'cmidnumber' => '',
    'defaultprofile' => 'conceptmap',
    'collaborationmode' => 0,
    'gradingmode' => 0,
    'aienabled' => 0,
];
$created = add_moduleinfo($moduleinfo, $course);
$cmid = (int) $created->coursemodule;

// Resolve the student's workspace exactly as get_workspace would.
$instance = $DB->get_record('vimipad', ['id' => $created->instance], '*', MUST_EXIST);
$cmcontext = context_module::instance($cmid);
$workspaceservice = new \mod_vimipad\local\service\workspace_service();
$workspace = $workspaceservice->get_or_create_for_user($instance, $cmcontext, $user->id);
$workspaceid = (int) $workspace->id;

// Bulk-fill the map. Direct inserts keep this fast for a large fixture.
$noderows = [];
for ($i = 0; $i < $nodes; $i++) {
    $noderows[] = (object) [
        'workspaceid' => $workspaceid,
        'stableid' => 'node_' . $i,
        'type' => 'concept',
        'label' => 'N' . $i,
        'content' => '',
        'contentformat' => FORMAT_HTML,
        'metadatajson' => '{}',
        'createdby' => $user->id,
        'modifiedby' => $user->id,
        'timecreated' => $now,
        'timemodified' => $now,
        'deleted' => 0,
    ];
}
$DB->insert_records('vimipad_node', $noderows);

$relrows = [];
$n = max($nodes, 1);
for ($i = 0; $i < $relations; $i++) {
    $relrows[] = (object) [
        'workspaceid' => $workspaceid,
        'stableid' => 'rel_' . $i,
        'sourceid' => 'node_' . ($i % $n),
        'targetid' => 'node_' . (($i + 1) % $n),
        'type' => 'link',
        'label' => 'r' . $i,
        'direction' => 1,
        'metadatajson' => '{}',
        'createdby' => $user->id,
        'modifiedby' => $user->id,
        'timecreated' => $now,
        'timemodified' => $now,
        'deleted' => 0,
    ];
}
$DB->insert_records('vimipad_relation', $relrows);

$conrows = [];
for ($i = 0; $i < $containers; $i++) {
    $conrows[] = (object) [
        'workspaceid' => $workspaceid,
        'stableid' => 'cont_' . $i,
        'type' => 'group',
        'label' => 'C' . $i,
        'geometryjson' => json_encode(['x' => 50 + $i * 20, 'y' => 50, 'w' => 200, 'h' => 150]),
        'metadatajson' => '{}',
        'deleted' => 0,
    ];
}
$DB->insert_records('vimipad_container', $conrows);

// Operation log for get_operations load.
if ($oplog > 0) {
    $oprows = [];
    for ($rev = 1; $rev <= $oplog; $rev++) {
        $oprows[] = (object) [
            'workspaceid' => $workspaceid,
            'revision' => $rev,
            'operationtype' => 'createnode',
            'payloadjson' => json_encode(['stableid' => 'node_' . ($rev % $n), 'label' => 'N' . ($rev % $n)]),
            'userid' => $user->id,
            'timecreated' => $now,
        ];
        // Insert in chunks to keep memory bounded.
        if (count($oprows) >= 1000) {
            $DB->insert_records('vimipad_operation', $oprows);
            $oprows = [];
        }
    }
    if ($oprows) {
        $DB->insert_records('vimipad_operation', $oprows);
    }
    $DB->set_field('vimipad_workspace', 'currentrevision', $oplog, ['id' => $workspaceid]);
}

// Enable web services + REST.
set_config('enablewebservices', 1);
$protocols = get_config('core', 'webserviceprotocols');
if (strpos((string) $protocols, 'rest') === false) {
    set_config('webserviceprotocols', trim($protocols . ',rest', ','));
}

// Allow REST use for authenticated users so the minted token can be used
// (dev/test convenience; do not do this on a production site).
$authrole = $DB->get_record('role', ['shortname' => 'user'], '*', IGNORE_MISSING);
if ($authrole) {
    assign_capability('webservice/rest:use', CAP_ALLOW, $authrole->id, context_system::instance()->id, true);
    context_system::instance()->mark_dirty();
}

// External service carrying the read functions, then a token for the student.
$readfunctions = [
    'mod_vimipad_get_workspace',
    'mod_vimipad_get_operations',
    'mod_vimipad_get_layout_history',
    'mod_vimipad_get_revision_state',
];
$service = (object) [
    'name' => 'ViMi Pad load ' . $now,
    'shortname' => 'vimipadload' . $now,
    'enabled' => 1,
    'restrictedusers' => 0,
    'downloadfiles' => 0,
    'uploadfiles' => 0,
    'timecreated' => $now,
    'timemodified' => $now,
];
$service->id = $DB->insert_record('external_services', $service);
foreach ($readfunctions as $fn) {
    $DB->insert_record('external_services_functions', (object) [
        'externalserviceid' => $service->id,
        'functionname' => $fn,
    ]);
}
$token = external_generate_token(
    EXTERNAL_TOKEN_PERMANENT,
    $service->id,
    $user->id,
    context_system::instance()
);

// Print shell exports for the load run.
echo "export BASE_URL='{$CFG->wwwroot}'\n";
echo "export TOKEN='{$token}'\n";
echo "export WORKSPACEID='{$workspaceid}'\n";
echo "export CMID='{$cmid}'\n";
// The workspace's current revision. Emitted so the load run's REVISION stays in
// range for get_operations/get_revision_state — the latter rejects a revision
// above the current one, so a too-high REVISION would fail every call.
echo "export REVISION='{$oplog}'\n";
echo "# Map: {$nodes} nodes / {$relations} relations / {$containers} containers; op-log: {$oplog}\n";
echo "# Run: make jmeter  (or: make load-k6)\n";
