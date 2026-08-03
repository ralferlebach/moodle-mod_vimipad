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

use mod_vimipad\local\output\grading_panel;
use mod_vimipad\local\service\assess_service;

/**
 * Authorisation contract tests for the AI scorer and the grading handler.
 *
 * The AI scorer must be gated at the service boundary: mod/vimipad:useai,
 * the site/activity AI switches and the user AI policy are all enforced in
 * assess_service::score_ai(), independent of any UI filtering. The grading
 * POST handler enforces mod/vimipad:grade itself.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\assess_service
 * @covers     \mod_vimipad\local\output\grading_panel
 */
final class ai_authorization_test extends \advanced_testcase {
    /**
     * Create a course, an AI-enabled activity, a workspace and a snapshot.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \context_module, 3: int, 4: \stdClass}
     *     [course, instance, context, snapshotid, cm]
     */
    private function setup_activity(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', [
            'course' => $course->id,
            'grade' => 100,
            'aienabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id,
            'userid' => 0,
            'groupid' => null,
            'revision' => 1,
            'locked' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $json = json_encode([
            'profile' => 'conceptmap',
            'nodes' => [['stableid' => 'node_000000000001', 'type' => 'concept', 'label' => 'Energy']],
            'relations' => [],
        ]);
        $snapshotid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $wsid,
            'revision' => 1,
            'snapshotjson' => $json,
            'submittedby' => 0,
            'status' => 0,
            'timecreated' => time(),
        ]);

        return [$course, $instance, $context, $snapshotid, $cm];
    }

    /**
     * Enrol a fresh user with the given archetype role and return their id.
     *
     * @param \stdClass $course The course.
     * @param string $archetype The role shortname (student, teacher, editingteacher).
     * @return int The user id.
     */
    private function enrol(\stdClass $course, string $archetype): int {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $archetype);
        return (int) $user->id;
    }

    /**
     * A non-editing teacher (grade, but no useai) must not run the AI scorer.
     *
     * @return void
     */
    public function test_score_ai_requires_useai_capability(): void {
        $this->resetAfterTest();
        [$course, $instance, $context, $snapshotid] = $this->setup_activity();
        $teacherid = $this->enrol($course, 'teacher');
        $this->setUser($teacherid);

        $this->expectException(\required_capability_exception::class);
        (new assess_service())->score_ai($context, $instance, $snapshotid, $teacherid);
    }

    /**
     * With useai granted but AI disabled on the activity, score_ai must refuse.
     *
     * @return void
     */
    public function test_score_ai_requires_activity_ai_enabled(): void {
        $this->resetAfterTest();
        [$course, $instance, $context, $snapshotid] = $this->setup_activity();
        $instance->aienabled = 0;
        $editingteacherid = $this->enrol($course, 'editingteacher');
        $this->setUser($editingteacherid);

        try {
            (new assess_service())->score_ai($context, $instance, $snapshotid, $editingteacherid);
            $this->fail('Expected moodle_exception (error:aiunavailable) was not thrown.');
        } catch (\required_capability_exception $e) {
            $this->fail('The capability gate passed; the availability gate should have fired instead.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:aiunavailable', $e->errorcode);
        }
    }

    /**
     * With useai granted but AI disabled site-wide for the plugin, score_ai must refuse.
     *
     * @return void
     */
    public function test_score_ai_requires_site_ai_enabled(): void {
        $this->resetAfterTest();
        [$course, $instance, $context, $snapshotid] = $this->setup_activity();
        set_config('enableai', '0', 'mod_vimipad');
        $editingteacherid = $this->enrol($course, 'editingteacher');
        $this->setUser($editingteacherid);

        try {
            (new assess_service())->score_ai($context, $instance, $snapshotid, $editingteacherid);
            $this->fail('Expected moodle_exception (error:aiunavailable) was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:aiunavailable', $e->errorcode);
        }
    }

    /**
     * With everything enabled but the AI policy not yet accepted, score_ai must refuse.
     *
     * A fresh test user has not accepted the core AI policy, so the policy gate
     * is the next one to fire once capability and availability pass.
     *
     * @return void
     */
    public function test_score_ai_requires_policy_acceptance(): void {
        $this->resetAfterTest();
        if (!class_exists('\core_ai\manager') || !method_exists('\core_ai\manager', 'get_user_policy_status')) {
            $this->markTestSkipped('core_ai policy API not present in this Moodle version.');
        }
        [$course, $instance, $context, $snapshotid] = $this->setup_activity();
        $editingteacherid = $this->enrol($course, 'editingteacher');
        $this->setUser($editingteacherid);

        try {
            (new assess_service())->score_ai($context, $instance, $snapshotid, $editingteacherid);
            $this->fail('Expected moodle_exception (ai:policyrequired) was not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertSame('ai:policyrequired', $e->errorcode);
        }
    }

    /**
     * When all AI gates pass, score_ai proceeds past them (and returns null
     * here because the llm scorer is not among the active scorers).
     *
     * @return void
     */
    public function test_score_ai_passes_gates_when_authorised(): void {
        $this->resetAfterTest();
        if (!class_exists('\core_ai\manager') || !method_exists('\core_ai\manager', 'user_policy_accepted')) {
            $this->markTestSkipped('core_ai policy acceptance API not present in this Moodle version.');
        }
        [$course, $instance, $context, $snapshotid] = $this->setup_activity();
        // Exclude the llm scorer so the call returns null right after the gates
        // instead of contacting an AI provider.
        $instance->activescorers = 'reference';
        $editingteacherid = $this->enrol($course, 'editingteacher');
        $this->setUser($editingteacherid);
        \core_ai\manager::user_policy_accepted($editingteacherid, $context->id);

        $result = (new assess_service())->score_ai($context, $instance, $snapshotid, $editingteacherid);
        $this->assertNull($result);
    }

    /**
     * The grading POST handler enforces mod/vimipad:grade itself.
     *
     * @return void
     */
    public function test_handle_action_requires_grade_capability(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $instance, $context, $snapshotid, $cm] = $this->setup_activity();
        $studentid = $this->enrol($course, 'student');
        $this->setUser($studentid);

        $snapshot = $DB->get_record('vimipad_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $snapshot->workspaceid], '*', MUST_EXIST);

        $this->expectException(\required_capability_exception::class);
        grading_panel::handle_action($cm, $course, $context, $instance, $snapshot, $workspace);
    }
}
