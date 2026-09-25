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
 * CLI seed for the mod_vimipad Playwright collaboration tests.
 *
 * Creates a course with a course-mode (shared) ViMi Pad activity and three
 * users (two collaborators and a teacher), then prints shell exports the
 * Playwright run consumes. Intended for a disposable CI or dev site.
 *
 * Usage: php mod/vimipad/tests/playwright/seed.php
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

/**
 * Create (or fetch) a user with a known password and enrol them in a course.
 *
 * @param string $username The username.
 * @param string $firstname The first name.
 * @param string $lastname The last name.
 * @param string $password The password to set.
 * @param int $courseid The course to enrol into.
 * @param string $rolearchetype The role archetype (student/editingteacher).
 * @return stdClass The user record.
 */
function vimipad_seed_user(
    string $username,
    string $firstname,
    string $lastname,
    string $password,
    int $courseid,
    string $rolearchetype
): stdClass {
    global $DB, $CFG;

    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        $user = (object) [
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username . '@example.invalid',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'manual',
        ];
        $user->id = user_create_user($user, false, false);
        $user->password = $password;
        update_internal_user_password($DB->get_record('user', ['id' => $user->id]), $password);
    }

    $context = context_course::instance($courseid);
    $role = $DB->get_record('role', ['archetype' => $rolearchetype], '*', MUST_EXIST);
    $manual = enrol_get_plugin('manual');
    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    $manual->enrol_user($instance, $user->id, $role->id);

    return $user;
}

// Create the course.
$course = create_course((object) [
    'fullname' => 'ViMi Pad collaboration ' . time(),
    'shortname' => 'vimicollab' . time(),
    'category' => 1,
    'format' => 'topics',
    'numsections' => 1,
]);

// Users: two collaborators and a teacher.
$usera = vimipad_seed_user('vimi_a', 'Ada', 'Author', 'Vimi!pad_A1', $course->id, 'student');
$userb = vimipad_seed_user('vimi_b', 'Ben', 'Builder', 'Vimi!pad_B1', $course->id, 'student');
$teacher = vimipad_seed_user('vimi_t', 'Tay', 'Teacher', 'Vimi!pad_T1', $course->id, 'editingteacher');

// Course-mode ViMi Pad so both collaborators share one workspace.
$module = $DB->get_record('modules', ['name' => 'vimipad'], '*', MUST_EXIST);
$moduleinfo = (object) [
    'modulename' => 'vimipad',
    'module' => $module->id,
    'course' => $course->id,
    'section' => 1,
    'visible' => 1,
    'name' => 'Shared map',
    'intro' => 'Collaboration fixture',
    'introformat' => FORMAT_HTML,
    'cmidnumber' => '',
    'defaultprofile' => 'conceptmap',
    'collaborationmode' => 2,
    'gradingmode' => 0,
    'aienabled' => 0,
];
$created = add_moduleinfo($moduleinfo, $course);
$activitypath = '/mod/vimipad/view.php?id=' . $created->coursemodule;

// An individual-mode activity, so a single student owns their own workspace
// (the "build and save" story) without another client touching it.
$individualinfo = clone $moduleinfo;
$individualinfo->name = 'My map';
$individualinfo->intro = 'Individual fixture';
$individualinfo->collaborationmode = 0;
$individualcreated = add_moduleinfo($individualinfo, $course);
$individualpath = '/mod/vimipad/view.php?id=' . $individualcreated->coursemodule;

// The site administrator, for the admin stories (AI gate, plugin overview).
$admin = get_admin();
$adminuser = $admin ? $admin->username : 'admin';

/**
 * Create a ViMi Pad activity holding a ready-made map.
 *
 * The representation stories check how a finished map is laid out and drawn, so
 * the fixture is built directly rather than by driving the editor: that keeps
 * the story about the representation instead of about node creation, which the
 * other stories already cover.
 *
 * @param stdClass $course The course.
 * @param stdClass $module The vimipad module record.
 * @param string $name The activity name.
 * @param string $profile The diagram profile.
 * @param array $nodes Each [stableid, label, metadata array].
 * @param array $relations Each [stableid, sourceid, targetid, type, label].
 * @return string The activity view path.
 */
function vimipad_seed_map(
    stdClass $course,
    stdClass $module,
    string $name,
    string $profile,
    array $nodes,
    array $relations
): string {
    global $DB;

    $info = (object) [
        'modulename' => 'vimipad',
        'module' => $module->id,
        'course' => $course->id,
        'section' => 1,
        'visible' => 1,
        'name' => $name,
        'intro' => $profile . ' fixture',
        'introformat' => FORMAT_HTML,
        'cmidnumber' => '',
        'defaultprofile' => $profile,
        'collaborationmode' => 2,
        'gradingmode' => 0,
        'aienabled' => 0,
    ];
    $created = add_moduleinfo($info, $course);

    $now = time();
    $workspaceid = $DB->insert_record('vimipad_workspace', (object) [
        'vimipadid' => $created->instance,
        'userid' => null,
        'groupid' => 0,
        'currentrevision' => 1,
        'locked' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    foreach ($nodes as [$stableid, $label, $metadata]) {
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspaceid,
            'stableid' => $stableid,
            'type' => 'concept',
            'label' => $label,
            'content' => '',
            'contentformat' => FORMAT_HTML,
            'metadatajson' => json_encode($metadata),
            'createdby' => 2,
            'modifiedby' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    foreach ($relations as [$stableid, $source, $target, $type, $label]) {
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $workspaceid,
            'stableid' => $stableid,
            'sourceid' => $source,
            'targetid' => $target,
            'type' => $type,
            'label' => $label,
            'direction' => 1,
            'metadatajson' => json_encode([]),
            'createdby' => 2,
            'modifiedby' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    return '/mod/vimipad/view.php?id=' . $created->coursemodule;
}

/**
 * Build a node id of the length the map value policy expects.
 *
 * @param string $seed A short mnemonic.
 * @return string The padded stable id.
 */
function vimipad_seed_id(string $seed): string {
    return 'node_' . substr(str_pad($seed, 12, 'a'), 0, 12);
}

// A flowchart using every process symbol (issue #14).
$flownodes = [
    [vimipad_seed_id('start'), 'Start', ['shape' => 'terminator']],
    [vimipad_seed_id('check'), 'Stock available?', ['shape' => 'diamond']],
    [vimipad_seed_id('pick'), 'Pick items', ['shape' => 'rect']],
    [vimipad_seed_id('note'), 'Print note', ['shape' => 'parallelogram']],
    [vimipad_seed_id('done'), 'End', ['shape' => 'terminator']],
];
$flowrels = [
    ['rel_flowaaaaaaaa', vimipad_seed_id('start'), vimipad_seed_id('check'), 'sequence', ''],
    ['rel_flowbbbbbbbb', vimipad_seed_id('check'), vimipad_seed_id('pick'), 'yes', 'yes'],
    ['rel_flowcccccccc', vimipad_seed_id('pick'), vimipad_seed_id('note'), 'sequence', ''],
    ['rel_flowdddddddd', vimipad_seed_id('note'), vimipad_seed_id('done'), 'sequence', ''],
];
$flowpath = vimipad_seed_map($course, $module, 'Order process', 'flow', $flownodes, $flowrels);

// A fishbone with four categories, causes and a third-level sub-cause (#15).
$fishnodes = [
    [vimipad_seed_id('effect'), 'Late delivery', []],
    [vimipad_seed_id('method'), 'Method', []],
    [vimipad_seed_id('machine'), 'Machine', []],
    [vimipad_seed_id('material'), 'Material', []],
    [vimipad_seed_id('people'), 'People', []],
    [vimipad_seed_id('cause1'), 'Unclear steps', []],
    [vimipad_seed_id('cause2'), 'Old press', []],
    [vimipad_seed_id('sub1'), 'No checklist', []],
];
$fishrels = [
    ['rel_fishaaaaaaaa', vimipad_seed_id('method'), vimipad_seed_id('effect'), '', ''],
    ['rel_fishbbbbbbbb', vimipad_seed_id('machine'), vimipad_seed_id('effect'), '', ''],
    ['rel_fishcccccccc', vimipad_seed_id('material'), vimipad_seed_id('effect'), '', ''],
    ['rel_fishdddddddd', vimipad_seed_id('people'), vimipad_seed_id('effect'), '', ''],
    ['rel_fisheeeeeeee', vimipad_seed_id('cause1'), vimipad_seed_id('method'), '', ''],
    ['rel_fishffffffff', vimipad_seed_id('cause2'), vimipad_seed_id('machine'), '', ''],
    ['rel_fishgggggggg', vimipad_seed_id('sub1'), vimipad_seed_id('cause1'), '', ''],
];
$fishpath = vimipad_seed_map($course, $module, 'Delivery causes', 'fishbone', $fishnodes, $fishrels);

// A stock-and-flow model with every system role and both relation types (#16).
$sfnodes = [
    [vimipad_seed_id('supply'), 'Raw material supply', ['systemtype' => 'source', 'shape' => 'cloud']],
    [vimipad_seed_id('prod'), 'Production rate', ['systemtype' => 'valve', 'shape' => 'valve']],
    [vimipad_seed_id('stock1'), 'Finished goods', ['systemtype' => 'stock', 'shape' => 'stock']],
    [vimipad_seed_id('ship'), 'Shipment rate', ['systemtype' => 'valve', 'shape' => 'valve']],
    [vimipad_seed_id('cust'), 'Customers', ['systemtype' => 'sink', 'shape' => 'cloud']],
    [vimipad_seed_id('delay1'), 'Order delay', ['systemtype' => 'delay', 'shape' => 'delay']],
    [vimipad_seed_id('demand'), 'Customer demand', ['systemtype' => 'auxiliary', 'shape' => 'ellipse']],
    [vimipad_seed_id('cap'), 'Production capacity', ['systemtype' => 'parameter', 'shape' => 'parameter']],
];
$sfrels = [
    ['rel_sfaaaaaaaaaa', vimipad_seed_id('supply'), vimipad_seed_id('prod'), 'flow', ''],
    ['rel_sfbbbbbbbbbb', vimipad_seed_id('prod'), vimipad_seed_id('stock1'), 'flow', ''],
    ['rel_sfcccccccccc', vimipad_seed_id('stock1'), vimipad_seed_id('ship'), 'flow', ''],
    ['rel_sfdddddddddd', vimipad_seed_id('ship'), vimipad_seed_id('cust'), 'flow', ''],
    ['rel_sfeeeeeeeeee', vimipad_seed_id('demand'), vimipad_seed_id('ship'), 'influence', 'increases'],
    ['rel_sfffffffffff', vimipad_seed_id('cap'), vimipad_seed_id('prod'), 'influence', 'limits'],
    ['rel_sfgggggggggg', vimipad_seed_id('delay1'), vimipad_seed_id('prod'), 'influence', 'delays'],
];
$stockflowpath = vimipad_seed_map($course, $module, 'Supply model', 'stockflow', $sfnodes, $sfrels);

// Print shell exports for the Playwright run. The base URL is derived from the
// site's own wwwroot, so `eval "$(php seed.php)"` sets everything the run needs
// and works for any install location (root or subdirectory) without a manual
// VIMIPAD_BASE_URL. The specs still allow overriding it via the environment.
echo "export VIMIPAD_BASE_URL='{$CFG->wwwroot}'\n";
echo "export VIMIPAD_FLOW_PATH='{$flowpath}'\n";
echo "export VIMIPAD_FISHBONE_PATH='{$fishpath}'\n";
echo "export VIMIPAD_STOCKFLOW_PATH='{$stockflowpath}'\n";
echo "export VIMIPAD_ACTIVITY_PATH='{$activitypath}'\n";
echo "export VIMIPAD_USER_A='{$usera->username}'\n";
echo "export VIMIPAD_PASS_A='Vimi!pad_A1'\n";
echo "export VIMIPAD_NAME_A='Ada Author'\n";
echo "export VIMIPAD_USER_B='{$userb->username}'\n";
echo "export VIMIPAD_PASS_B='Vimi!pad_B1'\n";
echo "export VIMIPAD_NAME_B='Ben Builder'\n";
echo "export VIMIPAD_TEACHER='{$teacher->username}'\n";
echo "export VIMIPAD_TEACHER_PASS='Vimi!pad_T1'\n";
echo "export VIMIPAD_TEACHER_NAME='Tay Teacher'\n";
echo "export VIMIPAD_INDIVIDUAL_PATH='{$individualpath}'\n";
echo "export VIMIPAD_COURSE_ID='{$course->id}'\n";
echo "export VIMIPAD_COURSE_NAME='{$course->fullname}'\n";
echo "export VIMIPAD_ADMIN='{$adminuser}'\n";
// The admin password is whatever the site was installed with; the CI workflow
// sets it explicitly and exports VIMIPAD_ADMIN_PASS itself. Left blank here so a
// local run fails loudly rather than guessing.
echo "export VIMIPAD_ADMIN_PASS='" . getenv('VIMIPAD_ADMIN_PASS') . "'\n";
