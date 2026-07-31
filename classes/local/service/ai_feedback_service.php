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

namespace mod_vimipad\local\service;

use context;
use context_module;
use stdClass;

/**
 * Assists teachers by drafting elaborated feedback via the Moodle AI subsystem.
 *
 * Strictly teacher-in-the-loop: this service produces a draft only. The draft
 * never reaches a learner until a teacher edits and actively accepts it. All
 * generation goes through the core AI subsystem (generate_text), never a
 * provider directly. Prompts are data-minimised: no learner names or ids, only
 * the frozen map content, task, rubric points and teacher notes.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_feedback_service {
    /**
     * Whether AI feedback is available in the given context and activity.
     *
     * Requires: the core AI subsystem present, AI enabled site-wide for this
     * plugin, enabled on the activity, and the acting user holding useai.
     *
     * @param context_module $context The module context.
     * @param stdClass $instance The vimipad instance.
     * @param int|null $userid The acting user id (defaults to the current user).
     * @return bool
     */
    public static function is_available(context_module $context, stdClass $instance, ?int $userid = null): bool {
        if (!class_exists('\core_ai\manager')) {
            return false;
        }
        if (get_config('mod_vimipad', 'enableai') === '0') {
            return false;
        }
        if ((int) $instance->aienabled !== 1) {
            return false;
        }
        return has_capability('mod/vimipad:useai', $context, $userid);
    }

    /**
     * Whether the given user has accepted the AI user policy.
     *
     * Per Moodle's AI principles, acceptance is required at the point of use.
     *
     * @param int $userid The user id.
     * @return bool True if accepted (or if the policy API is unavailable).
     */
    public static function policy_accepted(int $userid): bool {
        if (!class_exists('\core_ai\manager')) {
            return false;
        }
        if (method_exists('\core_ai\manager', 'get_user_policy_status')) {
            return (bool) \core_ai\manager::get_user_policy_status($userid);
        }
        return true;
    }

    /**
     * Build the data-minimised prompt for a snapshot.
     *
     * @param stdClass $instance The vimipad instance.
     * @param array $snapshotdata The decoded snapshot JSON.
     * @param string $notes The teacher's short notes.
     * @param int|null $points The awarded points, if any.
     * @return string The assembled prompt.
     */
    public function build_prompt(stdClass $instance, array $snapshotdata, string $notes, ?int $points): string {
        $task = trim(html_to_text($instance->intro ?? '', 0, false));
        $profile = $snapshotdata['profile'] ?? $instance->defaultprofile;

        $labels = [];
        foreach (($snapshotdata['nodes'] ?? []) as $node) {
            $labels[$node['stableid']] = $node['label'];
        }

        $lines = [];
        foreach (($snapshotdata['relations'] ?? []) as $rel) {
            $source = $labels[$rel['sourceid']] ?? $rel['sourceid'];
            $target = $labels[$rel['targetid']] ?? $rel['targetid'];
            $verb = $rel['label'] !== '' ? $rel['label'] : $rel['type'];
            $lines[] = "- {$source} — {$verb} — {$target}";
        }
        $relationtable = empty($lines)
            ? get_string('ai:norelations', 'mod_vimipad')
            : implode("\n", $lines);

        $parts = [];
        $parts[] = get_string('ai:promptintro', 'mod_vimipad');
        if ($task !== '') {
            $parts[] = get_string('ai:prompttask', 'mod_vimipad') . "\n" . $task;
        }
        $parts[] = get_string('ai:promptprofile', 'mod_vimipad', $profile);
        $parts[] = get_string('ai:promptmap', 'mod_vimipad') . "\n" . $relationtable;
        if ($points !== null) {
            $parts[] = get_string(
                'ai:promptpoints',
                'mod_vimipad',
                (object) ['points' => $points, 'max' => (int) $instance->grade]
            );
        }
        if (trim($notes) !== '') {
            $parts[] = get_string('ai:promptnotes', 'mod_vimipad') . "\n" . trim($notes);
        }
        $parts[] = get_string('ai:promptformat', 'mod_vimipad');
        $parts[] = get_string('ai:promptnohallucinate', 'mod_vimipad');

        return implode("\n\n", $parts);
    }

    /**
     * Call the Moodle AI subsystem to generate text for a prompt.
     *
     * @param context $context The context to run the action in.
     * @param int $userid The acting (teacher) user id.
     * @param string $prompt The prompt text.
     * @return array{text: string, providerinfo: string}
     * @throws \moodle_exception On failure or if AI is unavailable.
     */
    public function generate_text(context $context, int $userid, string $prompt): array {
        if (!class_exists('\core_ai\manager')) {
            throw new \moodle_exception('error:aiunavailable', 'mod_vimipad');
        }

        try {
            $manager = \core\di::get(\core_ai\manager::class);
            $action = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid: $userid,
                prompttext: $prompt,
            );
            $response = $manager->process_action($action);
        } catch (\Throwable $e) {
            throw new \moodle_exception('error:aifailed', 'mod_vimipad', '', null, $e->getMessage());
        }

        if (!$response->get_success()) {
            throw new \moodle_exception('error:aifailed', 'mod_vimipad');
        }

        $data = $response->get_response_data();
        $text = $data['generatedcontent'] ?? ($data['content'] ?? '');
        $providerinfo = $data['model'] ?? ($data['provider'] ?? '');

        return ['text' => (string) $text, 'providerinfo' => (string) $providerinfo];
    }

    /**
     * Store a generated draft for a snapshot.
     *
     * The prompt is only persisted if the administrator enabled prompt storage.
     *
     * @param int $snapshotid The snapshot id.
     * @param int $graderid The teacher user id.
     * @param string $prompt The prompt used.
     * @param string $drafttext The generated draft.
     * @param string $providerinfo Provider/model information.
     * @return int The created aifeedback record id.
     */
    public function store_draft(
        int $snapshotid,
        int $graderid,
        string $prompt,
        string $drafttext,
        string $providerinfo
    ): int {
        global $DB;

        $storeprompts = get_config('mod_vimipad', 'storeprompts') === '1';
        $now = time();

        return $DB->insert_record('vimipad_aifeedback', (object) [
            'snapshotid' => $snapshotid,
            'graderid' => $graderid,
            'promptcontextjson' => $storeprompts ? json_encode(['prompt' => $prompt]) : null,
            'drafttext' => $drafttext,
            'draftformat' => FORMAT_PLAIN,
            'acceptedtext' => null,
            'acceptedformat' => FORMAT_PLAIN,
            'providerinfo' => $providerinfo,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Generate and store a draft in one step.
     *
     * @param context_module $context The module context.
     * @param stdClass $instance The vimipad instance.
     * @param stdClass $snapshot The snapshot record.
     * @param string $notes The teacher notes.
     * @param int|null $points The awarded points.
     * @param int $graderid The teacher user id.
     * @return int The created aifeedback record id.
     */
    public function generate_draft(
        context_module $context,
        stdClass $instance,
        stdClass $snapshot,
        string $notes,
        ?int $points,
        int $graderid
    ): int {
        $snapshotdata = json_decode($snapshot->snapshotjson, true) ?: [];
        $prompt = $this->build_prompt($instance, $snapshotdata, $notes, $points);
        $result = $this->generate_text($context, $graderid, $prompt);

        return $this->store_draft(
            (int) $snapshot->id,
            $graderid,
            $prompt,
            $result['text'],
            $result['providerinfo']
        );
    }

    /**
     * Accept (store the teacher-approved text for) an AI feedback draft.
     *
     * The draft is looked up scoped to the given snapshot, so a draft belonging
     * to a different snapshot/workspace/activity cannot be modified even if its
     * id is supplied. Callers must have already validated access to $snapshotid.
     *
     * @param int $aifeedbackid The draft id.
     * @param int $snapshotid The snapshot the draft must belong to.
     * @param string $acceptedtext The approved feedback text.
     * @return void
     */
    public function accept_draft(int $aifeedbackid, int $snapshotid, string $acceptedtext): void {
        global $DB;

        // Verify the draft belongs to the already access-checked snapshot.
        $record = $DB->get_record(
            'vimipad_aifeedback',
            ['id' => $aifeedbackid, 'snapshotid' => $snapshotid],
            '*',
            MUST_EXIST
        );

        $DB->update_record('vimipad_aifeedback', (object) [
            'id' => $record->id,
            'acceptedtext' => $acceptedtext,
            'acceptedformat' => FORMAT_PLAIN,
            'timemodified' => time(),
        ]);
    }

    /**
     * Return the most recent AI feedback record for a snapshot, if any.
     *
     * @param int $snapshotid The snapshot id.
     * @return stdClass|null
     */
    public function get_latest(int $snapshotid): ?stdClass {
        global $DB;

        $records = $DB->get_records(
            'vimipad_aifeedback',
            ['snapshotid' => $snapshotid],
            'timecreated DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }
}
