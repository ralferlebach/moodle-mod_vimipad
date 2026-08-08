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

namespace mod_vimipad\check;

use core\check\check;
use core\check\result;

/**
 * Status check: the declared subplugin types are installed and consistent.
 *
 * mod_vimipad declares the vimipadassess and vimipadform subplugin types. This
 * check confirms each installed subplugin's version is registered with the core
 * plugin manager, so a half-installed or stale subplugin is surfaced rather than
 * failing silently at runtime.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subplugins extends check {
    /**
     * Verify the subplugin registration.
     *
     * @return result
     */
    public function get_result(): result {
        $manager = \core_plugin_manager::instance();
        $types = ['vimipadassess', 'vimipadform'];

        $installed = 0;
        $problems = [];
        foreach ($types as $type) {
            $plugins = \core_component::get_plugin_list($type);
            foreach ($plugins as $name => $dir) {
                $installed++;
                $info = $manager->get_plugin_info($type . '_' . $name);
                if ($info === null || $info->versiondb === null) {
                    $problems[] = get_string('check:subpluginmissing', 'mod_vimipad', $type . '_' . $name);
                }
            }
        }

        if (empty($problems)) {
            return new result(
                result::OK,
                get_string('check:subpluginsok', 'mod_vimipad', $installed)
            );
        }
        return new result(
            result::WARNING,
            get_string('check:subpluginproblems', 'mod_vimipad', count($problems)),
            \html_writer::alist($problems)
        );
    }
}
