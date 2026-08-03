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

use backup;
use backup_controller;
use backup_setting;
use core_privacy\local\request\approved_contextlist;
use mod_vimipad\local\service\assess_service;
use mod_vimipad\privacy\provider;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * The reference (model) solution is decoupled from learner data.
 *
 * Marking a snapshot as reference freezes its JSON on the activity record, so
 * the reference survives: a course backup without user info, privacy deletion
 * of the source learner, and workspace cleanup. Only the snapshot *pointer* is
 * cleared in those cases; scoring keeps working from the frozen copy.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\assess_service
 * @covers     \mod_vimipad\local\cleanup
 */
final class reference_decoupling_test extends \advanced_testcase {
    /**
     * Build a course with an activity whose reference is a frozen copy, plus a
     * learner workspace with the source snapshot.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: int} [course, instance, learner, snapshotid]
     */
    private function setup_reference(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $instance = $gen->create_module('vimipad', ['course' => $course->id, 'grade' => 100]);
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id, 'student');

        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $learner->id, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $json = json_encode([
            'profile' => 'conceptmap',
            'nodes' => [
                ['stableid' => 'node_000000000001', 'type' => 'concept', 'label' => 'Plant'],
                ['stableid' => 'node_000000000002', 'type' => 'concept', 'label' => 'Oxygen'],
            ],
            'relations' => [[
                'stableid' => 'rel_0000000000a1', 'sourceid' => 'node_000000000001',
                'targetid' => 'node_000000000002', 'type' => 'link', 'label' => 'produces', 'direction' => 1,
            ]],
        ]);
        $snapshotid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $wsid, 'revision' => 1, 'snapshotjson' => $json,
            'submittedby' => $learner->id, 'status' => 1, 'timecreated' => $now,
        ]);

        // Mark as reference the way the grading panel does: pointer + frozen copy.
        $DB->set_field('vimipad', 'referencesnapshotid', $snapshotid, ['id' => $instance->id]);
        $DB->set_field('vimipad', 'referencemapjson', $json, ['id' => $instance->id]);
        $instance = $DB->get_record('vimipad', ['id' => $instance->id], '*', MUST_EXIST);

        return [$course, $instance, $learner, $snapshotid];
    }

    /**
     * Scoring works from the frozen copy even when the source snapshot is gone.
     *
     * @return void
     */
    public function test_scoring_works_without_source_snapshot(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, , $refsnapshotid] = $this->setup_reference();

        // A second learner submission identical to the reference.
        $ws2 = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => 0, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $subid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $ws2, 'revision' => 1,
            'snapshotjson' => $instance->referencemapjson,
            'submittedby' => 0, 'status' => 1, 'timecreated' => time(),
        ]);

        // Remove the source snapshot entirely; only the frozen copy remains.
        $DB->delete_records('vimipad_snapshot', ['id' => $refsnapshotid]);
        $instance->referencesnapshotid = null;

        $result = (new assess_service())->score($instance, $subid);
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
    }

    /**
     * A course backup without user info keeps the reference active.
     *
     * @return void
     */
    public function test_reference_survives_backup_without_userinfo(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $instance] = $this->setup_reference();

        global $CFG;
        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(false);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_r',
            $course->category
        );
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value(false);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newinstance = $DB->get_record_select(
            'vimipad',
            'course = :c',
            ['c' => $newcourseid],
            '*',
            MUST_EXIST
        );
        // No learner workspaces came across, but the frozen reference did.
        $this->assertFalse($DB->record_exists('vimipad_workspace', ['vimipadid' => $newinstance->id]));
        $this->assertNotEmpty($newinstance->referencemapjson);
        $this->assertSame($instance->referencemapjson, $newinstance->referencemapjson);
        $this->assertTrue((new assess_service())->has_reference($newinstance));
    }

    /**
     * Privacy deletion of the source learner keeps the reference but clears the
     * pointer at the (now deleted) snapshot.
     *
     * @return void
     */
    public function test_reference_survives_privacy_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        [, $instance, $learner, $refsnapshotid] = $this->setup_reference();
        $cm = get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $contextlist = new approved_contextlist($learner, 'mod_vimipad', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $instance = $DB->get_record('vimipad', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertFalse($DB->record_exists('vimipad_snapshot', ['id' => $refsnapshotid]));
        $this->assertNull($instance->referencesnapshotid);
        $this->assertNotEmpty($instance->referencemapjson);
        $this->assertTrue((new assess_service())->has_reference($instance));
    }
}
