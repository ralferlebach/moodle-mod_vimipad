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

namespace mod_vimipad\local\id;

/**
 * Generator and validator for stable domain identifiers.
 *
 * Internal database ids must never serve as durable identifiers in snapshots,
 * client operations, import/export or backup. This helper produces short,
 * URL-safe, prefixed stable ids (e.g. "node_a1b2c3d4e5f6") that remain valid
 * across export/import and backup/restore.
 *
 * This class lives under \mod_vimipad\local and is internal: it carries no
 * stability guarantee for dependent plugins.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stable_id {
    /** @var int Number of random hex characters in the identifier body. */
    private const BODY_LENGTH = 12;

    /** @var array<string,string> Allowed entity kinds and their id prefixes. */
    private const PREFIXES = [
        'node' => 'node',
        'relation' => 'rel',
        'container' => 'cont',
    ];

    /**
     * Generate a new stable id for the given entity kind.
     *
     * @param string $kind One of 'node', 'relation', 'container'.
     * @return string The generated stable id, e.g. "rel_8fd4a1b2c3d4".
     * @throws \coding_exception If the entity kind is unknown.
     */
    public static function generate(string $kind): string {
        if (!isset(self::PREFIXES[$kind])) {
            throw new \coding_exception('Unknown stable id kind: ' . $kind);
        }

        $body = bin2hex(random_bytes((int) ceil(self::BODY_LENGTH / 2)));
        $body = substr($body, 0, self::BODY_LENGTH);

        return self::PREFIXES[$kind] . '_' . $body;
    }

    /**
     * Check whether a string is a syntactically valid stable id.
     *
     * @param string $value The candidate identifier.
     * @param string|null $kind Optional entity kind to enforce the prefix for.
     * @return bool True if the value is a valid stable id.
     */
    public static function is_valid(string $value, ?string $kind = null): bool {
        if ($kind !== null) {
            if (!isset(self::PREFIXES[$kind])) {
                return false;
            }
            $prefixes = [self::PREFIXES[$kind]];
        } else {
            $prefixes = array_values(self::PREFIXES);
        }

        $alternation = implode('|', array_map('preg_quote', $prefixes));
        $pattern = '/^(' . $alternation . ')_[0-9a-f]{' . self::BODY_LENGTH . '}$/';

        return (bool) preg_match($pattern, $value);
    }
}
