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
 * Restore task definition for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/vimipad/backup/moodle2/restore_vimipad_stepslib.php');

/**
 * Restore task for the ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_vimipad_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define restore steps.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_vimipad_activity_structure_step('vimipad_structure', 'vimipad.xml'));
    }

    /**
     * Define contents to be decoded.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('vimipad', ['intro'], 'vimipad'),
        ];
    }

    /**
     * Define link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('VIMIPADVIEWBYID', '/mod/vimipad/view.php?id=$1', 'course_module'),
            new restore_decode_rule('VIMIPADINDEX', '/mod/vimipad/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
