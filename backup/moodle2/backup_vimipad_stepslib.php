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

/**
 * Backup structure step for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the backup structure of the ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_vimipad_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure to be backed up.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $vimipad = new backup_nested_element('vimipad', ['id'], [
            'name', 'intro', 'introformat', 'defaultprofile',
            'collaborationmode', 'gradingmode', 'aienabled',
            'timecreated', 'timemodified',
        ]);

        // Future: workspaces, nodes, relations, snapshots, annotations
        // will be nested here (userinfo-dependent).

        $vimipad->set_source_table('vimipad', ['id' => backup::VAR_ACTIVITYID]);
        $vimipad->annotate_files('mod_vimipad', 'intro', null);

        return $this->prepare_activity_structure($vimipad);
    }
}
