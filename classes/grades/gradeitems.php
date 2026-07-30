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

namespace mod_vimipad\grades;

use core_grades\local\gradeitem\advancedgrading_mapping;
use core_grades\local\gradeitem\itemnumber_mapping;

/**
 * Grade item mapping for mod_vimipad.
 *
 * Maps the single grade (item number 0) to the "submissions" advanced-grading
 * area, so Moodle's grading manager can attach a rubric or marking guide to the
 * activity's grade.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeitems implements advancedgrading_mapping, itemnumber_mapping {
    /**
     * Map grade item numbers to their item names.
     *
     * @return array Item number => item name.
     */
    public static function get_itemname_mapping_for_component(): array {
        return [
            0 => 'submissions',
        ];
    }

    /**
     * The item names that support advanced grading.
     *
     * @return array List of grade item names.
     */
    public static function get_advancedgrading_itemnames(): array {
        return [
            'submissions',
        ];
    }
}
