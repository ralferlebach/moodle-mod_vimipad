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
    /** @var int Entry visible only to its author. */
    const VISIBILITY_PRIVATE = 0;

    /** @var int Entry visible to the author and to teachers. */
    const VISIBILITY_TEACHER = 1;

    /**
     * Add a journal entry.
     *
     * @param int $workspaceid The workspace id.
     * @param int $userid The author.
     * @param string $text The entry text.
     * @param int $format The text format.
     * @param int $visibility VISIBILITY_PRIVATE or VISIBILITY_TEACHER.
     * @param int|null $revisionref The map revision the entry refers to, if any.
     * @return int The new entry id.
     */
    public function add_entry(
        int $workspaceid,
        int $userid,
        string $text,
        int $format,
        int $visibility,
        ?int $revisionref = null
    ): int {
        global $DB;

        $now = time();
        $normalised = $visibility === self::VISIBILITY_TEACHER
            ? self::VISIBILITY_TEACHER
            : self::VISIBILITY_PRIVATE;

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
