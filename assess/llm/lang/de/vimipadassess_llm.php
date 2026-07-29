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

$string['pluginname'] = 'KI-Bewertung';
$string['privacy:metadata:core_ai'] = 'Dieser Scorer sendet die Einreichung (als Text) an Moodles KI-Subsystem, um einen Bewertungsvorschlag zu erhalten.';
$string['prompt:instruction'] = 'Antworten Sie mit einer ersten Zeile genau "SCORE: N" (N von 0 bis 100), danach eine kurze Begründung für die Lehrkraft. Vergeben Sie keine endgültige Note; dies ist nur ein Vorschlag.';
$string['prompt:intro'] = 'Sie helfen einer Lehrkraft, die Concept Map einer studierenden Person zu bewerten. Beurteilen Sie, wie gut sie das Thema erfasst.';
$string['prompt:reference'] = 'Musterlösung:';
$string['prompt:studentmap'] = 'Studentische Map:';
