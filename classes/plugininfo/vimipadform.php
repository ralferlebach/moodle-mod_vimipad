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

namespace mod_vimipad\plugininfo;

use core\plugininfo\base;

/**
 * Subplugin info for the vimipadform (diagram form / display type) type.
 *
 * Moodle's plugin manager resolves this class for the "vimipadform" subplugin
 * type declared in db/subplugins.json. Each vimipadform_* subplugin contributes
 * one display type (its shapes, connector style and bifurcation); they carry no
 * admin settings and are managed as part of the parent activity, so this info
 * class only needs to permit uninstallation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vimipadform extends base {
    /**
     * Whether a subplugin of this type may be uninstalled from the admin UI.
     *
     * Uninstalling is deliberately always allowed: form subplugins own no
     * tables, and an activity whose profile becomes unavailable degrades to the
     * safe fallback form definition (see \mod_vimipad\local\form\fallback and
     * vimipad_normalise_profile in lib.php), rather than breaking. This "repair,
     * don't block" behaviour is covered by profile_uninstall_safety_test. If a
     * profile's subplugin is removed while activities reference it, those
     * activities keep working with the generic fallback rendering.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }
}
