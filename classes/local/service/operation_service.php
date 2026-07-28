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

use mod_vimipad\local\id\stable_id;
use mod_vimipad\local\operation\operation_type;

/**
 * Applies validated operations to a workspace and maintains the operation log.
 *
 * This is the single source of truth for mutations: the client never writes
 * directly. Each applied operation is logged with a server-assigned revision;
 * optimistic concurrency is enforced by comparing the client's base revision
 * against the workspace's current revision. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class operation_service {
    /**
     * Apply a single operation to a workspace.
     *
     * @param int $workspaceid The target workspace id.
     * @param int $baserevision The revision the client based this operation on.
     * @param string $type The operation type (see operation_type).
     * @param array $payload The decoded, schema-validated payload.
     * @param int $userid The acting user id.
     * @return array{revision: int, stableid: ?string} New revision and any server-assigned stable id.
     * @throws \moodle_exception On lock, conflict, unknown type or invalid references.
     */
    public function apply(int $workspaceid, int $baserevision, string $type, array $payload, int $userid): array {
        global $DB;

        if (!operation_type::is_known($type)) {
            throw new \invalid_parameter_exception('Unknown operation type: ' . $type);
        }
        operation_type::validate_payload($type, $payload);

        $transaction = $DB->start_delegated_transaction();

        // Lock the workspace row for the duration of the transaction.
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $workspaceid], '*', MUST_EXIST);

        if ((int) $workspace->locked === 1) {
            throw new \moodle_exception('error:workspacelocked', 'mod_vimipad');
        }

        if ((int) $workspace->currentrevision !== $baserevision) {
            throw new \moodle_exception('error:revisionconflict', 'mod_vimipad', '', (object) [
                'expected' => $baserevision,
                'current' => (int) $workspace->currentrevision,
            ]);
        }

        $newrevision = (int) $workspace->currentrevision + 1;
        $stableid = $this->mutate($workspaceid, $type, $payload, $userid);

        // Ensure the logged operation carries the server-assigned stable id.
        // Create operations are sent without one (the server assigns it), so
        // without this a collaborator applying the operation from the poll feed
        // would have no id to add and would drop it (the element would only
        // appear for them after a full reload).
        if ($stableid !== null && !isset($payload['stableid'])) {
            $payload['stableid'] = $stableid;
        }

        // Append to the operation log.
        $DB->insert_record('vimipad_operation', (object) [
            'workspaceid' => $workspaceid,
            'revision' => $newrevision,
            'operationtype' => $type,
            'payloadjson' => json_encode($payload),
            'userid' => $userid,
            'timecreated' => time(),
        ]);

        // Advance the workspace revision.
        $DB->update_record('vimipad_workspace', (object) [
            'id' => $workspaceid,
            'currentrevision' => $newrevision,
            'timemodified' => time(),
        ]);

        $transaction->allow_commit();

        return ['revision' => $newrevision, 'stableid' => $stableid];
    }

    /**
     * Return operations applied after the given revision, oldest first.
     *
     * Used by poll_changes so a client can fetch only the delta since the
     * revision it last saw. Returned as a 0-indexed list in revision order.
     *
     * @param int $workspaceid The workspace id.
     * @param int $sincerevision The revision the client already has.
     * @return \stdClass[] Operation records (id, revision, operationtype, payloadjson, userid, timecreated).
     */
    public function get_operations_since(int $workspaceid, int $sincerevision): array {
        global $DB;

        $records = $DB->get_records_select(
            'vimipad_operation',
            'workspaceid = :wid AND revision > :rev',
            ['wid' => $workspaceid, 'rev' => $sincerevision],
            'revision ASC'
        );

        return array_values($records);
    }

    /**
     * Perform the concrete table mutation for an operation.
     *
     * @param int $workspaceid The workspace id.
     * @param string $type The operation type.
     * @param array $payload The validated payload.
     * @param int $userid The acting user id.
     * @return string|null The server-assigned stable id for create operations.
     * @throws \moodle_exception On invalid references.
     */
    private function mutate(int $workspaceid, string $type, array $payload, int $userid): ?string {
        global $DB;

        $now = time();

        switch ($type) {
            case operation_type::NODE_CREATE:
                $stableid = $this->pick_stable_id($payload, 'node');
                $record = [
                    'workspaceid' => $workspaceid,
                    'stableid' => $stableid,
                    'type' => $payload['type'],
                    'label' => $payload['label'] ?? null,
                    'content' => $payload['content'] ?? null,
                    'contentformat' => FORMAT_HTML,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'modifiedby' => $userid,
                    'timemodified' => $now,
                    'deleted' => 0,
                ];
                // Reuse any existing row with this stable id (revives a
                // soft-deleted node), so undo of a deletion / redo of a creation
                // does not violate the unique (workspaceid, stableid) index.
                $existing = $DB->get_record('vimipad_node', [
                    'workspaceid' => $workspaceid, 'stableid' => $stableid,
                ]);
                if ($existing) {
                    $record['id'] = $existing->id;
                    $DB->update_record('vimipad_node', (object) $record);
                } else {
                    $record['createdby'] = $userid;
                    $record['timecreated'] = $now;
                    $DB->insert_record('vimipad_node', (object) $record);
                }
                return $stableid;

            case operation_type::NODE_UPDATE:
                $node = $this->get_live_node($workspaceid, $payload['stableid']);
                $update = ['id' => $node->id, 'modifiedby' => $userid, 'timemodified' => $now];
                foreach (['label', 'type', 'content', 'metadatajson'] as $field) {
                    if (array_key_exists($field, $payload)) {
                        $update[$field] = $payload[$field];
                    }
                }
                $DB->update_record('vimipad_node', (object) $update);
                return null;

            case operation_type::NODE_DELETE:
                $node = $this->get_live_node($workspaceid, $payload['stableid']);
                $DB->update_record('vimipad_node', (object) [
                    'id' => $node->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
                ]);
                // Soft-delete relations attached to this node.
                $this->soft_delete_attached_relations($workspaceid, $payload['stableid'], $userid, $now);
                return null;

            case operation_type::RELATION_CREATE:
                $this->assert_node_exists($workspaceid, $payload['sourceid']);
                $this->assert_node_exists($workspaceid, $payload['targetid']);
                $stableid = $this->pick_stable_id($payload, 'relation');
                $record = [
                    'workspaceid' => $workspaceid,
                    'stableid' => $stableid,
                    'sourceid' => $payload['sourceid'],
                    'targetid' => $payload['targetid'],
                    'type' => $payload['type'],
                    'label' => $payload['label'] ?? null,
                    'direction' => isset($payload['direction']) ? (int) $payload['direction'] : 1,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'modifiedby' => $userid,
                    'timemodified' => $now,
                    'deleted' => 0,
                ];
                // Reuse any existing row with this stable id (revives a
                // soft-deleted relation), unique-index safe as for nodes.
                $existing = $DB->get_record('vimipad_relation', [
                    'workspaceid' => $workspaceid, 'stableid' => $stableid,
                ]);
                if ($existing) {
                    $record['id'] = $existing->id;
                    $DB->update_record('vimipad_relation', (object) $record);
                } else {
                    $record['createdby'] = $userid;
                    $record['timecreated'] = $now;
                    $DB->insert_record('vimipad_relation', (object) $record);
                }
                return $stableid;

            case operation_type::RELATION_UPDATE:
                $relation = $this->get_live_relation($workspaceid, $payload['stableid']);
                $update = ['id' => $relation->id, 'modifiedby' => $userid, 'timemodified' => $now];
                foreach (['type', 'label', 'metadatajson'] as $field) {
                    if (array_key_exists($field, $payload)) {
                        $update[$field] = $payload[$field];
                    }
                }
                if (array_key_exists('direction', $payload)) {
                    $update['direction'] = (int) $payload['direction'];
                }
                $DB->update_record('vimipad_relation', (object) $update);
                return null;

            case operation_type::RELATION_DELETE:
                $relation = $this->get_live_relation($workspaceid, $payload['stableid']);
                $DB->update_record('vimipad_relation', (object) [
                    'id' => $relation->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
                ]);
                return null;

            case operation_type::RELATION_RETARGET:
                $relation = $this->get_live_relation($workspaceid, $payload['stableid']);
                $update = ['id' => $relation->id, 'modifiedby' => $userid, 'timemodified' => $now];
                if (!empty($payload['newsource'])) {
                    $this->assert_node_exists($workspaceid, $payload['newsource']);
                    $update['sourceid'] = $payload['newsource'];
                }
                if (!empty($payload['newtarget'])) {
                    $this->assert_node_exists($workspaceid, $payload['newtarget']);
                    $update['targetid'] = $payload['newtarget'];
                }
                $DB->update_record('vimipad_relation', (object) $update);
                return null;

            default:
                throw new \invalid_parameter_exception('Unhandled operation type: ' . $type);
        }
    }

    /**
     * Choose a stable id from the payload or generate a fresh, valid one.
     *
     * @param array $payload The payload.
     * @param string $kind One of 'node', 'relation', 'container'.
     * @return string
     * @throws \invalid_parameter_exception If a supplied stable id is malformed.
     */
    private function pick_stable_id(array $payload, string $kind): string {
        if (!empty($payload['stableid'])) {
            if (!stable_id::is_valid($payload['stableid'], $kind)) {
                throw new \invalid_parameter_exception('Malformed stable id for ' . $kind);
            }
            return $payload['stableid'];
        }
        return stable_id::generate($kind);
    }

    /**
     * Fetch a non-deleted node by stable id.
     *
     * @param int $workspaceid The workspace id.
     * @param string $stableid The node stable id.
     * @return \stdClass
     * @throws \moodle_exception If not found.
     */
    private function get_live_node(int $workspaceid, string $stableid): \stdClass {
        global $DB;
        $node = $DB->get_record(
            'vimipad_node',
            ['workspaceid' => $workspaceid, 'stableid' => $stableid, 'deleted' => 0]
        );
        if (!$node) {
            throw new \moodle_exception('error:nodenotfound', 'mod_vimipad');
        }
        return $node;
    }

    /**
     * Fetch a non-deleted relation by stable id.
     *
     * @param int $workspaceid The workspace id.
     * @param string $stableid The relation stable id.
     * @return \stdClass
     * @throws \moodle_exception If not found.
     */
    private function get_live_relation(int $workspaceid, string $stableid): \stdClass {
        global $DB;
        $relation = $DB->get_record(
            'vimipad_relation',
            ['workspaceid' => $workspaceid, 'stableid' => $stableid, 'deleted' => 0]
        );
        if (!$relation) {
            throw new \moodle_exception('error:relationnotfound', 'mod_vimipad');
        }
        return $relation;
    }

    /**
     * Assert that a live node with the given stable id exists.
     *
     * @param int $workspaceid The workspace id.
     * @param string $stableid The node stable id.
     * @return void
     * @throws \moodle_exception If not found.
     */
    private function assert_node_exists(int $workspaceid, string $stableid): void {
        global $DB;
        $exists = $DB->record_exists(
            'vimipad_node',
            ['workspaceid' => $workspaceid, 'stableid' => $stableid, 'deleted' => 0]
        );
        if (!$exists) {
            throw new \moodle_exception('error:nodenotfound', 'mod_vimipad');
        }
    }

    /**
     * Soft-delete all live relations touching a node.
     *
     * @param int $workspaceid The workspace id.
     * @param string $nodestableid The node stable id.
     * @param int $userid The acting user id.
     * @param int $now Timestamp.
     * @return void
     */
    private function soft_delete_attached_relations(int $workspaceid, string $nodestableid, int $userid, int $now): void {
        global $DB;
        $relations = $DB->get_records_select(
            'vimipad_relation',
            'workspaceid = :wsid AND deleted = 0 AND (sourceid = :src OR targetid = :tgt)',
            ['wsid' => $workspaceid, 'src' => $nodestableid, 'tgt' => $nodestableid]
        );
        foreach ($relations as $relation) {
            $DB->update_record('vimipad_relation', (object) [
                'id' => $relation->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
            ]);
        }
    }
}
