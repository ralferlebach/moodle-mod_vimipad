# moodle-mod_vimipad

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-mod_vimipad/actions/workflows/moodle-ci.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-mod_vimipad/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

ViMi Pad (Visual Mind Pad — "Visuell Miteinander Lernen in Moodle") is a Moodle
activity module for visual knowledge construction: concept maps, mind maps,
trees, semantic networks and word maps — individually or in groups, with
snapshot-based grading, teacher annotations at the artefact and AI-assisted
feedback drafting via the Moodle AI subsystem.

**Development status: 0.5.13 — consensus state-machine backend (start/confirm/cancel/status) with web services and tests; UI + messaging next.**
The 0.5.x line adds import (JSON & XML, append or replace, including layout),
reopening a submitted map for revision, internal canvas refactoring and polling
scalability.
The activity can be created and configured (diagram profile, working mode,
optional companion channel, AI toggle), and provides the full editor: a React
canvas and an equal-rights relation list view with real-time collaboration,
undo/redo, automatic layout, and export (JSON, XML, SVG, PNG, PDF). Learners
work individually, in groups or course-wide; teachers submit snapshots for
grading, add annotations to the map or to individual concepts/relations, view a
shared learner journal, and draft AI-assisted feedback. Groups, gradebook,
completion, privacy and backup/restore are integrated. The 0.4.x line has since
focused on hardening (authorization, backup/restore completeness, a full privacy
provider, workspace-creation and submission concurrency, group/course grading
and completion semantics, operation payload contracts and layout merge).


## Requirements

This plugin requires Moodle 4.5+

No additional server software is required: no Node/npm, no Python, no
WebSocket services on the production server. Frontend assets ship prebuilt.
The AI feedback feature additionally requires a configured Moodle AI provider
(Moodle AI subsystem); without one, the feature degrades gracefully.


## Motivation for this plugin

Existing Moodle mindmap plugins are either unmaintained or plain drawing tools
without a pedagogical assessment workflow. ViMi Pad closes this gap with a
Moodle-native activity: a shared domain model (nodes, relations, containers,
revisions, snapshots, annotations, grading data) with diagram types layered on
top as profiles, an immutable snapshot as the basis for grading, an equal-rights
list view for accessible and mobile editing, and teacher-in-the-loop AI
feedback drafting — all fully integrated with Moodle groups, gradebook,
completion, privacy and backup.


## Installation

Install the plugin like any other plugin to folder
/mod/vimipad

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


## Usage & Settings

After installing the plugin, it is ready to use without the need for any
site-wide configuration.

Teachers add a "ViMi Pad" activity to a course and configure per activity:

* Diagram profile (concept map, mind map, tree, semantic network, bubble/word map)
* Working mode (individual, group, course map)
* AI feedback assistance (on/off; gated by capability and AI subsystem availability)

If you want to learn more about using activity plugins in Moodle, please see
https://docs.moodle.org/en/Activities.


## Capabilities

This plugin also introduces these additional capabilities:

### mod/vimipad:addinstance

Allows adding a new ViMi Pad activity to a course. Assigned to editing teachers and managers by default.

### mod/vimipad:view

Allows viewing a ViMi Pad activity. Assigned to all course roles by default.

### mod/vimipad:editown

Allows editing one's own map. Assigned to students, teachers, editing teachers and managers by default.

### mod/vimipad:editgroup

Allows editing the group map. Assigned to students, teachers, editing teachers and managers by default.

### mod/vimipad:comment

Allows commenting on maps and snapshots. Assigned to students, teachers, editing teachers and managers by default.

### mod/vimipad:submit

Allows submitting a snapshot for grading. Assigned to students by default.

### mod/vimipad:grade

Allows grading submitted snapshots. Assigned to teachers, editing teachers and managers by default.

### mod/vimipad:useai

Allows using the AI feedback assistance. Assigned to editing teachers and managers by default.

### mod/vimipad:export

Allows exporting maps and snapshots. Assigned to all course roles by default.

### mod/vimipad:manageprofiles

Allows managing diagram profiles. Assigned to editing teachers and managers by default.


## Scheduled Tasks

This plugin does not add any additional scheduled tasks.


## How this plugin works / Pitfalls

The plugin implements a shared domain model; diagram types are profiles on top
of it. All changes are recorded through a server-validated operation log with
revisions; near-realtime collaboration works via polling (no CRDT/WebSocket
infrastructure). Grading always references an immutable snapshot. AI feedback
is generated exclusively through the Moodle AI subsystem and remains a draft
until a teacher actively reviews and approves it.

**Shared code for satellite plugins:** mod_vimipad deliberately exposes a
public PHP API under the namespaces `\mod_vimipad\api\*` and
`\mod_vimipad\profile\*` for use by dependent plugins (e.g. a question type or
database field reusing the ViMi editor and profiles). These namespaces are
treated as a stable contract. Everything under `\mod_vimipad\local\*` is
internal implementation without any stability guarantee — do not depend on it.
Satellite plugins must declare `$plugin->dependencies = ['mod_vimipad' => ...]`
in their version.php. There is deliberately no separate local_* core plugin.


## Theme support

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


## Plugin repositories

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-mod_vimipad


## Bug and problem reports / Support requests

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


## Feature proposals

Please issue feature proposals on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/issues

Please create pull requests on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature proposals and not as feature requests.


## Moodle release support

This plugin is maintained for Moodle 4.5 LTS and all newer major releases
(currently tested: 4.5, 5.0, 5.2). The React/ESM frontend integration targets
Moodle 5.2+; Moodle 4.5–5.1 are served by a legacy AMD bundle built from the
same source.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.


## Translating this plugin

This Moodle plugin is shipped with an english and a german language pack.
Once published in the Moodle plugins repository, all translations into other
languages must be managed through AMOS (https://lang.moodle.org) by what they
will become part of Moodle's official language pack.


## Right-to-left support

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


## Maintainers

The plugin is maintained by
Ralf Erlebach


## Copyright

The copyright of this plugin is held by
Ralf Erlebach

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
