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

namespace mod_vimipad\api;

use mod_vimipad\local\policy\limits;
use mod_vimipad\local\style\node_style;
use mod_vimipad\profile\profiles;

/**
 * Public validation policy for ViMi Pad map values (the JSON documents that
 * consumer plugins store and hand back).
 *
 * Plugins such as qtype_vimipad, datafield_vimipad and mod_vimigallery persist
 * whole map documents that originate from a client and can therefore be forged.
 * Without a shared contract each of them would have to re-derive ViMi Pad's
 * limits, which is how a manipulated request ends up storing something the
 * editor itself could never produce. This facade is the single public place
 * where those limits live: it is stable, context-free and safe to call from any
 * plugin, unlike the internal classes under \mod_vimipad\local.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The class-level complexity rule is suppressed: a policy validator is by nature
 * a collection of independent checks, and its total complexity is the sum of
 * them. Each individual check is small; merging them would not reduce the work,
 * and splitting the class would scatter one contract across several files.
 */
class value {
    /** @var int Maximum accepted size of a map document in bytes. */
    public const MAX_BYTES = limits::MAX_IMPORT_BYTES;

    /** @var int Maximum accepted length of a stable id. */
    public const MAX_STABLEID = 64;

    /**
     * Whether a stable id is acceptable in a stored map value.
     *
     * Deliberately looser than the canonical generator format: existing values
     * stored by consumer plugins predate that format, and rejecting them would
     * break editing of legitimate old records. What matters for safety is that
     * the id is a bounded, plain string that can be compared and resolved.
     *
     * @param mixed $value The candidate id.
     * @return bool True when the id is acceptable.
     */
    private static function is_acceptable_id($value): bool {
        if (!is_string($value) || $value === '') {
            return false;
        }
        if (\core_text::strlen($value) > self::MAX_STABLEID) {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z0-9_.:-]+$/', $value);
    }

    /**
     * Validate a map document and collect every problem found.
     *
     * @param string $json The map document.
     * @param string|null $expectedprofile Require this profile, or null to accept any known profile.
     * @param array $allowedshapes Permitted node shapes; empty means any shape the profile allows.
     * @return array List of error identifiers; empty when the document is valid.
     */
    public static function validate(
        string $json,
        ?string $expectedprofile = null,
        array $allowedshapes = []
    ): array {
        if (strlen($json) > self::MAX_BYTES) {
            return ['toolarge'];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['notjson'];
        }

        $errors = self::check_profile($data, $expectedprofile);

        // Element collections must be lists before anything else can be said.
        foreach (['nodes', 'relations', 'containers', 'memberships'] as $kind) {
            if (array_key_exists($kind, $data) && (!is_array($data[$kind]) || !array_is_list($data[$kind]))) {
                $errors[] = 'badstructure';
                return array_values(array_unique($errors));
            }
        }

        $errors = array_merge($errors, self::check_counts($data));

        $profile = isset($data['profile']) && is_string($data['profile']) ? $data['profile'] : '';
        [$nodeerrors, $nodeids] = self::check_nodes($data['nodes'] ?? [], $profile, $allowedshapes);
        [$relerrors, $relationids] = self::check_relations($data['relations'] ?? [], $nodeids);
        [$conterrors, $containerids] = self::check_containers($data['containers'] ?? []);
        $membererrors = self::check_memberships(
            $data['memberships'] ?? [],
            $nodeids,
            $relationids,
            $containerids
        );

        $errors = array_merge($errors, $nodeerrors, $relerrors, $conterrors, $membererrors);
        return array_values(array_unique($errors));
    }

    /**
     * Check that the document declares a known (and, if required, expected) profile.
     *
     * @param array $data The decoded document.
     * @param string|null $expectedprofile The required profile, or null.
     * @return array The error identifiers.
     */
    private static function check_profile(array $data, ?string $expectedprofile): array {
        $profile = isset($data['profile']) && is_string($data['profile']) ? $data['profile'] : '';
        if ($profile === '' || !profiles::exists($profile)) {
            return ['unknownprofile'];
        }
        if ($expectedprofile !== null && $profile !== $expectedprofile) {
            return ['wrongprofile'];
        }
        return [];
    }

    /**
     * Check the element counts against the policy limits.
     *
     * @param array $data The decoded document.
     * @return array The error identifiers.
     */
    private static function check_counts(array $data): array {
        $errors = [];
        if (count($data['nodes'] ?? []) > limits::MAX_NODES) {
            $errors[] = 'toomanynodes';
        }
        if (count($data['relations'] ?? []) > limits::MAX_RELATIONS) {
            $errors[] = 'toomanyrelations';
        }
        if (count($data['containers'] ?? []) > limits::MAX_CONTAINERS) {
            $errors[] = 'toomanycontainers';
        }
        return $errors;
    }

    /**
     * Check the nodes: usable unique ids, bounded text, permitted shapes.
     *
     * @param array $nodes The node definitions.
     * @param string $profile The document's profile key.
     * @param array $allowedshapes Extra consumer restriction; empty means none.
     * @return array [error identifiers, set of node ids]
     */
    private static function check_nodes(array $nodes, string $profile, array $allowedshapes): array {
        $errors = [];
        $nodeids = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                $errors[] = 'badnode';
                continue;
            }
            if (!self::is_acceptable_id($node['stableid'] ?? null)) {
                $errors[] = 'badstableid';
                continue;
            }
            $stableid = (string) $node['stableid'];
            if (isset($nodeids[$stableid])) {
                $errors[] = 'duplicateid';
            }
            $nodeids[$stableid] = true;

            $errors = array_merge($errors, self::check_node_content($node, $profile, $allowedshapes));
        }
        return [$errors, $nodeids];
    }

    /**
     * Check the relations: unique ids, bounded labels, endpoints that exist.
     *
     * @param array $relations The relation definitions.
     * @param array $nodeids The set of known node ids.
     * @return array [error identifiers, set of relation ids]
     */
    private static function check_relations(array $relations, array $nodeids): array {
        $errors = [];
        $relationids = [];
        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                $errors[] = 'badrelation';
                continue;
            }
            $stableid = (string) ($relation['stableid'] ?? '');
            if ($stableid !== '') {
                if (!self::is_acceptable_id($stableid)) {
                    $errors[] = 'badstableid';
                } else if (isset($relationids[$stableid])) {
                    $errors[] = 'duplicateid';
                }
                $relationids[$stableid] = true;
            }

            $errors = array_merge($errors, self::check_relation_endpoints($relation, $nodeids));
        }
        return [$errors, $relationids];
    }

    /**
     * Check one node's text limits, metadata and shape.
     *
     * The visual shape of a node is not a top-level key and is not the node
     * type: `type` carries the profile-defined semantic type (for example
     * "concept"), while the shape lives in metadatajson as one of the values
     * node_style permits. The profile decides which shapes it allows; a consumer
     * may narrow that set further but never widen it.
     *
     * @param array $node The node definition.
     * @param string $profile The document's profile key.
     * @param array $allowedshapes Extra consumer restriction; empty means none.
     * @return array The error identifiers.
     */
    private static function check_node_content(array $node, string $profile, array $allowedshapes): array {
        $errors = [];
        if (\core_text::strlen((string) ($node['label'] ?? '')) > limits::MAX_LABEL) {
            $errors[] = 'labeltoolong';
        }
        if (\core_text::strlen((string) ($node['content'] ?? '')) > limits::MAX_CONTENT) {
            $errors[] = 'contenttoolong';
        }

        $metadata = $node['metadatajson'] ?? null;
        if ($metadata !== null && !is_string($metadata)) {
            $errors[] = 'badmetadata';
            return $errors;
        }
        if (is_string($metadata) && \core_text::strlen($metadata) > limits::MAX_METADATA) {
            $errors[] = 'metadatatoolong';
        }
        try {
            node_style::validate_metadata($metadata);
        } catch (\Throwable $e) {
            $errors[] = 'badmetadata';
            return $errors;
        }

        return array_merge($errors, self::check_shape($metadata, $profile, $allowedshapes));
    }

    /**
     * Check a node's shape against the profile and any consumer restriction.
     *
     * @param string|null $metadatajson The node's metadata, if any.
     * @param string $profile The document's profile key.
     * @param array $allowedshapes Extra consumer restriction; empty means none.
     * @return array The error identifiers.
     */
    private static function check_shape(?string $metadatajson, string $profile, array $allowedshapes): array {
        if ($profile === '' || !profiles::exists($profile)) {
            // The profile itself is already reported; nothing further to say.
            return [];
        }

        $decoded = ($metadatajson === null || $metadatajson === '') ? [] : json_decode($metadatajson, true);
        $shape = (is_array($decoded) && isset($decoded['shape']) && is_string($decoded['shape']))
            ? $decoded['shape']
            : null;

        // An absent shape means the profile default, which is allowed by
        // definition, so only an explicit shape can be wrong.
        if ($shape === null) {
            return [];
        }
        if (!profiles::is_shape_allowed($profile, $shape)) {
            return ['shapenotallowedbyprofile'];
        }
        if (!empty($allowedshapes) && !in_array($shape, $allowedshapes, true)) {
            return ['shapenotallowed'];
        }
        return [];
    }

    /**
     * Check that one relation resolves to existing nodes and has a bounded label.
     *
     * @param array $relation The relation definition.
     * @param array $nodeids The set of known node ids.
     * @return array The error identifiers.
     */
    private static function check_relation_endpoints(array $relation, array $nodeids): array {
        $errors = [];
        $source = (string) ($relation['sourceid'] ?? '');
        $target = (string) ($relation['targetid'] ?? '');
        if ($source === '' || $target === '' || !isset($nodeids[$source]) || !isset($nodeids[$target])) {
            $errors[] = 'danglingrelation';
        }
        if (\core_text::strlen((string) ($relation['label'] ?? '')) > limits::MAX_LABEL) {
            $errors[] = 'labeltoolong';
        }
        return $errors;
    }

    /**
     * Check the containers: usable, unique ids.
     *
     * @param array $containers The container definitions.
     * @return array [error identifiers, set of container ids]
     */
    private static function check_containers(array $containers): array {
        $errors = [];
        $containerids = [];
        foreach ($containers as $container) {
            if (!is_array($container)) {
                $errors[] = 'badcontainer';
                continue;
            }
            if (!self::is_acceptable_id($container['stableid'] ?? null)) {
                $errors[] = 'badstableid';
                continue;
            }
            $stableid = (string) $container['stableid'];
            if (isset($containerids[$stableid])) {
                $errors[] = 'duplicateid';
            }
            $containerids[$stableid] = true;
        }
        return [$errors, $containerids];
    }

    /**
     * Check that memberships point at elements that exist.
     *
     * @param array $memberships The membership definitions.
     * @param array $nodeids The set of known node ids.
     * @param array $relationids The set of known relation ids.
     * @param array $containerids The set of known container ids.
     * @return array The error identifiers.
     */
    private static function check_memberships(
        array $memberships,
        array $nodeids,
        array $relationids,
        array $containerids
    ): array {
        $errors = [];
        foreach ($memberships as $membership) {
            if (!is_array($membership)) {
                $errors[] = 'badmembership';
                continue;
            }
            if (!isset($containerids[(string) ($membership['containerstableid'] ?? '')])) {
                $errors[] = 'danglingmembership';
                continue;
            }
            $itemid = (string) ($membership['itemstableid'] ?? '');
            $known = ((string) ($membership['itemtype'] ?? 'node') === 'relation')
                ? isset($relationids[$itemid])
                : isset($nodeids[$itemid]);
            if (!$known) {
                $errors[] = 'danglingmembership';
            }
        }
        return $errors;
    }

    /**
     * Whether a map document satisfies the policy.
     *
     * @param string $json The map document.
     * @param string|null $expectedprofile Require this profile, or null for any known profile.
     * @param array $allowedshapes Permitted node shapes; empty means unrestricted.
     * @return bool True when the document is valid.
     */
    public static function is_valid(
        string $json,
        ?string $expectedprofile = null,
        array $allowedshapes = []
    ): bool {
        return self::validate($json, $expectedprofile, $allowedshapes) === [];
    }

    /**
     * Validate a map document, throwing on the first problem found.
     *
     * @param string $json The map document.
     * @param string|null $expectedprofile Require this profile, or null for any known profile.
     * @param array $allowedshapes Permitted node shapes; empty means unrestricted.
     * @return void
     * @throws \moodle_exception When the document violates the policy.
     */
    public static function assert_valid(
        string $json,
        ?string $expectedprofile = null,
        array $allowedshapes = []
    ): void {
        $errors = self::validate($json, $expectedprofile, $allowedshapes);
        if ($errors !== []) {
            throw new \moodle_exception('error:invalidmapvalue', 'mod_vimipad', '', implode(', ', $errors));
        }
    }

    /**
     * Normalise a validated map document to its canonical serialisation, so
     * consumers store a predictable value rather than whatever spacing or key
     * order a client happened to send.
     *
     * @param string $json The map document.
     * @param string|null $expectedprofile Require this profile, or null for any known profile.
     * @param array $allowedshapes Permitted node shapes; empty means unrestricted.
     * @return string The canonical JSON.
     * @throws \moodle_exception When the document violates the policy.
     */
    public static function normalise(
        string $json,
        ?string $expectedprofile = null,
        array $allowedshapes = []
    ): string {
        self::assert_valid($json, $expectedprofile, $allowedshapes);
        $data = json_decode($json, true);
        return (string) json_encode($data);
    }
}
