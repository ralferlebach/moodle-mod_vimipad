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
 * Resource limits for maps, texts and imports.
 *
 * These bounds protect the service against degenerate input (DoS-sized
 * payloads, non-finite geometry, oversized texts) and keep every stored state
 * renderable and exportable. They sit comfortably above the documented target
 * envelope (300 nodes / 500 relations per map) and the database column sizes.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class limits {
    /** @var int Maximum live nodes per map. */
    public const MAX_NODES = 1000;

    /** @var int Maximum live relations per map. */
    public const MAX_RELATIONS = 2000;

    /** @var int Maximum live containers per map. */
    public const MAX_CONTAINERS = 200;

    /** @var int Maximum label length (matches the char(255) columns). */
    public const MAX_LABEL = 255;

    /** @var int Maximum node rich-content length. */
    public const MAX_CONTENT = 50000;

    /** @var int Maximum per-element metadata JSON length. */
    public const MAX_METADATA = 20000;

    /** @var int Maximum free-text length (journal, review, feedback). */
    public const MAX_TEXT = 20000;

    /** @var int Maximum import document size in bytes. */
    public const MAX_IMPORT_BYTES = 5242880;

    /** @var int Maximum layout blob size in bytes. */
    public const MAX_LAYOUT_BYTES = 1048576;

    /** @var float Maximum absolute canvas coordinate / dimension. */
    public const MAX_COORDINATE = 1000000.0;

    /** @var int Maximum length of teacher AI notes fed into a prompt (chars). */
    public const MAX_AI_NOTES = 5000;

    /** @var int Maximum length of a full AI prompt sent to the provider (chars). */
    public const MAX_AI_PROMPT = 40000;

    /** @var int Maximum length of a generated AI draft stored/returned (chars). */
    public const MAX_AI_DRAFT = 20000;

    /** @var int Maximum length of stored provider info (char(255) column). */
    public const MAX_AI_PROVIDERINFO = 255;

    /**
     * Enforce a maximum string length.
     *
     * @param string|null $value The value (null is fine).
     * @param int $max The maximum length.
     * @param string $what A short identifier for the error message.
     * @return void
     * @throws \moodle_exception error:textlimit when the value is too long.
     */
    public static function check_text(?string $value, int $max, string $what): void {
        if ($value !== null && \core_text::strlen($value) > $max) {
            throw new \moodle_exception('error:textlimit', 'mod_vimipad', '', (object) ['what' => $what, 'max' => $max]);
        }
    }

    /**
     * Enforce a maximum byte length (for payload/file sizes, not text fields).
     *
     * Unlike check_text, which counts Unicode characters for human-facing text
     * limits, this counts bytes with strlen(), so a byte-documented cap such as
     * MAX_LAYOUT_BYTES / MAX_IMPORT_BYTES is actually enforced in bytes even for
     * multibyte payloads.
     *
     * @param string|null $value The value (null is fine).
     * @param int $maxbytes The maximum size in bytes.
     * @param string $what A short identifier for the error message.
     * @return void
     * @throws \moodle_exception error:bytelimit when the value is too large.
     */
    public static function check_bytes(?string $value, int $maxbytes, string $what): void {
        if ($value !== null && strlen($value) > $maxbytes) {
            throw new \moodle_exception('error:bytelimit', 'mod_vimipad', '', (object) ['what' => $what, 'max' => $maxbytes]);
        }
    }

    /**
     * Enforce a per-map element count limit.
     *
     * @param int $current The current live element count.
     * @param int $max The maximum.
     * @param string $what A short identifier for the error message.
     * @return void
     * @throws \moodle_exception error:maplimit when the map is full.
     */
    public static function check_count(int $current, int $max, string $what): void {
        if ($current >= $max) {
            throw new \moodle_exception('error:maplimit', 'mod_vimipad', '', (object) ['what' => $what, 'max' => $max]);
        }
    }

    /**
     * Validate a container geometry JSON blob.
     *
     * The box must decode to finite numeric x/y/w/h with positive dimensions
     * and coordinates within the canvas envelope; anything else would poison
     * rendering, export and the spatial membership derivation.
     *
     * @param string|null $geometryjson The geometry JSON (null/empty is fine).
     * @return void
     * @throws \moodle_exception error:invalidgeometry when the box is malformed.
     */
    public static function check_geometry(?string $geometryjson): void {
        if ($geometryjson === null || $geometryjson === '') {
            return;
        }
        $raw = json_decode($geometryjson, true);
        $valid = is_array($raw);
        if ($valid) {
            foreach (['x', 'y', 'w', 'h'] as $key) {
                if (
                    !isset($raw[$key]) || !is_numeric($raw[$key])
                        || !is_finite((float) $raw[$key])
                        || abs((float) $raw[$key]) > self::MAX_COORDINATE
                ) {
                    $valid = false;
                    break;
                }
            }
        }
        if ($valid && ((float) $raw['w'] <= 0 || (float) $raw['h'] <= 0)) {
            $valid = false;
        }
        if (!$valid) {
            throw new \moodle_exception('error:invalidgeometry', 'mod_vimipad');
        }
    }
}
