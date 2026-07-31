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
use mod_vimipad\local\policy\limits;

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
    /** @var bool Whether template element locks are bypassed (author/manage context). */
    private bool $bypasslocks;

    /**
     * Construct the service, optionally bypassing template element locks.
     *
     * @param bool $bypasslocks If true, template element locks are not enforced,
     *                          for users who may author/manage the template
     *                          (mod/vimipad:manageprofiles). Learners get false.
     */
    public function __construct(bool $bypasslocks = false) {
        $this->bypasslocks = $bypasslocks;
    }

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
        // Serialize every semantic mutation on the shared per-workspace write
        // lock so operations cannot interleave with each other or with an
        // import/submission (which would otherwise let a snapshot capture a
        // torn read across the node/relation/container tables).
        $lock = \mod_vimipad\local\lock\workspace_writelock::acquire($workspaceid);
        try {
            return $this->apply_locked($workspaceid, $baserevision, $type, $payload, $userid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Apply a single operation, assuming the caller already holds the workspace
     * write lock (see {@see \mod_vimipad\local\lock\workspace_writelock}).
     *
     * Used by import_service, which holds the lock once across many operations;
     * external single operations go through the locking {@see apply()} wrapper.
     *
     * @param int $workspaceid The workspace id.
     * @param int $baserevision The revision the client bases the operation on (optimistic concurrency).
     * @param string $type The operation type (see operation_type).
     * @param array $payload The decoded, schema-validated payload.
     * @param int $userid The acting user id.
     * @return array{revision: int, stableid: ?string} New revision and any server-assigned stable id.
     * @throws \moodle_exception On conflict, unknown type or invalid references.
     */
    public function apply_locked(int $workspaceid, int $baserevision, string $type, array $payload, int $userid): array {
        global $DB;

        if (!operation_type::is_known($type)) {
            throw new \invalid_parameter_exception('Unknown operation type: ' . $type);
        }
        operation_type::validate_payload($type, $payload);

        $transaction = $DB->start_delegated_transaction();

        // Re-read the workspace under the write lock the caller holds, so the
        // revision check and mutation act on a consistent, current row.
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
     * @param int $limit Maximum number of operations to return (0 = no limit).
     * @return \stdClass[] Operation records (id, revision, operationtype, payloadjson, userid, timecreated).
     */
    public function get_operations_since(int $workspaceid, int $sincerevision, int $limit = 0): array {
        global $DB;

        $records = $DB->get_records_select(
            'vimipad_operation',
            'workspaceid = :wid AND revision > :rev',
            ['wid' => $workspaceid, 'rev' => $sincerevision],
            'revision ASC',
            '*',
            0,
            $limit > 0 ? $limit : 0
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
        $this->enforce_limits($workspaceid, $type, $payload);

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
                $this->assert_element_editable(
                    $node->metadatajson,
                    'update',
                    $this->changed_fields($payload, ['label', 'type', 'content', 'metadatajson'])
                );
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
                $this->assert_element_editable($node->metadatajson, 'delete');
                $DB->update_record('vimipad_node', (object) [
                    'id' => $node->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
                ]);
                // Soft-delete relations attached to this node, and drop the
                // membership rows of the node and of those relations so no
                // membership can reference a deleted element.
                $attached = $this->soft_delete_attached_relations($workspaceid, $payload['stableid'], $userid, $now);
                $this->purge_memberships($workspaceid, 'node', [$payload['stableid']]);
                if (!empty($attached)) {
                    $this->purge_memberships($workspaceid, 'relation', $attached);
                }
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
                $this->assert_element_editable(
                    $relation->metadatajson,
                    'update',
                    $this->changed_fields($payload, ['type', 'label', 'metadatajson', 'direction'])
                );
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
                $this->assert_element_editable($relation->metadatajson, 'delete');
                $DB->update_record('vimipad_relation', (object) [
                    'id' => $relation->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
                ]);
                $this->purge_memberships($workspaceid, 'relation', [$payload['stableid']]);
                return null;

            case operation_type::RELATION_RETARGET:
                $relation = $this->get_live_relation($workspaceid, $payload['stableid']);
                $this->assert_element_editable(
                    $relation->metadatajson,
                    'update',
                    (!empty($payload['newsource']) || !empty($payload['newtarget'])) ? ['endpoints'] : []
                );
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

            case operation_type::CONTAINER_CREATE:
                $stableid = $this->pick_stable_id($payload, 'container');
                $record = [
                    'workspaceid' => $workspaceid,
                    'stableid' => $stableid,
                    'type' => $payload['type'],
                    'label' => $payload['label'] ?? null,
                    'geometryjson' => $payload['geometryjson'] ?? null,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'deleted' => 0,
                ];
                // Reuse any existing row with this stable id (revive a
                // soft-deleted container), keeping the unique index intact.
                $existing = $DB->get_record('vimipad_container', [
                    'workspaceid' => $workspaceid, 'stableid' => $stableid,
                ]);
                if ($existing) {
                    $record['id'] = $existing->id;
                    $DB->update_record('vimipad_container', (object) $record);
                } else {
                    $DB->insert_record('vimipad_container', (object) $record);
                }
                return $stableid;

            case operation_type::CONTAINER_UPDATE:
                $container = $this->get_live_container($workspaceid, $payload['stableid']);
                $this->assert_element_editable(
                    $container->metadatajson,
                    'update',
                    $this->changed_fields($payload, ['type', 'label', 'geometryjson', 'metadatajson'])
                );
                $update = ['id' => $container->id];
                foreach (['type', 'label', 'geometryjson', 'metadatajson'] as $field) {
                    if (array_key_exists($field, $payload)) {
                        $update[$field] = $payload[$field];
                    }
                }
                $DB->update_record('vimipad_container', (object) $update);
                return null;

            case operation_type::CONTAINER_DELETE:
                $container = $this->get_live_container($workspaceid, $payload['stableid']);
                $this->assert_element_editable($container->metadatajson, 'delete');
                $DB->set_field('vimipad_container', 'deleted', 1, ['id' => $container->id]);
                // Membership rows are meaningless once the container is gone:
                // both the rows it owns and any row where it is itself a member
                // of another container.
                $DB->delete_records('vimipad_membership', ['containerid' => $container->id]);
                $this->purge_memberships($workspaceid, 'container', [$payload['stableid']]);
                return null;

            case operation_type::MEMBERSHIP_ADD:
                $container = $this->get_live_container($workspaceid, $payload['containerstableid']);
                // The referenced item must exist live in this workspace, and a
                // container cannot be a member of itself.
                switch ($payload['itemtype']) {
                    case 'node':
                        $this->assert_node_exists($workspaceid, $payload['itemstableid']);
                        break;
                    case 'relation':
                        $this->get_live_relation($workspaceid, $payload['itemstableid']);
                        break;
                    case 'container':
                        if ($payload['itemstableid'] === $payload['containerstableid']) {
                            throw new \invalid_parameter_exception('A container cannot be a member of itself.');
                        }
                        $this->get_live_container($workspaceid, $payload['itemstableid']);
                        break;
                }
                $criteria = [
                    'containerid' => $container->id,
                    'itemtype' => $payload['itemtype'],
                    'itemstableid' => $payload['itemstableid'],
                ];
                // Upsert: at most one membership per (container, itemtype, item).
                $existing = $DB->get_record('vimipad_membership', $criteria);
                $rec = $criteria + [
                    'role' => $payload['role'] ?? null,
                    'sortorder' => isset($payload['sortorder']) ? (int) $payload['sortorder'] : 0,
                ];
                if ($existing) {
                    $rec['id'] = $existing->id;
                    $DB->update_record('vimipad_membership', (object) $rec);
                } else {
                    $DB->insert_record('vimipad_membership', (object) $rec);
                }
                return null;

            case operation_type::MEMBERSHIP_REMOVE:
                $container = $this->get_live_container($workspaceid, $payload['containerstableid']);
                $DB->delete_records('vimipad_membership', [
                    'containerid' => $container->id,
                    'itemtype' => $payload['itemtype'],
                    'itemstableid' => $payload['itemstableid'],
                ]);
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
     * Fetch a live (not soft-deleted) container by its stable id.
     *
     * @param int $workspaceid The workspace id.
     * @param string $stableid The container stable id.
     * @return \stdClass The container record.
     * @throws \moodle_exception If not found.
     */
    private function get_live_container(int $workspaceid, string $stableid): \stdClass {
        global $DB;
        $container = $DB->get_record(
            'vimipad_container',
            ['workspaceid' => $workspaceid, 'stableid' => $stableid, 'deleted' => 0]
        );
        if (!$container) {
            throw new \moodle_exception('error:containernotfound', 'mod_vimipad');
        }
        return $container;
    }

    /**
     * Enforce a template structural lock carried in an element's metadata.
     *
     * A locked element (metadata `{"locked": true}`) cannot be deleted, and can
     * only be updated in the fields listed in its `editable` whitelist (e.g.
     * `{"locked": true, "editable": ["label"]}`). Elements without the flag are
     * unaffected. This protects teacher-provided scaffolds while leaving the
     * rest of the map freely editable.
     *
     * @param string|null $metadatajson The live element's metadata JSON.
     * @param string $mode 'delete' or 'update'.
     * @param string[] $changedfields Fields the update would change (update mode).
     * @return void
     * @throws \moodle_exception error:elementlocked if the change is not permitted.
     */
    private function assert_element_editable(?string $metadatajson, string $mode, array $changedfields = []): void {
        if ($this->bypasslocks) {
            return;
        }
        if ($metadatajson === null || $metadatajson === '') {
            return;
        }
        $meta = json_decode($metadatajson, true);
        if (!is_array($meta) || empty($meta['locked'])) {
            return;
        }
        if ($mode === 'delete') {
            throw new \moodle_exception('error:elementlocked', 'mod_vimipad');
        }
        $editable = is_array($meta['editable'] ?? null) ? $meta['editable'] : [];
        foreach ($changedfields as $field) {
            if (!in_array($field, $editable, true)) {
                throw new \moodle_exception('error:elementlocked', 'mod_vimipad');
            }
        }
    }

    /**
     * The subset of candidate fields the payload would actually change.
     *
     * @param array $payload The operation payload.
     * @param string[] $candidates Mutable field names for the element.
     * @return string[]
     */
    private function changed_fields(array $payload, array $candidates): array {
        return array_values(array_intersect($candidates, array_keys($payload)));
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
     * @return string[] The stable ids of the relations that were soft-deleted.
     */
    private function soft_delete_attached_relations(int $workspaceid, string $nodestableid, int $userid, int $now): array {
        global $DB;
        $relations = $DB->get_records_select(
            'vimipad_relation',
            'workspaceid = :wsid AND deleted = 0 AND (sourceid = :src OR targetid = :tgt)',
            ['wsid' => $workspaceid, 'src' => $nodestableid, 'tgt' => $nodestableid]
        );
        $stableids = [];
        foreach ($relations as $relation) {
            $DB->update_record('vimipad_relation', (object) [
                'id' => $relation->id, 'deleted' => 1, 'modifiedby' => $userid, 'timemodified' => $now,
            ]);
            $stableids[] = $relation->stableid;
        }
        return $stableids;
    }

    /**
     * Enforce the resource limits for a mutating operation.
     *
     * Text lengths and geometry are checked on every payload that carries the
     * fields; element-count ceilings are checked on the create operations. The
     * import path funnels through here as well, so imported maps obey the same
     * envelope.
     *
     * @param int $workspaceid The target workspace id.
     * @param string $type The operation type.
     * @param array $payload The operation payload.
     * @return void
     * @throws \moodle_exception error:maplimit, error:textlimit or error:invalidgeometry.
     */
    private function enforce_limits(int $workspaceid, string $type, array $payload): void {
        global $DB;

        limits::check_text($payload['label'] ?? null, limits::MAX_LABEL, 'label');
        limits::check_text($payload['content'] ?? null, limits::MAX_CONTENT, 'content');
        limits::check_text($payload['metadatajson'] ?? null, limits::MAX_METADATA, 'metadata');
        if (array_key_exists('geometryjson', $payload)) {
            limits::check_geometry($payload['geometryjson']);
        }

        switch ($type) {
            case operation_type::NODE_CREATE:
                limits::check_count(
                    $DB->count_records('vimipad_node', ['workspaceid' => $workspaceid, 'deleted' => 0]),
                    limits::MAX_NODES,
                    'nodes'
                );
                break;
            case operation_type::RELATION_CREATE:
                limits::check_count(
                    $DB->count_records('vimipad_relation', ['workspaceid' => $workspaceid, 'deleted' => 0]),
                    limits::MAX_RELATIONS,
                    'relations'
                );
                break;
            case operation_type::CONTAINER_CREATE:
                limits::check_count(
                    $DB->count_records('vimipad_container', ['workspaceid' => $workspaceid, 'deleted' => 0]),
                    limits::MAX_CONTAINERS,
                    'containers'
                );
                break;
        }
    }

    /**
     * Delete all membership rows in this workspace that reference the given items.
     *
     * Keeps the membership store free of rows pointing at deleted elements.
     *
     * @param int $workspaceid The workspace id.
     * @param string $itemtype The member item type (node, relation, container).
     * @param string[] $itemstableids The member stable ids.
     * @return void
     */
    private function purge_memberships(int $workspaceid, string $itemtype, array $itemstableids): void {
        global $DB;
        if (empty($itemstableids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($itemstableids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(
            'vimipad_membership',
            "itemtype = :itemtype AND itemstableid $insql AND containerid IN (
                SELECT id FROM {vimipad_container} WHERE workspaceid = :wsid
            )",
            array_merge($inparams, ['itemtype' => $itemtype, 'wsid' => $workspaceid])
        );
    }
}
