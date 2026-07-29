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
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_vimipad_activity_structure_step
 * @covers     \restore_vimipad_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Back up a course and restore it into a fresh course.
     *
     * @param \stdClass $course The source course.
     * @param bool $userdata Whether to include user data.
     * @return int The new course id.
     */
    private function backup_and_restore(\stdClass $course, bool $userdata): int {
        global $USER, $CFG;

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
        $bc->get_plan()->get_setting('users')->set_value($userdata);
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
        $rc->get_plan()->get_setting('users')->set_value($userdata);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * A map with nodes, a relation and a snapshot survives backup and restore.
     *
     * @return void
     */
    public function test_backup_restore_roundtrip(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module(
            'vimipad',
            ['course' => $course->id, 'name' => 'Source map', 'collaborationmode' => 0]
        );

        $now = time();
        $workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $USER->id, 'groupid' => null,
            'currentrevision' => 2, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspaceid, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept',
            'label' => 'Energy', 'contentformat' => FORMAT_HTML, 'createdby' => $USER->id,
            'modifiedby' => $USER->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $workspaceid, 'stableid' => 'node_bbbbbbbbbbbb', 'type' => 'concept',
            'label' => 'Motion', 'contentformat' => FORMAT_HTML, 'createdby' => $USER->id,
            'modifiedby' => $USER->id, 'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $workspaceid, 'stableid' => 'rel_cccccccccccc',
            'sourceid' => 'node_aaaaaaaaaaaa', 'targetid' => 'node_bbbbbbbbbbbb',
            'type' => 'isform', 'label' => 'is a form of', 'direction' => 1,
            'createdby' => $USER->id, 'modifiedby' => $USER->id,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $snapshotid = $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspaceid, 'revision' => 2, 'snapshotjson' => '{"nodes":2}',
            'submittedby' => $USER->id, 'status' => 1, 'timecreated' => $now,
        ]);
        $DB->set_field('vimipad_workspace', 'submittedsnapshotid', $snapshotid, ['id' => $workspaceid]);
        $DB->insert_record('vimipad_annotation', (object) [
            'snapshotid' => $snapshotid, 'targettype' => 'node', 'targetstableid' => 'node_aaaaaaaaaaaa',
            'commenttext' => 'Good starting point', 'commentformat' => FORMAT_HTML,
            'userid' => $USER->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        // Activity configuration that must survive a restore.
        $DB->update_record('vimipad', (object) [
            'id' => $instance->id, 'grade' => 55,
            'completionsubmit' => 1, 'completionminnodes' => 3, 'completiongraded' => 1,
            'referencesnapshotid' => $snapshotid,
        ]);
        // A grade (referencing the snapshot) and a journal entry.
        $DB->insert_record('vimipad_grade', (object) [
            'vimipadid' => $instance->id, 'userid' => $USER->id, 'grade' => 42.0,
            'feedback' => 'Well structured', 'feedbackformat' => FORMAT_PLAIN,
            'snapshotid' => $snapshotid, 'grader' => $USER->id,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_journalentry', (object) [
            'workspaceid' => $workspaceid, 'userid' => $USER->id, 'revisionref' => null,
            'entrytext' => 'My reflection', 'entryformat' => FORMAT_PLAIN, 'visibility' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        $newcourseid = $this->backup_and_restore($course, true);

        $newinstances = $DB->get_records('vimipad', ['course' => $newcourseid]);
        $this->assertCount(1, $newinstances);
        $newinstance = reset($newinstances);

        $newworkspaces = $DB->get_records('vimipad_workspace', ['vimipadid' => $newinstance->id]);
        $this->assertCount(1, $newworkspaces);
        $newworkspace = reset($newworkspaces);
        $this->assertSame(2, (int) $newworkspace->currentrevision);

        $this->assertSame(2, $DB->count_records('vimipad_node', ['workspaceid' => $newworkspace->id]));
        $this->assertSame(1, $DB->count_records('vimipad_relation', ['workspaceid' => $newworkspace->id]));

        $rel = $DB->get_record('vimipad_relation', ['workspaceid' => $newworkspace->id]);
        $this->assertSame('node_aaaaaaaaaaaa', $rel->sourceid);
        $this->assertSame('node_bbbbbbbbbbbb', $rel->targetid);

        $newsnapshots = $DB->get_records('vimipad_snapshot', ['workspaceid' => $newworkspace->id]);
        $this->assertCount(1, $newsnapshots);
        $newsnapshot = reset($newsnapshots);
        $this->assertEquals($newsnapshot->id, $newworkspace->submittedsnapshotid);

        // The reference snapshot pointer is remapped to the restored snapshot.
        $this->assertEquals($newsnapshot->id, $newinstance->referencesnapshotid);

        $this->assertSame(1, $DB->count_records('vimipad_annotation', ['snapshotid' => $newsnapshot->id]));

        // Activity configuration survived.
        $this->assertSame(55, (int) $newinstance->grade);
        $this->assertSame(1, (int) $newinstance->completionsubmit);
        $this->assertSame(3, (int) $newinstance->completionminnodes);
        $this->assertSame(1, (int) $newinstance->completiongraded);

        // The grade was restored and its snapshot reference remapped.
        $newgrades = $DB->get_records('vimipad_grade', ['vimipadid' => $newinstance->id]);
        $this->assertCount(1, $newgrades);
        $newgrade = reset($newgrades);
        $this->assertEquals(42.0, (float) $newgrade->grade);
        $this->assertEquals($newsnapshot->id, (int) $newgrade->snapshotid);

        // The journal entry was restored.
        $this->assertSame(
            1,
            $DB->count_records('vimipad_journalentry', ['workspaceid' => $newworkspace->id])
        );
    }

    /**
     * Backup without user info restores the activity but no user content.
     *
     * @return void
     */
    public function test_backup_without_userinfo(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);

        $now = time();
        $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => $USER->id, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $newcourseid = $this->backup_and_restore($course, false);

        $newinstances = $DB->get_records('vimipad', ['course' => $newcourseid]);
        $this->assertCount(1, $newinstances);
        $newinstance = reset($newinstances);

        $this->assertSame(0, $DB->count_records('vimipad_workspace', ['vimipadid' => $newinstance->id]));
    }
}
