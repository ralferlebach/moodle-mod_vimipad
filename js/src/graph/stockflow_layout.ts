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
 * Orders the material chain of a system dynamics map before Arrange refines it.
 *
 * The force refiner is preservation-first: it tunes distances but leaves nodes
 * near where they started, so a chain drawn out of order stays out of order.
 * A stock-and-flow model is read along its flows - source, rate, stock, rate,
 * sink - so this seed places every node on a flow chain at the column its
 * position in that chain implies. Nodes connected only by influences keep their
 * place; the refiner then settles them beside the chain.
 *
 * @module     mod_vimipad/graph/stockflow_layout
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {LayoutMap, VimiNode, VimiRelation} from '../types';

/**
 * Horizontal distance between consecutive steps of a flow chain.
 *
 * Chosen near the length the refiner settles a flow edge at, so Arrange starts
 * close to equilibrium. A wider step leaves the refiner still moving nodes on
 * the next Arrange, and repeated Arrange stops converging; 140 to 200 all
 * settle, 220 did not.
 */
const STEP = 180;

/**
 * Seed positions that put every flow chain in reading order.
 *
 * Each node on a flow gets a column equal to the length of the longest flow
 * path leading into it, so a source is always leftmost, a sink always
 * rightmost and a delay or rate sits between the steps it connects. Cycles are
 * broken by ignoring an edge back to a node already being visited, which keeps
 * the result defined without analysing the loop.
 *
 * @param nodes The nodes of the map.
 * @param relations The relations of the map.
 * @param current The current positions.
 * @returns Positions with flow nodes ordered and every other node unchanged.
 */
export function stockflowSeed(
    nodes: VimiNode[],
    relations: VimiRelation[],
    current: LayoutMap
): LayoutMap {
    const known = new Set(nodes.map(n => n.stableid));
    const flows = relations.filter(r =>
        r.type === 'flow' && known.has(r.sourceid) && known.has(r.targetid) && r.sourceid !== r.targetid);
    if (flows.length === 0) {
        return {...current};
    }

    // Leave a chain that already reads left to right alone. Re-seeding it on
    // every Arrange would re-anchor it each time and keep it drifting, so a
    // second Arrange would never settle.
    const ordered = flows.every(f => {
        const a = current[f.sourceid];
        const b = current[f.targetid];
        return a !== undefined && b !== undefined && a.x < b.x;
    });
    if (ordered) {
        return {...current};
    }

    const incoming = new Map<string, string[]>();
    const onchain = new Set<string>();
    for (const f of flows) {
        onchain.add(f.sourceid);
        onchain.add(f.targetid);
        const list = incoming.get(f.targetid) ?? [];
        list.push(f.sourceid);
        incoming.set(f.targetid, list);
    }

    // Longest incoming flow path per node; the visiting set breaks cycles.
    const rank = new Map<string, number>();
    const visiting = new Set<string>();
    const depth = (id: string): number => {
        const known = rank.get(id);
        if (known !== undefined) {
            return known;
        }
        visiting.add(id);
        let best = 0;
        for (const from of incoming.get(id) ?? []) {
            if (!visiting.has(from)) {
                best = Math.max(best, depth(from) + 1);
            }
        }
        visiting.delete(id);
        rank.set(id, best);
        return best;
    };

    const chain = [...onchain].sort();
    chain.forEach(id => depth(id));

    // Anchor the chain where it already is, so Arrange does not throw the map
    // across the canvas: start at the leftmost chain node and keep the rows.
    const xs = chain.map(id => current[id]?.x).filter((x): x is number => typeof x === 'number');
    const ys = chain.map(id => current[id]?.y).filter((y): y is number => typeof y === 'number');
    const left = xs.length > 0 ? Math.min(...xs) : 200;
    const row = ys.length > 0 ? ys.reduce((a, b) => a + b, 0) / ys.length : 400;

    // Nodes that share a column are stacked so they never land on one spot.
    const perColumn = new Map<number, number>();
    const seed: LayoutMap = {...current};
    for (const id of chain) {
        const col = rank.get(id) ?? 0;
        const slot = perColumn.get(col) ?? 0;
        perColumn.set(col, slot + 1);
        seed[id] = {x: Math.round(left + col * STEP), y: Math.round(row + slot * 110)};
    }
    return seed;
}
