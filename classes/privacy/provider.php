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

namespace mod_vimipad\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_module;

/**
 * Privacy provider for mod_vimipad.
 *
 * Users author workspaces, nodes, relations, operations, snapshots,
 * annotations, AI feedback drafts and journal entries. All of these carry a
 * user reference and are handled here.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The metadata collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('vimipad_workspace', [
            'userid' => 'privacy:metadata:vimipad_workspace:userid',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:vimipad_workspace');

        $collection->add_database_table('vimipad_node', [
            'createdby' => 'privacy:metadata:createdby',
            'modifiedby' => 'privacy:metadata:modifiedby',
            'label' => 'privacy:metadata:vimipad_node:label',
            'content' => 'privacy:metadata:vimipad_node:content',
        ], 'privacy:metadata:vimipad_node');

        $collection->add_database_table('vimipad_relation', [
            'createdby' => 'privacy:metadata:createdby',
            'modifiedby' => 'privacy:metadata:modifiedby',
            'label' => 'privacy:metadata:vimipad_relation:label',
        ], 'privacy:metadata:vimipad_relation');

        $collection->add_database_table('vimipad_operation', [
            'userid' => 'privacy:metadata:vimipad_operation:userid',
            'operationtype' => 'privacy:metadata:vimipad_operation:operationtype',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:vimipad_operation');

        $collection->add_database_table('vimipad_snapshot', [
            'submittedby' => 'privacy:metadata:vimipad_snapshot:submittedby',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:vimipad_snapshot');

        $collection->add_database_table('vimipad_annotation', [
            'userid' => 'privacy:metadata:vimipad_annotation:userid',
            'commenttext' => 'privacy:metadata:vimipad_annotation:commenttext',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:vimipad_annotation');

        $collection->add_database_table('vimipad_aifeedback', [
            'graderid' => 'privacy:metadata:vimipad_aifeedback:graderid',
            'drafttext' => 'privacy:metadata:vimipad_aifeedback:drafttext',
            'acceptedtext' => 'privacy:metadata:vimipad_aifeedback:acceptedtext',
        ], 'privacy:metadata:vimipad_aifeedback');

        $collection->add_database_table('vimipad_journalentry', [
            'userid' => 'privacy:metadata:vimipad_journalentry:userid',
            'entrytext' => 'privacy:metadata:vimipad_journalentry:entrytext',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:vimipad_journalentry');

        $collection->add_database_table('vimipad_layout', [
            'modifiedby' => 'privacy:metadata:modifiedby',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:vimipad_layout');

        $collection->add_database_table('vimipad_grade', [
            'userid' => 'privacy:metadata:vimipad_grade:userid',
            'grade' => 'privacy:metadata:vimipad_grade:grade',
            'feedback' => 'privacy:metadata:vimipad_grade:feedback',
            'grader' => 'privacy:metadata:vimipad_grade:grader',
        ], 'privacy:metadata:vimipad_grade');

        $collection->add_subsystem_link(
            'core_ai',
            [],
            'privacy:metadata:core_ai'
        );

        return $collection;
    }

    /**
     * Get the list of module contexts that contain data for the given user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {vimipad} v ON v.id = cm.instance
                  JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id
             LEFT JOIN {vimipad_node} n ON n.workspaceid = ws.id
             LEFT JOIN {vimipad_operation} op ON op.workspaceid = ws.id
             LEFT JOIN {vimipad_journalentry} j ON j.workspaceid = ws.id
                 WHERE ws.userid = :u1
                    OR n.createdby = :u2
                    OR op.userid = :u3
                    OR j.userid = :u4";
        $params = [
            'modlevel' => CONTEXT_MODULE,
            'modname' => 'vimipad',
            'u1' => $userid, 'u2' => $userid, 'u3' => $userid, 'u4' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data in the given context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'vimipad'];

        $userlist->add_from_sql('userid', "
            SELECT ws.userid
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              JOIN {vimipad} v ON v.id = cm.instance
              JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id
             WHERE cm.id = :cmid AND ws.userid IS NOT NULL", $params);

        $userlist->add_from_sql('createdby', "
            SELECT n.createdby
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              JOIN {vimipad} v ON v.id = cm.instance
              JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id
              JOIN {vimipad_node} n ON n.workspaceid = ws.id
             WHERE cm.id = :cmid AND n.createdby IS NOT NULL", $params);

        $userlist->add_from_sql('userid', "
            SELECT op.userid
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = :modname
              JOIN {vimipad} v ON v.id = cm.instance
              JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id
              JOIN {vimipad_operation} op ON op.workspaceid = ws.id
             WHERE cm.id = :cmid", $params);
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('vimipad', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $workspaces = $DB->get_records_sql(
                "
                SELECT ws.*
                  FROM {vimipad_workspace} ws
                 WHERE ws.vimipadid = :vid AND ws.userid = :userid",
                ['vid' => $cm->instance, 'userid' => $userid]
            );

            foreach ($workspaces as $ws) {
                $nodes = $DB->get_records('vimipad_node', ['workspaceid' => $ws->id]);
                $relations = $DB->get_records('vimipad_relation', ['workspaceid' => $ws->id]);
                $journal = $DB->get_records(
                    'vimipad_journalentry',
                    ['workspaceid' => $ws->id, 'userid' => $userid]
                );

                $data = (object) [
                    'name' => $ws->name,
                    'currentrevision' => $ws->currentrevision,
                    'nodes' => array_values(array_map(static function ($n) {
                        return ['stableid' => $n->stableid, 'type' => $n->type, 'label' => $n->label];
                    }, $nodes)),
                    'relations' => array_values(array_map(static function ($r) {
                        return ['stableid' => $r->stableid, 'type' => $r->type, 'label' => $r->label];
                    }, $relations)),
                    'journal' => array_values(array_map(static function ($j) {
                        return ['entrytext' => $j->entrytext, 'timecreated' => $j->timecreated];
                    }, $journal)),
                ];

                writer::with_context($context)->export_data(
                    [get_string('privacy:path:workspace', 'mod_vimipad') . ' ' . $ws->id],
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the given context.
     *
     * @param context $context The context to delete in.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('vimipad', $context->instanceid);
        if (!$cm) {
            return;
        }

        $workspaceids = $DB->get_fieldset_select(
            'vimipad_workspace',
            'id',
            'vimipadid = :vid',
            ['vid' => $cm->instance]
        );
        $DB->delete_records('vimipad_grade', ['vimipadid' => $cm->instance]);
        if (empty($workspaceids)) {
            return;
        }

        \mod_vimipad\local\cleanup::delete_workspaces($workspaceids);
    }

    /**
     * Delete all data for the given user across approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('vimipad', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Delete workspaces owned by this user (individual mode) and their children.
            $ownworkspaces = $DB->get_fieldset_select(
                'vimipad_workspace',
                'id',
                'vimipadid = :vid AND userid = :userid',
                ['vid' => $cm->instance, 'userid' => $userid]
            );
            \mod_vimipad\local\cleanup::delete_workspaces($ownworkspaces);

            // Remove the user's own grade record.
            $DB->delete_records('vimipad_grade', ['vimipadid' => $cm->instance, 'userid' => $userid]);

            // Anonymise contributions to shared (group/course) workspaces the user does not own.
            self::anonymise_shared_contributions($cm->instance, $userid);
        }
    }

    /**
     * Delete data for the approved set of users in a single context.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('vimipad', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['vid' => $cm->instance], $inparams);

        $ownworkspaces = $DB->get_fieldset_select(
            'vimipad_workspace',
            'id',
            "vimipadid = :vid AND userid $insql",
            $params
        );
        \mod_vimipad\local\cleanup::delete_workspaces($ownworkspaces);

        foreach ($userids as $userid) {
            self::anonymise_shared_contributions($cm->instance, $userid);
        }
    }


    /**
     * Anonymise a user's contributions to workspaces they do not own.
     *
     * @param int $vimipadid The vimipad instance id.
     * @param int $userid The user id to anonymise.
     * @return void
     */
    private static function anonymise_shared_contributions(int $vimipadid, int $userid): void {
        global $DB;

        $sharedids = $DB->get_fieldset_select(
            'vimipad_workspace',
            'id',
            'vimipadid = :vid AND (userid IS NULL OR userid <> :userid)',
            ['vid' => $vimipadid, 'userid' => $userid]
        );
        if (empty($sharedids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($sharedids, SQL_PARAMS_NAMED);

        $DB->set_field_select(
            'vimipad_node',
            'createdby',
            0,
            "workspaceid $insql AND createdby = :userid",
            array_merge($params, ['userid' => $userid])
        );
        $DB->set_field_select(
            'vimipad_node',
            'modifiedby',
            0,
            "workspaceid $insql AND modifiedby = :userid",
            array_merge($params, ['userid' => $userid])
        );
        $DB->set_field_select(
            'vimipad_relation',
            'createdby',
            0,
            "workspaceid $insql AND createdby = :userid",
            array_merge($params, ['userid' => $userid])
        );
        $DB->set_field_select(
            'vimipad_relation',
            'modifiedby',
            0,
            "workspaceid $insql AND modifiedby = :userid",
            array_merge($params, ['userid' => $userid])
        );
        $DB->set_field_select(
            'vimipad_operation',
            'userid',
            0,
            "workspaceid $insql AND userid = :userid",
            array_merge($params, ['userid' => $userid])
        );

        $DB->set_field_select(
            'vimipad_layout',
            'modifiedby',
            0,
            "workspaceid $insql AND modifiedby = :userid",
            array_merge($params, ['userid' => $userid])
        );

        // Journal entries are personal: delete rather than anonymise.
        $DB->delete_records_select(
            'vimipad_journalentry',
            "workspaceid $insql AND userid = :userid",
            array_merge($params, ['userid' => $userid])
        );
    }
}
