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

namespace mod_vimipad\local\policy;

/**
 * Central server-side schema validation for layout and viewport payloads.
 *
 * The layout channel stores node positions and sizes as a versioned envelope
 * {v, pos, size}; a bare position map (the older format) is also accepted for
 * backward compatibility. Beyond valid JSON and byte size (checked elsewhere),
 * this policy enforces the structural contract: an object root, only known
 * top-level fields, finite in-range coordinates, positive sizes, and a bounded
 * number of layout objects — so a syntactically valid but semantically broken
 * payload (e.g. `42`, `true`, or `{"pos":{"n":{"x":"abc"}}}`) is rejected.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_policy {
    /** Allowed top-level fields in a layout envelope. */
    private const ALLOWED_LAYOUT_KEYS = ['v', 'pos', 'size'];

    /** Allowed top-level fields in a viewport payload. */
    private const ALLOWED_VIEWPORT_KEYS = ['x', 'y', 'zoom', 'scale'];

    /** Maximum number of positioned/sized objects in one layout. */
    public const MAX_LAYOUT_OBJECTS = 5000;

    /**
     * Validate a layout JSON payload against the structural schema.
     *
     * An empty string or the literal 'null' is treated as "no layout" and is
     * accepted (the caller may legitimately clear the layout).
     *
     * @param string $json The layout JSON.
     * @return void
     * @throws \moodle_exception error:invalidlayout on a schema violation.
     */
    public static function validate_layout(string $json): void {
        if ($json === '' || trim($json) === 'null') {
            return;
        }
        $decoded = json_decode($json, true);
        // Root must be an object (associative array), not a scalar or list.
        if (!is_array($decoded) || self::is_list($decoded)) {
            self::reject();
        }
        // Distinguish the versioned envelope from the legacy bare position map.
        // The envelope has at least one of v/pos/size; anything else is treated
        // as a legacy bare map (stableid => {x,y}).
        $haswrapper = array_key_exists('pos', $decoded) || array_key_exists('size', $decoded)
            || array_key_exists('v', $decoded);
        if ($haswrapper) {
            // In envelope form, only the known top-level fields are allowed.
            foreach (array_keys($decoded) as $key) {
                if (!in_array($key, self::ALLOWED_LAYOUT_KEYS, true)) {
                    self::reject();
                }
            }
            $positions = $decoded['pos'] ?? [];
            $sizes = $decoded['size'] ?? [];
        } else {
            // Legacy bare map: the whole object is the position map.
            $positions = $decoded;
            $sizes = [];
        }

        self::validate_point_map($positions, ['x', 'y']);
        self::validate_size_map($sizes);

        if (count($positions) > self::MAX_LAYOUT_OBJECTS || count($sizes) > self::MAX_LAYOUT_OBJECTS) {
            self::reject();
        }
    }

    /**
     * Validate a viewport JSON payload against the structural schema.
     *
     * @param string $json The viewport JSON.
     * @return void
     * @throws \moodle_exception error:invalidlayout on a schema violation.
     */
    public static function validate_viewport(string $json): void {
        if ($json === '' || trim($json) === 'null') {
            return;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || self::is_list($decoded)) {
            self::reject();
        }
        foreach ($decoded as $key => $value) {
            if (!in_array($key, self::ALLOWED_VIEWPORT_KEYS, true)) {
                self::reject();
            }
            if (!is_int($value) && !is_float($value)) {
                self::reject();
            }
            if (!is_finite((float) $value) || abs((float) $value) > limits::MAX_COORDINATE) {
                self::reject();
            }
        }
    }

    /**
     * Every entry must be an object with finite, in-range numeric coordinates.
     *
     * @param mixed $map The candidate position map.
     * @param array $keys Required numeric keys (e.g. ['x','y']).
     * @return void
     */
    private static function validate_point_map($map, array $keys): void {
        if (!is_array($map)) {
            self::reject();
        }
        // A position map is an object keyed by stable id, not a list.
        if (self::is_list($map) && $map !== []) {
            self::reject();
        }
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                self::reject();
            }
            foreach ($keys as $k) {
                if (!array_key_exists($k, $entry)) {
                    self::reject();
                }
                $v = $entry[$k];
                if (
                    (!is_int($v) && !is_float($v)) || !is_finite((float) $v)
                    || abs((float) $v) > limits::MAX_COORDINATE
                ) {
                    self::reject();
                }
            }
        }
    }

    /**
     * Every size entry must have positive, finite, in-range w and h.
     *
     * @param mixed $map The candidate size map.
     * @return void
     */
    private static function validate_size_map($map): void {
        if (!is_array($map)) {
            self::reject();
        }
        if (self::is_list($map) && $map !== []) {
            self::reject();
        }
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                self::reject();
            }
            foreach (['w', 'h'] as $k) {
                if (!array_key_exists($k, $entry)) {
                    self::reject();
                }
                $v = $entry[$k];
                if (
                    (!is_int($v) && !is_float($v)) || !is_finite((float) $v)
                    || (float) $v <= 0 || (float) $v > limits::MAX_COORDINATE
                ) {
                    self::reject();
                }
            }
        }
    }

    /**
     * Whether a PHP array decoded from JSON is a list (JSON array) rather than
     * an object. An empty array is ambiguous; callers handle [] explicitly.
     *
     * @param array $arr The array.
     * @return bool
     */
    private static function is_list(array $arr): bool {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * Reject with the standard invalid-layout error.
     *
     * @return void
     * @throws \moodle_exception
     */
    private static function reject(): void {
        throw new \moodle_exception('error:invalidlayout', 'mod_vimipad');
    }
}
