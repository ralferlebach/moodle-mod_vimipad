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

use mod_vimipad\local\service\assess_service;

/**
 * Tests for the assessment service (reference designation + scoring).
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\assess_service
 */
final class assess_service_test extends \advanced_testcase {
    /**
     * Insert a snapshot with a crafted concept map.
     *
     * @param int $workspaceid The owning workspace.
     * @param string[] $concepts Concept labels.
     * @param array[] $triples Propositions as [sourcelabel, relationlabel, targetlabel].
     * @return int The new snapshot id.
     */
    private function snapshot(int $workspaceid, array $concepts, array $triples): int {
        global $DB;

        $ids = [];
        $nodes = [];
        foreach (array_values($concepts) as $i => $label) {
            $sid = 'node_' . sprintf('%012x', $i + 1);
            $ids[$label] = $sid;
            $nodes[] = ['stableid' => $sid, 'type' => 'concept', 'label' => $label];
        }
        $relations = [];
        foreach ($triples as $j => $triple) {
            $relations[] = [
                'stableid' => 'rel_' . sprintf('%012x', $j + 1),
                'sourceid' => $ids[$triple[0]],
                'targetid' => $ids[$triple[2]],
                'type' => 'link',
                'label' => $triple[1],
                'direction' => 1,
            ];
        }
        $json = json_encode(['profile' => 'conceptmap', 'nodes' => $nodes, 'relations' => $relations]);
        return (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspaceid,
            'revision' => 1,
            'snapshotjson' => $json,
            'submittedby' => 0,
            'status' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Create a course, activity and workspace, returning the instance and workspace id.
     *
     * @return array{0: \stdClass, 1: int}
     */
    private function setup_activity(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id, 'grade' => 100]);
        $user = $this->getDataGenerator()->create_user();
        $workspaceid = (int) $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id,
            'userid' => $user->id,
            'groupid' => null,
            'currentrevision' => 1,
            'locked' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return [$instance, $workspaceid];
    }

    /**
     * An identical submission scores near the maximum.
     *
     * @return void
     */
    public function test_score_matches_reference(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();

        $refid = $this->snapshot($wsid, ['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $subid = $this->snapshot($wsid, ['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $instance->referencesnapshotid = $refid;

        $result = (new assess_service())->score($instance, $subid);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(1.0, $result->score, 0.0001);
        $this->assertSame([], $result->concepts['missing']);
    }

    /**
     * A weaker submission scores lower and reports what is missing.
     *
     * @return void
     */
    public function test_partial_submission(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();

        $refid = $this->snapshot($wsid, ['Plant', 'Oxygen', 'Sunlight'], [
            ['Plant', 'produces', 'Oxygen'],
            ['Sunlight', 'drives', 'Plant'],
        ]);
        $subid = $this->snapshot($wsid, ['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);
        $instance->referencesnapshotid = $refid;

        $result = (new assess_service())->score($instance, $subid);

        $this->assertNotNull($result);
        $this->assertLessThan(1.0, $result->score);
        $this->assertContains('Sunlight', $result->concepts['missing']);
        $this->assertGreaterThan(0.0, $result->suggested_grade(100.0));
    }

    /**
     * With no reference marked, scoring yields null.
     *
     * @return void
     */
    public function test_no_reference(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();
        $subid = $this->snapshot($wsid, ['Plant'], []);

        $this->assertNull((new assess_service())->score($instance, $subid));
    }

    /**
     * The reference snapshot is not scored against itself.
     *
     * @return void
     */
    public function test_reference_not_scored_against_itself(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();
        $refid = $this->snapshot($wsid, ['Plant'], []);
        $instance->referencesnapshotid = $refid;

        $this->assertNull((new assess_service())->score($instance, $refid));
    }

    /**
     * A submission is rebuilt from its snapshot's stored map.
     *
     * @return void
     */
    public function test_submission_from_snapshot(): void {
        $this->resetAfterTest();
        [, $wsid] = $this->setup_activity();
        $id = $this->snapshot($wsid, ['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);

        $submission = (new assess_service())->submission_from_snapshot($id);

        $this->assertNotNull($submission);
        $this->assertContains('Plant', $submission->concept_labels());
        $this->assertCount(1, $submission->propositions);
    }

    /**
     * score_all runs the synchronous scorers but never the on-demand AI scorer.
     *
     * @return void
     */
    public function test_score_all_skips_ai(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();
        $subid = $this->snapshot($wsid, ['Plant', 'Oxygen'], [['Plant', 'produces', 'Oxygen']]);

        $results = (new assess_service())->score_all($instance, $subid);

        $this->assertArrayNotHasKey('llm', $results);
        // The reference-free structural scorer runs without a reference.
        $this->assertArrayHasKey('structure', $results);
    }

    /**
     * Without a reference, score_all still runs reference-free scorers.
     *
     * @return void
     */
    public function test_score_all_reference_free_without_reference(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();
        $subid = $this->snapshot($wsid, ['A', 'B'], [['A', 'r', 'B']]);

        $results = (new assess_service())->score_all($instance, $subid);

        $this->assertArrayHasKey('structure', $results);
        $this->assertArrayNotHasKey('reference', $results);
        $this->assertTrue($results['structure']['result']->informational);
    }

    /**
     * With a reference marked, score_all runs both reference-free and reference scorers.
     *
     * @return void
     */
    public function test_score_all_includes_reference_when_marked(): void {
        $this->resetAfterTest();
        [$instance, $wsid] = $this->setup_activity();
        $refid = $this->snapshot($wsid, ['A', 'B'], [['A', 'r', 'B']]);
        $subid = $this->snapshot($wsid, ['A', 'B'], [['A', 'r', 'B']]);
        $instance->referencesnapshotid = $refid;

        $results = (new assess_service())->score_all($instance, $subid);

        $this->assertArrayHasKey('structure', $results);
        $this->assertArrayHasKey('reference', $results);
    }
}
