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
 * Status check: the append-only histories stay within a healthy size.
 *
 * The operation log and the layout history grow as maps are edited. They are
 * bounded per workspace, but a very large total across the site can indicate a
 * pruning/compaction problem or an unusually heavy deployment. This surfaces the
 * totals so an admin can act before it affects replay performance.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_size extends check {
    /** @var int Soft budget for operation-log rows before warning. */
    private const OP_BUDGET = 5000000;

    /** @var int Soft budget for layout-history rows before warning. */
    private const LAYOUT_BUDGET = 2000000;

    /**
     * Measure the history tables against their soft budgets.
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;

        $ops = $DB->count_records('vimipad_operation');
        $layouts = $DB->count_records('vimipad_layouthist');

        $details = get_string('check:historydetails', 'mod_vimipad', (object) [
            'ops' => $ops, 'layouts' => $layouts,
        ]);

        if ($ops > self::OP_BUDGET || $layouts > self::LAYOUT_BUDGET) {
            return new result(
                result::WARNING,
                get_string('check:historylarge', 'mod_vimipad'),
                $details
            );
        }
        return new result(result::OK, get_string('check:historyok', 'mod_vimipad'), $details);
    }
}
