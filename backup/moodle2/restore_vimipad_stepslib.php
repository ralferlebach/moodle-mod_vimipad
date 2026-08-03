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
 * Restore structure step for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure of the ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_vimipad_activity_structure_step extends restore_activity_structure_step {
    /**
     * Maps new workspace id to the old submitted snapshot id for later remap.
     *
     * @var array<int,int>
     */
    private $pendingsubmitted = [];

    /**
     * Maps new grade record id to the old snapshot id for later remap.
     *
     * @var array<int,int>
     */
    private $pendinggradesnapshot = [];

    /**
     * Maps new vimipad instance id to the old reference snapshot id for later remap.
     *
     * @var array<int,int>
     */
    private $pendingreference = [];

    /**
     * Restored advanced-grading links: new grading instance id => new snapshot id,
     * used to realign the core grading instance's itemid after restore.
     *
     * @var array<int,int>
     */
    private $pendinggradinginstances = [];

    /**
     * Define the structure to be restored.
     *
     * @return array
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $paths = [];
        $paths[] = new restore_path_element('vimipad', '/activity/vimipad');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'vimipad_grade',
                '/activity/vimipad/grades/grade'
            );
            $paths[] = new restore_path_element(
                'vimipad_workspace',
                '/activity/vimipad/workspaces/workspace'
            );
            $paths[] = new restore_path_element(
                'vimipad_node',
                '/activity/vimipad/workspaces/workspace/nodes/node'
            );
            $paths[] = new restore_path_element(
                'vimipad_relation',
                '/activity/vimipad/workspaces/workspace/relations/relation'
            );
            $paths[] = new restore_path_element(
                'vimipad_container',
                '/activity/vimipad/workspaces/workspace/containers/container'
            );
            $paths[] = new restore_path_element(
                'vimipad_membership',
                '/activity/vimipad/workspaces/workspace/containers/container/memberships/membership'
            );
            $paths[] = new restore_path_element(
                'vimipad_layout',
                '/activity/vimipad/workspaces/workspace/layouts/layout'
            );
            $paths[] = new restore_path_element(
                'vimipad_operation',
                '/activity/vimipad/workspaces/workspace/operations/operation'
            );
            $paths[] = new restore_path_element(
                'vimipad_snapshot',
                '/activity/vimipad/workspaces/workspace/snapshots/snapshot'
            );
            $paths[] = new restore_path_element(
                'vimipad_annotation',
                '/activity/vimipad/workspaces/workspace/snapshots/snapshot/annotations/annotation'
            );
            $paths[] = new restore_path_element(
                'vimipad_aifeedback',
                '/activity/vimipad/workspaces/workspace/snapshots/snapshot/aifeedbacks/aifeedback'
            );
            $paths[] = new restore_path_element(
                'vimipad_peerreview',
                '/activity/vimipad/workspaces/workspace/snapshots/snapshot/peerreviews/peerreview'
            );
            $paths[] = new restore_path_element(
                'vimipad_gradeinstance',
                '/activity/vimipad/workspaces/workspace/snapshots/snapshot/gradeinstances/gradeinstance'
            );
            $paths[] = new restore_path_element(
                'vimipad_journalentry',
                '/activity/vimipad/workspaces/workspace/journalentries/journalentry'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity instance.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad($data): void {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        // The reference snapshot id can only be remapped once snapshots exist.
        $oldreference = !empty($data->referencesnapshotid) ? (int) $data->referencesnapshotid : 0;
        $data->referencesnapshotid = null;

        $newitemid = $DB->insert_record('vimipad', $data);
        $this->apply_activity_instance($newitemid);

        if ($oldreference) {
            $this->pendingreference[$newitemid] = $oldreference;
        }
    }

    /**
     * Restore a grade record.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_grade($data): void {
        global $DB;

        $data = (object) $data;
        $data->vimipadid = $this->get_new_parentid('vimipad');
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;
        $data->grader = $this->get_mappingid('user', $data->grader) ?: null;
        $oldsnapshotid = !empty($data->snapshotid) ? (int) $data->snapshotid : 0;
        // The snapshotid is remapped in after_execute() once snapshots exist.
        $data->snapshotid = null;

        $newid = $DB->insert_record('vimipad_grade', $data);
        if ($oldsnapshotid) {
            $this->pendinggradesnapshot[$newid] = $oldsnapshotid;
        }
    }

    /**
     * Restore a workspace. The submitted snapshot reference is a forward
     * reference and is resolved in after_execute().
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_workspace($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $oldsubmitted = !empty($data->submittedsnapshotid) ? (int) $data->submittedsnapshotid : 0;

        $data->vimipadid = $this->get_new_parentid('vimipad');
        $data->submittedsnapshotid = null;
        $data->userid = $this->get_mappingid('user', $data->userid) ?: null;
        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid) ?: null;
        } else {
            $data->groupid = null;
        }

        $newid = $DB->insert_record('vimipad_workspace', $data);
        $this->set_mapping('vimipad_workspace', $oldid, $newid);

        if ($oldsubmitted) {
            $this->pendingsubmitted[$newid] = $oldsubmitted;
        }
    }

    /**
     * Restore a node.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_node($data): void {
        global $DB;

        $data = (object) $data;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->createdby = $this->get_mappingid('user', $data->createdby) ?: null;
        $data->modifiedby = $this->get_mappingid('user', $data->modifiedby) ?: null;

        $DB->insert_record('vimipad_node', $data);
    }

    /**
     * Restore a relation. Endpoints reference stable ids and need no remap.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_relation($data): void {
        global $DB;

        $data = (object) $data;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->createdby = $this->get_mappingid('user', $data->createdby) ?: null;
        $data->modifiedby = $this->get_mappingid('user', $data->modifiedby) ?: null;

        $DB->insert_record('vimipad_relation', $data);
    }

    /**
     * Restore a container.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_container($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');

        $newid = $DB->insert_record('vimipad_container', $data);
        $this->set_mapping('vimipad_container', $oldid, $newid);
    }

    /**
     * Restore a container membership.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_membership($data): void {
        global $DB;

        $data = (object) $data;
        $data->containerid = $this->get_new_parentid('vimipad_container');

        $DB->insert_record('vimipad_membership', $data);
    }

    /**
     * Restore a layout.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_layout($data): void {
        global $DB;

        $data = (object) $data;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->modifiedby = $this->get_mappingid('user', $data->modifiedby) ?: null;

        $DB->insert_record('vimipad_layout', $data);
    }

    /**
     * Restore an operation log entry.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_operation($data): void {
        global $DB;

        $data = (object) $data;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;

        $DB->insert_record('vimipad_operation', $data);
    }

    /**
     * Restore a snapshot.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_snapshot($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->submittedby = $this->get_mappingid('user', $data->submittedby) ?: null;

        // Remap the frozen grade-recipient cohort onto the restored user ids,
        // dropping members that were not included in the backup.
        if (!empty($data->cohortjson)) {
            $cohort = json_decode($data->cohortjson, true);
            if (is_array($cohort)) {
                $mapped = [];
                foreach ($cohort as $olduserid) {
                    $newuserid = $this->get_mappingid('user', (int) $olduserid);
                    if ($newuserid) {
                        $mapped[] = (int) $newuserid;
                    }
                }
                $data->cohortjson = json_encode($mapped);
            }
        }

        $newid = $DB->insert_record('vimipad_snapshot', $data);
        $this->set_mapping('vimipad_snapshot', $oldid, $newid);
    }

    /**
     * Restore a snapshot annotation.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_annotation($data): void {
        global $DB;

        $data = (object) $data;
        $data->snapshotid = $this->get_new_parentid('vimipad_snapshot');
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;

        $DB->insert_record('vimipad_annotation', $data);
    }

    /**
     * Restore an AI feedback record.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_aifeedback($data): void {
        global $DB;

        $data = (object) $data;
        $data->snapshotid = $this->get_new_parentid('vimipad_snapshot');
        $data->graderid = $this->get_mappingid('user', $data->graderid) ?: 0;

        $DB->insert_record('vimipad_aifeedback', $data);
    }

    /**
     * Restore a peer review record.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_peerreview($data): void {
        global $DB;

        $data = (object) $data;
        $data->snapshotid = $this->get_new_parentid('vimipad_snapshot');
        $data->reviewerid = $this->get_mappingid('user', $data->reviewerid) ?: 0;

        $DB->insert_record('vimipad_peerreview', $data);
    }

    /**
     * Restore an advanced-grading link record.
     *
     * The grading definitions and instances are restored by the grading
     * structure step (which runs first), so the grading_instance mapping is
     * available here. The core instance's itemid is realigned to the new
     * snapshot id in after_execute().
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_gradeinstance($data): void {
        global $DB;

        $data = (object) $data;
        $data->snapshotid = $this->get_new_parentid('vimipad_snapshot');
        $data->raterid = $this->get_mappingid('user', $data->raterid) ?: 0;
        $data->instanceid = (int) ($this->get_mappingid('grading_instance', $data->instanceid) ?: 0);

        // Without the restored grading instance the link would dangle; skip it.
        if (empty($data->instanceid)) {
            return;
        }

        $DB->insert_record('vimipad_gradeinstance', $data);
        $this->pendinggradinginstances[(int) $data->instanceid] = (int) $data->snapshotid;
    }

    /**
     * Restore a journal entry.
     *
     * @param array $data Parsed data.
     * @return void
     */
    protected function process_vimipad_journalentry($data): void {
        global $DB;

        $data = (object) $data;
        $data->workspaceid = $this->get_new_parentid('vimipad_workspace');
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;

        $DB->insert_record('vimipad_journalentry', $data);
    }

    /**
     * Post-execution: resolve forward references and restore intro files.
     *
     * @return void
     */
    protected function after_execute(): void {
        global $DB;

        $this->add_related_files('mod_vimipad', 'intro', null);

        // Resolve workspace.submittedsnapshotid now that snapshots are mapped.
        foreach ($this->pendingsubmitted as $newworkspaceid => $oldsnapshotid) {
            $newsnapshotid = $this->get_mappingid('vimipad_snapshot', $oldsnapshotid);
            if ($newsnapshotid) {
                $DB->set_field(
                    'vimipad_workspace',
                    'submittedsnapshotid',
                    $newsnapshotid,
                    ['id' => $newworkspaceid]
                );
            }
        }

        // Resolve grade.snapshotid now that snapshots are mapped.
        foreach ($this->pendinggradesnapshot as $newgradeid => $oldsnapshotid) {
            $newsnapshotid = $this->get_mappingid('vimipad_snapshot', $oldsnapshotid);
            if ($newsnapshotid) {
                $DB->set_field('vimipad_grade', 'snapshotid', $newsnapshotid, ['id' => $newgradeid]);
            }
        }

        // Resolve the activity's reference snapshot now that snapshots are mapped.
        foreach ($this->pendingreference as $newvimipadid => $oldsnapshotid) {
            $newsnapshotid = $this->get_mappingid('vimipad_snapshot', $oldsnapshotid);
            if ($newsnapshotid) {
                $DB->set_field('vimipad', 'referencesnapshotid', $newsnapshotid, ['id' => $newvimipadid]);
            }
        }

        // Realign each restored grading instance's itemid to its new snapshot
        // (advanced grading uses the snapshot id as the itemid).
        foreach ($this->pendinggradinginstances as $instanceid => $snapshotid) {
            if ($DB->record_exists('grading_instances', ['id' => $instanceid])) {
                $DB->set_field('grading_instances', 'itemid', $snapshotid, ['id' => $instanceid]);
            }
        }
    }
}
