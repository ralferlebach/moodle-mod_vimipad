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
 * Behat data generator for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares the entities that Behat can seed for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_vimipad_generator extends behat_generator_base {
    /**
     * Entities this generator can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'submissions' => [
                'singular' => 'submission',
                'datagenerator' => 'submission',
                'required' => ['vimipad', 'user'],
                'switchids' => ['vimipad' => 'instanceid', 'user' => 'userid'],
            ],
            'maps' => [
                'singular' => 'map',
                'datagenerator' => 'map',
                'required' => ['vimipad', 'user'],
                'switchids' => ['vimipad' => 'instanceid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Turn an activity idnumber into a vimipad instance id.
     *
     * @param string $idnumber The activity idnumber.
     * @return int The instance id.
     */
    protected function get_vimipad_id(string $idnumber): int {
        global $DB;

        $cm = $DB->get_record('course_modules', ['idnumber' => $idnumber], '*', MUST_EXIST);
        return (int) $DB->get_field(
            'vimipad',
            'id',
            ['id' => $cm->instance],
            MUST_EXIST
        );
    }

    /**
     * Create a submitted snapshot for a user.
     *
     * @param array $data Requires instanceid, userid; optional label.
     * @return void
     */
    protected function process_submission(array $data): void {
        global $DB;

        $instance = $DB->get_record('vimipad', ['id' => $data['instanceid']], '*', MUST_EXIST);
        $label = $data['label'] ?? 'Energy';

        /** @var mod_vimipad_generator $generator */
        $generator = $this->componentdatagenerator;
        $generator->create_workspace($instance, (int) $data['userid'], [
            ['stableid' => 'node_seedaaaaaaa', 'label' => $label],
        ], true);
    }

    /**
     * Create a sized map (small/medium/large) for a user, so a scenario can seed
     * a realistic workspace with one Given step.
     *
     * @param array $data Requires instanceid, userid; optional size.
     * @return void
     */
    protected function process_map(array $data): void {
        global $DB;

        $instance = $DB->get_record('vimipad', ['id' => $data['instanceid']], '*', MUST_EXIST);
        $size = $data['size'] ?? 'small';

        /** @var mod_vimipad_generator $generator */
        $generator = $this->componentdatagenerator;
        $generator->create_map_profile($instance, (int) $data['userid'], $size);
    }
}
