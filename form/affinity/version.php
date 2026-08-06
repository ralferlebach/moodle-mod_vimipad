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
 * Version definition for the Affinity board display-type subplugin.
 *
 * @package    vimipadform_affinity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'vimipadform_affinity';
$plugin->version   = 2026072796;
$plugin->requires  = 2024100700;   // Moodle 4.5.0.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    // Requires the cluster-cohesion contract added in mod_vimipad 0.8.26.
    'mod_vimipad' => 2026072796,
];
