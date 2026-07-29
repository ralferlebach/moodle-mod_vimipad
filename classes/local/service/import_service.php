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
     * @return array{nodes: int, relations: int} Counts of imported elements.
     * @throws \moodle_exception If the document is not a valid ViMi Pad export.
     */
    public function import_json(string $json, stdClass $workspace, int $userid, string $mode = 'append'): array {
        $envelope = json_decode($json, true);
        if (!is_array($envelope) || ($envelope['generator'] ?? '') !== 'mod_vimipad') {
            throw new \moodle_exception('error:importformat', 'mod_vimipad');
        }
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
     * @return array{nodes: int, relations: int} Counts of imported elements.
     * @throws \moodle_exception If the document is not a valid ViMi Pad export.
     */
    public function import_xml(string $xml, stdClass $workspace, int $userid, string $mode = 'append'): array {
        return $this->apply_data($this->parse_xml($xml), $workspace, $userid, $mode);
    }

    /**
     * Parse a ViMi Pad XML export into the normalized node/relation structure.
     *
     * @param string $xml The XML document.
     * @return array{nodes: array, relations: array, layout: array|null}
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

        $layout = null;
        if (isset($doc->layout)) {
            $decoded = json_decode((string) $doc->layout, true);
            if (is_array($decoded)) {
                $layout = $decoded;
            }
        }

        return ['nodes' => $nodes, 'relations' => $relations, 'layout' => $layout];
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
     * Append the nodes and relations from a normalized data structure to a
     * workspace, atomically, through the validated operation path.
     *
     * @param array $data The normalized data (with 'nodes' and 'relations').
     * @param stdClass $workspace The target workspace record.
     * @param int $userid The acting user id.
     * @param string $mode 'append' (default) or 'replace'.
     * @return array{nodes: int, relations: int} Counts of imported elements.
     */
    private function apply_data(array $data, stdClass $workspace, int $userid, string $mode = 'append'): array {
        global $DB;

        $nodes = is_array($data['nodes'] ?? null) ? $data['nodes'] : [];
        $relations = is_array($data['relations'] ?? null) ? $data['relations'] : [];

        $operationservice = new operation_service();
        $revision = (int) $workspace->currentrevision;
        $wsid = (int) $workspace->id;
        $idmap = [];
        $nodecount = 0;
        $relationcount = 0;

        $transaction = $DB->start_delegated_transaction();

        if ($mode === 'replace') {
            // Remove the existing map first (deleting a node cascades to its
            // relations), so the import starts from a clean workspace.
            $existing = $DB->get_fieldset_select(
                'vimipad_node',
                'stableid',
                'workspaceid = :wsid AND deleted = 0',
                ['wsid' => $wsid]
            );
            foreach ($existing as $stableid) {
                $result = $operationservice->apply($wsid, $revision, 'node_delete', ['stableid' => $stableid], $userid);
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

            $result = $operationservice->apply($wsid, $revision, 'node_create', $payload, $userid);
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

            $result = $operationservice->apply($wsid, $revision, 'relation_create', $payload, $userid);
            $revision = (int) $result['revision'];
            $relationcount++;
        }

        $transaction->allow_commit();

        // Apply the imported layout after commit (remapped to the new stable
        // ids), so the imported arrangement is preserved. Positions are
        // non-critical: a failure here does not undo the imported elements.
        $this->apply_layout($data, $workspace, $idmap, $userid);

        return ['nodes' => $nodecount, 'relations' => $relationcount];
    }

    /**
     * Persist the imported layout, remapped from the exported stable ids to the
     * freshly assigned ones. Merged so existing positions are preserved.
     *
     * @param array $data The normalized data (may contain 'layout').
     * @param stdClass $workspace The target workspace record.
     * @param array $idmap Old stable id => new stable id.
     * @param int $userid The acting user id.
     * @return void
     */
    private function apply_layout(array $data, stdClass $workspace, array $idmap, int $userid): void {
        global $DB;

        $layout = $data['layout'] ?? null;
        if (!is_array($layout)) {
            return;
        }
        $pos = self::remap_layout_map(is_array($layout['pos'] ?? null) ? $layout['pos'] : [], $idmap);
        $size = self::remap_layout_map(is_array($layout['size'] ?? null) ? $layout['size'] : [], $idmap);
        if (empty($pos) && empty($size)) {
            return;
        }

        $profile = (string) $DB->get_field(
            'vimipad',
            'defaultprofile',
            ['id' => $workspace->vimipadid],
            MUST_EXIST
        );
        $layoutjson = json_encode(['v' => $layout['v'] ?? 1, 'pos' => $pos, 'size' => $size]);

        (new layout_service())->save((int) $workspace->id, $profile, $layoutjson, '', $userid, 'merge');
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
