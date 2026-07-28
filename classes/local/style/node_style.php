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

namespace mod_vimipad\local\style;

/**
 * Node visual style schema: shape, fill and text styling.
 *
 * Validates the style object carried in a node's metadatajson before it is
 * stored, so a malformed payload can never reach the graded snapshot or a
 * collaborator's canvas. Mirrors the client module mod_vimipad/canvas/node_style.
 * Internal (\mod_vimipad\local); the allowed shapes per profile belong to the
 * future profile registry, so this validator accepts any universal shape and
 * leaves profile clamping to the renderer.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class node_style {
    /** @var string[] The universal node shapes. */
    private const SHAPES = ['roundrect', 'rect', 'ellipse'];

    /** @var string[] The universal font families. */
    private const FONTS = ['sans', 'serif', 'mono'];

    /** @var int Smallest accepted relative font size step. */
    private const MIN_SIZE = -3;

    /** @var int Largest accepted relative font size step. */
    private const MAX_SIZE = 6;

    /**
     * Validate a node metadata JSON string, throwing on malformed style.
     *
     * Unknown keys are tolerated (reserved for profile metadata); only the
     * recognised style keys are checked. An empty or absent value is valid.
     *
     * @param string|null $metadatajson The raw metadata JSON.
     * @return void
     * @throws \invalid_parameter_exception If the style is malformed.
     */
    public static function validate_metadata(?string $metadatajson): void {
        if ($metadatajson === null || $metadatajson === '') {
            return;
        }
        $decoded = json_decode($metadatajson, true);
        if (!is_array($decoded)) {
            throw new \invalid_parameter_exception('metadatajson must decode to an object');
        }
        if (array_key_exists('shape', $decoded)) {
            if (!is_string($decoded['shape']) || !in_array($decoded['shape'], self::SHAPES, true)) {
                throw new \invalid_parameter_exception('Invalid node shape');
            }
        }
        if (array_key_exists('fill', $decoded)) {
            self::assert_color($decoded['fill'], 'fill');
        }
        if (array_key_exists('text', $decoded)) {
            self::validate_text($decoded['text']);
        }
    }

    /**
     * Validate the text-style sub-object.
     *
     * @param mixed $text The decoded text style.
     * @return void
     * @throws \invalid_parameter_exception
     */
    private static function validate_text($text): void {
        if (!is_array($text)) {
            throw new \invalid_parameter_exception('text style must be an object');
        }
        if (array_key_exists('font', $text)) {
            if (!is_string($text['font']) || !in_array($text['font'], self::FONTS, true)) {
                throw new \invalid_parameter_exception('Invalid font family');
            }
        }
        if (array_key_exists('size', $text)) {
            if (!is_int($text['size']) || $text['size'] < self::MIN_SIZE || $text['size'] > self::MAX_SIZE) {
                throw new \invalid_parameter_exception('Font size step out of range');
            }
        }
        if (array_key_exists('color', $text)) {
            self::assert_color($text['color'], 'text color');
        }
        if (array_key_exists('background', $text)) {
            self::assert_color($text['background'], 'text background');
        }
        foreach (['bold', 'italic', 'underline'] as $flag) {
            if (array_key_exists($flag, $text) && !is_bool($text[$flag])) {
                throw new \invalid_parameter_exception('Text ' . $flag . ' must be boolean');
            }
        }
    }

    /**
     * Assert that a value is a #rrggbb colour.
     *
     * @param mixed $value The candidate value.
     * @param string $label A label for the error message.
     * @return void
     * @throws \invalid_parameter_exception
     */
    private static function assert_color($value, string $label): void {
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw new \invalid_parameter_exception('Invalid ' . $label . ' colour');
        }
    }
}
