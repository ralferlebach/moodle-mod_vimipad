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

namespace mod_vimipad\local\policy;

/**
 * Request-shape policy for the classic (non-webservice) PHP handlers.
 *
 * Every state-changing handler in view.php and the output panels is reached
 * from a POST form and guarded by sesskey. This policy adds the second half of
 * that contract: the request method itself must be POST, so a mutation can
 * never be triggered by a plain GET — browser prefetch, history replay, a
 * pasted URL or an embedded image tag must all be inert. Keeping the check in
 * one place means the handlers cannot drift apart, and it can be unit tested
 * without a web request.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_policy {
    /**
     * Whether the current request may perform a state change.
     *
     * @param string|null $method Request method to evaluate; defaults to the
     *                            current request's method. Passing it
     *                            explicitly keeps this unit testable.
     * @return bool True when the request is a POST.
     */
    public static function is_mutating_request(?string $method = null): bool {
        if ($method === null) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }
        return strtoupper(trim($method)) === 'POST';
    }
}
