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
     * is serialized per workspace/profile; if the serialization lock cannot be
     * acquired the save fails closed (no unserialized write).
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param string $layoutjson The layout JSON payload (full, or a patch in merge mode).
     * @param string $viewportjson The viewport JSON payload.
     * @param int $userid The acting user id.
     * @param string $mode 'replace' (default) or 'merge'.
     * @param array $pinnedstableids Node stable ids whose position/size must not
     *     change (move-locked elements under active lock enforcement). Their
     *     stored geometry is restored after the merge, so a move-locked node
     *     cannot be repositioned via the layout channel. A node with no stored
     *     position yet (first placement) is not pinned. Empty by default (no
     *     enforcement — e.g. the import path or a bypassing manager).
     * @return void
     * @throws \moodle_exception If the layout is being written concurrently and the lock times out.
     */
    public function save(
        int $workspaceid,
        string $profile,
        string $layoutjson,
        string $viewportjson,
        int $userid,
        string $mode = 'replace',
        array $pinnedstableids = []
    ): void {
        \mod_vimipad\local\policy\limits::check_bytes(
            $layoutjson,
            \mod_vimipad\local\policy\limits::MAX_LAYOUT_BYTES,
            'layout'
        );
        \mod_vimipad\local\policy\limits::check_bytes(
            $viewportjson,
            \mod_vimipad\local\policy\limits::MAX_LAYOUT_BYTES,
            'viewport'
        );
        // Enforce the structural layout/viewport schema at the service boundary,
        // so every caller (external endpoint AND import path) is validated, not
        // just the external endpoint.
        \mod_vimipad\local\policy\layout_policy::validate_layout($layoutjson);
        \mod_vimipad\local\policy\layout_policy::validate_viewport($viewportjson);
        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_vimipad_layout');
        $lock = $lockfactory->get_lock($workspaceid . ':' . $profile, 5);

        if (!$lock) {
            // Fail closed: an unserialized write is exactly what the lock is
            // there to prevent (lost read-merge-write updates).
            throw new \moodle_exception('error:layoutbusy', 'mod_vimipad');
        }
        try {
            $this->write($workspaceid, $profile, $layoutjson, $viewportjson, $userid, $mode, $pinnedstableids);
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
     * @param array $pinnedstableids Node stable ids to pin to their stored geometry.
     * @return void
     */
    private function write(
        int $workspaceid,
        string $profile,
        string $layoutjson,
        string $viewportjson,
        int $userid,
        string $mode,
        array $pinnedstableids = []
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

        // Restore the stored position/size of move-locked nodes: their geometry
        // must not change through the layout channel while lock enforcement is
        // active. This runs after the merge, so it also protects a locked node
        // in a full 'replace' payload that tried to move it.
        if (!empty($pinnedstableids)) {
            $layoutjson = self::pin_positions(
                $layoutjson,
                $existing && $existing->layoutjson !== null ? $existing->layoutjson : '',
                $pinnedstableids
            );
        }

        // A strictly monotonic change token: never reuse a value (two saves in
        // the same second must be distinguishable), and never fall below the
        // wall clock so in-flight client tokens from the previous
        // timestamp-based scheme stay comparable.
        $revision = max((int) ($existing->layoutrevision ?? 0) + 1, $now);

        if ($existing) {
            $DB->update_record('vimipad_layout', (object) [
                'id' => $existing->id,
                'viewportjson' => $viewportjson,
                'layoutjson' => $layoutjson,
                'modifiedby' => $userid,
                'timemodified' => $now,
                'layoutrevision' => $revision,
            ]);
            $this->append_history($workspaceid, $profile, $layoutjson, $userid);
            return;
        }

        $DB->insert_record('vimipad_layout', (object) [
            'workspaceid' => $workspaceid,
            'profile' => $profile,
            'viewportjson' => $viewportjson,
            'layoutjson' => $layoutjson,
            'modifiedby' => $userid,
            'timemodified' => $now,
            'layoutrevision' => $revision,
        ]);
        $this->append_history($workspaceid, $profile, $layoutjson, $userid);
    }

    /** Maximum layout-history rows kept per workspace/profile. */
    private const MAX_HISTORY = 400;

    /**
     * Append the current layout to the append-only history, so a past revision
     * can later be replayed with the topology it actually had.
     *
     * The row is tagged with the workspace's semantic revision at this moment;
     * a replay of revision N picks the newest history row with revision <= N.
     * Consecutive identical layouts are de-duplicated, and the history is capped
     * per workspace/profile (oldest rows pruned) to bound growth.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param string $layoutjson The layout JSON just written.
     * @param int $userid The acting user id.
     * @return void
     */
    private function append_history(int $workspaceid, string $profile, string $layoutjson, int $userid): void {
        global $DB;

        // The semantic revision current right now (positions are non-revisioned,
        // so we correlate them to the op-log by the workspace revision at save
        // time). A missing workspace row (shouldn't happen) means revision 0.
        $revision = (int) ($DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $workspaceid]) ?: 0);

        // De-duplicate: skip if the newest history row for this profile already
        // holds an identical layout (e.g. an idempotent re-save).
        $last = $DB->get_records(
            'vimipad_layouthist',
            ['workspaceid' => $workspaceid, 'profile' => $profile],
            'revision DESC, id DESC',
            'id, layoutjson',
            0,
            1
        );
        $lastrow = $last ? reset($last) : null;
        if ($lastrow && (string) $lastrow->layoutjson === (string) $layoutjson) {
            return;
        }

        $DB->insert_record('vimipad_layouthist', (object) [
            'workspaceid' => $workspaceid,
            'profile' => $profile,
            'revision' => $revision,
            'layoutjson' => $layoutjson,
            'modifiedby' => $userid,
            'timecreated' => time(),
        ]);

        // Cap the history: prune the oldest rows beyond the budget.
        $count = $DB->count_records('vimipad_layouthist', ['workspaceid' => $workspaceid, 'profile' => $profile]);
        if ($count > self::MAX_HISTORY) {
            $excess = $count - self::MAX_HISTORY;
            $oldest = $DB->get_records(
                'vimipad_layouthist',
                ['workspaceid' => $workspaceid, 'profile' => $profile],
                'revision ASC, id ASC',
                'id',
                0,
                $excess
            );
            if ($oldest) {
                $DB->delete_records_list('vimipad_layouthist', 'id', array_keys($oldest));
            }
        }
    }

    /**
     * The stored layout JSON as it was at (or before) a given revision.
     *
     * Returns the newest history row whose revision is <= the target, so a
     * replay shows the topology that was current at that revision. An empty
     * string means no layout was recorded that early (the caller falls back to
     * an automatic layout).
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @param int $revision The target revision.
     * @return string The layout JSON, or '' when none applies.
     */
    public function layout_at_revision(int $workspaceid, string $profile, int $revision): string {
        global $DB;

        $rows = $DB->get_records_select(
            'vimipad_layouthist',
            'workspaceid = :wsid AND profile = :profile AND revision <= :rev',
            ['wsid' => $workspaceid, 'profile' => $profile, 'rev' => $revision],
            'revision DESC, id DESC',
            'id, layoutjson',
            0,
            1
        );
        $row = $rows ? reset($rows) : null;
        return $row && $row->layoutjson !== null ? (string) $row->layoutjson : '';
    }

    /**
     * The full layout history for a workspace/profile, as {revision, layoutjson}
     * entries in ascending revision order. Used by the replay player to pick the
     * layout for each frame client-side.
     *
     * @param int $workspaceid The workspace id.
     * @param string $profile The diagram profile.
     * @return array[] List of ['revision' => int, 'layoutjson' => string].
     */
    public function layout_history(int $workspaceid, string $profile): array {
        global $DB;

        $rows = $DB->get_records(
            'vimipad_layouthist',
            ['workspaceid' => $workspaceid, 'profile' => $profile],
            'revision ASC, id ASC',
            'id, revision, layoutjson'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'revision' => (int) $row->revision,
                'layoutjson' => $row->layoutjson !== null ? (string) $row->layoutjson : '',
            ];
        }
        return $out;
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
     * Restore the stored position and size of pinned (move-locked) nodes.
     *
     * For each pinned stable id that has a stored position/size, the final
     * layout's entry is overwritten with the stored value, so a move-locked
     * node keeps its geometry regardless of what the incoming payload tried to
     * set (whether a merge patch or a full replace). A pinned node with no
     * stored geometry (first placement) is left untouched, so a freshly created
     * locked element can still be positioned once.
     *
     * @param string $finaljson The layout JSON about to be written (post-merge).
     * @param string $storedjson The previously stored layout JSON (may be empty).
     * @param array $pinned The stable ids to pin.
     * @return string The layout JSON with pinned nodes restored.
     */
    private static function pin_positions(string $finaljson, string $storedjson, array $pinned): string {
        $final = json_decode($finaljson, true);
        if (!is_array($final)) {
            return $finaljson;
        }
        $stored = ($storedjson !== '') ? json_decode($storedjson, true) : null;
        $storedpos = (is_array($stored) && is_array($stored['pos'] ?? null)) ? $stored['pos'] : [];
        $storedsize = (is_array($stored) && is_array($stored['size'] ?? null)) ? $stored['size'] : [];

        foreach ($pinned as $sid) {
            if (array_key_exists($sid, $storedpos)) {
                if (!isset($final['pos']) || !is_array($final['pos'])) {
                    $final['pos'] = [];
                }
                $final['pos'][$sid] = $storedpos[$sid];
            }
            if (array_key_exists($sid, $storedsize)) {
                if (!isset($final['size']) || !is_array($final['size'])) {
                    $final['size'] = [];
                }
                $final['size'][$sid] = $storedsize[$sid];
            }
        }

        return json_encode($final);
    }

    /**
     * The stable ids of all live nodes in a workspace whose move group is
     * locked in their metadata.
     *
     * Used by the layout endpoint to pin move-locked nodes when lock
     * enforcement is active. Only nodes carry layout positions (containers and
     * relations derive geometry elsewhere), so only nodes need checking here.
     *
     * @param int $workspaceid The workspace id.
     * @return string[] The move-locked node stable ids.
     */
    public function move_locked_node_stableids(int $workspaceid): array {
        global $DB;

        $rs = $DB->get_recordset(
            'vimipad_node',
            ['workspaceid' => $workspaceid, 'deleted' => 0],
            '',
            'id, stableid, metadatajson'
        );
        $locked = [];
        foreach ($rs as $node) {
            $meta = ($node->metadatajson === null || $node->metadatajson === '')
                ? null : json_decode($node->metadatajson, true);
            $meta = is_array($meta) ? $meta : null;
            $movelocked = \mod_vimipad\local\lock\element_lock::is_group_locked(
                $meta,
                \mod_vimipad\local\lock\element_lock::GROUP_MOVE
            );
            if ($movelocked) {
                $locked[] = $node->stableid;
            }
        }
        $rs->close();
        return $locked;
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
            return ['layoutjson' => '', 'revision' => 0, 'changed' => false];
        }

        // The change token is the strictly monotonic layout revision (seeded
        // from the former timestamp scheme on upgrade, so old client tokens
        // remain comparable).
        $revision = (int) ($record->layoutrevision ?? 0);
        $changed = $revision > $since;

        return [
            'layoutjson' => ($changed && $record->layoutjson !== null) ? $record->layoutjson : '',
            'revision' => $revision,
            'changed' => $changed,
        ];
    }
}
