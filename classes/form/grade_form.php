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

namespace mod_vimipad\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Grade form for a single submission.
 *
 * Uses Moodle's advanced grading element (rubric / marking guide) when a method
 * is active for the activity, otherwise a plain numeric grade. Feedback is
 * always available.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'id', (int) $custom['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'grade');
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('hidden', 'snapshotid', (int) $custom['snapshotid']);
        $mform->setType('snapshotid', PARAM_INT);

        if (!empty($custom['gradinginstance'])) {
            // Advanced grading: the rubric / marking guide widget derives the grade.
            $mform->addElement(
                'grading',
                'advancedgrading',
                get_string('grade', 'mod_vimipad'),
                ['gradinginstance' => $custom['gradinginstance']]
            );
        } else {
            $mform->addElement('text', 'grade', get_string('gradeoutof', 'mod_vimipad', $custom['maxgrade']));
            $mform->setType('grade', PARAM_RAW_TRIMMED);
            if (isset($custom['grade']) && $custom['grade'] !== null && $custom['grade'] !== '') {
                $mform->setDefault('grade', $custom['grade']);
            }
        }

        $mform->addElement('textarea', 'feedback', get_string('feedback', 'mod_vimipad'), ['rows' => 3]);
        $mform->setType('feedback', PARAM_TEXT);
        if (!empty($custom['feedback'])) {
            $mform->setDefault('feedback', $custom['feedback']);
        }

        $this->add_action_buttons(false, get_string('savegrade', 'mod_vimipad'));
    }
}
