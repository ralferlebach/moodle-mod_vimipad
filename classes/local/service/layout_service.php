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

/**
 * Persists per-profile layout state for a workspace.
 *
 * Layout is presentation state, deliberately kept out of the operation log and
 * the revision counter: repositioning a node must not create semantic
 * conflicts with concurrent editors. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class layout_service {
    /**
     * Upsert the layout for a workspace and profile.
     *
     * In 'merge' mode the given layout is treated as a patch: its per-node
     * positions and sizes are merged into the stored layout (other nodes are
     * preserved), so concurrent moves of different nodes do not clobber each
     * other. In 'replace' mode the layout is stored as-is. The read-merge-write
     * is serialized per workspace/profile.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param string $layoutjson The layout JSON payload (full, or a patch in merge mode).
     * @param string $viewportjson The viewport JSON payload.
     * @param int $userid The acting user id.
     * @param string $mode 'replace' (default) or 'merge'.
     * @return void
     */
    public function save(
        int $workspaceid,
        string $profile,
        string $layoutjson,
        string $viewportjson,
        int $userid,
        string $mode = 'replace'
    ): void {
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_vimipad_layout');
        $lock = $lockfactory->get_lock($workspaceid . ':' . $profile, 5);

        if (!$lock) {
            // Could not serialize; fall back to a direct write.
            $this->write($workspaceid, $profile, $layoutjson, $viewportjson, $userid, $mode);
            return;
        }
        try {
            $this->write($workspaceid, $profile, $layoutjson, $viewportjson, $userid, $mode);
        } finally {
            $lock->release();
        }
    }

    /**
     * Read-merge-write of the layout row (call site holds the lock).
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param string $layoutjson The layout JSON (full, or a patch in merge mode).
     * @param string $viewportjson The viewport JSON payload.
     * @param int $userid The acting user id.
     * @param string $mode 'replace' or 'merge'.
     * @return void
     */
    private function write(
        int $workspaceid,
        string $profile,
        string $layoutjson,
        string $viewportjson,
        int $userid,
        string $mode
    ): void {
        global $DB;

        $now = time();
        $existing = $DB->get_record(
            'vimipad_layout',
            ['workspaceid' => $workspaceid, 'profile' => $profile]
        );

        if ($mode === 'merge' && $existing && $existing->layoutjson !== null && $existing->layoutjson !== '') {
            $layoutjson = self::merge_layout($existing->layoutjson, $layoutjson);
            if ($viewportjson === '' && $existing->viewportjson !== null) {
                $viewportjson = $existing->viewportjson;
            }
        }

        if ($existing) {
            $DB->update_record('vimipad_layout', (object) [
                'id' => $existing->id,
                'viewportjson' => $viewportjson,
                'layoutjson' => $layoutjson,
                'modifiedby' => $userid,
                'timemodified' => $now,
            ]);
            return;
        }

        $DB->insert_record('vimipad_layout', (object) [
            'workspaceid' => $workspaceid,
            'profile' => $profile,
            'viewportjson' => $viewportjson,
            'layoutjson' => $layoutjson,
            'modifiedby' => $userid,
            'timemodified' => $now,
        ]);
    }

    /**
     * Merge a layout patch (per-node positions and sizes) into a stored layout.
     *
     * @param string $storedjson The stored layout JSON.
     * @param string $patchjson The incoming patch layout JSON.
     * @return string The merged layout JSON.
     */
    private static function merge_layout(string $storedjson, string $patchjson): string {
        $stored = json_decode($storedjson, true);
        $patch = json_decode($patchjson, true);
        if (!is_array($patch)) {
            return $storedjson;
        }
        if (!is_array($stored)) {
            return $patchjson;
        }

        $merged = $stored;
        $merged['v'] = $patch['v'] ?? ($stored['v'] ?? 1);
        if (isset($patch['pos']) && is_array($patch['pos'])) {
            $merged['pos'] = array_merge(is_array($stored['pos'] ?? null) ? $stored['pos'] : [], $patch['pos']);
        }
        if (isset($patch['size']) && is_array($patch['size'])) {
            $merged['size'] = array_merge(is_array($stored['size'] ?? null) ? $stored['size'] : [], $patch['size']);
        }

        return json_encode($merged);
    }

    /**
     * Return the stored layout JSON for a workspace and profile.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @return string The layout JSON, or an empty string if none stored.
     */
    public function get_layout_json(int $workspaceid, string $profile): string {
        global $DB;

        $record = $DB->get_record(
            'vimipad_layout',
            ['workspaceid' => $workspaceid, 'profile' => $profile]
        );

        return $record && $record->layoutjson !== null ? $record->layoutjson : '';
    }

    /**
     * Return the layout only when it changed after the given timestamp.
     *
     * Lets a poller skip re-sending an unchanged layout: the JSON is returned
     * only when the stored layout is newer than $since, alongside its
     * modification time so the caller can pass it back next time.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param int $since The timestamp the caller already has (0 = none).
     * @return array The layout json (empty when unchanged), its time and a changed flag.
     */
    public function get_layout_since(int $workspaceid, string $profile, int $since): array {
        global $DB;

        $record = $DB->get_record(
            'vimipad_layout',
            ['workspaceid' => $workspaceid, 'profile' => $profile]
        );
        if (!$record) {
            return ['layoutjson' => '', 'timemodified' => 0, 'changed' => false];
        }

        $timemodified = (int) $record->timemodified;
        $changed = $timemodified > $since;

        return [
            'layoutjson' => ($changed && $record->layoutjson !== null) ? $record->layoutjson : '',
            'timemodified' => $timemodified,
            'changed' => $changed,
        ];
    }
}
