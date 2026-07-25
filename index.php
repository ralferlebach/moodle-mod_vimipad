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
 * List all ViMi Pad instances in a course.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url('/mod/vimipad/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_vimipad'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_vimipad'));

$instances = get_all_instances_in_course('vimipad', $course);

if (empty($instances)) {
    notice(get_string('noinstances', 'mod_vimipad'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name')];
$table->align = ['left'];

foreach ($instances as $instance) {
    $url = new moodle_url('/mod/vimipad/view.php', ['id' => $instance->coursemodule]);
    $class = $instance->visible ? '' : 'dimmed';
    $table->data[] = [html_writer::link($url, format_string($instance->name), ['class' => $class])];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
