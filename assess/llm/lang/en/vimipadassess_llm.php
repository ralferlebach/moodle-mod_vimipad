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
 * Strings for the LLM scorer.
 *
 * @package    vimipadassess_llm
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI assessment';
$string['privacy:metadata:core_ai'] = 'This scorer sends the submitted map (as text) to Moodle\'s AI subsystem to obtain an assessment suggestion.';
$string['prompt:instruction'] = 'Reply with a first line exactly "SCORE: N" where N is 0 to 100, then a short justification for the teacher. Do not set a final grade; this is only a suggestion.';
$string['prompt:intro'] = 'You are helping a teacher assess a student\'s concept map. Judge how well it captures the topic.';
$string['prompt:reference'] = 'Reference solution:';
$string['prompt:studentmap'] = 'Student map:';
