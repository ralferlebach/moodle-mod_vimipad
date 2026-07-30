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

namespace mod_vimipad\event;

/**
 * The mod_vimipad map updated event.
 *
 * Fired when a learner applies a semantic operation to a workspace (node or
 * relation created, updated, moved or deleted), so course logging and reports
 * can see editing activity. One event per applied operation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class map_updated extends \core\event\base {
    /**
     * Initialise event parameters.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'vimipad_workspace';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * The human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:map_updated', 'mod_vimipad');
    }

    /**
     * The plain-text description for the logs.
     *
     * @return string
     */
    public function get_description(): string {
        $type = isset($this->other['operationtype']) ? (string) $this->other['operationtype'] : 'operation';
        return "The user with id '{$this->userid}' applied a '{$type}' operation to the workspace " .
            "with id '{$this->objectid}' in the vimipad activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * The URL related to this event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/vimipad/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Object id mapping for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'vimipad_workspace', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
