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

namespace mod_vimipad\api;

use mod_vimipad\local\service\reconstruction_service;

/**
 * Public, stable facade for reading a reconstructed map state.
 *
 * A map's authoritative history lives in the server-validated operation log.
 * This facade lets a dependent plugin (e.g. a question type embedding a map,
 * or a report) reconstruct the surviving state at any revision without knowing
 * the internal service layout.
 *
 * Access control is the caller's responsibility: this facade performs pure
 * reconstruction and does NOT check capabilities or context. Callers exposing
 * it over the web must enforce their own access checks first (the module's own
 * external functions do so via \mod_vimipad\external\helper).
 *
 * This class is part of the intentionally stable contract under
 * \mod_vimipad\api; its signatures are treated as stable across minor releases,
 * while the internal implementation under \mod_vimipad\local may change freely.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class map {
    /**
     * Reconstruct the surviving state of a map at a given revision.
     *
     * The returned array has three keys — 'nodes', 'relations' and
     * 'containers' — each a list of stdClass records for elements that are
     * live (not deleted) at that revision. Relations whose endpoints no longer
     * exist are omitted.
     *
     * The revision must be non-negative. A revision beyond the workspace's
     * current revision simply yields the full current state (all operations
     * are replayed); callers that need strict upper-bound enforcement should
     * check against the workspace's current revision themselves.
     *
     * @param int $workspaceid The workspace id.
     * @param int $revision The revision to rebuild (inclusive); must be >= 0.
     * @return array The reconstructed state: nodes, relations, containers.
     * @throws \invalid_parameter_exception If the revision is negative.
     */
    public static function state_at(int $workspaceid, int $revision): array {
        if ($revision < 0) {
            throw new \invalid_parameter_exception('revision must be non-negative');
        }
        return (new reconstruction_service())->reconstruct($workspaceid, $revision);
    }
}
