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
 * The size of the map canvas, in canvas units.
 *
 * Kept in a module with no imports of its own. autolayout imports the fishbone
 * layout, and the fishbone layout needs these sizes; while they lived in
 * autolayout the two modules imported each other. When autolayout loaded
 * first - which is what the editor does - the fishbone layout read the sizes
 * before they existed and placed every node at NaN.
 *
 * @module     mod_vimipad/graph/canvas_size
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Width of the map canvas. */
export const CANVAS_WIDTH = 2400;

/** Height of the map canvas. */
export const CANVAS_HEIGHT = 1600;
