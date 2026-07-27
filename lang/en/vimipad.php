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
 * English language strings for mod_vimipad.
 *
 * @package    mod_vimipad
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['actions'] = 'Actions';
$string['add'] = 'Add';
$string['addannotation'] = 'Add annotation';
$string['ai:accept'] = 'Accept as feedback';
$string['ai:draft'] = 'AI draft';
$string['ai:draftaccepted'] = 'Feedback accepted.';
$string['ai:draftgenerated'] = 'AI draft generated. Please review and edit it.';
$string['ai:editaccept'] = 'Edit and accept the feedback';
$string['ai:generate'] = 'Generate draft';
$string['ai:heading'] = 'AI feedback assistance';
$string['ai:intro'] = 'Generate a draft of elaborated feedback. You must review, edit and accept it before it is used. Nothing is sent to the learner automatically.';
$string['ai:norelations'] = 'The map contains no relations yet.';
$string['ai:notes'] = 'Your notes for the AI (optional)';
$string['ai:policyrequired'] = 'You must accept the AI user policy before using this feature.';
$string['ai:promptformat'] = 'Write at most 180 words. Name two strengths, two concrete improvements and one next step. Be constructive, concrete and supportive.';
$string['ai:promptintro'] = 'You are helping a teacher write individual feedback for a learner\'s knowledge map. Do not re-grade; base your wording on the teacher\'s decision.';
$string['ai:promptmap'] = 'The map as a compact relation table (subject — relation — object):';
$string['ai:promptnohallucinate'] = 'Use only the information provided. Do not invent facts, concepts or relations that are not in the map.';
$string['ai:promptnotes'] = 'Teacher notes:';
$string['ai:promptpoints'] = 'The teacher awarded {$a->points} out of {$a->max} points.';
$string['ai:promptprofile'] = 'Diagram profile: {$a}';
$string['ai:prompttask'] = 'Task:';
$string['aienabled'] = 'Enable AI feedback assistance';
$string['aienabled_help'] = 'Allows teachers to generate a draft of elaborated feedback via the Moodle AI subsystem. Drafts are never sent to learners without explicit teacher review and approval. Requires a configured AI provider and the "Use AI" capability.';
$string['annotationadded'] = 'Annotation added.';
$string['annotations'] = 'Annotations';
$string['annotationtext'] = 'Annotation';
$string['collaborationmode'] = 'Working mode';
$string['collaborationmode_help'] = 'Individual: one map per participant. Group: one map per Moodle group. Course: one shared map for the whole course.';
$string['completionsubmit'] = 'Require snapshot submission';
$string['completionsubmit_desc'] = 'Submit a snapshot for grading';
$string['completionsubmit_label'] = 'Learner must submit a snapshot to complete this activity';
$string['defaultprofile'] = 'Diagram profile';
$string['defaultprofile_help'] = 'The diagram profile defines allowed node and relation types, structural rules and the default layout of the map.';
$string['editor:actions'] = 'Actions';
$string['editor:add'] = 'Add';
$string['editor:addnode'] = 'Add concept';
$string['editor:addrelation'] = 'Add relation';
$string['editor:beingedited'] = 'Being edited';
$string['editor:canvasaria'] = 'Map canvas with draggable concepts';
$string['editor:canvasplaceholder'] = 'The graphical canvas will appear here in a later version.';
$string['editor:canvasview'] = 'Canvas';
$string['editor:deleterelation'] = 'Delete relation';
$string['editor:dir_both'] = 'Double arrow';
$string['editor:dir_left'] = 'Arrow to source';
$string['editor:dir_none'] = 'No arrow';
$string['editor:dir_right'] = 'Arrow to target';
$string['editor:dragnodes'] = 'Drag a concept onto a subject or object cell to retarget a relation';
$string['editor:fmt_bigger'] = 'Larger text';
$string['editor:fmt_delete'] = 'Delete node';
$string['editor:fmt_duplicate'] = 'Duplicate node';
$string['editor:fmt_ellipse'] = 'Ellipse';
$string['editor:fmt_fill'] = 'Fill colour';
$string['editor:fmt_font'] = 'Font';
$string['editor:fmt_fontdefault'] = 'Default font';
$string['editor:fmt_highlight'] = 'Highlight colour';
$string['editor:fmt_move'] = 'Move';
$string['editor:fmt_rect'] = 'Rectangle';
$string['editor:fmt_reset'] = 'Clear formatting';
$string['editor:fmt_roundrect'] = 'Rounded rectangle';
$string['editor:fmt_shape'] = 'Shape';
$string['editor:fmt_smaller'] = 'Smaller text';
$string['editor:fmt_text'] = 'Text';
$string['editor:fmt_textcolor'] = 'Text colour';
$string['editor:fmt_toolbar'] = 'Format node';
$string['editor:listview'] = 'List';
$string['editor:loading'] = 'Loading…';
$string['editor:locked'] = 'This map is locked and can no longer be edited.';
$string['editor:nodelabel'] = 'Concept label';
$string['editor:norelations'] = 'No relations yet. Add concepts, then connect them.';
$string['editor:object'] = 'Object';
$string['editor:relation'] = 'Relation';
$string['editor:relations'] = 'Relations';
$string['editor:retarget'] = 'Retarget';
$string['editor:revision'] = 'Revision';
$string['editor:subject'] = 'Subject';
$string['editor:submit'] = 'Submit for grading';
$string['editor:submitted'] = 'Submitted for grading. The map is now locked.';
$string['editorloading'] = 'Loading the ViMi Pad editor…';
$string['editorplaceholder'] = 'The ViMi Pad editor will appear here. The editor is not part of this early development version yet.';
$string['editorpreview'] = 'Editor preview';
$string['error:aifailed'] = 'The AI request could not be completed. Please try again later.';
$string['error:aiunavailable'] = 'AI feedback is not available. Check that a provider is configured and AI is enabled.';
$string['error:alreadysubmitted'] = 'This map has already been submitted.';
$string['error:nodenotfound'] = 'The referenced node could not be found.';
$string['error:nogroup'] = 'You are not a member of any group in this activity.';
$string['error:notingroup'] = 'You are not a member of the selected group.';
$string['error:notownworkspace'] = 'You may only edit your own map.';
$string['error:relationnotfound'] = 'The referenced relation could not be found.';
$string['error:revisionconflict'] = 'Your changes are based on an outdated version. Please reload and try again.';
$string['error:workspacelocked'] = 'This map is locked and can no longer be edited.';
$string['feedback'] = 'Feedback';
$string['grade'] = 'Grade';
$string['gradeoutof'] = 'Grade (out of {$a})';
$string['gradesaved'] = 'Grade saved.';
$string['gradetitle'] = 'View and grade snapshot';
$string['mode_course'] = 'Course map';
$string['mode_group'] = 'Group work';
$string['mode_individual'] = 'Individual work';
$string['modulename'] = 'ViMi Pad';
$string['modulename_help'] = 'ViMi Pad (Visual Mind Pad) is an activity for visual knowledge construction: concept maps, mind maps, trees, semantic networks and word maps — individually, in groups, with snapshot-based grading and AI-assisted teacher feedback.';
$string['modulenameplural'] = 'ViMi Pads';
$string['noaccess'] = 'You do not have permission to edit a map in this activity.';
$string['noinstances'] = 'There are no ViMi Pad activities in this course.';
$string['nosubmissions'] = 'No snapshots have been submitted yet.';
$string['participant'] = 'Participant';
$string['pluginadministration'] = 'ViMi Pad administration';
$string['pluginname'] = 'ViMi Pad';
$string['privacy:metadata:core_ai'] = 'AI feedback drafts are generated through the Moodle AI subsystem, which processes a data-minimised representation of the map.';
$string['privacy:metadata:createdby'] = 'The user who created the item.';
$string['privacy:metadata:modifiedby'] = 'The user who last modified the item.';
$string['privacy:metadata:timecreated'] = 'The time the item was created.';
$string['privacy:metadata:timemodified'] = 'The time the item was last modified.';
$string['privacy:metadata:vimipad_aifeedback'] = 'AI feedback drafts and approved teacher feedback for a snapshot.';
$string['privacy:metadata:vimipad_aifeedback:acceptedtext'] = 'The teacher-approved feedback text.';
$string['privacy:metadata:vimipad_aifeedback:drafttext'] = 'The AI-generated feedback draft.';
$string['privacy:metadata:vimipad_aifeedback:graderid'] = 'The teacher who generated or approved the feedback.';
$string['privacy:metadata:vimipad_annotation'] = 'Annotations attached to a snapshot.';
$string['privacy:metadata:vimipad_annotation:commenttext'] = 'The annotation text.';
$string['privacy:metadata:vimipad_annotation:userid'] = 'The user who wrote the annotation.';
$string['privacy:metadata:vimipad_grade'] = 'Grades and overall feedback for a learner.';
$string['privacy:metadata:vimipad_grade:feedback'] = 'The overall feedback text.';
$string['privacy:metadata:vimipad_grade:grade'] = 'The grade awarded.';
$string['privacy:metadata:vimipad_grade:grader'] = 'The user who awarded the grade.';
$string['privacy:metadata:vimipad_grade:userid'] = 'The graded user.';
$string['privacy:metadata:vimipad_journalentry'] = 'Personal journal entries written during map construction.';
$string['privacy:metadata:vimipad_journalentry:entrytext'] = 'The journal entry content.';
$string['privacy:metadata:vimipad_journalentry:userid'] = 'The author of the journal entry.';
$string['privacy:metadata:vimipad_layout'] = 'Node and viewport positions of a map layout.';
$string['privacy:metadata:vimipad_lock'] = 'Short-lived editing locks record which user is currently editing an element, so collaborators do not overwrite each other. Locks expire automatically.';
$string['privacy:metadata:vimipad_lock:userid'] = 'The user who currently holds the editing lock.';
$string['privacy:metadata:vimipad_node'] = 'Nodes created within a map.';
$string['privacy:metadata:vimipad_node:content'] = 'Optional rich content of the node.';
$string['privacy:metadata:vimipad_node:label'] = 'The node label.';
$string['privacy:metadata:vimipad_operation'] = 'The log of edit operations performed on a map.';
$string['privacy:metadata:vimipad_operation:operationtype'] = 'The type of operation performed.';
$string['privacy:metadata:vimipad_operation:userid'] = 'The user who performed the operation.';
$string['privacy:metadata:vimipad_relation'] = 'Relations created within a map.';
$string['privacy:metadata:vimipad_relation:label'] = 'The relation label.';
$string['privacy:metadata:vimipad_snapshot'] = 'Submitted, immutable snapshots of a map.';
$string['privacy:metadata:vimipad_snapshot:submittedby'] = 'The user who submitted the snapshot.';
$string['privacy:metadata:vimipad_workspace'] = 'A user\'s or group\'s editable map.';
$string['privacy:metadata:vimipad_workspace:userid'] = 'The owner of an individual map.';
$string['privacy:path:workspace'] = 'Workspace';
$string['profile_bubblemap'] = 'Bubble / word map';
$string['profile_conceptmap'] = 'Concept map';
$string['profile_mindmap'] = 'Mind map / radial map';
$string['profile_semanticnetwork'] = 'Semantic network';
$string['profile_tree'] = 'Tree map';
$string['savegrade'] = 'Save grade';
$string['setting:collabheading'] = 'Collaboration';
$string['setting:collabheading_desc'] = 'Settings for real-time collaboration: change polling, adaptive intervals, element locking and optional push notifications.';
$string['setting:enableai'] = 'Enable AI feedback';
$string['setting:enableai_desc'] = 'Allow teachers to generate AI feedback drafts via the Moodle AI subsystem. Requires a configured AI provider. Can also be turned off per activity.';
$string['setting:leasetimeout'] = 'Element lock timeout';
$string['setting:leasetimeout_desc'] = 'How long an editing lock on a node or relation is held. The editing client renews it automatically; if the client disconnects, the lock expires after this time.';
$string['setting:polladaptive'] = 'Adaptive polling';
$string['setting:polladaptive_desc'] = 'Automatically lengthen the polling interval when responses are slow or there is nothing new, and shorten it again when activity resumes.';
$string['setting:pollinterval'] = 'Default polling interval';
$string['setting:pollinterval_desc'] = 'How often the editor checks for changes made by collaborators. Lower values feel more live but increase server load.';
$string['setting:pollmax'] = 'Maximum polling interval';
$string['setting:pollmax_desc'] = 'The longest interval adaptive polling will use.';
$string['setting:pollmin'] = 'Minimum polling interval';
$string['setting:pollmin_desc'] = 'The shortest interval adaptive polling will use.';
$string['setting:pushenabled'] = 'Enable push notifications';
$string['setting:pushenabled_desc'] = 'Optional. When enabled and a push endpoint is configured, the editor receives changes in real time instead of polling. Requires a separate push service; polling remains the fallback.';
$string['setting:pushendpoint'] = 'Push endpoint URL';
$string['setting:pushendpoint_desc'] = 'The URL of the push service the editor connects to when push notifications are enabled.';
$string['setting:storeprompts'] = 'Store AI prompts';
$string['setting:storeprompts_desc'] = 'If enabled, the prompt sent to the AI is stored alongside each draft for transparency and auditing. Prompts are data-minimised and contain no learner names.';
$string['snapshotstatus_0'] = 'Draft';
$string['snapshotstatus_1'] = 'Submitted';
$string['snapshotstatus_2'] = 'In review';
$string['snapshotstatus_3'] = 'Graded';
$string['snapshotstatus_4'] = 'Returned';
$string['status'] = 'Status';
$string['submissions'] = 'Submissions';
$string['submit'] = 'Submit for grading';
$string['submitconfirm'] = 'Once submitted, the map is locked and can no longer be edited. Continue?';
$string['submitted'] = 'Submitted for grading.';
$string['viewandgrade'] = 'View and grade';
$string['vimipad:addinstance'] = 'Add a new ViMi Pad activity';
$string['vimipad:comment'] = 'Comment on maps and snapshots';
$string['vimipad:editgroup'] = 'Edit the group map';
$string['vimipad:editown'] = 'Edit own map';
$string['vimipad:export'] = 'Export maps and snapshots';
$string['vimipad:grade'] = 'Grade submitted snapshots';
$string['vimipad:manageprofiles'] = 'Manage diagram profiles';
$string['vimipad:submit'] = 'Submit a snapshot for grading';
$string['vimipad:useai'] = 'Use AI feedback assistance';
$string['vimipad:view'] = 'View a ViMi Pad activity';
$string['vimipadname'] = 'Activity name';
