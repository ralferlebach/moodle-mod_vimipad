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
 * Client-side incremental reconstruction of a map from its operation log.
 *
 * Mirrors the server's reconstruction_service so the revision player can fetch
 * the whole op-log once and build every frame by folding operations forward —
 * O(N) total — instead of requesting a full server reconstruction per revision
 * (which cost O(N^2) across N web-service calls).
 *
 * @module     mod_vimipad/graph/reconstruct
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {VimiNode, VimiRelation, VimiContainer} from '../types';

/** One operation as returned by the get_operations web service. */
export interface Operation {
    revision: number;
    operationtype: string;
    payloadjson: string;
}

/** A reconstructed, surviving state: the three live element lists. */
export interface ReconstructedState {
    nodes: VimiNode[];
    relations: VimiRelation[];
    containers: VimiContainer[];
}

/** Internal accumulator entry: an element plus a soft-delete flag. */
interface Tracked<T> {
    value: T;
    deleted: boolean;
}

/** The mutable accumulator the fold operates on. */
interface Accumulator {
    nodes: Map<string, Tracked<VimiNode>>;
    relations: Map<string, Tracked<VimiRelation & {sourceid: string; targetid: string}>>;
    containers: Map<string, Tracked<VimiContainer>>;
}

/**
 * Apply a single operation to the accumulator, mirroring the server's apply().
 *
 * @param acc The accumulator (mutated in place).
 * @param type The operation type.
 * @param payload The decoded payload.
 */
function applyOne(acc: Accumulator, type: string, payload: Record<string, unknown>): void {
    const id = (payload.stableid as string) ?? '';
    switch (type) {
        case 'node_create':
            acc.nodes.set(id, {
                value: {
                    stableid: id,
                    type: (payload.type as string) ?? 'concept',
                    label: (payload.label as string) ?? '',
                    content: (payload.content as string) ?? '',
                    contentformat: 1,
                    metadatajson: (payload.metadatajson as string) ?? '',
                },
                deleted: false,
            });
            break;
        case 'node_update': {
            const n = acc.nodes.get(id);
            if (n) {
                for (const field of ['label', 'type', 'content', 'metadatajson'] as const) {
                    if (field in payload) {
                        (n.value as unknown as Record<string, unknown>)[field] = payload[field];
                    }
                }
            }
            break;
        }
        case 'node_delete': {
            const n = acc.nodes.get(id);
            if (n) {
                n.deleted = true;
            }
            // Relations touching a deleted node are deleted too.
            for (const rel of acc.relations.values()) {
                if (rel.value.sourceid === id || rel.value.targetid === id) {
                    rel.deleted = true;
                }
            }
            break;
        }
        case 'relation_create':
            acc.relations.set(id, {
                value: {
                    stableid: id,
                    sourceid: (payload.sourceid as string) ?? '',
                    targetid: (payload.targetid as string) ?? '',
                    type: (payload.type as string) ?? 'link',
                    label: (payload.label as string) ?? '',
                    direction: payload.direction !== undefined ? Number(payload.direction) : 1,
                    metadatajson: (payload.metadatajson as string) ?? '',
                },
                deleted: false,
            });
            break;
        case 'relation_update': {
            const r = acc.relations.get(id);
            if (r) {
                for (const field of ['label', 'type', 'direction', 'metadatajson'] as const) {
                    if (field in payload) {
                        (r.value as unknown as Record<string, unknown>)[field] = payload[field];
                    }
                }
            }
            break;
        }
        case 'relation_delete': {
            const r = acc.relations.get(id);
            if (r) {
                r.deleted = true;
            }
            break;
        }
        case 'relation_retarget': {
            const r = acc.relations.get(id);
            if (r) {
                if (payload.newsource) {
                    r.value.sourceid = payload.newsource as string;
                }
                if (payload.newtarget) {
                    r.value.targetid = payload.newtarget as string;
                }
            }
            break;
        }
        case 'container_create':
            acc.containers.set(id, {
                value: {
                    stableid: id,
                    type: (payload.type as string) ?? 'group',
                    label: (payload.label as string) ?? '',
                    geometryjson: (payload.geometryjson as string) ?? '',
                    metadatajson: (payload.metadatajson as string) ?? '',
                },
                deleted: false,
            });
            break;
        case 'container_update': {
            const c = acc.containers.get(id);
            if (c) {
                for (const field of ['type', 'label', 'geometryjson', 'metadatajson'] as const) {
                    if (field in payload) {
                        (c.value as unknown as Record<string, unknown>)[field] = payload[field];
                    }
                }
            }
            break;
        }
        case 'container_delete': {
            const c = acc.containers.get(id);
            if (c) {
                c.deleted = true;
            }
            break;
        }
        default:
            // Unknown/irrelevant operation types (memberships, etc.) do not
            // affect the rendered graph and are ignored, as on the server.
            break;
    }
}

/**
 * Produce the surviving snapshot from the accumulator: live nodes, live
 * relations whose endpoints both still exist, and live containers. Mirrors the
 * server's surviving-element filter exactly.
 *
 * Each element is shallow-copied into the frame so a frame is an immutable
 * historical snapshot: later operations mutate the accumulator's objects in
 * place, and without the copy an earlier frame would alias — and so retroactively
 * show — a later revision's content. A shallow copy is a complete copy here
 * because every element field is a primitive (metadatajson / geometryjson are
 * JSON *strings*, not nested objects); if a nested-object field is ever added,
 * this must become a deeper clone.
 *
 * @param acc The accumulator.
 * @returns The reconstructed surviving state, with copied elements.
 */
function survivors(acc: Accumulator): ReconstructedState {
    const nodes: VimiNode[] = [];
    for (const n of acc.nodes.values()) {
        if (!n.deleted) {
            nodes.push({...n.value});
        }
    }
    const relations: VimiRelation[] = [];
    for (const r of acc.relations.values()) {
        if (r.deleted) {
            continue;
        }
        const src = acc.nodes.get(r.value.sourceid);
        const tgt = acc.nodes.get(r.value.targetid);
        if (src && !src.deleted && tgt && !tgt.deleted) {
            relations.push({...r.value});
        }
    }
    const containers: VimiContainer[] = [];
    for (const c of acc.containers.values()) {
        if (!c.deleted) {
            containers.push({...c.value});
        }
    }
    return {nodes, relations, containers};
}

/**
 * Reconstruct the surviving state at a single revision by folding all
 * operations with revision <= target.
 *
 * @param operations The operation log (revision-ascending).
 * @param revision The target revision (inclusive).
 * @returns The reconstructed surviving state.
 */
export function reconstructAt(operations: Operation[], revision: number): ReconstructedState {
    const acc: Accumulator = {nodes: new Map(), relations: new Map(), containers: new Map()};
    for (const op of operations) {
        if (op.revision > revision) {
            break;
        }
        let payload: Record<string, unknown>;
        try {
            payload = JSON.parse(op.payloadjson) as Record<string, unknown>;
        } catch {
            continue;
        }
        applyOne(acc, op.operationtype, payload);
    }
    return survivors(acc);
}

/**
 * Build every frame from revision 1..maxRevision in a single pass, returning a
 * map from revision number to its surviving state. Each frame is the survivors
 * snapshot after applying all operations up to that revision, with elements
 * copied so frames are immutable.
 *
 * Note: this retains one full frame per revision, so its memory is proportional
 * to the sum of live-element counts across revisions. It is intended for short
 * histories and tests. For long histories use {@link ReplayEngine}, which keeps
 * only periodic checkpoints and a bounded frame cache.
 *
 * @param operations The operation log (revision-ascending).
 * @param maxRevision The highest revision to build.
 * @returns A map: revision => surviving state (also includes revision 0 = empty).
 */
export function buildFrames(operations: Operation[], maxRevision: number): Map<number, ReconstructedState> {
    const frames = new Map<number, ReconstructedState>();
    const acc: Accumulator = {nodes: new Map(), relations: new Map(), containers: new Map()};
    // Revision 0: the empty map, before any operation.
    frames.set(0, survivors(acc));

    let idx = 0;
    for (let rev = 1; rev <= maxRevision; rev++) {
        // Apply every operation that produced this revision (usually one).
        while (idx < operations.length && operations[idx].revision === rev) {
            let payload: Record<string, unknown>;
            try {
                payload = JSON.parse(operations[idx].payloadjson) as Record<string, unknown>;
                applyOne(acc, operations[idx].operationtype, payload);
            } catch {
                // Skip an undecodable payload, as the server does.
            }
            idx++;
        }
        frames.set(rev, survivors(acc));
    }
    return frames;
}

/**
 * Deep-clone an accumulator so a checkpoint is an immutable snapshot that later
 * in-place mutations cannot alter. Each tracked value is shallow-copied, which
 * is complete because element fields are primitives (see survivors()).
 *
 * @param acc The accumulator to clone.
 * @returns An independent copy.
 */
function cloneAccumulator(acc: Accumulator): Accumulator {
    const clone: Accumulator = {nodes: new Map(), relations: new Map(), containers: new Map()};
    for (const [k, v] of acc.nodes) {
        clone.nodes.set(k, {value: {...v.value}, deleted: v.deleted});
    }
    for (const [k, v] of acc.relations) {
        clone.relations.set(k, {value: {...v.value}, deleted: v.deleted});
    }
    for (const [k, v] of acc.containers) {
        clone.containers.set(k, {value: {...v.value}, deleted: v.deleted});
    }
    return clone;
}

/** Apply every operation that produced revision `rev` to the accumulator. */
function applyRevision(acc: Accumulator, operations: Operation[], startIdx: number, rev: number): number {
    let idx = startIdx;
    while (idx < operations.length && operations[idx].revision === rev) {
        try {
            const payload = JSON.parse(operations[idx].payloadjson) as Record<string, unknown>;
            applyOne(acc, operations[idx].operationtype, payload);
        } catch {
            // Skip an undecodable payload, as the server does.
        }
        idx++;
    }
    return idx;
}

/**
 * A bounded, checkpoint-based replay engine. Instead of materialising and
 * retaining a full frame for every revision (which is O(N^2) in memory and copy
 * cost), it keeps periodic checkpoints of the accumulator and a small LRU cache
 * of recently produced frames. A frame is produced by cloning the nearest
 * checkpoint at or below the target revision and applying the deltas forward.
 *
 * Sequential playback stays O(N) overall because playback advances one revision
 * at a time from a running position; random scrubbing costs at most the
 * checkpoint interval. Peak memory is bounded by the checkpoint count plus the
 * LRU size, not by the history length times the map size.
 */
export class ReplayEngine {
    private readonly operations: Operation[];
    private readonly maxRevision: number;
    private readonly checkpointInterval: number;
    private readonly maxCheckpoints: number;
    /** Checkpoints keyed by revision (revision -> accumulator snapshot). */
    private readonly checkpoints: Map<number, Accumulator> = new Map();
    /** For each checkpoint revision, the op-array index of the first operation
     * with revision > checkpoint (so forward replay needs no rescan). */
    private readonly checkpointOpIndex: Map<number, number> = new Map();
    private readonly sortedCheckpointRevs: number[] = [];
    /** Bounded LRU cache of produced frames. */
    private readonly frameCache: Map<number, ReconstructedState> = new Map();
    private readonly frameCacheLimit: number;

    /**
     * @param operations The operation log (revision-ascending).
     * @param maxRevision The highest revision this engine will serve.
     * @param maxCheckpoints Upper bound on retained checkpoints (default 64),
     *   so checkpoint memory does not grow without limit for long histories.
     * @param frameCacheLimit Maximum cached frames (default 8).
     */
    constructor(
        operations: Operation[],
        maxRevision: number,
        maxCheckpoints = 64,
        frameCacheLimit = 8
    ) {
        this.operations = operations;
        this.maxRevision = maxRevision;
        this.maxCheckpoints = Math.max(1, maxCheckpoints);
        // Derive the interval from the checkpoint budget: with N revisions and
        // a cap of C checkpoints, space them ceil(N / C) apart (min 1). This
        // bounds the number of retained checkpoints by ~C regardless of history
        // length, at the cost of a longer forward-replay between checkpoints.
        this.checkpointInterval = Math.max(1, Math.ceil(maxRevision / this.maxCheckpoints));
        this.frameCacheLimit = Math.max(1, frameCacheLimit);
        this.buildCheckpoints();
    }

    /** Build checkpoints in one O(N) pass over the operations. */
    private buildCheckpoints(): void {
        const acc: Accumulator = {nodes: new Map(), relations: new Map(), containers: new Map()};
        // Checkpoint at revision 0 (empty) so early scrubbing has a base.
        this.checkpoints.set(0, cloneAccumulator(acc));
        this.checkpointOpIndex.set(0, 0);
        this.sortedCheckpointRevs.push(0);

        let idx = 0;
        for (let rev = 1; rev <= this.maxRevision; rev++) {
            idx = applyRevision(acc, this.operations, idx, rev);
            if (rev % this.checkpointInterval === 0 || rev === this.maxRevision) {
                this.checkpoints.set(rev, cloneAccumulator(acc));
                // idx now points at the first op with revision > rev.
                this.checkpointOpIndex.set(rev, idx);
                this.sortedCheckpointRevs.push(rev);
            }
        }
    }

    /** The nearest checkpoint revision at or below `rev` (binary search). */
    private nearestCheckpoint(rev: number): number {
        const revs = this.sortedCheckpointRevs;
        let lo = 0;
        let hi = revs.length - 1;
        let best = 0;
        while (lo <= hi) {
            const mid = (lo + hi) >> 1;
            if (revs[mid] <= rev) {
                best = revs[mid];
                lo = mid + 1;
            } else {
                hi = mid - 1;
            }
        }
        return best;
    }

    /**
     * The surviving state at a revision, produced from the nearest checkpoint
     * plus forward deltas. Results are served from a bounded LRU cache.
     *
     * @param revision The target revision (clamped to [0, maxRevision]).
     * @returns The reconstructed surviving state.
     */
    stateAt(revision: number): ReconstructedState {
        const rev = Math.max(0, Math.min(revision, this.maxRevision));
        const cached = this.frameCache.get(rev);
        if (cached) {
            // Refresh LRU recency.
            this.frameCache.delete(rev);
            this.frameCache.set(rev, cached);
            return cached;
        }

        const base = this.nearestCheckpoint(rev);
        const acc = cloneAccumulator(this.checkpoints.get(base)!);
        // Start from the op index stored for this checkpoint — no rescan of the
        // op-log — and apply forward to the target revision.
        let idx = this.checkpointOpIndex.get(base) ?? 0;
        for (let r = base + 1; r <= rev; r++) {
            idx = applyRevision(acc, this.operations, idx, r);
        }
        const frame = survivors(acc);

        this.frameCache.set(rev, frame);
        if (this.frameCache.size > this.frameCacheLimit) {
            // Evict the least-recently-used (first inserted) entry.
            const oldest = this.frameCache.keys().next().value;
            if (oldest !== undefined) {
                this.frameCache.delete(oldest);
            }
        }
        return frame;
    }
}
