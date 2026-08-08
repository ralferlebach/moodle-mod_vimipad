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

use mod_vimipad\local\policy\request_policy;

/**
 * Tests the request-shape policy that keeps state-changing PHP handlers POST-only.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\policy\request_policy
 */
final class request_policy_test extends \advanced_testcase {
    /**
     * Only POST counts as a mutating request; GET and the other read-ish verbs
     * do not, however they are cased.
     *
     * @return void
     */
    public function test_only_post_is_a_mutating_request(): void {
        $this->assertTrue(request_policy::is_mutating_request('POST'));
        $this->assertTrue(request_policy::is_mutating_request('post'));
        $this->assertTrue(request_policy::is_mutating_request(' POST '));

        foreach (['GET', 'HEAD', 'get', 'PUT', 'DELETE', 'OPTIONS', ''] as $method) {
            $this->assertFalse(
                request_policy::is_mutating_request($method),
                "{$method} must not be treated as mutating"
            );
        }
    }

    /**
     * With no explicit method the policy reads the current request, and a
     * missing REQUEST_METHOD is treated as non-mutating (fail closed).
     *
     * @return void
     */
    public function test_falls_back_to_current_request_method(): void {
        $this->resetAfterTest();
        $original = $_SERVER['REQUEST_METHOD'] ?? null;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(request_policy::is_mutating_request());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse(request_policy::is_mutating_request());

        unset($_SERVER['REQUEST_METHOD']);
        $this->assertFalse(request_policy::is_mutating_request());

        if ($original !== null) {
            $_SERVER['REQUEST_METHOD'] = $original;
        }
    }

    /**
     * A GET request must not reach a grading mutation: handle_action returns
     * before touching state even when the action parameters are present.
     *
     * @return void
     */
    public function test_grading_handler_ignores_get_request(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('vimipad', $instance->id);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $now = time();
        $workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => (int) $student->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $workspaceid], '*', MUST_EXIST);
        $snapshotid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspaceid, 'revision' => 0,
            'snapshotjson' => '{"nodes":[],"relations":[],"containers":[]}',
            'submittedby' => (int) $student->id, 'status' => 1, 'timecreated' => $now,
        ]);
        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);

        // Simulate a GET that carries a valid-looking annotation mutation.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $_GET['addannotation'] = 1;
        $_GET['annotationbody'] = 'injected via GET';
        $_GET['sesskey'] = sesskey();

        $before = $DB->count_records('vimipad_annotation', ['snapshotid' => $snapshotid]);
        \mod_vimipad\local\output\grading_panel::handle_action(
            $cm,
            get_course($course->id),
            $context,
            $instance,
            $snapshot,
            $workspace
        );
        $after = $DB->count_records('vimipad_annotation', ['snapshotid' => $snapshotid]);

        $this->assertSame($before, $after, 'a GET request must not create an annotation');

        unset($_GET['addannotation'], $_GET['annotationbody'], $_GET['sesskey']);
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }
}
