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
 * Activity creation/edit form for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Settings form for the ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_vimipad_mod_form extends moodleform_mod {

    /**
     * Define the form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('vimipadname', 'mod_vimipad'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Diagram profile. Only the MVP profiles are listed; the list will be
        // provided by the profile subplugin registry once it exists.
        $profiles = [
            'conceptmap' => get_string('profile_conceptmap', 'mod_vimipad'),
            'mindmap' => get_string('profile_mindmap', 'mod_vimipad'),
            'tree' => get_string('profile_tree', 'mod_vimipad'),
            'semanticnetwork' => get_string('profile_semanticnetwork', 'mod_vimipad'),
            'bubblemap' => get_string('profile_bubblemap', 'mod_vimipad'),
        ];
        $mform->addElement('select', 'defaultprofile', get_string('defaultprofile', 'mod_vimipad'), $profiles);
        $mform->setDefault('defaultprofile', 'conceptmap');
        $mform->addHelpButton('defaultprofile', 'defaultprofile', 'mod_vimipad');

        // Collaboration mode: individual / group / course.
        $modes = [
            0 => get_string('mode_individual', 'mod_vimipad'),
            1 => get_string('mode_group', 'mod_vimipad'),
            2 => get_string('mode_course', 'mod_vimipad'),
        ];
        $mform->addElement('select', 'collaborationmode', get_string('collaborationmode', 'mod_vimipad'), $modes);
        $mform->setDefault('collaborationmode', 0);
        $mform->addHelpButton('collaborationmode', 'collaborationmode', 'mod_vimipad');

        // AI assistance toggle (gated at runtime by capability + core AI subsystem availability).
        $mform->addElement('advcheckbox', 'aienabled', get_string('aienabled', 'mod_vimipad'));
        $mform->setDefault('aienabled', 0);
        $mform->addHelpButton('aienabled', 'aienabled', 'mod_vimipad');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
