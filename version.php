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
 * Plugin version definition for mod_vimipad (ViMi Pad - Visual Mind Pad).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component    = 'mod_vimipad';
$plugin->version      = 2026072720;
$plugin->requires     = 2024100700;   // Moodle 4.5.0 — hard minimum, per Lastenheft.
// Target range: Moodle 4.5 LTS up to 5.3. From 5.3 the React runtime ships in
// core (react_autoinit); 4.5-5.2 use the bundled editor asset shipped here.
$plugin->supported    = [405, 503];
$plugin->maturity     = MATURITY_ALPHA;
$plugin->release      = '0.6.6';   // Shared read-access helper (removes external-function duplication).

// No plugin dependencies. AI feedback uses the core AI subsystem (Moodle >= 4.5),
// detected and gated at runtime — deliberately NOT declared as a dependency so the
// activity installs and runs on instances without any AI provider configured.
$plugin->dependencies = [];
