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

/**
 * Download a workspace export (JSON) from a ViMi Pad activity.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$workspaceid = required_param('workspaceid', PARAM_INT);
$format = optional_param('format', 'json', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'vimipad');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/vimipad:export', $context);

$instance = $DB->get_record('vimipad', ['id' => $cm->instance], '*', MUST_EXIST);
$workspace = $DB->get_record(
    'vimipad_workspace',
    ['id' => $workspaceid, 'vimipadid' => $instance->id],
    '*',
    MUST_EXIST
);

// Graders may export any workspace; everyone else only one they may edit.
if (!has_capability('mod/vimipad:grade', $context)) {
    \mod_vimipad\local\access::require_edit($instance, $context, $workspace, (int) $USER->id);
}

$exporter = new \mod_vimipad\local\service\export_service();
$profile = $instance->defaultprofile;

if ($format === 'json') {
    $content = $exporter->export_json($instance, $workspace, $profile);
    $filename = $exporter->filename($instance, $profile, 'json');
    send_file($content, $filename, 0, 0, true, true, 'application/json');
}

if ($format === 'xml') {
    $content = $exporter->export_xml($instance, $workspace, $profile);
    $filename = $exporter->filename($instance, $profile, 'xml');
    send_file($content, $filename, 0, 0, true, true, 'application/xml');
}

throw new moodle_exception('error:unknownexportformat', 'mod_vimipad', '', $format);
