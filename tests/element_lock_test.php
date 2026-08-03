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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/workspace_fixture.php');

/**
 * Tests for template structural locks enforced in the operation service.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_vimipad\local\service\operation_service
 */
final class element_lock_test extends \advanced_testcase {
    use \mod_vimipad\workspace_fixture;

    /**
     * Create a course, module and empty workspace.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->set_up_workspace();
    }

    /**
     * Insert a node directly with the given metadata and return its stable id.
     *
     * @param string $stableid The stable id.
     * @param string|null $metadatajson The metadata JSON, or null.
     * @return string
     */
    private function make_node(string $stableid, ?string $metadatajson): string {
        global $DB;
        $now = time();
        $DB->insert_record('vimipad_node', (object) [
            'workspaceid' => $this->workspaceid, 'stableid' => $stableid, 'type' => 'concept',
            'label' => 'Scaffold', 'content' => null, 'contentformat' => FORMAT_HTML,
            'metadatajson' => $metadatajson, 'createdby' => 1, 'modifiedby' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        return $stableid;
    }

    /**
     * Current workspace revision, so tests can chain operations.
     *
     * @return int
     */
    private function rev(): int {
        global $DB;
        return (int) $DB->get_field('vimipad_workspace', 'currentrevision', ['id' => $this->workspaceid]);
    }

    /**
     * A locked node cannot be deleted or updated (no whitelist).
     *
     * @return void
     */
    public function test_locked_node_is_protected(): void {
        $id = $this->make_node('node_lockedaaaaa', json_encode(['locked' => true]));
        $service = new operation_service();

        try {
            $service->apply($this->workspaceid, $this->rev(), 'node_delete', ['stableid' => $id], 1);
            $this->fail('Deleting a locked node should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('elementlocked', $e->errorcode);
        }

        try {
            $service->apply($this->workspaceid, $this->rev(), 'node_update', ['stableid' => $id, 'label' => 'X'], 1);
            $this->fail('Updating a locked node without a whitelist should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('elementlocked', $e->errorcode);
        }
    }

    /**
     * A locked node allows changes only to whitelisted fields.
     *
     * @return void
     */
    public function test_locked_node_respects_group_locks(): void {
        global $DB;
        // Lock only the move and colour groups; text stays editable.
        $id = $this->make_node('node_lockedbbbbb', json_encode([
            'locked' => true,
            'locks' => ['move' => true, 'color' => true, 'text' => false],
        ]));
        $service = new operation_service();

        // Label is a text change and text is unlocked: allowed.
        $service->apply($this->workspaceid, $this->rev(), 'node_update', ['stableid' => $id, 'label' => 'Renamed'], 1);
        $this->assertSame('Renamed', $DB->get_field('vimipad_node', 'label', ['stableid' => $id]));

        // Changing the fill colour is a colour change and colour is locked: rejected.
        try {
            $service->apply($this->workspaceid, $this->rev(), 'node_update', [
                'stableid' => $id,
                'metadatajson' => json_encode([
                    'locked' => true,
                    'locks' => ['move' => true, 'color' => true, 'text' => false],
                    'fill' => '#ff0000',
                ]),
            ], 1);
            $this->fail('Changing a locked colour group should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('elementlocked', $e->errorcode);
        }
    }

    /**
     * A legacy global lock ({"locked":true} without a locks map) locks every
     * group, so any change is rejected.
     *
     * @return void
     */
    public function test_legacy_global_lock_blocks_all_groups(): void {
        $id = $this->make_node('node_legacyglobal', json_encode(['locked' => true]));
        $service = new operation_service();

        foreach (['label' => 'X', 'content' => 'Y'] as $field => $value) {
            try {
                $service->apply($this->workspaceid, $this->rev(), 'node_update', [
                    'stableid' => $id, $field => $value,
                ], 1);
                $this->fail("Legacy global lock should reject changing $field.");
            } catch (\moodle_exception $e) {
                $this->assertStringContainsString('elementlocked', $e->errorcode);
            }
        }
    }

    /**
     * An unlocked node (no metadata) is freely editable and deletable.
     *
     * @return void
     */
    public function test_unlocked_node_is_free(): void {
        global $DB;
        $id = $this->make_node('node_freeaaaaaaa', null);
        $service = new operation_service();

        $service->apply($this->workspaceid, $this->rev(), 'node_update', ['stableid' => $id, 'type' => 'goal'], 1);
        $this->assertSame('goal', $DB->get_field('vimipad_node', 'type', ['stableid' => $id]));

        $service->apply($this->workspaceid, $this->rev(), 'node_delete', ['stableid' => $id], 1);
        $this->assertEquals(1, $DB->get_field('vimipad_node', 'deleted', ['stableid' => $id]));
    }

    /**
     * A manager (bypasslocks) may edit and delete a locked element.
     *
     * @return void
     */
    public function test_manager_bypasses_locks(): void {
        global $DB;
        $id = $this->make_node('node_lockedcccc', json_encode(['locked' => true]));
        $service = new operation_service(true);

        // Update the locked node's label: allowed under bypass.
        $service->apply($this->workspaceid, $this->rev(), 'node_update', ['stableid' => $id, 'label' => 'Edited'], 1);
        $this->assertSame('Edited', $DB->get_field('vimipad_node', 'label', ['stableid' => $id]));

        // Delete it: also allowed under bypass.
        $service->apply($this->workspaceid, $this->rev(), 'node_delete', ['stableid' => $id], 1);
        $this->assertEquals(1, $DB->get_field('vimipad_node', 'deleted', ['stableid' => $id]));
    }

    /**
     * A locked relation cannot be deleted or retargeted.
     *
     * @return void
     */
    public function test_locked_relation_is_protected(): void {
        global $DB;
        $a = $this->make_node('node_aaaaaaaaaaaa', null);
        $b = $this->make_node('node_bbbbbbbbbbbb', null);
        $now = time();
        $DB->insert_record('vimipad_relation', (object) [
            'workspaceid' => $this->workspaceid, 'stableid' => 'rel_lockedaaaaaa',
            'sourceid' => $a, 'targetid' => $b, 'type' => 'link', 'label' => null, 'direction' => 1,
            'metadatajson' => json_encode(['locked' => true]), 'createdby' => 1, 'modifiedby' => 1,
            'timecreated' => $now, 'timemodified' => $now, 'deleted' => 0,
        ]);
        $service = new operation_service();

        try {
            $service->apply($this->workspaceid, $this->rev(), 'relation_retarget', [
                'stableid' => 'rel_lockedaaaaaa', 'newtarget' => $a,
            ], 1);
            $this->fail('Retargeting a locked relation should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('elementlocked', $e->errorcode);
        }

        try {
            $service->apply($this->workspaceid, $this->rev(), 'relation_delete', ['stableid' => 'rel_lockedaaaaaa'], 1);
            $this->fail('Deleting a locked relation should be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertStringContainsString('elementlocked', $e->errorcode);
        }
    }
}
