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

namespace mod_vimipad\local\service;

use stdClass;

/**
 * Creates and reads immutable snapshots.
 *
 * A snapshot freezes the full normalized state of a workspace (nodes,
 * relations, containers, memberships, layout, profile, revision, metadata) as
 * JSON. It is the stable object that grading, annotations and AI feedback refer
 * to; later edits to the workspace never change a taken snapshot. Internal
 * (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_service {
    /** @var int Draft, not yet submitted. */
    public const STATUS_DRAFT = 0;

    /** @var int Submitted by the learner. */
    public const STATUS_SUBMITTED = 1;

    /** @var int Being reviewed by a teacher. */
    public const STATUS_INREVIEW = 2;

    /** @var int Graded. */
    public const STATUS_GRADED = 3;

    /** @var int Returned to the learner. */
    public const STATUS_RETURNED = 4;

    /**
     * Build the normalized snapshot array for a workspace at its current state.
     *
     * @param stdClass $workspace The workspace record.
     * @param string $profile The active diagram profile.
     * @return array The normalized snapshot structure.
     */
    public function build_normalized(stdClass $workspace, string $profile): array {
        global $DB;

        $wsid = (int) $workspace->id;

        $nodes = $DB->get_records('vimipad_node', ['workspaceid' => $wsid, 'deleted' => 0]);
        $relations = $DB->get_records('vimipad_relation', ['workspaceid' => $wsid, 'deleted' => 0]);
        $containers = $DB->get_records('vimipad_container', ['workspaceid' => $wsid, 'deleted' => 0]);
        $layout = $DB->get_record('vimipad_layout', ['workspaceid' => $wsid, 'profile' => $profile]);

        // Container memberships, keyed to container stable ids so the snapshot is
        // self-contained and independent of database ids.
        $containerstable = [];
        foreach ($containers as $container) {
            $containerstable[(int) $container->id] = $container->stableid;
        }
        $membershiprows = $DB->get_records_sql(
            "SELECT m.*
               FROM {vimipad_membership} m
               JOIN {vimipad_container} c ON c.id = m.containerid
              WHERE c.workspaceid = :wsid",
            ['wsid' => $wsid]
        );
        $memberships = [];
        foreach ($membershiprows as $membership) {
            $cid = (int) $membership->containerid;
            if (!isset($containerstable[$cid])) {
                continue;
            }
            $memberships[] = [
                'containerstableid' => $containerstable[$cid],
                'itemtype' => $membership->itemtype,
                'itemstableid' => $membership->itemstableid,
                'role' => $membership->role,
                'sortorder' => (int) $membership->sortorder,
            ];
        }

        $mapfields = static function (array $records, array $fields): array {
            $out = [];
            foreach ($records as $record) {
                $item = [];
                foreach ($fields as $field) {
                    $item[$field] = $record->$field ?? null;
                }
                $out[] = $item;
            }
            return $out;
        };

        return [
            'profile' => $profile,
            'revision' => (int) $workspace->currentrevision,
            'nodes' => $mapfields($nodes, ['stableid', 'type', 'label', 'content', 'contentformat', 'metadatajson']),
            'relations' => $mapfields(
                $relations,
                ['stableid', 'sourceid', 'targetid', 'type', 'label', 'direction', 'metadatajson']
            ),
            'containers' => $mapfields($containers, ['stableid', 'type', 'label', 'geometryjson', 'metadatajson']),
            'memberships' => $memberships,
            'layout' => $layout && $layout->layoutjson !== null ? json_decode($layout->layoutjson, true) : null,
            'metadata' => [
                'takenat' => time(),
            ],
        ];
    }

    /**
     * Create a submitted snapshot from the workspace and lock it.
     *
     * @param stdClass $workspace The workspace record.
     * @param string $profile The active diagram profile.
     * @param int $userid The submitting user id.
     * @return stdClass The created snapshot record.
     */
    public function create_submission(stdClass $workspace, string $profile, int $userid): stdClass {
        global $DB;

        $normalized = $this->build_normalized($workspace, $profile);

        $transaction = $DB->start_delegated_transaction();

        $snapshot = (object) [
            'workspaceid' => (int) $workspace->id,
            'revision' => (int) $workspace->currentrevision,
            'snapshotjson' => json_encode($normalized),
            'submittedby' => $userid,
            'status' => self::STATUS_SUBMITTED,
            'timecreated' => time(),
        ];
        $snapshot->id = $DB->insert_record('vimipad_snapshot', $snapshot);

        $DB->update_record('vimipad_workspace', (object) [
            'id' => (int) $workspace->id,
            'submittedsnapshotid' => $snapshot->id,
            'locked' => 1,
            'timemodified' => time(),
        ]);

        $transaction->allow_commit();

        return $snapshot;
    }

    /**
     * Load a snapshot record.
     *
     * @param int $snapshotid The snapshot id.
     * @return stdClass The snapshot record.
     */
    public function get(int $snapshotid): stdClass {
        global $DB;
        return $DB->get_record('vimipad_snapshot', ['id' => $snapshotid], '*', MUST_EXIST);
    }

    /**
     * Set the status of a snapshot.
     *
     * @param int $snapshotid The snapshot id.
     * @param int $status One of the STATUS_* constants.
     * @return void
     */
    public function set_status(int $snapshotid, int $status): void {
        global $DB;
        $DB->set_field('vimipad_snapshot', 'status', $status, ['id' => $snapshotid]);
    }
}
