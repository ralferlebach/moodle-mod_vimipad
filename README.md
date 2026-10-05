moodle-mod_vimipad
==================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-mod_vimipad/actions/workflows/moodle-ci.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-mod_vimipad/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain) [![ViMi Pad: Project site](https://img.shields.io/badge/ViMi%20Pad-Project%20site-0f6cbf)](https://ralferlebach.github.io/Moodle-ViMiPad-Plugins/)

The ViMi Pad activity lets learners build knowledge maps - concept maps, mind maps, trees, semantic networks, flowcharts and more - alone or together, and lets teachers grade a frozen snapshot of that work.

ViMi Pad is not a single plugin but a family of four that work as one system. They are released
together, carry the same version number and the three satellites declare the activity as a
dependency, so a satellite is only ever as current as the activity it was qualified against.

* **mod_vimipad** is the editor and the data model: it owns the map itself - nodes, relations, containers, revisions, snapshots, annotations and grades - and exposes the public map API that the other three build on.
* **mod_vimigallery** is the reading view: it shows a set of maps as an album, one at a time, embedded on the course page or behind a link.
* **qtype_vimipad** turns a map into a quiz question: learners answer by drawing, and the answer is marked automatically against a reference map.
* **datafield_vimipad** adds a map as a field type in the Database activity, so a map can be one column of a collection.

This README documents **mod_vimipad** - the first bullet point above. The other three plugins are
documented in their own repositories.

Because the responsibilities are split this way, the rules that decide what a valid map is exist in
exactly one place. The satellites never parse map data themselves; they hand it to this plugin's map
API and get back either a validated map or a reason why it was refused.


Requirements
------------

This plugin requires Moodle 4.5+

It has no dependencies on other plugins. The three satellites depend on it, not the other way round,
so this activity can be installed on its own:

* **mod_vimigallery (ViMi Gallery)** - optional, shows ViMi Pad maps as an album\
  https://github.com/ralferlebach/moodle-mod_vimigallery
* **qtype_vimipad (ViMi Pad question type)** - optional, map questions in the Quiz\
  https://github.com/ralferlebach/moodle-qtype_vimipad
* **datafield_vimipad (ViMi Pad database field)** - optional, maps inside the Database activity\
  https://github.com/ralferlebach/moodle-datafield_vimipad


Motivation for this plugin
--------------------------

Drawing a concept map is one of the few activities where arranging the material is the learning, not
a way of presenting it afterwards. Moodle had no place for that: a map drawn in an external tool
arrives as a flat image, so a teacher can look at it but not grade the structure behind it, and a
learner cannot continue where they left off.

This plugin keeps the map as data rather than as a picture. That one decision is what makes the rest
possible - collaborative editing, replaying how a map grew, structural checks against a reference
solution, and a snapshot that stays fixed while the learner keeps working.


Installation
------------

Install the plugin like any other plugin to folder
/mod/vimipad

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it is ready to use. Add a ViMi Pad activity to a course and choose a
diagram form; learners then edit the map directly in the course.

The activity settings decide how the work is done: whether learners work alone, in groups or on one
shared course map, which diagram form applies, what a map must contain before it can be submitted,
and how it is graded.

To configure the site-wide defaults, please visit:
Site administration -> Plugins -> Activity modules -> ViMi Pad

There, you find settings in two groups:

* **Editor** - how the canvas behaves when a learner presses Arrange: how hard the layout solver works, and whether containers shrink to fit their contents.
* **Collaboration** - how co-editing is kept in step: the polling interval and its adaptive range, how long an element stays locked while someone edits it, and an optional push endpoint for sites that would rather not poll. AI feedback is switched on here as well, together with whether the prompts sent to the AI are stored for transparency.

If you want to learn more about using activity plugins in Moodle, please see https://docs.moodle.org/en/Activities.


Capabilities
------------

This plugin also introduces these additional capabilities:

* **mod/vimipad:addinstance** - Add a ViMi Pad activity to a course. By default, this is assigned to managers and editing teachers.
* **mod/vimipad:view** - View a ViMi Pad activity. By default, this is assigned to all participating roles.
* **mod/vimipad:editown** - Edit one's own map. By default, this is assigned to students and teachers.
* **mod/vimipad:editgroup** - Edit the shared map of one's own group. By default, this is assigned to students and teachers.
* **mod/vimipad:comment** - Annotate nodes, relations and whole maps. By default, this is assigned to students and teachers.
* **mod/vimipad:submit** - Submit a snapshot for grading. By default, this is assigned to students.
* **mod/vimipad:peerreview** - Review maps submitted by other learners. By default, this is assigned to students.
* **mod/vimipad:grade** - Grade a submitted snapshot and write feedback. By default, this is assigned to teachers and managers.
* **mod/vimipad:useai** - Generate an AI feedback draft. By default, this is assigned to teachers and managers.
* **mod/vimipad:export** - Export maps and snapshots. By default, this is assigned to students and teachers.
* **mod/vimipad:manageprofiles** - Manage diagram forms. By default, this is assigned to managers.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

* **\mod_vimipad\task\purge_expired_locks** - Removes editing locks whose holder disconnected without releasing them.\ By default, the task is enabled and runs on a short schedule.


How this plugin works / Pitfalls
--------------------------------

A map is stored as nodes and relations, not as a drawing. Every edit is an operation applied on the
server against a revision number, so two people editing the same map cannot silently overwrite each
other: an edit based on a stale revision is refused rather than merged by chance. While someone is
editing one element, that element is locked for a short lease which renews itself and expires on its
own if the browser goes away.

The diagram form is a subplugin. It decides which node shapes and relation types a map may use and
how Arrange lays it out - a fishbone is built from a spine with alternating ribs, a flowchart from
process symbols, a system dynamics model from accumulations and rates. Adding a form does not touch
the editor; it declares its vocabulary and its layout rules, and the editor follows them.

Grading works on a snapshot. When a learner submits, the current state is frozen, and that frozen
state is what the teacher sees and marks. The learner can be allowed to continue afterwards without
the graded artefact changing underneath the grade.

**Pitfall:** the automatic scorers suggest, they do not decide. A structural match against a
reference map says how much of the expected structure is present, which is a useful starting point
and a misleading verdict - a map can reproduce every expected relation and still show a
misconception. The grade is always the teacher's.

**Pitfall:** the optional push endpoint needs a separate service. Without one, co-editing still
works; it falls back to polling, which is the default.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-mod_vimipad

An overview of the whole plugin family is published at:
https://ralferlebach.github.io/Moodle-ViMiPad-Plugins/


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own local needs and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/issues

Please create pull requests on Github:
https://github.com/ralferlebach/moodle-mod_vimipad/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature _proposals_ and not as feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

This plugin is designed to be compatible with all currently supported versions of Moodle, leveraging its latest APIs. However, if you are using a legacy version of Moodle, we kindly advise against installing or using this plugin. Instead, we strongly recommend updating your Moodle instance to a supported version to ensure security and compliance with current technological standards. Thank you for your understanding.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into other languages must be managed through AMOS (https://lang.moodle.org), where they will become part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages, we kindly ask you to contribute your translations directly in AMOS. These contributions will be reviewed by Moodle's official language pack maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
Ralf Erlebach


Copyright
---------

The copyright of this plugin is held by\
Ralf Erlebach

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
