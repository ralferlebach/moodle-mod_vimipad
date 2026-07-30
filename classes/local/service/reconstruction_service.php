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

use mod_vimipad\local\operation\operation_type;

/**
 * Rebuilds the node/relation state of a workspace at a past revision.
 *
 * The operation log stores each change with its server-assigned stable id, so
 * replaying the logged payloads in order up to a target revision reproduces the
 * exact topology at that point — used to show the editing state a journal entry
 * refers to, read-only.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconstruction_service {
    /**
     * Reconstruct the surviving nodes and relations at the given revision.
     *
     * @param int $workspaceid The workspace id.
     * @param int $revision The revision to rebuild (inclusive).
     * @return array The reconstructed state: nodes, relations and containers (stdClass[]).
     */
    public function reconstruct(int $workspaceid, int $revision): array {
        global $DB;

        $ops = $DB->get_records_select(
            'vimipad_operation',
            'workspaceid = :wid AND revision <= :rev',
            ['wid' => $workspaceid, 'rev' => $revision],
            'revision ASC'
        );

        $nodes = [];
        $relations = [];
        $containers = [];
        foreach ($ops as $op) {
            $payload = json_decode($op->payloadjson, true);
            if (!is_array($payload)) {
                continue;
            }
            $this->apply($op->operationtype, $payload, $nodes, $relations, $containers);
        }

        $survivingnodes = [];
        foreach ($nodes as $node) {
            if (empty($node->deleted)) {
                $survivingnodes[] = $node;
            }
        }

        // Only keep relations that are live and whose endpoints still exist.
        $survivingrelations = [];
        foreach ($relations as $relation) {
            if (!empty($relation->deleted)) {
                continue;
            }
            $sourcelive = isset($nodes[$relation->sourceid]) && empty($nodes[$relation->sourceid]->deleted);
            $targetlive = isset($nodes[$relation->targetid]) && empty($nodes[$relation->targetid]->deleted);
            if ($sourcelive && $targetlive) {
                $survivingrelations[] = $relation;
            }
        }

        $survivingcontainers = [];
        foreach ($containers as $container) {
            if (empty($container->deleted)) {
                $survivingcontainers[] = $container;
            }
        }

        return [
            'nodes' => $survivingnodes,
            'relations' => $survivingrelations,
            'containers' => $survivingcontainers,
        ];
    }

    /**
     * Apply a single logged operation to the in-memory state.
     *
     * @param string $type The operation type.
     * @param array $payload The decoded operation payload (carries the stable id).
     * @param array $nodes In/out: nodes keyed by stable id.
     * @param array $relations In/out: relations keyed by stable id.
     * @param array $containers In/out: containers keyed by stable id.
     * @return void
     */
    private function apply(
        string $type,
        array $payload,
        array &$nodes,
        array &$relations,
        array &$containers
    ): void {
        $stableid = $payload['stableid'] ?? null;
        if ($stableid === null) {
            return;
        }

        switch ($type) {
            case operation_type::NODE_CREATE:
                $nodes[$stableid] = (object) [
                    'stableid' => $stableid,
                    'type' => $payload['type'] ?? 'concept',
                    'label' => $payload['label'] ?? null,
                    'content' => $payload['content'] ?? null,
                    'contentformat' => FORMAT_HTML,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'deleted' => 0,
                ];
                break;

            case operation_type::NODE_UPDATE:
                if (isset($nodes[$stableid])) {
                    foreach (['label', 'type', 'content', 'metadatajson'] as $field) {
                        if (array_key_exists($field, $payload)) {
                            $nodes[$stableid]->$field = $payload[$field];
                        }
                    }
                }
                break;

            case operation_type::NODE_DELETE:
                if (isset($nodes[$stableid])) {
                    $nodes[$stableid]->deleted = 1;
                }
                foreach ($relations as $relation) {
                    if ($relation->sourceid === $stableid || $relation->targetid === $stableid) {
                        $relation->deleted = 1;
                    }
                }
                break;

            case operation_type::RELATION_CREATE:
                $relations[$stableid] = (object) [
                    'stableid' => $stableid,
                    'sourceid' => $payload['sourceid'] ?? '',
                    'targetid' => $payload['targetid'] ?? '',
                    'type' => $payload['type'] ?? 'link',
                    'label' => $payload['label'] ?? null,
                    'direction' => isset($payload['direction']) ? (int) $payload['direction'] : 1,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'deleted' => 0,
                ];
                break;

            case operation_type::RELATION_UPDATE:
                if (isset($relations[$stableid])) {
                    foreach (['label', 'type', 'direction', 'metadatajson'] as $field) {
                        if (array_key_exists($field, $payload)) {
                            $relations[$stableid]->$field = $payload[$field];
                        }
                    }
                }
                break;

            case operation_type::RELATION_DELETE:
                if (isset($relations[$stableid])) {
                    $relations[$stableid]->deleted = 1;
                }
                break;

            case operation_type::RELATION_RETARGET:
                if (isset($relations[$stableid])) {
                    if (array_key_exists('sourceid', $payload)) {
                        $relations[$stableid]->sourceid = $payload['sourceid'];
                    }
                    if (array_key_exists('targetid', $payload)) {
                        $relations[$stableid]->targetid = $payload['targetid'];
                    }
                }
                break;

            case operation_type::CONTAINER_CREATE:
                $containers[$stableid] = (object) [
                    'stableid' => $stableid,
                    'type' => $payload['type'] ?? 'group',
                    'label' => $payload['label'] ?? null,
                    'geometryjson' => $payload['geometryjson'] ?? null,
                    'metadatajson' => $payload['metadatajson'] ?? null,
                    'deleted' => 0,
                ];
                break;

            case operation_type::CONTAINER_UPDATE:
                if (isset($containers[$stableid])) {
                    foreach (['type', 'label', 'geometryjson', 'metadatajson'] as $field) {
                        if (array_key_exists($field, $payload)) {
                            $containers[$stableid]->$field = $payload[$field];
                        }
                    }
                }
                break;

            case operation_type::CONTAINER_DELETE:
                if (isset($containers[$stableid])) {
                    $containers[$stableid]->deleted = 1;
                }
                break;
        }
    }
}
