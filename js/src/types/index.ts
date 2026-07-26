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
 * Shared editor types.
 *
 * @module     mod_vimipad/types
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export interface VimiNode {
    stableid: string;
    type: string;
    label: string;
}

export interface VimiRelation {
    stableid: string;
    sourceid: string;
    targetid: string;
    type: string;
    label: string;
    direction: number;
}

export interface WorkspaceState {
    workspaceid: number;
    revision: number;
    locked: number;
    profile: string;
    layoutjson: string;
    nodes: VimiNode[];
    relations: VimiRelation[];
}

/** A 2D position for a node on the canvas. */
export interface Point {
    x: number;
    y: number;
}

/** Map of node stable id to its stored canvas position. */
export type LayoutMap = Record<string, Point>;

/** A transport that dispatches a single Moodle external function call. */
export type ServiceTransport = (methodname: string, args: Record<string, unknown>) => Promise<unknown>;

/** Configuration handed to mount() by the host page. */
export interface MountConfig {
    cmid: number;
    /** Optional injected transport; if absent, the built-in fetch client is used. */
    callService?: ServiceTransport;
    /** Optional string getter for i18n; if absent, keys are echoed. */
    getString?: (key: string) => string;
}
