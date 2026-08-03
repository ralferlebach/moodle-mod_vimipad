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

/**
 * Tests for client-side incremental reconstruction (mirrors the server).
 *
 * @module     mod_vimipad/tests/reconstruct
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {reconstructAt, buildFrames, ReplayEngine, Operation} from '../src/graph/reconstruct';

/** Build an operation with an auto-incrementing revision. */
function ops(...items: Array<[string, Record<string, unknown>]>): Operation[] {
    return items.map(([operationtype, payload], i) => ({
        revision: i + 1,
        operationtype,
        payloadjson: JSON.stringify(payload),
    }));
}

const A = 'node_aaaaaaaaaaaa';
const B = 'node_bbbbbbbbbbbb';
const C = 'node_cccccccccccc';
const R = 'rel_aaaaaaaaaaaa';
const K = 'cont_aaaaaaaaaaaa';

describe('incremental reconstruction', () => {
    test('reconstructs nodes, relations and containers', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['container_create', {stableid: K, type: 'group', label: 'C', geometryjson: '{"x":0,"y":0,"w":10,"h":10}'}],
        );
        const state = reconstructAt(log, 4);
        expect(state.nodes.length).toBe(2);
        expect(state.relations.length).toBe(1);
        expect(state.containers.length).toBe(1);
    });

    test('a node update is applied', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_update', {stableid: A, label: 'A-renamed'}],
        );
        const state = reconstructAt(log, 2);
        expect(state.nodes[0].label).toBe('A-renamed');
    });

    test('deleting a node also drops relations touching it', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['node_delete', {stableid: A}],
        );
        const state = reconstructAt(log, 4);
        expect(state.nodes.length).toBe(1);
        expect(state.relations.length).toBe(0);
    });

    test('a relation with a missing endpoint does not survive', () => {
        // Relation created against a node that is never created.
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: C, type: 'link', label: 'r', direction: 1}],
        );
        const state = reconstructAt(log, 2);
        expect(state.relations.length).toBe(0);
    });

    test('relation retarget moves the endpoint', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['node_create', {stableid: C, type: 'concept', label: 'C'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['relation_retarget', {stableid: R, newtarget: C}],
        );
        const state = reconstructAt(log, 5);
        expect(state.relations.length).toBe(1);
        expect(state.relations[0].targetid).toBe(C);
    });

    test('buildFrames yields the correct state at each revision', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
        );
        const frames = buildFrames(log, 3);
        expect(frames.get(0)!.nodes.length).toBe(0);
        expect(frames.get(1)!.nodes.length).toBe(1);
        expect(frames.get(2)!.nodes.length).toBe(2);
        expect(frames.get(3)!.relations.length).toBe(1);
    });

    test('buildFrames matches reconstructAt for every revision', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['node_delete', {stableid: B}],
            ['container_create', {stableid: K, type: 'group', label: 'C'}],
        );
        const frames = buildFrames(log, 5);
        for (let rev = 0; rev <= 5; rev++) {
            const incremental = frames.get(rev)!;
            const direct = reconstructAt(log, rev);
            expect(incremental.nodes.length).toBe(direct.nodes.length);
            expect(incremental.relations.length).toBe(direct.relations.length);
            expect(incremental.containers.length).toBe(direct.containers.length);
        }
    });

    // Sort helper for order-independent deep comparison.
    const byId = <T extends {stableid: string}>(xs: T[]): T[] =>
        [...xs].sort((a, b) => a.stableid.localeCompare(b.stableid));

    test('frames are immutable: a later node update does not change an earlier frame', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'Alt'}],
            ['node_update', {stableid: A, label: 'Neu'}],
        );
        const frames = buildFrames(log, 2);
        expect(frames.get(1)!.nodes[0].label).toBe('Alt');
        expect(frames.get(2)!.nodes[0].label).toBe('Neu');
    });

    test('frames are immutable: a later retarget does not change an earlier frame', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['node_create', {stableid: C, type: 'concept', label: 'C'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['relation_retarget', {stableid: R, newtarget: C}],
        );
        const frames = buildFrames(log, 5);
        expect(frames.get(4)!.relations[0].targetid).toBe(B);
        expect(frames.get(5)!.relations[0].targetid).toBe(C);
    });

    test('frames are immutable: a later relation update does not change an earlier frame', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'old', direction: 1}],
            ['relation_update', {stableid: R, label: 'new', type: 'causes'}],
        );
        const frames = buildFrames(log, 4);
        expect(frames.get(3)!.relations[0].label).toBe('old');
        expect(frames.get(3)!.relations[0].type).toBe('link');
        expect(frames.get(4)!.relations[0].label).toBe('new');
        expect(frames.get(4)!.relations[0].type).toBe('causes');
    });

    test('frames are immutable: a later container update does not change an earlier frame', () => {
        const log = ops(
            ['container_create', {stableid: K, type: 'group', label: 'C', geometryjson: '{"x":0,"y":0,"w":10,"h":10}'}],
            ['container_update', {stableid: K, geometryjson: '{"x":5,"y":5,"w":20,"h":20}', label: 'C2'}],
        );
        const frames = buildFrames(log, 2);
        expect(frames.get(1)!.containers[0].geometryjson).toBe('{"x":0,"y":0,"w":10,"h":10}');
        expect(frames.get(1)!.containers[0].label).toBe('C');
        expect(frames.get(2)!.containers[0].geometryjson).toBe('{"x":5,"y":5,"w":20,"h":20}');
    });

    describe('ReplayEngine (bounded, checkpoint-based)', () => {
        // A longer log with updates/retargets/deletes to stress checkpoints.
        function longLog(n: number): Operation[] {
            const out: Operation[] = [];
            let rev = 0;
            for (let i = 0; i < n; i++) {
                const id = 'node_' + String(i).padStart(12, '0');
                out.push({revision: ++rev, operationtype: 'node_create',
                    payloadjson: JSON.stringify({stableid: id, type: 'concept', label: 'L' + i})});
            }
            // Rename the first node much later.
            out.push({revision: ++rev, operationtype: 'node_update',
                payloadjson: JSON.stringify({stableid: 'node_' + '0'.padStart(12, '0'), label: 'RENAMED'})});
            return out;
        }

        test('stateAt equals reconstructAt across checkpoints', () => {
            const log = longLog(25);
            const max = log[log.length - 1].revision;
            // Small interval so several checkpoints are created.
            const engine = new ReplayEngine(log, max, 5, 4);
            for (let rev = 0; rev <= max; rev++) {
                const e = byId(engine.stateAt(rev).nodes);
                const d = byId(reconstructAt(log, rev).nodes);
                expect(e).toEqual(d);
            }
        });

        test('produces immutable frames (earlier frame keeps old label)', () => {
            const log = longLog(25);
            const max = log[log.length - 1].revision;
            const engine = new ReplayEngine(log, max, 5, 4);
            const first = 'node_' + '0'.padStart(12, '0');
            const early = engine.stateAt(10).nodes.find(nd => nd.stableid === first)!;
            const late = engine.stateAt(max).nodes.find(nd => nd.stableid === first)!;
            expect(early.label).toBe('L0');
            expect(late.label).toBe('RENAMED');
        });

        test('scrubbing back and forth is consistent (cache is a bounded LRU)', () => {
            const log = longLog(30);
            const max = log[log.length - 1].revision;
            const engine = new ReplayEngine(log, max, 5, 3);
            // Touch more distinct revisions than the cache holds, then revisit.
            const revs = [1, 8, 15, 22, 29, 3, 8, 1, max];
            for (const rev of revs) {
                const e = byId(engine.stateAt(rev).nodes);
                const d = byId(reconstructAt(log, rev).nodes);
                expect(e).toEqual(d);
            }
        });

        test('clamps out-of-range revisions', () => {
            const log = longLog(5);
            const max = log[log.length - 1].revision;
            const engine = new ReplayEngine(log, max, 100, 4);
            expect(engine.stateAt(-5).nodes.length).toBe(0);
            expect(engine.stateAt(9999).nodes.length).toBe(engine.stateAt(max).nodes.length);
        });

        test('checkpoint count is bounded regardless of history length', () => {
            // A very long history with a small checkpoint budget must still be
            // correct AND not create one checkpoint per revision.
            const log = longLog(2000);
            const max = log[log.length - 1].revision;
            const budget = 16;
            const engine = new ReplayEngine(log, max, budget, 4);
            // Correctness at a spread of revisions.
            for (const rev of [0, 1, 500, 1000, 1500, max]) {
                expect(byId(engine.stateAt(rev).nodes)).toEqual(byId(reconstructAt(log, rev).nodes));
            }
            // The retained checkpoints are bounded by ~budget (+ endpoints),
            // not by the ~2000 revisions.
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const cpCount = (engine as any).checkpoints.size as number;
            expect(cpCount).toBeLessThanOrEqual(budget + 2);
        });
    });

    test('buildFrames deep-equals reconstructAt for every revision (full content)', () => {
        const log = ops(
            ['node_create', {stableid: A, type: 'concept', label: 'A'}],
            ['node_create', {stableid: B, type: 'concept', label: 'B'}],
            ['node_update', {stableid: A, label: 'A2', content: 'body'}],
            ['relation_create', {stableid: R, sourceid: A, targetid: B, type: 'link', label: 'r', direction: 1}],
            ['relation_update', {stableid: R, label: 'r2', direction: 2}],
            ['container_create', {stableid: K, type: 'group', label: 'K', geometryjson: '{"x":1,"y":2,"w":3,"h":4}'}],
            ['node_delete', {stableid: B}],
        );
        const frames = buildFrames(log, 7);
        for (let rev = 0; rev <= 7; rev++) {
            const inc = frames.get(rev)!;
            const dir = reconstructAt(log, rev);
            expect(byId(inc.nodes)).toEqual(byId(dir.nodes));
            expect(byId(inc.relations)).toEqual(byId(dir.relations));
            expect(byId(inc.containers)).toEqual(byId(dir.containers));
        }
    });
});
