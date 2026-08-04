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

        $collection->add_database_table('vimipad_layouthist', [
            'modifiedby' => 'privacy:metadata:modifiedby',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:vimipad_layouthist');

        $collection->add_database_table('vimipad_grade', [
            'userid' => 'privacy:metadata:vimipad_grade:userid',
            'grade' => 'privacy:metadata:vimipad_grade:grade',
            'feedback' => 'privacy:metadata:vimipad_grade:feedback',
            'grader' => 'privacy:metadata:vimipad_grade:grader',
        ], 'privacy:metadata:vimipad_grade');

        $collection->add_database_table('vimipad_peerreview', [
            'reviewerid' => 'privacy:metadata:vimipad_peerreview:reviewerid',
            'score' => 'privacy:metadata:vimipad_peerreview:score',
            'reviewcomment' => 'privacy:metadata:vimipad_peerreview:reviewcomment',
        ], 'privacy:metadata:vimipad_peerreview');

        $collection->add_database_table('vimipad_gradeinstance', [
            'raterid' => 'privacy:metadata:vimipad_gradeinstance:raterid',
        ], 'privacy:metadata:vimipad_gradeinstance');

        $collection->add_database_table('vimipad_lock', [
            'userid' => 'privacy:metadata:vimipad_lock:userid',
        ], 'privacy:metadata:vimipad_lock');

        $collection->add_database_table('vimipad_submissionintent', [
            'userid' => 'privacy:metadata:vimipad_submissionintent:userid',
        ], 'privacy:metadata:vimipad_submissionintent');

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
        $mod = ['modlevel' => CONTEXT_MODULE, 'modname' => 'vimipad'];

        $base = "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modlevel
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   JOIN {vimipad} v ON v.id = cm.instance ";
        $ws = "JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id ";
        $snap = $ws . "JOIN {vimipad_snapshot} s ON s.workspaceid = ws.id ";

        // Workspace owner.
        $contextlist->add_from_sql($base . $ws . "WHERE ws.userid = :u", $mod + ['u' => $userid]);
        // Nodes authored or modified.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_node} n ON n.workspaceid = ws.id
                           WHERE n.createdby = :u1 OR n.modifiedby = :u2",
            $mod + ['u1' => $userid, 'u2' => $userid]
        );
        // Relations authored or modified.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_relation} r ON r.workspaceid = ws.id
                           WHERE r.createdby = :u1 OR r.modifiedby = :u2",
            $mod + ['u1' => $userid, 'u2' => $userid]
        );
        // Operations.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_operation} op ON op.workspaceid = ws.id WHERE op.userid = :u",
            $mod + ['u' => $userid]
        );
        // Journal entries.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_journalentry} j ON j.workspaceid = ws.id WHERE j.userid = :u",
            $mod + ['u' => $userid]
        );
        // Layout modifications.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_layout} l ON l.workspaceid = ws.id WHERE l.modifiedby = :u",
            $mod + ['u' => $userid]
        );
        // Layout history modifications.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_layouthist} lh ON lh.workspaceid = ws.id WHERE lh.modifiedby = :u",
            $mod + ['u' => $userid]
        );
        // Element locks.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_lock} lk ON lk.workspaceid = ws.id WHERE lk.userid = :u",
            $mod + ['u' => $userid]
        );
        // Group submit intents.
        $contextlist->add_from_sql(
            $base . $ws . "JOIN {vimipad_submissionintent} si ON si.workspaceid = ws.id WHERE si.userid = :u",
            $mod + ['u' => $userid]
        );
        // Snapshots submitted.
        $contextlist->add_from_sql($base . $snap . "WHERE s.submittedby = :u", $mod + ['u' => $userid]);
        // Annotations authored.
        $contextlist->add_from_sql(
            $base . $snap . "JOIN {vimipad_annotation} a ON a.snapshotid = s.id WHERE a.userid = :u",
            $mod + ['u' => $userid]
        );
        // AI feedback authored (grader).
        $contextlist->add_from_sql(
            $base . $snap . "JOIN {vimipad_aifeedback} af ON af.snapshotid = s.id WHERE af.graderid = :u",
            $mod + ['u' => $userid]
        );
        // Peer reviews written (reviewer).
        $contextlist->add_from_sql(
            $base . $snap . "JOIN {vimipad_peerreview} pr ON pr.snapshotid = s.id WHERE pr.reviewerid = :u",
            $mod + ['u' => $userid]
        );
        // Advanced-grading instances rated (rater).
        $contextlist->add_from_sql(
            $base . $snap . "JOIN {vimipad_gradeinstance} gi ON gi.snapshotid = s.id WHERE gi.raterid = :u",
            $mod + ['u' => $userid]
        );
        // Grades received or given (attached to the instance directly).
        $contextlist->add_from_sql(
            $base . "JOIN {vimipad_grade} g ON g.vimipadid = v.id WHERE g.userid = :u1 OR g.grader = :u2",
            $mod + ['u1' => $userid, 'u2' => $userid]
        );

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
        $ws = "FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
               JOIN {vimipad} v ON v.id = cm.instance
               JOIN {vimipad_workspace} ws ON ws.vimipadid = v.id ";
        $snap = $ws . "JOIN {vimipad_snapshot} s ON s.workspaceid = ws.id ";
        $grade = "FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {vimipad} v ON v.id = cm.instance
                  JOIN {vimipad_grade} g ON g.vimipadid = v.id ";

        $userlist->add_from_sql('userid', "SELECT ws.userid $ws
            WHERE cm.id = :cmid AND ws.userid IS NOT NULL", $params);
        $userlist->add_from_sql('createdby', "SELECT n.createdby $ws
            JOIN {vimipad_node} n ON n.workspaceid = ws.id WHERE cm.id = :cmid AND n.createdby IS NOT NULL", $params);
        $userlist->add_from_sql('modifiedby', "SELECT n.modifiedby $ws
            JOIN {vimipad_node} n ON n.workspaceid = ws.id WHERE cm.id = :cmid AND n.modifiedby IS NOT NULL", $params);
        $userlist->add_from_sql('createdby', "SELECT r.createdby $ws
            JOIN {vimipad_relation} r ON r.workspaceid = ws.id WHERE cm.id = :cmid AND r.createdby IS NOT NULL", $params);
        $userlist->add_from_sql('modifiedby', "SELECT r.modifiedby $ws
            JOIN {vimipad_relation} r ON r.workspaceid = ws.id WHERE cm.id = :cmid AND r.modifiedby IS NOT NULL", $params);
        $userlist->add_from_sql('userid', "SELECT op.userid $ws
            JOIN {vimipad_operation} op ON op.workspaceid = ws.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('userid', "SELECT j.userid $ws
            JOIN {vimipad_journalentry} j ON j.workspaceid = ws.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('modifiedby', "SELECT l.modifiedby $ws
            JOIN {vimipad_layout} l ON l.workspaceid = ws.id WHERE cm.id = :cmid AND l.modifiedby IS NOT NULL", $params);
        $userlist->add_from_sql(
            'modifiedby',
            "SELECT lh.modifiedby $ws
            JOIN {vimipad_layouthist} lh ON lh.workspaceid = ws.id WHERE cm.id = :cmid AND lh.modifiedby IS NOT NULL",
            $params
        );
        $userlist->add_from_sql('userid', "SELECT lk.userid $ws
            JOIN {vimipad_lock} lk ON lk.workspaceid = ws.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('userid', "SELECT si.userid $ws
            JOIN {vimipad_submissionintent} si ON si.workspaceid = ws.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('submittedby', "SELECT s.submittedby $snap
            WHERE cm.id = :cmid AND s.submittedby IS NOT NULL", $params);
        $userlist->add_from_sql('userid', "SELECT a.userid $snap
            JOIN {vimipad_annotation} a ON a.snapshotid = s.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('graderid', "SELECT af.graderid $snap
            JOIN {vimipad_aifeedback} af ON af.snapshotid = s.id WHERE cm.id = :cmid AND af.graderid IS NOT NULL", $params);
        $userlist->add_from_sql('reviewerid', "SELECT pr.reviewerid $snap
            JOIN {vimipad_peerreview} pr ON pr.snapshotid = s.id WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql('raterid', "SELECT gi.raterid $snap
            JOIN {vimipad_gradeinstance} gi ON gi.snapshotid = s.id WHERE cm.id = :cmid AND gi.raterid IS NOT NULL", $params);
        $userlist->add_from_sql('userid', "SELECT g.userid $grade
            WHERE cm.id = :cmid AND g.userid IS NOT NULL", $params);
        $userlist->add_from_sql('grader', "SELECT g.grader $grade
            WHERE cm.id = :cmid AND g.grader IS NOT NULL", $params);
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

            // Load nodes, relations and journal for ALL of this user's
            // workspaces in three IN-queries and group in PHP, instead of three
            // queries per workspace (the former 3N+1 pattern).
            $nodesby = [];
            $relationsby = [];
            $journalby = [];
            if ($workspaces) {
                $wsids = array_keys($workspaces);
                [$insql, $inparams] = $DB->get_in_or_equal($wsids, SQL_PARAMS_NAMED, 'ws');
                foreach ($DB->get_records_select('vimipad_node', "workspaceid $insql", $inparams) as $n) {
                    $nodesby[(int) $n->workspaceid][] = $n;
                }
                foreach ($DB->get_records_select('vimipad_relation', "workspaceid $insql", $inparams) as $r) {
                    $relationsby[(int) $r->workspaceid][] = $r;
                }
                $jparams = $inparams + ['juser' => $userid];
                foreach (
                    $DB->get_records_select(
                        'vimipad_journalentry',
                        "workspaceid $insql AND userid = :juser",
                        $jparams
                    ) as $j
                ) {
                    $journalby[(int) $j->workspaceid][] = $j;
                }
            }

            foreach ($workspaces as $ws) {
                $nodes = $nodesby[(int) $ws->id] ?? [];
                $relations = $relationsby[(int) $ws->id] ?? [];
                $journal = $journalby[(int) $ws->id] ?? [];

                $data = (object) [
                    'name' => $ws->name,
                    'currentrevision' => $ws->currentrevision,
                    'nodes' => array_values(array_map(static function ($n) {
                        return [
                            'stableid' => $n->stableid,
                            'type' => $n->type,
                            'label' => $n->label,
                            'content' => $n->content,
                        ];
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

            // Grades the user received in this activity.
            $grades = $DB->get_records('vimipad_grade', ['vimipadid' => $cm->instance, 'userid' => $userid]);
            if ($grades) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:grades', 'mod_vimipad')],
                    (object) ['grades' => array_values(array_map(static function ($g) {
                        return [
                            'grade' => $g->grade,
                            'feedback' => $g->feedback,
                            'timemodified' => $g->timemodified,
                        ];
                    }, $grades))]
                );
            }

            // The user's authored contributions across every workspace of the
            // activity, including shared group/course workspaces.
            $vid = $cm->instance;
            $cnodes = $DB->get_records_sql(
                "SELECT n.* FROM {vimipad_node} n JOIN {vimipad_workspace} ws ON ws.id = n.workspaceid
                  WHERE ws.vimipadid = :vid AND (n.createdby = :u1 OR n.modifiedby = :u2)",
                ['vid' => $vid, 'u1' => $userid, 'u2' => $userid]
            );
            $crelations = $DB->get_records_sql(
                "SELECT r.* FROM {vimipad_relation} r JOIN {vimipad_workspace} ws ON ws.id = r.workspaceid
                  WHERE ws.vimipadid = :vid AND (r.createdby = :u1 OR r.modifiedby = :u2)",
                ['vid' => $vid, 'u1' => $userid, 'u2' => $userid]
            );
            $cjournal = $DB->get_records_sql(
                "SELECT j.* FROM {vimipad_journalentry} j JOIN {vimipad_workspace} ws ON ws.id = j.workspaceid
                  WHERE ws.vimipadid = :vid AND j.userid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $cannotations = $DB->get_records_sql(
                "SELECT a.* FROM {vimipad_annotation} a
                   JOIN {vimipad_snapshot} s ON s.id = a.snapshotid
                   JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                  WHERE ws.vimipadid = :vid AND a.userid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            if ($cnodes || $crelations || $cjournal || $cannotations) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:contributions', 'mod_vimipad')],
                    (object) [
                        'nodes' => array_values(array_map(static function ($n) {
                            return ['stableid' => $n->stableid, 'label' => $n->label, 'content' => $n->content];
                        }, $cnodes)),
                        'relations' => array_values(array_map(static function ($r) {
                            return ['stableid' => $r->stableid, 'label' => $r->label];
                        }, $crelations)),
                        'journal' => array_values(array_map(static function ($j) {
                            return ['entrytext' => $j->entrytext, 'timecreated' => $j->timecreated];
                        }, $cjournal)),
                        'annotations' => array_values(array_map(static function ($a) {
                            return ['commenttext' => $a->commenttext, 'timecreated' => $a->timecreated];
                        }, $cannotations)),
                    ]
                );
            }

            // Activity involvement: everything else declared in the metadata
            // that carries this user's id — operations, submitted snapshots,
            // layout edits, locks and submit intents.
            $ops = $DB->get_records_sql(
                "SELECT op.id, op.operationtype, op.timecreated
                   FROM {vimipad_operation} op
                   JOIN {vimipad_workspace} ws ON ws.id = op.workspaceid
                  WHERE ws.vimipadid = :vid AND op.userid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $snapshots = $DB->get_records_sql(
                "SELECT s.id, s.revision, s.timecreated
                   FROM {vimipad_snapshot} s
                   JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                  WHERE ws.vimipadid = :vid AND s.submittedby = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $layouts = $DB->get_records_sql(
                "SELECT l.id, l.profile, l.timemodified
                   FROM {vimipad_layout} l
                   JOIN {vimipad_workspace} ws ON ws.id = l.workspaceid
                  WHERE ws.vimipadid = :vid AND l.modifiedby = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $locks = $DB->get_records_sql(
                "SELECT lk.id, lk.targettype, lk.timeexpires
                   FROM {vimipad_lock} lk
                   JOIN {vimipad_workspace} ws ON ws.id = lk.workspaceid
                  WHERE ws.vimipadid = :vid AND lk.userid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $intents = $DB->get_records_sql(
                "SELECT si.id, si.timecreated
                   FROM {vimipad_submissionintent} si
                   JOIN {vimipad_workspace} ws ON ws.id = si.workspaceid
                  WHERE ws.vimipadid = :vid AND si.userid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            if ($ops || $snapshots || $layouts || $locks || $intents) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:involvement', 'mod_vimipad')],
                    (object) [
                        'operations' => array_values(array_map(static function ($o) {
                            return ['operationtype' => $o->operationtype, 'timecreated' => $o->timecreated];
                        }, $ops)),
                        'submittedsnapshots' => array_values(array_map(static function ($s) {
                            return ['revision' => $s->revision, 'timecreated' => $s->timecreated];
                        }, $snapshots)),
                        'layoutedits' => array_values(array_map(static function ($l) {
                            return ['profile' => $l->profile, 'timemodified' => $l->timemodified];
                        }, $layouts)),
                        'elementlocks' => array_values(array_map(static function ($lk) {
                            return ['targettype' => $lk->targettype, 'timeexpires' => $lk->timeexpires];
                        }, $locks)),
                        'submitintents' => array_values(array_map(static function ($si) {
                            return ['timecreated' => $si->timecreated];
                        }, $intents)),
                    ]
                );
            }

            // Assessment activity as a reviewer, rater or AI-feedback grader,
            // and grades given (grader identity + feedback text authored).
            $reviews = $DB->get_records_sql(
                "SELECT pr.id, pr.score, pr.reviewcomment, pr.timemodified
                   FROM {vimipad_peerreview} pr
                   JOIN {vimipad_snapshot} s ON s.id = pr.snapshotid
                   JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                  WHERE ws.vimipadid = :vid AND pr.reviewerid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $aifeedback = $DB->get_records_sql(
                "SELECT af.id, af.drafttext, af.acceptedtext, af.timecreated
                   FROM {vimipad_aifeedback} af
                   JOIN {vimipad_snapshot} s ON s.id = af.snapshotid
                   JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                  WHERE ws.vimipadid = :vid AND af.graderid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $ratings = $DB->get_records_sql(
                "SELECT gi.id, gi.timemodified
                   FROM {vimipad_gradeinstance} gi
                   JOIN {vimipad_snapshot} s ON s.id = gi.snapshotid
                   JOIN {vimipad_workspace} ws ON ws.id = s.workspaceid
                  WHERE ws.vimipadid = :vid AND gi.raterid = :u",
                ['vid' => $vid, 'u' => $userid]
            );
            $gradesgiven = $DB->get_records('vimipad_grade', ['vimipadid' => $vid, 'grader' => $userid]);
            if ($reviews || $aifeedback || $ratings || $gradesgiven) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:grading', 'mod_vimipad')],
                    (object) [
                        'peerreviews' => array_values(array_map(static function ($pr) {
                            return [
                                'score' => $pr->score,
                                'reviewcomment' => $pr->reviewcomment,
                                'timemodified' => $pr->timemodified,
                            ];
                        }, $reviews)),
                        'aifeedback' => array_values(array_map(static function ($af) {
                            return [
                                'drafttext' => $af->drafttext,
                                'acceptedtext' => $af->acceptedtext,
                                'timecreated' => $af->timecreated,
                            ];
                        }, $aifeedback)),
                        'advancedgradings' => array_values(array_map(static function ($gi) {
                            return ['timemodified' => $gi->timemodified];
                        }, $ratings)),
                        'gradesgiven' => array_values(array_map(static function ($g) {
                            return ['feedback' => $g->feedback, 'timemodified' => $g->timemodified];
                        }, $gradesgiven)),
                    ]
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

            // Remove the peer reviews this user wrote.
            $DB->delete_records_select(
                'vimipad_peerreview',
                'reviewerid = :reviewer AND snapshotid IN (
                    SELECT s.id FROM {vimipad_snapshot} s
                      JOIN {vimipad_workspace} w ON w.id = s.workspaceid
                     WHERE w.vimipadid = :vid)',
                ['reviewer' => $userid, 'vid' => $cm->instance]
            );

            // Remove the user's advanced-grading instance links (as a grader).
            $DB->delete_records_select(
                'vimipad_gradeinstance',
                'raterid = :rater AND snapshotid IN (
                    SELECT s.id FROM {vimipad_snapshot} s
                      JOIN {vimipad_workspace} w ON w.id = s.workspaceid
                     WHERE w.vimipadid = :vid)',
                ['rater' => $userid, 'vid' => $cm->instance]
            );

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

        // Mirror the single-user path exactly, so the deletion outcome does not
        // depend on which privacy pathway Moodle chose: own grade records, peer
        // reviews written, and advanced-grading instance links as a rater.
        $DB->delete_records_select(
            'vimipad_grade',
            "vimipadid = :vid AND userid $insql",
            $params
        );
        [$rinsql, $rinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'vimipad_peerreview',
            "reviewerid $rinsql AND snapshotid IN (
                SELECT s.id FROM {vimipad_snapshot} s
                  JOIN {vimipad_workspace} w ON w.id = s.workspaceid
                 WHERE w.vimipadid = :vid)",
            array_merge($rinparams, ['vid' => $cm->instance])
        );
        [$ginsql, $ginparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'vimipad_gradeinstance',
            "raterid $ginsql AND snapshotid IN (
                SELECT s.id FROM {vimipad_snapshot} s
                  JOIN {vimipad_workspace} w ON w.id = s.workspaceid
                 WHERE w.vimipadid = :vid)",
            array_merge($ginparams, ['vid' => $cm->instance])
        );

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

        // The user acted as grader on grades belonging to others: keep the grade
        // but drop the grader identity. (Grades the user received are deleted in
        // the caller.) This is instance-scoped, independent of shared workspaces.
        $DB->set_field_select(
            'vimipad_grade',
            'grader',
            null,
            'vimipadid = :vid AND grader = :userid',
            ['vid' => $vimipadid, 'userid' => $userid]
        );

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

        $DB->set_field_select(
            'vimipad_layouthist',
            'modifiedby',
            0,
            "workspaceid $insql AND modifiedby = :userid",
            array_merge($params, ['userid' => $userid])
        );

        // Snapshots submitted by the user in shared workspaces: keep the
        // immutable snapshot, drop the submitter identity.
        $DB->set_field_select(
            'vimipad_snapshot',
            'submittedby',
            0,
            "workspaceid $insql AND submittedby = :userid",
            array_merge($params, ['userid' => $userid])
        );

        // Annotations and AI-feedback the user authored on shared snapshots.
        $snapsubquery = "SELECT s.id FROM {vimipad_snapshot} s WHERE s.workspaceid $insql";
        $DB->set_field_select(
            'vimipad_annotation',
            'userid',
            0,
            "userid = :userid AND snapshotid IN ($snapsubquery)",
            array_merge($params, ['userid' => $userid])
        );
        $DB->set_field_select(
            'vimipad_aifeedback',
            'graderid',
            0,
            "graderid = :userid AND snapshotid IN ($snapsubquery)",
            array_merge($params, ['userid' => $userid])
        );

        // Element locks are transient: remove the user's leases in shared workspaces.
        $DB->delete_records_select(
            'vimipad_lock',
            "workspaceid $insql AND userid = :userid",
            array_merge($params, ['userid' => $userid])
        );

        // Group submit intents are transient: remove the user's intents.
        $DB->delete_records_select(
            'vimipad_submissionintent',
            "workspaceid $insql AND userid = :userid",
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
