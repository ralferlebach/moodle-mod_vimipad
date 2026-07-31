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
 * Imports a ViMi Pad JSON export into a workspace.
 *
 * The counterpart to {@see export_service}. Nodes and relations from the
 * envelope are appended to the target workspace through the validated operation
 * path, so revisions advance and collaborators receive the changes. Imported
 * elements get fresh stable ids; relation endpoints are remapped accordingly.
 * The whole import is atomic. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_service {
    /**
     * Import a JSON export envelope into a workspace.
     *
     * @param string $json The JSON export document.
     * @param stdClass $workspace The target workspace record.
     * @param int $userid The acting user id.
     * @param string $mode 'append' (default) or 'replace'.
     * @return array{nodes: int, relations: int, containers: int, memberships: int} Counts of imported elements.
     * @throws \moodle_exception If the document is not a valid ViMi Pad export.
     */
    public function import_json(string $json, stdClass $workspace, int $userid, string $mode = 'append'): array {
        $envelope = json_decode($json, true);
        if (!is_array($envelope) || ($envelope['generator'] ?? '') !== 'mod_vimipad') {
            throw new \moodle_exception('error:importformat', 'mod_vimipad');
        }
        self::assert_supported_version($envelope['formatversion'] ?? null);
        $data = $envelope['data'] ?? null;
        if (!is_array($data)) {
            throw new \moodle_exception('error:importformat', 'mod_vimipad');
        }

        return $this->apply_data($data, $workspace, $userid, $mode);
    }

    /**
     * Import an XML export document into a workspace.
     *
     * @param string $xml The XML export document.
     * @param stdClass $workspace The target workspace record.
     * @param int $userid The acting user id.
     * @param string $mode 'append' (default) or 'replace'.
     * @return array{nodes: int, relations: int, containers: int, memberships: int} Counts of imported elements.
     * @throws \moodle_exception If the document is not a valid ViMi Pad export.
     */
    public function import_xml(string $xml, stdClass $workspace, int $userid, string $mode = 'append'): array {
        return $this->apply_data($this->parse_xml($xml), $workspace, $userid, $mode);
    }

    /**
     * Parse a ViMi Pad XML export into the normalized node/relation structure.
     *
     * @param string $xml The XML document.
     * @return array{nodes: array, relations: array, containers: array, memberships: array, layout: array|null}
     * @throws \moodle_exception If the document is not a valid ViMi Pad export.
     */
    private function parse_xml(string $xml): array {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (
            $doc === false
                || $doc->getName() !== 'vimipad'
                || (string) ($doc['generator'] ?? '') !== 'mod_vimipad'
        ) {
            throw new \moodle_exception('error:importformat', 'mod_vimipad');
        }
        $versionattr = (string) ($doc['formatversion'] ?? '');
        self::assert_supported_version($versionattr === '' ? null : $versionattr);

        $nodes = [];
        if (isset($doc->nodes)) {
            foreach ($doc->nodes->node as $node) {
                $nodes[] = self::element_to_array($node);
            }
        }
        $relations = [];
        if (isset($doc->relations)) {
            foreach ($doc->relations->relation as $relation) {
                $relations[] = self::element_to_array($relation);
            }
        }
        $containers = [];
        if (isset($doc->containers)) {
            foreach ($doc->containers->container as $container) {
                $containers[] = self::element_to_array($container);
            }
        }
        $memberships = [];
        if (isset($doc->memberships)) {
            foreach ($doc->memberships->membership as $membership) {
                $memberships[] = self::element_to_array($membership);
            }
        }

        $layout = null;
        if (isset($doc->layout)) {
            $decoded = json_decode((string) $doc->layout, true);
            if (is_array($decoded)) {
                $layout = $decoded;
            }
        }

        return [
            'nodes' => $nodes,
            'relations' => $relations,
            'containers' => $containers,
            'memberships' => $memberships,
            'layout' => $layout,
        ];
    }

    /**
     * Convert an XML record element's child elements to an associative array.
     *
     * @param \SimpleXMLElement $element The record element.
     * @return array<string,string>
     */
    private static function element_to_array(\SimpleXMLElement $element): array {
        $out = [];
        foreach ($element->children() as $child) {
            $out[$child->getName()] = (string) $child;
        }
        return $out;
    }

    /**
     * Import a normalized data structure into a workspace under the shared
     * workspace write lock, so the whole import runs as one serialized unit
     * against concurrent edits and submissions.
     *
     * Atomicity contract: the semantic import (nodes + relations) is atomic —
     * it commits or rolls back as a whole. The layout is then applied on a
     * best-effort basis after commit; a layout failure does not undo the
     * imported elements (positions are non-critical).
     *
     * @param array $data The normalized data (with 'nodes' and 'relations').
     * @param stdClass $workspace The target workspace record.
     * @param int $userid The acting user id.
     * @param string $mode 'append' (default) or 'replace'.
     * @return array{nodes: int, relations: int, containers: int, memberships: int} Counts of imported elements.
     * @throws \moodle_exception On concurrency (lock contention).
     */
    private function apply_data(array $data, stdClass $workspace, int $userid, string $mode = 'append'): array {
        $lock = \mod_vimipad\local\lock\workspace_writelock::acquire((int) $workspace->id);
        try {
            return $this->apply_data_locked($data, $workspace, $userid, $mode);
        } finally {
            $lock->release();
        }
    }

    /**
     * Import body, assuming the caller holds the workspace write lock. Uses
     * operation_service::apply_locked() because this method already owns the
     * lock (a re-entrant acquire could deadlock).
     *
     * @param array $data The normalized data (with 'nodes' and 'relations').
     * @param stdClass $workspace The target workspace record.
     * @param int $userid The acting user id.
     * @param string $mode 'append' (default) or 'replace'.
     * @return array{nodes: int, relations: int, containers: int, memberships: int} Counts of imported elements.
     */
    private function apply_data_locked(array $data, stdClass $workspace, int $userid, string $mode = 'append'): array {
        global $DB;

        $nodes = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        $relations = is_array($data['relations'] ?? null) ? $data['relations'] : [];

        if (!in_array($mode, ['append', 'replace'], true)) {
            // An unknown mode must not silently fall back to append.
            throw new \invalid_parameter_exception('Unknown import mode: ' . $mode);
        }

        $operationservice = new operation_service();
        $revision = (int) $workspace->currentrevision;
        $wsid = (int) $workspace->id;
        $idmap = [];
        $nodecount = 0;
        $relationcount = 0;

        $transaction = $DB->start_delegated_transaction();

        if ($mode === 'replace') {
            // Remove the entire existing map first, so the import starts from a
            // clean workspace: nodes (deleting a node cascades to its
            // relations) and containers (deleting a container drops its
            // membership rows). The stored layout is replaced below.
            $existing = $DB->get_fieldset_select(
                'vimipad_node',
                'stableid',
                'workspaceid = :wsid AND deleted = 0',
                ['wsid' => $wsid]
            );
            foreach ($existing as $stableid) {
                $result = $operationservice->apply_locked($wsid, $revision, 'node_delete', ['stableid' => $stableid], $userid);
                $revision = (int) $result['revision'];
            }
            $existingcontainers = $DB->get_fieldset_select(
                'vimipad_container',
                'stableid',
                'workspaceid = :wsid AND deleted = 0',
                ['wsid' => $wsid]
            );
            foreach ($existingcontainers as $stableid) {
                $result = $operationservice->apply_locked(
                    $wsid,
                    $revision,
                    'container_delete',
                    ['stableid' => $stableid],
                    $userid
                );
                $revision = (int) $result['revision'];
            }
        }

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $payload = ['type' => (string) ($node['type'] ?? 'concept')];
            if (isset($node['label']) && is_string($node['label'])) {
                $payload['label'] = $node['label'];
            }
            if (isset($node['content']) && is_string($node['content'])) {
                $payload['content'] = $node['content'];
            }
            if (isset($node['metadatajson']) && is_string($node['metadatajson']) && $node['metadatajson'] !== '') {
                $payload['metadatajson'] = $node['metadatajson'];
            }

            $result = $operationservice->apply_locked($wsid, $revision, 'node_create', $payload, $userid);
            $revision = (int) $result['revision'];
            if (isset($node['stableid']) && is_string($node['stableid'])) {
                $idmap[$node['stableid']] = $result['stableid'];
            }
            $nodecount++;
        }

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }
            $source = $idmap[$relation['sourceid'] ?? ''] ?? null;
            $target = $idmap[$relation['targetid'] ?? ''] ?? null;
            if ($source === null || $target === null) {
                continue;
            }
            $payload = [
                'sourceid' => $source,
                'targetid' => $target,
                'type' => (string) ($relation['type'] ?? 'link'),
            ];
            if (isset($relation['label']) && is_string($relation['label'])) {
                $payload['label'] = $relation['label'];
            }
            if (isset($relation['direction']) && is_numeric($relation['direction'])) {
                $payload['direction'] = (int) $relation['direction'];
            }
            if (
                isset($relation['metadatajson']) && is_string($relation['metadatajson'])
                    && $relation['metadatajson'] !== ''
            ) {
                $payload['metadatajson'] = $relation['metadatajson'];
            }

            $result = $operationservice->apply_locked($wsid, $revision, 'relation_create', $payload, $userid);
            $revision = (int) $result['revision'];
            // Map the relation's old stable id to its new one, so memberships
            // referencing a relation can be remapped (node and relation stable
            // ids share one namespace, so a single idmap is sufficient).
            if (isset($relation['stableid']) && is_string($relation['stableid'])) {
                $idmap[$relation['stableid']] = $result['stableid'];
            }
            $relationcount++;
        }

        // Containers: create each (fresh stable ids), tracking old => new so
        // memberships can be remapped.
        $containeridmap = [];
        $containercount = 0;
        $containers = is_array($data['containers'] ?? null) ? $data['containers'] : [];
        foreach ($containers as $container) {
            if (!is_array($container)) {
                continue;
            }
            $payload = ['type' => (string) ($container['type'] ?? 'group')];
            foreach (['label', 'geometryjson', 'metadatajson'] as $field) {
                if (isset($container[$field]) && is_string($container[$field]) && $container[$field] !== '') {
                    $payload[$field] = $container[$field];
                }
            }
            $result = $operationservice->apply_locked($wsid, $revision, 'container_create', $payload, $userid);
            $revision = (int) $result['revision'];
            if (isset($container['stableid']) && is_string($container['stableid'])) {
                $containeridmap[$container['stableid']] = $result['stableid'];
            }
            $containercount++;
        }

        // Memberships: remap the container and the member item onto the new
        // stable ids; drop any whose referent was not imported.
        $membershipcount = 0;
        $memberships = is_array($data['memberships'] ?? null) ? $data['memberships'] : [];
        foreach ($memberships as $membership) {
            if (!is_array($membership)) {
                continue;
            }
            $newcontainer = $containeridmap[$membership['containerstableid'] ?? ''] ?? null;
            $itemtype = (string) ($membership['itemtype'] ?? '');
            $olditem = $membership['itemstableid'] ?? '';
            $newitem = $itemtype === 'container'
                ? ($containeridmap[$olditem] ?? null)
                : ($idmap[$olditem] ?? null);
            if ($newcontainer === null || $newitem === null || !in_array($itemtype, ['node', 'relation', 'container'], true)) {
                continue;
            }
            $payload = [
                'containerstableid' => $newcontainer,
                'itemtype' => $itemtype,
                'itemstableid' => $newitem,
            ];
            if (isset($membership['role']) && is_string($membership['role']) && $membership['role'] !== '') {
                $payload['role'] = $membership['role'];
            }
            if (isset($membership['sortorder']) && is_numeric($membership['sortorder'])) {
                $payload['sortorder'] = (int) $membership['sortorder'];
            }
            $result = $operationservice->apply_locked($wsid, $revision, 'membership_add', $payload, $userid);
            $revision = (int) $result['revision'];
            $membershipcount++;
        }

        $transaction->allow_commit();

        // Apply the imported layout after commit (remapped to the new stable
        // ids), so the imported arrangement is preserved. Positions are
        // non-critical: a failure here does not undo the imported elements.
        $this->apply_layout($data, $workspace, $idmap, $userid, $mode);

        return [
            'nodes' => $nodecount,
            'relations' => $relationcount,
            'containers' => $containercount,
            'memberships' => $membershipcount,
        ];
    }

    /**
     * Reject export documents with an unsupported format version.
     *
     * Version 1 is the only supported format. A missing version is read as 1
     * (all real exports carry it; the tolerance keeps hand-written documents
     * working); anything else is rejected instead of being silently
     * misinterpreted.
     *
     * @param mixed $version The declared format version, or null when absent.
     * @return void
     * @throws \moodle_exception If the version is present but unsupported.
     */
    private static function assert_supported_version(mixed $version): void {
        if ($version === null) {
            return;
        }
        if (!is_numeric($version) || (int) $version !== export_service::FORMAT_VERSION) {
            throw new \moodle_exception('error:importversion', 'mod_vimipad');
        }
    }

    /**
     * Persist the imported layout, remapped from the exported stable ids to the
     * freshly assigned ones. In append mode the layout is merged so existing
     * positions are preserved; in replace mode it replaces the stored layout
     * entirely (an import without layout clears it), matching the replace
     * semantics of the map itself.
     *
     * @param array $data The normalized data (may contain 'layout').
     * @param stdClass $workspace The target workspace record.
     * @param array $idmap Old stable id => new stable id.
     * @param int $userid The acting user id.
     * @param string $mode 'append' or 'replace'.
     * @return void
     */
    private function apply_layout(array $data, stdClass $workspace, array $idmap, int $userid, string $mode): void {
        global $DB;

        $layout = $data['layout'] ?? null;
        $layout = is_array($layout) ? $layout : [];
        $pos = self::remap_layout_map(is_array($layout['pos'] ?? null) ? $layout['pos'] : [], $idmap);
        $size = self::remap_layout_map(is_array($layout['size'] ?? null) ? $layout['size'] : [], $idmap);
        if ($mode !== 'replace' && empty($pos) && empty($size)) {
            return;
        }

        $profile = (string) $DB->get_field(
            'vimipad',
            'defaultprofile',
            ['id' => $workspace->vimipadid],
            MUST_EXIST
        );
        $layoutjson = json_encode(['v' => $layout['v'] ?? 1, 'pos' => $pos, 'size' => $size]);

        try {
            (new layout_service())->save(
                (int) $workspace->id,
                $profile,
                $layoutjson,
                '',
                $userid,
                $mode === 'replace' ? 'replace' : 'merge'
            );
        } catch (\moodle_exception $e) {
            // Positions are non-critical presentation state: the semantic
            // import has committed, so a layout lock timeout must not fail the
            // import (a retry would duplicate content in append mode).
            debugging('vimipad import: layout not applied (' . $e->getMessage() . ')', DEBUG_DEVELOPER);
        }
    }

    /**
     * Remap a layout map (stable id => value) onto the imported stable ids,
     * dropping entries whose node was not imported.
     *
     * @param array $map The stable-id-keyed map.
     * @param array $idmap Old stable id => new stable id.
     * @return array The remapped map.
     */
    private static function remap_layout_map(array $map, array $idmap): array {
        $out = [];
        foreach ($map as $oldid => $value) {
            if (isset($idmap[$oldid])) {
                $out[$idmap[$oldid]] = $value;
            }
        }
        return $out;
    }
}
