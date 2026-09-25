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
 * Derives the Ishikawa structure of a fishbone map from its semantic graph.
 *
 * A fishbone diagram is not just a directed graph drawn diagonally: it has an
 * explicit combinatorial shape — one effect, main categories attached to a
 * spine at ordered stations, and causes hanging off those category bones. This
 * module works that shape out; placing it on the canvas is
 * {@link module:mod_vimipad/graph/fishbone_layout}'s job.
 *
 * Pure and side-effect free, so it can be unit tested without a canvas.
 *
 * @module     mod_vimipad/graph/fishbone_topology
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, VimiNode, VimiRelation} from '../types';

/** Which side of the spine a branch sits on. */
export type FishboneSide = 'top' | 'bottom';

/** One main category bone and everything hanging off it. */
export interface FishboneCategory {
    /** The category node's stable id. */
    id: string;
    /** Whether the bone points up or down from the spine. */
    side: FishboneSide;
    /** Position along the spine, 0 being furthest from the effect. */
    order: number;
    /**
     * The causes on this branch, nearest-first.
     *
     * Each carries its depth so a layout can place sub-causes further out along
     * the same bone rather than collapsing them into a flat fan.
     */
    ancestors: Array<{id: string; depth: number; parent: string}>;
}

/** The Ishikawa structure of a map. */
export interface FishboneTopology {
    /** The effect node every category points at. */
    head: string;
    /** The main category bones, in spine order. */
    categories: FishboneCategory[];
    /** Nodes that belong to no branch, e.g. disconnected ones. */
    orphans: string[];
    /**
     * True when the graph has no single obvious effect.
     *
     * The resolver still returns a usable structure so Arrange never refuses to
     * run, but a caller may want to tell the user the map is ambiguous.
     */
    ambiguous: boolean;
}

/**
 * Pick the effect node: the sink every cause ultimately points at.
 *
 * Prefers a unique sink (no outgoing relations). With several candidates the
 * one with the most incoming relations wins, and any remaining tie is broken by
 * stable id so the result never depends on map order.
 *
 * @param nodes The nodes of the map.
 * @param relations The relations of the map.
 * @returns The head id and whether the choice was ambiguous.
 */
function resolveHead(
    nodes: VimiNode[],
    relations: VimiRelation[]
): {head: string; ambiguous: boolean} {
    const ids = nodes.map(n => n.stableid);
    const outgoing = new Map<string, number>();
    const incoming = new Map<string, number>();
    for (const id of ids) {
        outgoing.set(id, 0);
        incoming.set(id, 0);
    }
    for (const rel of relations) {
        if (outgoing.has(rel.sourceid)) {
            outgoing.set(rel.sourceid, (outgoing.get(rel.sourceid) ?? 0) + 1);
        }
        if (incoming.has(rel.targetid)) {
            incoming.set(rel.targetid, (incoming.get(rel.targetid) ?? 0) + 1);
        }
    }

    const sinks = ids.filter(id => (outgoing.get(id) ?? 0) === 0 && (incoming.get(id) ?? 0) > 0);
    const rank = (a: string, b: string): number => {
        const byIn = (incoming.get(b) ?? 0) - (incoming.get(a) ?? 0);
        return byIn !== 0 ? byIn : a.localeCompare(b);
    };

    if (sinks.length === 1) {
        return {head: sinks[0], ambiguous: false};
    }
    if (sinks.length > 1) {
        return {head: [...sinks].sort(rank)[0], ambiguous: true};
    }
    // No sink at all: a cycle, or a map with no relations yet.
    const connected = ids.filter(id => (incoming.get(id) ?? 0) > 0);
    if (connected.length > 0) {
        return {head: [...connected].sort(rank)[0], ambiguous: true};
    }
    return {head: ids.length > 0 ? [...ids].sort()[0] : '', ambiguous: ids.length > 1};
}

/**
 * Order the main categories along the spine.
 *
 * Existing positions carry the author's intent, so they win when available:
 * categories keep their left-to-right order, and a category already drawn above
 * the spine stays above it. Without positions the order falls back to stable id
 * so the result is still deterministic.
 *
 * @param categories The category ids.
 * @param layout The current positions, if any.
 * @param headPos The effect's position, if known.
 * @returns The categories in spine order, with their side.
 */
function orderCategories(
    categories: string[],
    layout: LayoutMap | undefined,
    headPos: {x: number; y: number} | undefined
): Array<{id: string; side: FishboneSide}> {
    const positioned = categories.filter(id => layout?.[id]);

    if (positioned.length === categories.length && categories.length > 0) {
        const sorted = [...categories].sort((a, b) => {
            const dx = (layout?.[a]?.x ?? 0) - (layout?.[b]?.x ?? 0);
            return dx !== 0 ? dx : a.localeCompare(b);
        });
        // Keep each category on the side it already sits on, but do not let one
        // side run empty: a fishbone alternates, so a lopsided map is rebalanced.
        const baseline = headPos?.y ?? average(sorted.map(id => layout?.[id]?.y ?? 0));
        const sides = sorted.map(id => ((layout?.[id]?.y ?? 0) <= baseline ? 'top' : 'bottom') as FishboneSide);
        const tops = sides.filter(s => s === 'top').length;
        if (tops > 0 && tops < sorted.length) {
            return sorted.map((id, i) => ({id, side: sides[i]}));
        }
        return sorted.map((id, i) => ({id, side: (i % 2 === 0 ? 'top' : 'bottom') as FishboneSide}));
    }

    const sorted = [...categories].sort((a, b) => a.localeCompare(b));
    return sorted.map((id, i) => ({id, side: (i % 2 === 0 ? 'top' : 'bottom') as FishboneSide}));
}

/**
 * The arithmetic mean, or zero for an empty list.
 *
 * @param values The numbers to average.
 * @returns The mean.
 */
function average(values: number[]): number {
    return values.length === 0 ? 0 : values.reduce((a, b) => a + b, 0) / values.length;
}

/**
 * Work out the Ishikawa structure of a map.
 *
 * @param nodes The nodes of the map.
 * @param relations The relations of the map.
 * @param layout The current positions, used to preserve authored order.
 * @returns The resolved topology.
 */
export function fishboneTopology(
    nodes: VimiNode[],
    relations: VimiRelation[],
    layout?: LayoutMap
): FishboneTopology {
    if (nodes.length === 0) {
        return {head: '', categories: [], orphans: [], ambiguous: false};
    }

    const known = new Set(nodes.map(n => n.stableid));
    const edges = relations.filter(r => known.has(r.sourceid) && known.has(r.targetid));

    const {head, ambiguous} = resolveHead(nodes, edges);

    // Who points at whom, so a branch can be walked backwards from the head.
    const sources = new Map<string, string[]>();
    for (const rel of edges) {
        const list = sources.get(rel.targetid) ?? [];
        list.push(rel.sourceid);
        sources.set(rel.targetid, list);
    }

    const categoryIds = [...new Set(sources.get(head) ?? [])].filter(id => id !== head);
    const ordered = orderCategories(categoryIds, layout, layout?.[head]);

    const claimed = new Set<string>([head]);
    categoryIds.forEach(id => claimed.add(id));

    const categories: FishboneCategory[] = ordered.map(({id, side}, order) => {
        // Walk the branch backwards, breadth first, so a cause sits nearer its
        // category than its own sub-causes do. A node is claimed once, which
        // both keeps branches disjoint and stops a cycle looping forever.
        const ancestors: FishboneCategory['ancestors'] = [];
        let frontier: Array<{id: string; parent: string}> = (sources.get(id) ?? [])
            .filter(src => !claimed.has(src))
            .map(src => ({id: src, parent: id}));
        frontier.forEach(entry => claimed.add(entry.id));

        let depth = 1;
        while (frontier.length > 0 && depth < 32) {
            const next: Array<{id: string; parent: string}> = [];
            for (const entry of [...frontier].sort((a, b) => a.id.localeCompare(b.id))) {
                ancestors.push({id: entry.id, depth, parent: entry.parent});
                for (const src of sources.get(entry.id) ?? []) {
                    if (!claimed.has(src)) {
                        claimed.add(src);
                        next.push({id: src, parent: entry.id});
                    }
                }
            }
            frontier = next;
            depth++;
        }

        return {id, side, order, ancestors};
    });

    const orphans = nodes.map(n => n.stableid).filter(id => !claimed.has(id)).sort();

    return {head, categories, orphans, ambiguous};
}
