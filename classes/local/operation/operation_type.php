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

namespace mod_vimipad\local\operation;

/**
 * Operation type catalogue and payload schema validation.
 *
 * Every write to a workspace goes through a typed operation whose payload is
 * validated here before it is applied. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class operation_type {
    /** @var string Create a node. */
    public const NODE_CREATE = 'node_create';

    /** @var string Update a node's label/type/content. */
    public const NODE_UPDATE = 'node_update';

    /** @var string Soft-delete a node. */
    public const NODE_DELETE = 'node_delete';

    /** @var string Create a relation between two nodes. */
    public const RELATION_CREATE = 'relation_create';

    /** @var string Update a relation's type/label/direction. */
    public const RELATION_UPDATE = 'relation_update';

    /** @var string Soft-delete a relation. */
    public const RELATION_DELETE = 'relation_delete';

    /** @var string Retarget a relation's source and/or target node. */
    public const RELATION_RETARGET = 'relation_retarget';

    /**
     * All known operation types.
     *
     * @return string[]
     */
    public static function all(): array {
        return [
            self::NODE_CREATE, self::NODE_UPDATE, self::NODE_DELETE,
            self::RELATION_CREATE, self::RELATION_UPDATE, self::RELATION_DELETE,
            self::RELATION_RETARGET,
        ];
    }

    /**
     * Whether the given string is a known operation type.
     *
     * @param string $type The operation type.
     * @return bool
     */
    public static function is_known(string $type): bool {
        return in_array($type, self::all(), true);
    }

    /**
     * Validate a decoded payload for the given operation type.
     *
     * Checks required keys and scalar types only; referential checks (e.g.
     * that a source node exists) happen in the processor within the
     * transaction.
     *
     * @param string $type The operation type.
     * @param array $payload The decoded payload.
     * @return void
     * @throws \invalid_parameter_exception If the payload is malformed.
     */
    public static function validate_payload(string $type, array $payload): void {
        switch ($type) {
            case self::NODE_CREATE:
                self::require_string($payload, 'type');
                self::optional_string($payload, 'stableid');
                self::optional_string($payload, 'label');
                self::optional_string($payload, 'content');
                self::validate_node_metadata($payload);
                break;
            case self::NODE_UPDATE:
                self::require_string($payload, 'stableid');
                self::optional_string($payload, 'content');
                self::validate_node_metadata($payload);
                break;
            case self::NODE_DELETE:
                self::require_string($payload, 'stableid');
                break;
            case self::RELATION_CREATE:
                self::require_string($payload, 'sourceid');
                self::require_string($payload, 'targetid');
                self::require_string($payload, 'type');
                self::optional_string($payload, 'stableid');
                break;
            case self::RELATION_UPDATE:
                self::require_string($payload, 'stableid');
                break;
            case self::RELATION_DELETE:
                self::require_string($payload, 'stableid');
                break;
            case self::RELATION_RETARGET:
                self::require_string($payload, 'stableid');
                if (!array_key_exists('newsource', $payload) && !array_key_exists('newtarget', $payload)) {
                    throw new \invalid_parameter_exception('relation_retarget requires newsource or newtarget');
                }
                self::optional_string($payload, 'newsource');
                self::optional_string($payload, 'newtarget');
                break;
            default:
                throw new \invalid_parameter_exception('Unknown operation type: ' . $type);
        }
    }

    /**
     * Validate an optional node metadatajson style payload, if present.
     *
     * @param array $payload The payload.
     * @return void
     * @throws \invalid_parameter_exception
     */
    private static function validate_node_metadata(array $payload): void {
        if (!array_key_exists('metadatajson', $payload)) {
            return;
        }
        if (!is_string($payload['metadatajson'])) {
            throw new \invalid_parameter_exception('Invalid field type: metadatajson');
        }
        \mod_vimipad\local\style\node_style::validate_metadata($payload['metadatajson']);
    }

    /**
     * Assert that a required key is present and a non-empty string.
     *
     * @param array $payload The payload.
     * @param string $key The key to check.
     * @return void
     * @throws \invalid_parameter_exception
     */
    private static function require_string(array $payload, string $key): void {
        if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
            throw new \invalid_parameter_exception('Missing or invalid required field: ' . $key);
        }
    }

    /**
     * Assert that an optional key, if present, is a string.
     *
     * @param array $payload The payload.
     * @param string $key The key to check.
     * @return void
     * @throws \invalid_parameter_exception
     */
    private static function optional_string(array $payload, string $key): void {
        if (array_key_exists($key, $payload) && !is_string($payload[$key])) {
            throw new \invalid_parameter_exception('Invalid field type: ' . $key);
        }
    }
}
