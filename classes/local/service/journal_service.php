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
 * Stores and retrieves learner journal entries for a workspace.
 *
 * Each entry belongs to a workspace and its author. Entries are either private
 * (author only) or teacher-visible. Internal (\mod_vimipad\local).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class journal_service {
    /** @var int Entry visible to the author and to teachers (the default). */
    const VISIBILITY_TEACHER = 0;

    /** @var int Entry visible only to its author (hidden from teachers). */
    const VISIBILITY_PRIVATE = 1;

    /**
     * Add a journal entry.
     *
     * Visibility contract (teachers can always read the journal by default):
     * an entry is teacher-visible unless the author marks it private AND the
     * activity allows private entries. The "allow private" gate is enforced
     * here, so a client cannot hide an entry when the activity forbids it.
     *
     * @param int $workspaceid The workspace id.
     * @param int $userid The author.
     * @param string $text The entry text.
     * @param int $format The text format.
     * @param bool $private Whether the author asked to keep the entry private.
     * @param bool $allowprivate Whether the activity allows private entries.
     * @param int|null $revisionref The map revision the entry refers to, if any.
     * @return int The new entry id.
     */
    public function add_entry(
        int $workspaceid,
        int $userid,
        string $text,
        int $format,
        bool $private,
        bool $allowprivate,
        ?int $revisionref = null
    ): int {
        global $DB;

        $now = time();
        // Private only when the author asked for it and the activity permits
        // it; otherwise the entry is visible to teachers.
        $normalised = ($private && $allowprivate)
            ? self::VISIBILITY_PRIVATE
            : self::VISIBILITY_TEACHER;

        return (int) $DB->insert_record('vimipad_journalentry', (object) [
            'workspaceid' => $workspaceid,
            'userid' => $userid,
            'revisionref' => $revisionref,
            'entrytext' => $text,
            'entryformat' => $format,
            'visibility' => $normalised,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * All entries a given author wrote in a workspace, newest first.
     *
     * @param int $workspaceid The workspace id.
     * @param int $userid The author.
     * @return array List of entry records.
     */
    public function get_entries_for_user(int $workspaceid, int $userid): array {
        global $DB;
        return array_values($DB->get_records(
            'vimipad_journalentry',
            ['workspaceid' => $workspaceid, 'userid' => $userid],
            'timecreated DESC'
        ));
    }

    /**
     * All teacher-visible entries in a workspace, newest first (for graders).
     *
     * @param int $workspaceid The workspace id.
     * @return array List of entry records.
     */
    public function get_teacher_visible(int $workspaceid): array {
        global $DB;
        return array_values($DB->get_records(
            'vimipad_journalentry',
            ['workspaceid' => $workspaceid, 'visibility' => self::VISIBILITY_TEACHER],
            'timecreated DESC'
        ));
    }
}
