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

namespace mod_vimipad;

use mod_vimipad\local\service\operation_service;

/**
 * T4: a container carries the same style model as a node (shape, fill, text) in
 * its metadatajson, and container_update persists it.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\operation_service
 */
final class container_style_test extends \advanced_testcase {
    /**
     * Create, style and re-style a container; the metadata round-trips.
     *
     * @return void
     */
    public function test_container_style_roundtrips(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $service = new operation_service();
        $rev = 0;

        // Create with a shape + fill + text style, exactly as the editor sends it.
        $style = ['shape' => 'ellipse', 'fill' => '#ffe0b2', 'text' => ['color' => '#3e2723', 'bold' => true]];
        $r = $service->apply($wsid, $rev, 'container_create', [
            'type' => 'group', 'label' => 'Region', 'metadatajson' => json_encode($style),
        ], 1);
        $rev = $r['revision'];
        $cid = $r['stableid'];

        $stored = json_decode($DB->get_field('vimipad_container', 'metadatajson', ['stableid' => $cid]), true);
        $this->assertSame('ellipse', $stored['shape']);
        $this->assertSame('#ffe0b2', $stored['fill']);
        $this->assertTrue($stored['text']['bold']);

        // Re-style via container_update (change shape and drop bold).
        $restyle = ['shape' => 'rect', 'fill' => '#c8e6c9', 'text' => ['color' => '#1b5e20']];
        $r = $service->apply($wsid, $rev, 'container_update', [
            'stableid' => $cid, 'metadatajson' => json_encode($restyle),
        ], 1);
        $rev = $r['revision'];

        $stored = json_decode($DB->get_field('vimipad_container', 'metadatajson', ['stableid' => $cid]), true);
        $this->assertSame('rect', $stored['shape']);
        $this->assertSame('#c8e6c9', $stored['fill']);
        $this->assertArrayNotHasKey('bold', $stored['text']);

        // The label channel is independent of the style metadata.
        $r = $service->apply($wsid, $rev, 'container_update', ['stableid' => $cid, 'label' => 'Renamed'], 1);
        $this->assertSame('Renamed', $DB->get_field('vimipad_container', 'label', ['stableid' => $cid]));
        $stored = json_decode($DB->get_field('vimipad_container', 'metadatajson', ['stableid' => $cid]), true);
        $this->assertSame('rect', $stored['shape'], 'Renaming must not clear the style.');
    }
}
