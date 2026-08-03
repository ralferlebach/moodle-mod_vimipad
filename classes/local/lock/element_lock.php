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

namespace mod_vimipad\local\lock;

/**
 * The differentiated element-lock model.
 *
 * A locked element may restrict three independent groups of edits:
 *  - move:  position, size and shape (geometry and the shape metadata key);
 *  - color: the element's fill colour;
 *  - text:  text content, text styling (font, size, weight, style, decoration)
 *           and the text colour/background.
 *
 * The groups are semantic and cut across the database fields: geometry lives in
 * its own column, but shape, fill and all text styling share the metadata JSON.
 * This class is the single source of truth for which change belongs to which
 * group, so the server can allow or reject an update precisely. The frontend
 * mirrors this mapping to hide the corresponding controls.
 *
 * Lock state is stored in an element's metadata as
 * `{"locked": true, "locks": {"move": true, "color": false, "text": true}}`.
 * A legacy plain `{"locked": true}` (no `locks` map) means everything is locked.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element_lock {
    /** @var string The move group: position, size, shape. */
    public const GROUP_MOVE = 'move';

    /** @var string The colour group: fill colour. */
    public const GROUP_COLOR = 'color';

    /** @var string The text group: content, text style, text colours. */
    public const GROUP_TEXT = 'text';

    /** @var string[] All lock groups. */
    public const GROUPS = [self::GROUP_MOVE, self::GROUP_COLOR, self::GROUP_TEXT];

    /**
     * The metadata style keys that belong to each group.
     *
     * Keys not listed here (unknown/forward-compatible) are treated as text so
     * that a new text-styling key cannot slip past a text lock.
     *
     * @var array<string, string[]>
     */
    private const META_KEYS = [
        self::GROUP_MOVE => ['shape'],
        self::GROUP_COLOR => ['fill'],
        self::GROUP_TEXT => ['text'],
    ];

    /**
     * The database update fields that belong to each group (outside metadata).
     *
     * @var array<string, string[]>
     */
    private const DB_FIELDS = [
        self::GROUP_MOVE => ['geometryjson', 'newsource', 'newtarget'],
        self::GROUP_TEXT => ['label', 'content'],
    ];

    /**
     * Whether the element is locked at all.
     *
     * @param array|null $meta The decoded metadata.
     * @return bool
     */
    public static function is_locked(?array $meta): bool {
        return is_array($meta) && !empty($meta['locked']);
    }

    /**
     * Whether a specific group is locked on the element.
     *
     * With no `locks` map a locked element locks every group (legacy/global).
     *
     * @param array|null $meta The decoded metadata.
     * @param string $group One of the GROUP_* constants.
     * @return bool
     */
    public static function is_group_locked(?array $meta, string $group): bool {
        if (!self::is_locked($meta)) {
            return false;
        }
        $locks = $meta['locks'] ?? null;
        if (!is_array($locks)) {
            // Legacy global lock: everything is locked.
            return true;
        }
        return !empty($locks[$group]);
    }

    /**
     * The group a database field belongs to, or null if the field is not
     * gated by any group (e.g. 'type', 'direction').
     *
     * @param string $field The database field name.
     * @return string|null
     */
    public static function group_for_field(string $field): ?string {
        foreach (self::DB_FIELDS as $group => $fields) {
            if (in_array($field, $fields, true)) {
                return $group;
            }
        }
        return null;
    }

    /**
     * The group a metadata style key belongs to. Unknown keys map to text so a
     * new text key cannot bypass a text lock.
     *
     * @param string $key The metadata key.
     * @return string
     */
    public static function group_for_meta_key(string $key): string {
        foreach (self::META_KEYS as $group => $keys) {
            if (in_array($key, $keys, true)) {
                return $group;
            }
        }
        return self::GROUP_TEXT;
    }

    /**
     * Compare old and new metadata and return the set of lock groups whose
     * content changed. Lock bookkeeping keys (locked, locks, editable) are
     * ignored so that (un)locking itself is never treated as a styling change.
     *
     * @param array $oldmeta The current decoded metadata.
     * @param array $newmeta The incoming decoded metadata.
     * @return string[] The groups that changed.
     */
    public static function changed_meta_groups(array $oldmeta, array $newmeta): array {
        $ignore = ['locked', 'locks', 'editable'];
        $keys = array_diff(
            array_unique(array_merge(array_keys($oldmeta), array_keys($newmeta))),
            $ignore
        );
        $groups = [];
        foreach ($keys as $key) {
            $before = $oldmeta[$key] ?? null;
            $after = $newmeta[$key] ?? null;
            if ($before !== $after) {
                $groups[self::group_for_meta_key((string) $key)] = true;
            }
        }
        return array_keys($groups);
    }
}
