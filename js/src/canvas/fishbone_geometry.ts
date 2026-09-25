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
 * Routing for Ishikawa diagrams: one shared spine plus per-branch bones.
 *
 * A category relation is semantically "category causes effect", but drawing it
 * as a straight line from the category to the effect produces a fan, not a
 * fishbone. Instead every category relation is rendered as a short bone that
 * meets the spine at its own station, and the spine itself is drawn exactly
 * once. Drawing the shared backbone per relation would overpaint the same
 * segment repeatedly, which makes stroke width inconsistent and gives several
 * relations the same selection target.
 *
 * The virtual junctions exist only here: no extra nodes are persisted, and each
 * rendered path stays associated with its original semantic relation so
 * selection and hit-testing are unaffected.
 *
 * @module     mod_vimipad/canvas/fishbone_geometry
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {FishboneTopology} from '../graph/fishbone_topology';
import {LayoutMap, Point, VimiRelation} from '../types';

/** The shared backbone, drawn once per diagram. */
export interface Spine {
    from: Point;
    to: Point;
}

/** How one relation should be drawn in a fishbone. */
export interface FishboneRoute {
    /** The semantic relation this path belongs to. */
    relationid: string;
    /** The path to draw, as a polyline. */
    points: Point[];
    /**
     * What the path represents.
     *
     * A 'bone' meets the spine at a station; a 'sub' connects a cause to its
     * parent inside a branch; 'plain' is anything the topology does not cover,
     * which is drawn the ordinary way.
     */
    kind: 'bone' | 'sub' | 'plain';
}

/** Everything the renderer needs to draw a fishbone. */
export interface FishboneRouting {
    /** The single shared backbone. */
    spine: Spine;
    /** Where each category meets the spine. */
    stations: Record<string, Point>;
    /** One route per relation, in input order. */
    routes: FishboneRoute[];
}

/**
 * Work out the spine and the routed path of every relation.
 *
 * @param topology The resolved Ishikawa structure.
 * @param layout The node positions.
 * @param relations The relations to route.
 * @returns The spine, the stations and one route per relation.
 */
export function fishboneRouting(
    topology: FishboneTopology,
    layout: LayoutMap,
    relations: VimiRelation[]
): FishboneRouting {
    const headPos = layout[topology.head];
    const spineYPos = headPos?.y ?? 0;

    // Each category meets the spine directly below or above itself, so the bone
    // is a single straight segment and the station order follows the layout.
    const stations: Record<string, Point> = {};
    for (const category of topology.categories) {
        const pos = layout[category.id];
        if (pos) {
            stations[category.id] = {x: pos.x, y: spineYPos};
        }
    }

    // The backbone spans from the leftmost station to the effect, so every bone
    // lands on it and it is drawn exactly once.
    const stationXs = Object.values(stations).map(p => p.x);
    const left = stationXs.length > 0 ? Math.min(...stationXs) : (headPos?.x ?? 0);
    const spine: Spine = {
        from: {x: left - 80, y: spineYPos},
        to: {x: headPos?.x ?? left, y: spineYPos},
    };

    const categoryIds = new Set(topology.categories.map(c => c.id));
    const parentOf = new Map<string, string>();
    for (const category of topology.categories) {
        for (const ancestor of category.ancestors) {
            parentOf.set(ancestor.id, ancestor.parent);
        }
    }

    const routes: FishboneRoute[] = relations.map(rel => {
        const from = layout[rel.sourceid];
        const to = layout[rel.targetid];
        if (!from || !to) {
            return {relationid: rel.stableid, points: [], kind: 'plain'};
        }

        // A main category: draw only the bone down to its own station. The run
        // along the spine to the effect is the shared backbone, drawn once.
        if (rel.targetid === topology.head && categoryIds.has(rel.sourceid)) {
            const station = stations[rel.sourceid];
            if (station) {
                return {relationid: rel.stableid, points: [from, station], kind: 'bone'};
            }
        }

        // A cause attaching to its own parent stays a local sub-bone.
        if (parentOf.get(rel.sourceid) === rel.targetid) {
            return {relationid: rel.stableid, points: [from, to], kind: 'sub'};
        }

        return {relationid: rel.stableid, points: [from, to], kind: 'plain'};
    });

    return {spine, stations, routes};
}
