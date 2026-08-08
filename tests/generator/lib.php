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
 * Test data generator for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * ViMi Pad module data generator.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_vimipad_generator extends testing_module_generator {
    /**
     * Create a vimipad instance with sensible defaults.
     *
     * @param array|stdClass|null $record Instance data overrides.
     * @param array|null $options Course module options.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'defaultprofile' => 'conceptmap',
            'collaborationmode' => 0,
            'gradingmode' => 0,
            'aienabled' => 0,
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * Create a workspace with an optional set of nodes and a submitted snapshot.
     *
     * Used by Behat and integration tests to seed a gradable submission without
     * driving the JavaScript editor.
     *
     * @param stdClass $instance The vimipad instance.
     * @param int $userid The owning user id (individual mode).
     * @param array $nodes List of ['stableid' => ..., 'label' => ...] node specs.
     * @param bool $submit Whether to create and lock a submitted snapshot.
     * @return stdClass The workspace record (with ->snapshotid if submitted).
     */
    public function create_workspace(stdClass $instance, int $userid, array $nodes = [], bool $submit = false): stdClass {
        global $DB;

        $now = time();
        $workspace = (object) [
            'vimipadid' => $instance->id,
            'userid' => $userid,
            'groupid' => null,
            'currentrevision' => count($nodes),
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $workspace->id = $DB->insert_record('vimipad_workspace', $workspace);

        foreach ($nodes as $node) {
            $DB->insert_record('vimipad_node', (object) [
                'workspaceid' => $workspace->id,
                'stableid' => $node['stableid'],
                'type' => $node['type'] ?? 'concept',
                'label' => $node['label'],
                'contentformat' => FORMAT_HTML,
                'createdby' => $userid,
                'modifiedby' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
                'deleted' => 0,
            ]);
        }

        if ($submit) {
            $service = new \mod_vimipad\local\service\snapshot_service();
            $cm = get_coursemodule_from_instance('vimipad', $instance->id, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $result = $service->create_submission($instance, $workspace, $context, $userid);
            if ($result['snapshot'] !== null) {
                $workspace->snapshotid = $result['snapshot']->id;
            }
        }

        return $workspace;
    }

    /**
     * Create a single node in a workspace.
     *
     * @param stdClass $workspace The workspace record.
     * @param array $record Field overrides (stableid, type, label, metadatajson, ...).
     * @return stdClass The created node record (with ->id).
     */
    public function create_node(stdClass $workspace, array $record = []): stdClass {
        global $DB;

        $now = time();
        $node = (object) array_merge([
            'workspaceid' => $workspace->id,
            'stableid' => $this->stableid('node'),
            'type' => 'concept',
            'label' => 'Node',
            'content' => null,
            'contentformat' => FORMAT_HTML,
            'metadatajson' => null,
            'createdby' => $workspace->userid,
            'modifiedby' => $workspace->userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'deleted' => 0,
        ], $record);
        $node->id = $DB->insert_record('vimipad_node', $node);
        return $node;
    }

    /**
     * Create a single relation between two nodes.
     *
     * @param stdClass $workspace The workspace record.
     * @param array $record Field overrides (must include sourceid and targetid).
     * @return stdClass The created relation record (with ->id).
     */
    public function create_relation(stdClass $workspace, array $record = []): stdClass {
        global $DB;

        $now = time();
        $relation = (object) array_merge([
            'workspaceid' => $workspace->id,
            'stableid' => $this->stableid('rel'),
            'sourceid' => '',
            'targetid' => '',
            'type' => 'link',
            'label' => '',
            'direction' => 1,
            'metadatajson' => null,
            'createdby' => $workspace->userid,
            'modifiedby' => $workspace->userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'deleted' => 0,
        ], $record);
        $relation->id = $DB->insert_record('vimipad_relation', $relation);
        return $relation;
    }

    /**
     * Create a container (author/teacher background box).
     *
     * @param stdClass $workspace The workspace record.
     * @param array $record Field overrides (stableid, label, geometryjson, metadatajson).
     * @return stdClass The created container record (with ->id).
     */
    public function create_container(stdClass $workspace, array $record = []): stdClass {
        global $DB;

        $container = (object) array_merge([
            'workspaceid' => $workspace->id,
            'stableid' => $this->stableid('cont'),
            'type' => 'group',
            'label' => 'Container',
            'geometryjson' => json_encode(['x' => 100, 'y' => 100, 'w' => 200, 'h' => 150]),
            'metadatajson' => null,
            'deleted' => 0,
        ], $record);
        $container->id = $DB->insert_record('vimipad_container', $container);
        return $container;
    }

    /**
     * Create a container membership (a node/relation assigned to a container).
     *
     * @param stdClass $container The container record.
     * @param array $record Field overrides (itemtype, itemstableid, role, sortorder).
     * @return stdClass The created membership record (with ->id).
     */
    public function create_membership(stdClass $container, array $record = []): stdClass {
        global $DB;

        $membership = (object) array_merge([
            'containerid' => $container->id,
            'itemtype' => 'node',
            'itemstableid' => '',
            'role' => 'member',
            'sortorder' => 0,
        ], $record);
        $membership->id = $DB->insert_record('vimipad_membership', $membership);
        return $membership;
    }

    /**
     * Append a batch of operation-log entries to a workspace, advancing its
     * revision. Useful for exercising replay/compaction and load paths.
     *
     * @param stdClass $workspace The workspace record.
     * @param int $count How many operations to append.
     * @param array $record Field overrides (operationtype, payloadjson, userid).
     * @return int The workspace's revision after appending.
     */
    public function create_operations(stdClass $workspace, int $count, array $record = []): int {
        global $DB;

        $now = time();
        $revision = (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $workspace->id]);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $revision++;
            $rows[] = (object) array_merge([
                'workspaceid' => $workspace->id,
                'revision' => $revision,
                'operationtype' => 'node_update',
                'payloadjson' => json_encode(['stableid' => $this->stableid('node'), 'label' => 'op' . $i]),
                'userid' => $workspace->userid,
                'timecreated' => $now,
            ], $record);
        }
        $DB->insert_records('vimipad_operation', $rows);
        $DB->set_field('vimipad_workspace', 'currentrevision', $revision, ['id' => $workspace->id]);
        return $revision;
    }

    /**
     * Create a snapshot of a workspace at its current revision.
     *
     * @param stdClass $workspace The workspace record.
     * @param array $record Field overrides (revision, snapshotjson, submittedby, status).
     * @return stdClass The created snapshot record (with ->id).
     */
    public function create_snapshot(stdClass $workspace, array $record = []): stdClass {
        global $DB;

        $revision = (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $workspace->id]);
        $snapshot = (object) array_merge([
            'workspaceid' => $workspace->id,
            'revision' => $revision,
            'snapshotjson' => json_encode(['nodes' => [], 'relations' => [], 'containers' => []]),
            'submittedby' => $workspace->userid,
            'status' => 1,
            'cohortjson' => null,
            'timecreated' => time(),
        ], $record);
        $snapshot->id = $DB->insert_record('vimipad_snapshot', $snapshot);
        return $snapshot;
    }

    /**
     * Create a gradebook grade row for a user in an instance.
     *
     * @param stdClass $instance The vimipad instance.
     * @param int $userid The graded user.
     * @param array $record Field overrides (grade, feedback, snapshotid, grader).
     * @return stdClass The created grade record (with ->id).
     */
    public function create_grade(stdClass $instance, int $userid, array $record = []): stdClass {
        global $DB, $USER;

        $now = time();
        $grade = (object) array_merge([
            'vimipadid' => $instance->id,
            'userid' => $userid,
            'grade' => 50.0,
            'feedback' => '',
            'feedbackformat' => FORMAT_HTML,
            'snapshotid' => null,
            'grader' => $USER->id ?: $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);
        $grade->id = $DB->insert_record('vimipad_grade', $grade);
        return $grade;
    }

    /**
     * Create a peer-review allocation/record against a snapshot.
     *
     * @param stdClass $snapshot The snapshot record.
     * @param int $reviewerid The reviewing user.
     * @param array $record Field overrides (status, score, reviewcomment).
     * @return stdClass The created peer-review record (with ->id).
     */
    public function create_peer_review(stdClass $snapshot, int $reviewerid, array $record = []): stdClass {
        global $DB;

        $now = time();
        $review = (object) array_merge([
            'snapshotid' => $snapshot->id,
            'reviewerid' => $reviewerid,
            'status' => 0,
            'score' => null,
            'reviewcomment' => '',
            'commentformat' => FORMAT_HTML,
            'timeallocated' => $now,
            'timemodified' => $now,
        ], $record);
        $review->id = $DB->insert_record('vimipad_peerreview', $review);
        return $review;
    }

    /**
     * Build a load-test map of a named size (small/medium/large), seeding nodes,
     * relations and containers so a single call produces a realistic workspace.
     *
     * Sizes (nodes/relations/containers): small 20/30/5, medium 200/400/40,
     * large 1000/2000/200. These are test profiles, not product limits.
     *
     * @param stdClass $instance The vimipad instance.
     * @param int $userid The owning user.
     * @param string $size One of 'small', 'medium', 'large'.
     * @return stdClass The workspace record.
     */
    public function create_map_profile(stdClass $instance, int $userid, string $size = 'small'): stdClass {
        $sizes = [
            'small' => ['nodes' => 20, 'relations' => 30, 'containers' => 5],
            'medium' => ['nodes' => 200, 'relations' => 400, 'containers' => 40],
            'large' => ['nodes' => 1000, 'relations' => 2000, 'containers' => 200],
        ];
        $spec = $sizes[$size] ?? $sizes['small'];

        $workspace = $this->create_workspace($instance, $userid);

        $nodeids = [];
        for ($i = 0; $i < $spec['nodes']; $i++) {
            $node = $this->create_node($workspace, ['label' => 'N' . $i]);
            $nodeids[] = $node->stableid;
        }
        $n = max(count($nodeids), 1);
        for ($i = 0; $i < $spec['relations']; $i++) {
            $this->create_relation($workspace, [
                'sourceid' => $nodeids[$i % $n],
                'targetid' => $nodeids[($i + 1) % $n],
                'label' => 'r' . $i,
            ]);
        }
        for ($i = 0; $i < $spec['containers']; $i++) {
            $this->create_container($workspace, [
                'label' => 'C' . $i,
                'geometryjson' => json_encode(['x' => 50 + $i * 20, 'y' => 50, 'w' => 200, 'h' => 150]),
            ]);
        }
        return $workspace;
    }

    /**
     * Append a long collaboration/operation history to a workspace, for replay
     * and load benchmarks.
     *
     * @param stdClass $workspace The workspace record.
     * @param int $count How many operations to append.
     * @return int The workspace's revision after appending.
     */
    public function create_collaboration_history(stdClass $workspace, int $count): int {
        return $this->create_operations($workspace, $count);
    }

    /** @var int Monotonic counter for deterministic, unique stable ids. */
    private $stableseq = 0;

    /**
     * A unique, deterministic stable id with the given prefix, within the 40
     * character column limit.
     *
     * @param string $prefix A short kind prefix (node, rel, cont).
     * @return string The stable id.
     */
    private function stableid(string $prefix): string {
        $this->stableseq++;
        return substr($prefix . '_' . str_pad((string) $this->stableseq, 12, '0', STR_PAD_LEFT), 0, 40);
    }
}
