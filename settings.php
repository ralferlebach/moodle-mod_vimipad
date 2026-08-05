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
 * Administration settings for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_vimipad/enableai',
        get_string('setting:enableai', 'mod_vimipad'),
        get_string('setting:enableai_desc', 'mod_vimipad'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_vimipad/storeprompts',
        get_string('setting:storeprompts', 'mod_vimipad'),
        get_string('setting:storeprompts_desc', 'mod_vimipad'),
        0
    ));

    // Editor: canvas behaviour.
    $settings->add(new admin_setting_heading(
        'mod_vimipad/editorheading',
        get_string('setting:editorheading', 'mod_vimipad'),
        get_string('setting:editorheading_desc', 'mod_vimipad')
    ));

    // Iteration ceiling for the "Arrange" action's layout solver. Higher lets a
    // single press converge further on large maps, at some CPU cost per press.
    $settings->add(new admin_setting_configtext(
        'mod_vimipad/arrangeiterations',
        get_string('setting:arrangeiterations', 'mod_vimipad'),
        get_string('setting:arrangeiterations_desc', 'mod_vimipad'),
        500,
        PARAM_INT
    ));

    // Whether the "Arrange" action may shrink an oversized container toward its
    // members. On by default (boxes hug their contents); turn off to keep boxes
    // at their drawn size and only ever grow them to contain overflow — useful
    // where teachers size template containers deliberately.
    $settings->add(new admin_setting_configcheckbox(
        'mod_vimipad/arrangeshrink',
        get_string('setting:arrangeshrink', 'mod_vimipad'),
        get_string('setting:arrangeshrink_desc', 'mod_vimipad'),
        1
    ));

    // Collaboration: polling, adaptive intervals, element leases and optional push.
    $settings->add(new admin_setting_heading(
        'mod_vimipad/collabheading',
        get_string('setting:collabheading', 'mod_vimipad'),
        get_string('setting:collabheading_desc', 'mod_vimipad')
    ));

    $settings->add(new admin_setting_configduration(
        'mod_vimipad/pollinterval',
        get_string('setting:pollinterval', 'mod_vimipad'),
        get_string('setting:pollinterval_desc', 'mod_vimipad'),
        1,
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_vimipad/polladaptive',
        get_string('setting:polladaptive', 'mod_vimipad'),
        get_string('setting:polladaptive_desc', 'mod_vimipad'),
        1
    ));

    $settings->add(new admin_setting_configduration(
        'mod_vimipad/pollmin',
        get_string('setting:pollmin', 'mod_vimipad'),
        get_string('setting:pollmin_desc', 'mod_vimipad'),
        1,
        1
    ));

    $settings->add(new admin_setting_configduration(
        'mod_vimipad/pollmax',
        get_string('setting:pollmax', 'mod_vimipad'),
        get_string('setting:pollmax_desc', 'mod_vimipad'),
        10,
        1
    ));

    $settings->add(new admin_setting_configduration(
        'mod_vimipad/leasetimeout',
        get_string('setting:leasetimeout', 'mod_vimipad'),
        get_string('setting:leasetimeout_desc', 'mod_vimipad'),
        15,
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_vimipad/pushenabled',
        get_string('setting:pushenabled', 'mod_vimipad'),
        get_string('setting:pushenabled_desc', 'mod_vimipad'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_vimipad/pushendpoint',
        get_string('setting:pushendpoint', 'mod_vimipad'),
        get_string('setting:pushendpoint_desc', 'mod_vimipad'),
        '',
        PARAM_URL
    ));
}
