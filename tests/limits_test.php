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

use mod_vimipad\local\policy\limits;
use mod_vimipad\local\service\operation_service;

/**
 * Resource limit enforcement.
 *
 * The limits policy guards text lengths, element counts and geometry sanity;
 * the operation service enforces it on every mutating payload (which the
 * import path funnels through as well).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\policy\limits
 * @covers     \mod_vimipad\local\service\operation_service
 */
final class limits_test extends \advanced_testcase {
    /**
     * The pure policy checks: text, count and geometry domains.
     *
     * @return void
     */
    public function test_policy_checks(): void {
        // Within bounds: no exception.
        limits::check_text(str_repeat('a', limits::MAX_LABEL), limits::MAX_LABEL, 'label');
        limits::check_count(limits::MAX_NODES - 1, limits::MAX_NODES, 'nodes');
        limits::check_geometry(json_encode(['x' => 0, 'y' => 0, 'w' => 100, 'h' => 50]));
        limits::check_geometry(null);

        // Text over the ceiling.
        try {
            limits::check_text(str_repeat('a', limits::MAX_LABEL + 1), limits::MAX_LABEL, 'label');
            $this->fail('Expected error:textlimit.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:textlimit', $e->errorcode);
        }

        // Count at the ceiling.
        try {
            limits::check_count(limits::MAX_NODES, limits::MAX_NODES, 'nodes');
            $this->fail('Expected error:maplimit.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:maplimit', $e->errorcode);
        }

        // Geometry: non-finite, non-positive and oversized are all rejected.
        foreach (
            [
                json_encode(['x' => 0, 'y' => 0, 'w' => 0, 'h' => 50]),
                json_encode(['x' => 0, 'y' => 0, 'w' => 100]),
                '{"x":"NaN","y":0,"w":1,"h":1}',
                json_encode(['x' => limits::MAX_COORDINATE * 2, 'y' => 0, 'w' => 1, 'h' => 1]),
                'not json',
            ] as $bad
        ) {
            try {
                limits::check_geometry($bad);
                $this->fail('Expected error:invalidgeometry for: ' . $bad);
            } catch (\moodle_exception $e) {
                $this->assertSame('error:invalidgeometry', $e->errorcode);
            }
        }
    }

    /**
     * The operation service rejects an over-long label and a broken geometry.
     *
     * @return void
     */
    public function test_operation_service_enforces_limits(): void {
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

        try {
            $service->apply($wsid, 0, 'node_create', [
                'type' => 'concept', 'label' => str_repeat('x', limits::MAX_LABEL + 1),
            ], 1);
            $this->fail('Expected error:textlimit.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:textlimit', $e->errorcode);
        }

        try {
            $service->apply($wsid, 0, 'container_create', [
                'type' => 'group', 'geometryjson' => json_encode(['x' => 0, 'y' => 0, 'w' => -5, 'h' => 10]),
            ], 1);
            $this->fail('Expected error:invalidgeometry.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:invalidgeometry', $e->errorcode);
        }

        // A well-formed create still passes.
        $result = $service->apply($wsid, 0, 'node_create', ['type' => 'concept', 'label' => 'Fine'], 1);
        $this->assertNotEmpty($result['stableid']);
    }
}
