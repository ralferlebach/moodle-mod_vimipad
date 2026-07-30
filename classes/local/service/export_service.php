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
     * Serialize a workspace to a well-formed XML string.
     *
     * Mirrors the JSON envelope so both formats round-trip the same data. Uses
     * XMLWriter for correct escaping.
     *
     * @param stdClass $instance The activity instance.
     * @param stdClass $workspace The workspace record.
     * @param string $profile The diagram profile to export.
     * @return string The XML document.
     */
    public function export_xml(stdClass $instance, stdClass $workspace, string $profile): string {
        $envelope = $this->build_envelope($instance, $workspace, $profile);
        $data = $envelope['data'];

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('vimipad');
        $writer->writeAttribute('formatversion', (string) $envelope['formatversion']);
        $writer->writeAttribute('generator', (string) $envelope['generator']);
        $writer->writeElement('activity', (string) $envelope['activity']);
        $writer->writeElement('exportedat', (string) $envelope['exportedat']);
        $writer->writeElement('profile', (string) ($data['profile'] ?? ''));
        $writer->writeElement('revision', (string) ($data['revision'] ?? 0));

        $this->write_collection($writer, 'nodes', 'node', $data['nodes'] ?? []);
        $this->write_collection($writer, 'relations', 'relation', $data['relations'] ?? []);
        $this->write_collection($writer, 'containers', 'container', $data['containers'] ?? []);
        $this->write_collection($writer, 'memberships', 'membership', $data['memberships'] ?? []);

        $writer->startElement('layout');
        if (!empty($data['layout'])) {
            $writer->writeCdata(json_encode($data['layout']));
        }
        $writer->endElement();

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * Write a list of associative records as repeated child elements.
     *
     * @param \XMLWriter $writer The writer.
     * @param string $wrapper The wrapping element name (e.g. 'nodes').
     * @param string $item The per-record element name (e.g. 'node').
     * @param array $records The records to write.
     * @return void
     */
    private function write_collection(\XMLWriter $writer, string $wrapper, string $item, array $records): void {
        $writer->startElement($wrapper);
        foreach ($records as $record) {
            $writer->startElement($item);
            foreach ((array) $record as $field => $value) {
                if ($value === null) {
                    continue;
                }
                $writer->writeElement((string) $field, (string) $value);
            }
            $writer->endElement();
        }
        $writer->endElement();
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
