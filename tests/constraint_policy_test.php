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

use mod_vimipad\local\policy\constraint_config;
use mod_vimipad\local\policy\constraint_policy;
use mod_vimipad\local\service\snapshot_service;

/**
 * Tests for the constraint policy resolver and the hard submission gate.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\policy\constraint_policy
 * @covers     \mod_vimipad\local\policy\constraint_config
 * @covers     \mod_vimipad\local\policy\constraint_report
 */
final class constraint_policy_test extends \advanced_testcase {
    /**
     * A sample two-node, two-relation map for the pure tests.
     *
     * @return array
     */
    private function sample_map(): array {
        return [
            'nodes' => [
                ['label' => 'Cell', 'type' => 'concept'],
                ['label' => 'DNA', 'type' => 'concept'],
            ],
            'relations' => [
                ['type' => 'contains', 'label' => ''],
                ['type' => 'unknown', 'label' => ''],
            ],
        ];
    }

    /**
     * An empty config is always satisfied.
     *
     * @return void
     */
    public function test_empty_config_is_satisfied(): void {
        $report = constraint_policy::evaluate($this->sample_map(), new constraint_config());
        $this->assertTrue($report->is_satisfied());
        $this->assertSame('', $report->summary());
    }

    /**
     * Each constraint kind is detected (case-insensitively).
     *
     * @return void
     */
    public function test_each_constraint_kind_is_detected(): void {
        $config = new constraint_config();
        $config->requiredconcepts = ['photosynthesis', 'cell'];
        $config->forbiddenconcepts = ['dna'];
        $config->allowedrelationtypes = ['contains'];
        $config->minnodes = 3;
        $config->minrelations = 3;

        $report = constraint_policy::evaluate($this->sample_map(), $config);

        $this->assertFalse($report->is_satisfied());
        $this->assertSame(['photosynthesis'], $report->requiredmissing, 'cell is present (case-insensitive)');
        $this->assertSame(['dna'], $report->forbiddenpresent);
        $this->assertSame(['unknown'], $report->typeviolations);
        $this->assertSame([3, 2], $report->belowminnodes);
        $this->assertSame([3, 2], $report->belowminrelations);
        $this->assertNotEmpty($report->messages());
    }

    /**
     * A fully satisfied config yields a clean report.
     *
     * @return void
     */
    public function test_satisfied_config(): void {
        $config = new constraint_config();
        $config->requiredconcepts = ['cell', 'dna'];
        $config->forbiddenconcepts = ['virus'];
        $config->allowedrelationtypes = ['contains', 'unknown'];
        $config->minnodes = 2;
        $config->minrelations = 2;

        $report = constraint_policy::evaluate($this->sample_map(), $config);
        $this->assertTrue($report->is_satisfied());
    }

    /**
     * split_terms normalizes, lowercases and de-duplicates free text.
     *
     * @return void
     */
    public function test_split_terms_normalizes(): void {
        $this->assertSame(
            ['cell', 'dna', 'rna'],
            constraint_config::split_terms("Cell, DNA\n rna , Cell ")
        );
        $this->assertSame([], constraint_config::split_terms('   '));
    }

    /**
     * from_instance reads the qualitative fields when present.
     *
     * @return void
     */
    public function test_from_instance_reads_fields(): void {
        $config = constraint_config::from_instance((object) [
            'requiredconcepts' => 'Water, Sun',
            'minnodes' => 4,
        ]);
        $this->assertSame(['water', 'sun'], $config->requiredconcepts);
        $this->assertSame(4, $config->minnodes);
        $this->assertFalse($config->is_empty());
    }

    /**
     * The submission gate blocks a map that violates a constraint and leaves
     * the workspace unlocked.
     *
     * @return void
     */
    public function test_submission_gate_blocks_invalid_map(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);
        $now = time();
        $wsid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 1, 'locked' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $wsid, 'stableid' => 'node_aaaaaaaaaaaa', 'type' => 'concept', 'label' => 'Cell',
            'contentformat' => FORMAT_HTML, 'createdby' => 1, 'modifiedby' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $workspace = $DB->get_record('vimipad_workspace', ['id' => $wsid], '*', MUST_EXIST);

        // Inject a required concept the map does not contain.
        $instance->requiredconcepts = 'Mitochondria';
        try {
            (new snapshot_service())->create_submission($instance, $workspace, $context, 1);
            $this->fail('Expected the submission to be blocked.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('constraintsnotmet', $e->errorcode);
        }
        $this->assertEquals(0, $DB->get_field('vimipad_workspace', 'locked', ['id' => $wsid]));

        // Add the required concept: submission now succeeds and locks the map.
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $wsid, 'stableid' => 'node_bbbbbbbbbbbb', 'type' => 'concept', 'label' => 'Mitochondria',
            'contentformat' => FORMAT_HTML, 'createdby' => 1, 'modifiedby' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $result = (new snapshot_service())->create_submission($instance, $workspace, $context, 1);
        $this->assertNotNull($result['snapshot']);
        $this->assertEquals(1, $DB->get_field('vimipad_workspace', 'locked', ['id' => $wsid]));
    }
}
