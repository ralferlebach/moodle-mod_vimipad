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
 * Subplugin info for the vimipadassess (automatic scoring) type.
 *
 * Moodle's plugin manager resolves this class for the "vimipadassess" subplugin
 * type declared in db/subplugins.json. Each vimipadassess_* subplugin contributes
 * one scorer (reference, structure, tree, llm, ...); they carry no admin settings
 * and are managed as part of the parent activity, so this info class only needs
 * to permit uninstallation.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vimipadassess extends base {
    /**
     * Whether a subplugin of this type may be uninstalled from the admin UI.
     *
     * Uninstalling is deliberately always allowed: an activity whose configured
     * scorer becomes unavailable simply produces no automatic score (the
     * assess_service returns null when the scorer is absent) rather than
     * breaking, and teacher grading is unaffected. This graceful degradation is
     * covered by assess_uninstall_safety_test.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }
}
