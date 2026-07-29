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

namespace mod_vimipad\local;

/**
 * Sends system notifications to group members on consensus events.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consensus_notifier {
    /**
     * Notify the group's members of a consensus event.
     *
     * @param \stdClass $cm The course module.
     * @param \stdClass $instance The activity instance.
     * @param string $event One of 'started', 'cancelled', 'submitted'.
     * @param int $actorid The user who triggered the event (not notified).
     * @param int[] $memberids The group member user ids to notify.
     * @return void
     */
    public static function notify(\stdClass $cm, \stdClass $instance, string $event, int $actorid, array $memberids): void {
        $actor = \core_user::get_user($actorid);
        $placeholders = (object) [
            'activity' => format_string($instance->name),
            'user' => $actor ? fullname($actor) : '',
        ];
        $subject = get_string('message:consensus:' . $event . ':subject', 'mod_vimipad', $placeholders);
        $body = get_string('message:consensus:' . $event . ':body', 'mod_vimipad', $placeholders);
        $url = new \moodle_url('/mod/vimipad/view.php', ['id' => $cm->id, 'tab' => 'journal']);

        foreach ($memberids as $memberid) {
            if ((int) $memberid === $actorid) {
                continue;
            }
            $message = new \core\message\message();
            $message->component = 'mod_vimipad';
            $message->name = 'consensus';
            $message->userfrom = $actor ?: \core_user::get_noreply_user();
            $message->userto = (int) $memberid;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = \html_writer::tag('p', s($body));
            $message->smallmessage = $subject;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = format_string($instance->name);
            $message->courseid = (int) $instance->course;
            message_send($message);
        }
    }
}
