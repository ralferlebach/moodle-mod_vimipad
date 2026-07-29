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
    /** Optional rich text content (FORMAT_HTML), empty if none. */
    content?: string;
    /** Content format constant; 1 == FORMAT_HTML. */
    contentformat?: number;
    /** Raw style/profile metadata JSON (shape, fill, text style), empty if none. */
    metadatajson?: string;
}

export interface VimiRelation {
    stableid: string;
    sourceid: string;
    targetid: string;
    type: string;
    label: string;
    direction: number;
    /** Raw style/profile metadata JSON, empty if none. */
    metadatajson?: string;
}

/** Collaboration client configuration, sourced from plugin settings. */
export interface CollabConfig {
    pollinterval: number;
    polladaptive: number;
    pollmin: number;
    pollmax: number;
    leasetimeout: number;
    pushenabled: number;
    pushendpoint: string;
}

export interface WorkspaceState {
    workspaceid: number;
    revision: number;
    locked: number;
    profile: string;
    /** Active form (display type) config from the backend registry, if present. */
    formconfig?: FormConfig;
    layoutjson: string;
    nodes: VimiNode[];
    relations: VimiRelation[];
    collab?: CollabConfig;
}

/**
 * Rendering configuration for the active diagram form (display type).
 *
 * Supplied by the backend vimipadform subplugin registry. The editor prefers
 * these values over its built-in defaults, so a new display type can ship as a
 * subplugin without changing the renderer.
 */
export interface FormConfig {
    /** The profile key (e.g. 'tree'). */
    profile: string;
    /** Localised display name. */
    name: string;
    /** Node shapes offered, in picker order. */
    allowedshapes: string[];
    /** Default node shape. */
    defaultshape: string;
    /** Connector line style: 'straight' | 'curved' | 'orthogonal'. */
    line: string;
    /** Bifurcation behaviour: 'individual' | 'shared' | 'radial'. */
    bifurcation: string;
}

/** A 2D position for a node on the canvas. */
export interface Point {
    x: number;
    y: number;
}

/** Map of node stable id to its stored canvas position. */
export type LayoutMap = Record<string, Point>;

/** A node box size in canvas units. */
export interface Size {
    w: number;
    h: number;
}

/** Map of node stable id to its stored canvas size (manual resize). */
export type SizeMap = Record<string, Size>;

/** An active editing lease held by a collaborator (presence). */
export interface Lease {
    targettype: string;
    targetstableid: string;
    userid: number;
    timeexpires: number;
}

/** A single operation returned by poll_changes. */
export interface PolledOperation {
    revision: number;
    operationtype: string;
    payloadjson: string;
    userid: number;
}

/** The payload returned by the poll_changes external function. */
export interface PollResult {
    revision: number;
    locked: number;
    profile: string;
    operations: PolledOperation[];
    hasmore?: boolean;
    layoutjson: string;
    layouttime?: number;
    leases: Lease[];
}

/** A learner journal entry. */
export interface JournalEntry {
    id: number;
    entrytext: string;
    visibility: number;
    timecreated: number;
}

/** A transport that dispatches a single Moodle external function call. */
export type ServiceTransport = (methodname: string, args: Record<string, unknown>) => Promise<unknown>;

/** Configuration handed to mount() by the host page. */
export interface MountConfig {
    cmid: number;
    /** Active group id for group collaboration mode (0 = auto-select). */
    groupid?: number;
    /** Which view opens first, driven by the surrounding Moodle tab. */
    initialView?: 'canvas' | 'list';
    /** If true, the map is foreign: view live but block all edits. */
    readonly?: boolean;
    /** Owner user to view read-only (0 = self), for teacher inspection. */
    targetUserid?: number;
    /** Optional injected transport; if absent, the built-in fetch client is used. */
    callService?: ServiceTransport;
    /** Optional string getter for i18n; if absent, keys are echoed. */
    getString?: (key: string) => string;
}
