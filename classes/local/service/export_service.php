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

namespace mod_vimipad\local\service;

use stdClass;

/**
 * Exports a workspace to a portable, versioned file format.
 *
 * The JSON envelope wraps the same normalized structure used for snapshots
 * (nodes, relations, containers, layout) with export metadata, so it can be
 * re-imported (a later milestone) and used as a master-solution source.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_service {
    /** @var int The export envelope format version. */
    public const FORMAT_VERSION = 1;

    /**
     * Build the versioned export envelope for a workspace.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The workspace record.
     * @param string $profile The diagram profile to export.
     * @return array The export envelope.
     */
    public function build_envelope(stdClass $instance, stdClass $workspace, string $profile): array {
        $snapshotservice = new snapshot_service();
        return [
            'formatversion' => self::FORMAT_VERSION,
            'generator' => 'mod_vimipad',
            'activity' => $instance->name,
            'exportedat' => time(),
            'data' => $snapshotservice->build_normalized($workspace, $profile),
        ];
    }

    /**
     * Serialize a workspace to a pretty-printed JSON string.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The workspace record.
     * @param string $profile The diagram profile to export.
     * @return string The JSON document.
     */
    public function export_json(stdClass $instance, stdClass $workspace, string $profile): string {
        $envelope = $this->build_envelope($instance, $workspace, $profile);
        return json_encode(
            $envelope,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Build a safe download filename for an export.
     *
     * @param stdClass $instance The activity instance.
     * @param string $profile The diagram profile.
     * @param string $extension The file extension without a dot.
     * @return string The cleaned filename.
     */
    public function filename(stdClass $instance, string $profile, string $extension): string {
        return clean_filename($instance->name . '-' . $profile) . '.' . $extension;
    }
}
