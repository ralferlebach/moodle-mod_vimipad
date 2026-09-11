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

use mod_vimipad\api\value;

/**
 * Tests for the public map value policy.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\api\value
 */
final class api_value_test extends \advanced_testcase {
    /**
     * A small valid map.
     *
     * @param array $overrides Keys to replace in the document.
     * @return string The map JSON.
     */
    private function map(array $overrides = []): string {
        return (string) json_encode($overrides + [
            'profile' => 'conceptmap',
            'nodes' => [
                ['stableid' => 'n1', 'label' => 'Cat'],
                ['stableid' => 'n2', 'label' => 'Animal'],
            ],
            'relations' => [
                ['stableid' => 'r1', 'sourceid' => 'n1', 'targetid' => 'n2', 'label' => 'is a'],
            ],
        ]);
    }

    /**
     * A well-formed map passes.
     *
     * @return void
     */
    public function test_valid_map_passes(): void {
        $this->assertSame([], value::validate($this->map()));
        $this->assertTrue(value::is_valid($this->map()));
    }

    /**
     * Anything that is not a JSON object is refused.
     *
     * @return void
     */
    public function test_non_json_is_refused(): void {
        $this->assertSame(['notjson'], value::validate('not json at all'));
        $this->assertSame(['notjson'], value::validate('"a string"'));
    }

    /**
     * An oversized document is refused before it is parsed.
     *
     * @return void
     */
    public function test_oversized_is_refused(): void {
        $huge = str_repeat('x', value::MAX_BYTES + 1);
        $this->assertSame(['toolarge'], value::validate($huge));
    }

    /**
     * An unknown profile is refused, and a mismatch against an expected profile
     * is reported separately.
     *
     * @return void
     */
    public function test_profile_checks(): void {
        $this->assertContains('unknownprofile', value::validate($this->map(['profile' => 'nosuchprofile'])));
        $this->assertContains('wrongprofile', value::validate($this->map(), 'mindmap'));
        $this->assertSame([], value::validate($this->map(), 'conceptmap'));
    }

    /**
     * A relation whose endpoint does not exist is refused. This is the shape
     * that would otherwise reach the scorer and be silently dropped.
     *
     * @return void
     */
    public function test_dangling_relation_is_refused(): void {
        $json = $this->map([
            'relations' => [['stableid' => 'r1', 'sourceid' => 'n1', 'targetid' => 'ghost']],
        ]);
        $this->assertContains('danglingrelation', value::validate($json));
    }

    /**
     * A node without a usable stable id is refused, including the empty-object
     * node that is syntactically valid JSON but not a ViMi Pad document.
     *
     * @return void
     */
    public function test_node_without_stableid_is_refused(): void {
        $this->assertContains('badstableid', value::validate($this->map(['nodes' => [[]]])));
    }

    /**
     * Duplicate stable ids are refused.
     *
     * @return void
     */
    public function test_duplicate_ids_are_refused(): void {
        $json = $this->map([
            'nodes' => [
                ['stableid' => 'n1', 'label' => 'One'],
                ['stableid' => 'n1', 'label' => 'Two'],
            ],
            'relations' => [],
        ]);
        $this->assertContains('duplicateid', value::validate($json));
    }

    /**
     * Element counts and text lengths are bounded.
     *
     * @return void
     */
    public function test_limits_are_enforced(): void {
        $nodes = [];
        for ($i = 0; $i <= \mod_vimipad\local\policy\limits::MAX_NODES; $i++) {
            $nodes[] = ['stableid' => 'n' . $i, 'label' => 'x'];
        }
        $this->assertContains('toomanynodes', value::validate($this->map(['nodes' => $nodes, 'relations' => []])));

        $long = $this->map([
            'nodes' => [['stableid' => 'n1', 'label' => str_repeat('x', \mod_vimipad\local\policy\limits::MAX_LABEL + 1)]],
            'relations' => [],
        ]);
        $this->assertContains('labeltoolong', value::validate($long));
    }

    /**
     * A node's shape lives in metadatajson, not in a top-level key and not in
     * `type` (which carries the profile's semantic node type). The profile
     * decides which shapes it allows; a consumer may narrow that further.
     *
     * @return void
     */
    public function test_shape_is_read_from_metadata(): void {
        // The value "concept" is a node type, never a shape: it must not be mistaken for
        // one and rejected when a consumer restricts shapes.
        $typed = $this->map([
            'nodes' => [[
                'stableid' => 'n1',
                'label' => 'One',
                'type' => 'concept',
                'metadatajson' => '{"shape":"ellipse"}',
            ]],
            'relations' => [],
        ]);
        $this->assertSame([], value::validate($typed, null, ['ellipse']));
    }

    /**
     * A shape the profile does not allow is refused even with no consumer
     * restriction, so a forged request cannot widen the profile's own rules.
     *
     * @return void
     */
    public function test_shape_must_satisfy_the_profile(): void {
        $bogus = $this->map([
            'nodes' => [[
                'stableid' => 'n1',
                'label' => 'One',
                'metadatajson' => '{"shape":"triangle"}',
            ]],
            'relations' => [],
        ]);
        $this->assertNotSame([], value::validate($bogus));
    }

    /**
     * A consumer restriction narrows the profile's set: a shape the profile
     * allows but the consumer does not is refused.
     *
     * @return void
     */
    public function test_consumer_restriction_narrows_the_profile(): void {
        $json = $this->map([
            'nodes' => [[
                'stableid' => 'n1',
                'label' => 'One',
                'metadatajson' => '{"shape":"ellipse"}',
            ]],
            'relations' => [],
        ]);
        $this->assertContains('shapenotallowed', value::validate($json, null, ['rect']));
        $this->assertSame([], value::validate($json, null, ['rect', 'ellipse']));
    }

    /**
     * A node without a shape uses the profile default and is accepted.
     *
     * @return void
     */
    public function test_absent_shape_is_accepted(): void {
        $json = $this->map([
            'nodes' => [['stableid' => 'n1', 'label' => 'One']],
            'relations' => [],
        ]);
        $this->assertSame([], value::validate($json, null, ['rect']));
    }

    /**
     * Malformed node metadata is refused.
     *
     * @return void
     */
    public function test_bad_metadata_is_refused(): void {
        $json = $this->map([
            'nodes' => [['stableid' => 'n1', 'label' => 'One', 'metadatajson' => 'not json']],
            'relations' => [],
        ]);
        $this->assertContains('badmetadata', value::validate($json));
    }

    /**
     * An element collection sent as an object rather than a list is refused.
     *
     * @return void
     */
    public function test_collections_must_be_lists(): void {
        $json = (string) json_encode([
            'profile' => 'conceptmap',
            'nodes' => ['a' => ['stableid' => 'n1', 'label' => 'One']],
            'relations' => [],
        ]);
        $this->assertContains('badstructure', value::validate($json));
    }

    /**
     * assert_valid throws, and normalise returns canonical JSON.
     *
     * @return void
     */
    public function test_assert_and_normalise(): void {
        $this->assertSame($this->map(), value::normalise($this->map()));

        $this->expectException(\moodle_exception::class);
        value::assert_valid('{"nodes":[{}]}');
    }
}
