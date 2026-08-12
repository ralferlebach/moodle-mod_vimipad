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
        $errors = [];

        if (strlen($json) > self::MAX_BYTES) {
            return ['toolarge'];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['notjson'];
        }

        // Profile.
        $profile = isset($data['profile']) && is_string($data['profile']) ? $data['profile'] : '';
        if ($profile === '' || !profiles::exists($profile)) {
            $errors[] = 'unknownprofile';
        } else if ($expectedprofile !== null && $profile !== $expectedprofile) {
            $errors[] = 'wrongprofile';
        }

        // Element collections must be lists.
        foreach (['nodes', 'relations', 'containers', 'memberships'] as $kind) {
            if (array_key_exists($kind, $data) && !is_array($data[$kind])) {
                $errors[] = 'badstructure';
                return $errors;
            }
        }
        $nodes = $data['nodes'] ?? [];
        $relations = $data['relations'] ?? [];
        $containers = $data['containers'] ?? [];

        // Counts.
        if (count($nodes) > limits::MAX_NODES) {
            $errors[] = 'toomanynodes';
        }
        if (count($relations) > limits::MAX_RELATIONS) {
            $errors[] = 'toomanyrelations';
        }
        if (count($containers) > limits::MAX_CONTAINERS) {
            $errors[] = 'toomanycontainers';
        }

        // Nodes: stable ids must be present, well formed and unique; text bounded.
        $nodeids = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                $errors[] = 'badnode';
                continue;
            }
            $stableid = $node['stableid'] ?? null;
            if (!self::is_acceptable_id($stableid)) {
                $errors[] = 'badstableid';
                continue;
            }
            $stableid = (string) $stableid;
            if (isset($nodeids[$stableid])) {
                $errors[] = 'duplicateid';
            }
            $nodeids[$stableid] = true;
            if (\core_text::strlen((string) ($node['label'] ?? '')) > limits::MAX_LABEL) {
                $errors[] = 'labeltoolong';
            }
            if (\core_text::strlen((string) ($node['content'] ?? '')) > limits::MAX_CONTENT) {
                $errors[] = 'contenttoolong';
            }
            if (!empty($allowedshapes)) {
                $shape = (string) ($node['shape'] ?? ($node['type'] ?? ''));
                if ($shape !== '' && !in_array($shape, $allowedshapes, true)) {
                    $errors[] = 'shapenotallowed';
                }
            }
        }

        // Relations: valid ids, unique, and endpoints that exist among the nodes.
        $relationids = [];
        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                $errors[] = 'badrelation';
                continue;
            }
            $stableid = (string) ($relation['stableid'] ?? '');
            if ($stableid !== '' && !self::is_acceptable_id($stableid)) {
                $errors[] = 'badstableid';
            }
            if ($stableid !== '') {
                if (isset($relationids[$stableid])) {
                    $errors[] = 'duplicateid';
                }
                $relationids[$stableid] = true;
            }
            $source = (string) ($relation['sourceid'] ?? '');
            $target = (string) ($relation['targetid'] ?? '');
            if ($source === '' || $target === '' || !isset($nodeids[$source]) || !isset($nodeids[$target])) {
                $errors[] = 'danglingrelation';
            }
            if (\core_text::strlen((string) ($relation['label'] ?? '')) > limits::MAX_LABEL) {
                $errors[] = 'labeltoolong';
            }
        }

        // Containers: valid, unique ids.
        $containerids = [];
        foreach ($containers as $container) {
            if (!is_array($container)) {
                $errors[] = 'badcontainer';
                continue;
            }
            $stableid = $container['stableid'] ?? null;
            if (!self::is_acceptable_id($stableid)) {
                $errors[] = 'badstableid';
                continue;
            }
            $stableid = (string) $stableid;
            if (isset($containerids[$stableid])) {
                $errors[] = 'duplicateid';
            }
            $containerids[$stableid] = true;
        }

        // Memberships must point at elements that exist.
        foreach (($data['memberships'] ?? []) as $membership) {
            if (!is_array($membership)) {
                $errors[] = 'badmembership';
                continue;
            }
            $containerid = (string) ($membership['containerstableid'] ?? '');
            $itemid = (string) ($membership['itemstableid'] ?? '');
            if (!isset($containerids[$containerid])) {
                $errors[] = 'danglingmembership';
                continue;
            }
            $itemtype = (string) ($membership['itemtype'] ?? 'node');
            $known = ($itemtype === 'relation') ? isset($relationids[$itemid]) : isset($nodeids[$itemid]);
            if (!$known) {
                $errors[] = 'danglingmembership';
            }
        }

        return array_values(array_unique($errors));
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
