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
 * Backup structure step for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure of the ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_vimipad_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the structure to be backed up.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $vimipad = new backup_nested_element('vimipad', ['id'], [
            'name', 'intro', 'introformat', 'defaultprofile',
            'collaborationmode', 'gradingmode', 'aienabled', 'channelurl',
            'timecreated', 'timemodified',
        ]);

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'userid', 'grade', 'feedback', 'feedbackformat', 'snapshotid',
            'grader', 'timecreated', 'timemodified',
        ]);

        $workspaces = new backup_nested_element('workspaces');
        $workspace = new backup_nested_element('workspace', ['id'], [
            'userid', 'groupid', 'name', 'currentrevision',
            'submittedsnapshotid', 'locked', 'timecreated', 'timemodified',
        ]);

        $nodes = new backup_nested_element('nodes');
        $node = new backup_nested_element('node', ['id'], [
            'stableid', 'type', 'label', 'content', 'contentformat',
            'metadatajson', 'createdby', 'modifiedby',
            'timecreated', 'timemodified', 'deleted',
        ]);

        $relations = new backup_nested_element('relations');
        $relation = new backup_nested_element('relation', ['id'], [
            'stableid', 'sourceid', 'targetid', 'type', 'label', 'direction',
            'metadatajson', 'createdby', 'modifiedby',
            'timecreated', 'timemodified', 'deleted',
        ]);

        $containers = new backup_nested_element('containers');
        $container = new backup_nested_element('container', ['id'], [
            'stableid', 'type', 'label', 'geometryjson', 'metadatajson', 'deleted',
        ]);

        $memberships = new backup_nested_element('memberships');
        $membership = new backup_nested_element('membership', ['id'], [
            'itemtype', 'itemstableid', 'role', 'sortorder',
        ]);

        $layouts = new backup_nested_element('layouts');
        $layout = new backup_nested_element('layout', ['id'], [
            'profile', 'viewportjson', 'layoutjson', 'modifiedby', 'timemodified',
        ]);

        $operations = new backup_nested_element('operations');
        $operation = new backup_nested_element('operation', ['id'], [
            'revision', 'operationtype', 'payloadjson', 'userid', 'timecreated',
        ]);

        $snapshots = new backup_nested_element('snapshots');
        $snapshot = new backup_nested_element('snapshot', ['id'], [
            'revision', 'snapshotjson', 'submittedby', 'status', 'timecreated',
        ]);

        $annotations = new backup_nested_element('annotations');
        $annotation = new backup_nested_element('annotation', ['id'], [
            'targettype', 'targetstableid', 'commenttext', 'commentformat',
            'userid', 'timecreated', 'timemodified',
        ]);

        $aifeedbacks = new backup_nested_element('aifeedbacks');
        $aifeedback = new backup_nested_element('aifeedback', ['id'], [
            'graderid', 'promptcontextjson', 'drafttext', 'draftformat',
            'acceptedtext', 'acceptedformat', 'providerinfo',
            'timecreated', 'timemodified',
        ]);

        $journalentries = new backup_nested_element('journalentries');
        $journalentry = new backup_nested_element('journalentry', ['id'], [
            'userid', 'revisionref', 'entrytext', 'entryformat',
            'visibility', 'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $vimipad->add_child($grades);
        $grades->add_child($grade);
        $vimipad->add_child($workspaces);
        $workspaces->add_child($workspace);

        $workspace->add_child($nodes);
        $nodes->add_child($node);
        $workspace->add_child($relations);
        $relations->add_child($relation);
        $workspace->add_child($containers);
        $containers->add_child($container);
        $container->add_child($memberships);
        $memberships->add_child($membership);
        $workspace->add_child($layouts);
        $layouts->add_child($layout);
        $workspace->add_child($operations);
        $operations->add_child($operation);
        $workspace->add_child($snapshots);
        $snapshots->add_child($snapshot);
        $snapshot->add_child($annotations);
        $annotations->add_child($annotation);
        $snapshot->add_child($aifeedbacks);
        $aifeedbacks->add_child($aifeedback);
        $workspace->add_child($journalentries);
        $journalentries->add_child($journalentry);

        // Sources.
        $vimipad->set_source_table('vimipad', ['id' => backup::VAR_ACTIVITYID]);

        // User-generated content only when userinfo is requested.
        if ($userinfo) {
            $grade->set_source_table('vimipad_grade', ['vimipadid' => backup::VAR_PARENTID]);
            $workspace->set_source_table('vimipad_workspace', ['vimipadid' => backup::VAR_PARENTID]);
            $node->set_source_table('vimipad_node', ['workspaceid' => backup::VAR_PARENTID]);
            $relation->set_source_table('vimipad_relation', ['workspaceid' => backup::VAR_PARENTID]);
            $container->set_source_table('vimipad_container', ['workspaceid' => backup::VAR_PARENTID]);
            $membership->set_source_table('vimipad_membership', ['containerid' => backup::VAR_PARENTID]);
            $layout->set_source_table('vimipad_layout', ['workspaceid' => backup::VAR_PARENTID]);
            $operation->set_source_table('vimipad_operation', ['workspaceid' => backup::VAR_PARENTID]);
            $snapshot->set_source_table('vimipad_snapshot', ['workspaceid' => backup::VAR_PARENTID]);
            $annotation->set_source_table('vimipad_annotation', ['snapshotid' => backup::VAR_PARENTID]);
            $aifeedback->set_source_table('vimipad_aifeedback', ['snapshotid' => backup::VAR_PARENTID]);
            $journalentry->set_source_table('vimipad_journalentry', ['workspaceid' => backup::VAR_PARENTID]);
        }

        // Id annotations.
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'grader');
        $workspace->annotate_ids('user', 'userid');
        $workspace->annotate_ids('group', 'groupid');
        $node->annotate_ids('user', 'createdby');
        $node->annotate_ids('user', 'modifiedby');
        $relation->annotate_ids('user', 'createdby');
        $relation->annotate_ids('user', 'modifiedby');
        $layout->annotate_ids('user', 'modifiedby');
        $operation->annotate_ids('user', 'userid');
        $snapshot->annotate_ids('user', 'submittedby');
        $annotation->annotate_ids('user', 'userid');
        $aifeedback->annotate_ids('user', 'graderid');
        $journalentry->annotate_ids('user', 'userid');

        // File annotations.
        $vimipad->annotate_files('mod_vimipad', 'intro', null);

        return $this->prepare_activity_structure($vimipad);
    }
}
