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

        // Diagram profile. The list is provided by the profile subplugin
        // registry: the built-in profiles plus any installed vimipadform_*.
        $profiles = \mod_vimipad\local\form\registry::menu_options();
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

        $mform->addElement(
            'text',
            'channelurl',
            get_string('channelurl', 'mod_vimipad'),
            ['size' => '64', 'placeholder' => 'https://']
        );
        $mform->setType('channelurl', PARAM_URL);
        $mform->addHelpButton('channelurl', 'channelurl', 'mod_vimipad');

        // Group consensus: require every member to submit (group mode only).
        $mform->addElement(
            'advcheckbox',
            'requireallteamsubmit',
            get_string('requireallteamsubmit', 'mod_vimipad'),
            get_string('requireallteamsubmit_label', 'mod_vimipad')
        );
        $mform->setDefault('requireallteamsubmit', 0);
        $mform->addHelpButton('requireallteamsubmit', 'requireallteamsubmit', 'mod_vimipad');
        $mform->hideIf('requireallteamsubmit', 'collaborationmode', 'neq', 1);

        // Availability: optional due and cut-off dates.
        $mform->addElement('header', 'availability', get_string('availability', 'mod_vimipad'));

        $mform->addElement(
            'date_time_selector',
            'duedate',
            get_string('duedate', 'mod_vimipad'),
            ['optional' => true]
        );
        $mform->addHelpButton('duedate', 'duedate', 'mod_vimipad');

        $mform->addElement(
            'date_time_selector',
            'cutoffdate',
            get_string('cutoffdate', 'mod_vimipad'),
            ['optional' => true]
        );
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'mod_vimipad');

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add the completion-on-submit rule to the completion section.
     *
     * @return string[] The names of the added rule elements.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'completionsubmit',
            get_string('completionsubmit', 'mod_vimipad'),
            get_string('completionsubmit_label', 'mod_vimipad')
        );
        $mform->addHelpButton('completionsubmit', 'completionsubmit', 'mod_vimipad');
        $mform->setDefault('completionsubmit', 0);

        // Minimum concepts: an enable checkbox plus a number.
        $group = [];
        $group[] =& $mform->createElement(
            'checkbox',
            'completionminnodesenabled',
            '',
            get_string('completionminnodes', 'mod_vimipad')
        );
        $group[] =& $mform->createElement('text', 'completionminnodes', '', ['size' => 3]);
        $mform->setType('completionminnodes', PARAM_INT);
        $mform->addGroup(
            $group,
            'completionminnodesgroup',
            get_string('completionminnodesgroup', 'mod_vimipad'),
            [' '],
            false
        );
        $mform->addHelpButton('completionminnodesgroup', 'completionminnodesgroup', 'mod_vimipad');
        $mform->disabledIf('completionminnodes', 'completionminnodesenabled', 'notchecked');
        $mform->setDefault('completionminnodes', 3);

        $mform->addElement(
            'advcheckbox',
            'completiongraded',
            get_string('completiongraded', 'mod_vimipad'),
            get_string('completiongraded_label', 'mod_vimipad')
        );
        $mform->addHelpButton('completiongraded', 'completiongraded', 'mod_vimipad');
        $mform->setDefault('completiongraded', 0);

        return ['completionsubmit', 'completionminnodesgroup', 'completiongraded'];
    }

    /**
     * Whether any completion rule added by this form is enabled.
     *
     * @param array $data The submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionsubmit'])
            || !empty($data['completiongraded'])
            || (!empty($data['completionminnodesenabled']) && (int) $data['completionminnodes'] > 0);
    }

    /**
     * Validate the completion detail rules.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['completionminnodesenabled']) && (int) $data['completionminnodes'] < 1) {
            $errors['completionminnodesgroup'] = get_string('completionminnodes_error', 'mod_vimipad');
        }
        if (
            !empty($data['duedate']) && !empty($data['cutoffdate'])
                && (int) $data['cutoffdate'] < (int) $data['duedate']
        ) {
            $errors['cutoffdate'] = get_string('cutoffbeforedue', 'mod_vimipad');
        }
        return $errors;
    }
}
