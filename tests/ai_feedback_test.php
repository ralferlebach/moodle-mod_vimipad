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

use mod_vimipad\local\service\ai_feedback_service;
use mod_vimipad\local\policy\limits;

/**
 * Tests for AI feedback prompt building and draft storage/acceptance.
 *
 * The AI provider call itself is not exercised (no provider in tests); the pure
 * prompt assembly and the storage/acceptance logic are.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\ai_feedback_service
 */
final class ai_feedback_test extends \advanced_testcase {
    /**
     * The prompt contains the task, profile, relation table, notes and the
     * no-hallucination instruction, and no learner identifiers.
     *
     * @return void
     */
    public function test_build_prompt_is_data_minimised(): void {
        $this->resetAfterTest();

        $instance = (object) [
            'intro' => 'Model the energy concept.',
            'defaultprofile' => 'conceptmap',
            'grade' => 100,
        ];
        $snapshotdata = [
            'profile' => 'conceptmap',
            'nodes' => [
                ['stableid' => 'node_a', 'label' => 'Energy'],
                ['stableid' => 'node_b', 'label' => 'Motion'],
            ],
            'relations' => [
                ['sourceid' => 'node_a', 'targetid' => 'node_b', 'type' => 'isform', 'label' => 'is a form of'],
            ],
        ];

        $service = new ai_feedback_service();
        $prompt = $service->build_prompt($instance, $snapshotdata, 'Structure is good.', 85);

        $this->assertStringContainsString('Model the energy concept.', $prompt);
        $this->assertStringContainsString('conceptmap', $prompt);
        $this->assertStringContainsString('Energy — is a form of — Motion', $prompt);
        $this->assertStringContainsString('Structure is good.', $prompt);
        $this->assertStringContainsString('85', $prompt);
        // No stable ids or user identifiers leak into the prompt.
        $this->assertStringNotContainsString('node_a', $prompt);
        $this->assertStringNotContainsString('userid', $prompt);
    }

    /**
     * An empty map still yields a valid prompt with a "no relations" note.
     *
     * @return void
     */
    public function test_build_prompt_handles_empty_map(): void {
        $this->resetAfterTest();

        $instance = (object) ['intro' => '', 'defaultprofile' => 'mindmap', 'grade' => 100];
        $service = new ai_feedback_service();
        $prompt = $service->build_prompt(
            $instance,
            ['profile' => 'mindmap', 'nodes' => [], 'relations' => []],
            '',
            null
        );

        $this->assertStringContainsString('mindmap', $prompt);
        $this->assertNotEmpty($prompt);
    }

    /**
     * Storing a draft honours the prompt-storage setting and acceptance works.
     *
     * @return void
     */
    public function test_store_and_accept_draft(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $now = time();
        $workspaceid = $DB->insert_record('vimipad_workspace', (object) [
            'vimipadid' => $instance->id, 'userid' => null, 'groupid' => null,
            'currentrevision' => 0, 'locked' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $snapshotid = $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspaceid, 'revision' => 0, 'snapshotjson' => '{}',
            'status' => 1, 'timecreated' => $now,
        ]);

        set_config('storeprompts', '0', 'mod_vimipad');
        $service = new ai_feedback_service();
        $id = $service->store_draft($snapshotid, (int) $teacher->id, 'PROMPT', 'DRAFT TEXT', 'model-x');

        $record = $DB->get_record('vimipad_aifeedback', ['id' => $id]);
        $this->assertSame('DRAFT TEXT', $record->drafttext);
        $this->assertNull($record->promptcontextjson); // Not stored.
        $this->assertNull($record->acceptedtext);

        $service->accept_draft($id, (int) $snapshotid, 'FINAL FEEDBACK');
        $record = $DB->get_record('vimipad_aifeedback', ['id' => $id]);
        $this->assertSame('FINAL FEEDBACK', $record->acceptedtext);

        // A draft cannot be accepted against a different snapshot.
        $othersnapshot = $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $workspaceid, 'revision' => 0, 'snapshotjson' => '{}',
            'status' => 1, 'timecreated' => $now,
        ]);
        try {
            $service->accept_draft($id, (int) $othersnapshot, 'HIJACK');
            $this->fail('Expected accepting a draft against a foreign snapshot to fail.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $record = $DB->get_record('vimipad_aifeedback', ['id' => $id]);
        $this->assertSame('FINAL FEEDBACK', $record->acceptedtext); // Unchanged.

        // With prompt storage on, the prompt is persisted.
        set_config('storeprompts', '1', 'mod_vimipad');
        $id2 = $service->store_draft($snapshotid, (int) $teacher->id, 'PROMPT2', 'DRAFT2', '');
        $record2 = $DB->get_record('vimipad_aifeedback', ['id' => $id2]);
        $this->assertNotNull($record2->promptcontextjson);
        $this->assertStringContainsString('PROMPT2', $record2->promptcontextjson);
    }

    /**
     * AI resource limits: over-long notes are truncated in the prompt, the
     * overall prompt is capped, and store_draft bounds draft/providerinfo to
     * their column sizes.
     *
     * @return void
     */
    public function test_ai_resource_limits(): void {
        global $DB;
        $this->resetAfterTest();

        $instance = (object) ['intro' => 'Task.', 'defaultprofile' => 'conceptmap', 'grade' => 100];
        $service = new ai_feedback_service();

        // Over-long notes are truncated, and the whole prompt stays within cap.
        $hugenotes = str_repeat('x', limits::MAX_AI_NOTES + 5000);
        $prompt = $service->build_prompt(
            $instance,
            ['profile' => 'conceptmap', 'nodes' => [], 'relations' => []],
            $hugenotes,
            null
        );
        $this->assertLessThanOrEqual(limits::MAX_AI_PROMPT, \core_text::strlen($prompt));
        // The notes portion is bounded (not the full 10k of x's).
        $this->assertLessThan(limits::MAX_AI_NOTES + 2000, substr_count($prompt, 'x'));

        // A huge map produces a prompt bounded to MAX_AI_PROMPT.
        $nodes = [];
        $relations = [];
        for ($i = 0; $i < 5000; $i++) {
            $nodes[] = ['stableid' => 'node_' . $i, 'label' => 'Concept number ' . $i];
        }
        for ($i = 0; $i < 4999; $i++) {
            $relations[] = ['sourceid' => 'node_' . $i, 'targetid' => 'node_' . ($i + 1),
                'type' => 'link', 'label' => 'relates to'];
        }
        $bigprompt = $service->build_prompt($instance, ['profile' => 'conceptmap',
            'nodes' => $nodes, 'relations' => $relations], '', null);
        $this->assertLessThanOrEqual(limits::MAX_AI_PROMPT, \core_text::strlen($bigprompt));

        // The store_draft call bounds an over-long draft and provider info.
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('vimipad', ['course' => $course->id]);
        $ws = (object) ['vimipadid' => $module->id, 'userid' => 0, 'groupid' => 0,
            'currentrevision' => 0, 'timecreated' => time(), 'timemodified' => time()];
        $ws->id = $DB->insert_record('vimipad_workspace', $ws);
        $snapid = (int) $DB->insert_record('vimipad_snapshot', (object) [
            'workspaceid' => $ws->id, 'status' => 0, 'revision' => 0,
            'snapshotjson' => '{"nodes":[],"relations":[],"containers":[]}', 'cohortjson' => '', 'timecreated' => time(),
        ]);
        $hugedraft = str_repeat('d', limits::MAX_AI_DRAFT + 5000);
        $hugeinfo = str_repeat('p', limits::MAX_AI_PROVIDERINFO + 100);
        $id = $service->store_draft($snapid, 1, 'P', $hugedraft, $hugeinfo);
        $rec = $DB->get_record('vimipad_aifeedback', ['id' => $id]);
        $this->assertLessThanOrEqual(limits::MAX_AI_DRAFT, \core_text::strlen($rec->drafttext));
        $this->assertLessThanOrEqual(limits::MAX_AI_PROVIDERINFO, \core_text::strlen($rec->providerinfo));
    }
}
