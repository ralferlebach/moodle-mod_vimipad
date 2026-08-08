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

// Print shell exports for the Playwright run. The base URL is derived from the
// site's own wwwroot, so `eval "$(php seed.php)"` sets everything the run needs
// and works for any install location (root or subdirectory) without a manual
// VIMIPAD_BASE_URL. The specs still allow overriding it via the environment.
echo "export VIMIPAD_BASE_URL='{$CFG->wwwroot}'\n";
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
