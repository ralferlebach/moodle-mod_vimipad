# moodle-mod_vimipad

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-mod_vimipad/actions/workflows/moodle-ci.yml/badge.svg?branch=main)](https://github.com/ralferlebach/moodle-mod_vimipad/actions?query=workflow%3A%22Moodle+Plugin+CI%22)

ViMi Pad (Visual Mind Pad) is a Moodle activity module for visual, collaborative
and reflective knowledge construction. Learners build concept maps, mind maps,
trees, semantic networks and many more diagram types — individually, in groups or
as a whole course — while teachers set the task, grade an immutable snapshot,
annotate directly on the artefact and draft elaborated feedback with the help of
the Moodle AI subsystem.

## Status

**0.9.0 — beta** (`MATURITY_BETA`). The core plugin and all 19 bundled
subplugins carry the same release and maturity, so the plugin overview shows a
consistent picture.

Beta means: the architecture, data model, security model, privacy and backup
paths are complete and covered by an automated suite (PHPUnit, Jest, Behat,
Playwright, plus jMeter/k6 load harnesses), and an external audit has signed the
codebase off for a beta branch. What beta is *for* is the validation that only
real use can provide — classroom-scale field testing, browser matrix, long-run
collaboration and accessibility verification with actual assistive technology.
Please report findings via the issue tracker; see `docs/beta/beta-testing.md`.

## Requirements

This plugin requires Moodle 4.5+

It is developed and continuously tested against Moodle 4.5, 5.0 and 5.2 on
PHP 8.1–8.3 with both PostgreSQL and MariaDB. The interactive editor is a
React/TypeScript application shipped as a pre-built, committed AMD bundle, so no
build step is required at install or run time.

## Motivation for this plugin

Representing knowledge visually — and being able to assess that representation
fairly — is hard to do well inside an LMS. Existing tools are either standalone
mind-map editors with no assessment story, or they require extra servers for
real-time collaboration. ViMi Pad was written to keep everything inside Moodle: a
shared domain model for nodes, relations, containers, revisions, snapshots,
annotations and grades, with the different diagram types layered on top as
profiles. Collaboration is achieved with server-authoritative operations,
revisions and short polling — no WebSocket, CRDT or Node/Python service is part
of the base architecture — and grading works on a frozen snapshot so the object
being assessed cannot change underneath the teacher.

## Installation

Install the plugin like any other plugin to folder

    /mod/vimipad

See <http://docs.moodle.org/en/Installing_plugins> for details on installing
Moodle plugins.

The activity ships with two subplugin types, which live inside the plugin and are
installed automatically:

- **vimipadform** (in `mod/vimipad/form/`) — the display types (diagram
  profiles).
- **vimipadassess** (in `mod/vimipad/assess/`) — the assessment scorers used for
  automatic structural checks against a reference map.

## Usage & Settings

After installing the plugin, it is ready to use without the need for any
configuration; sensible defaults are provided.

To adjust the global behaviour, please visit:
Site administration -> Plugins -> Activity modules -> ViMi Pad

There, you find settings grouped into:

- **Editor** — automatic-layout iterations and shrink behaviour for the
  "arrange" action (`arrangeiterations`, `arrangeshrink`).
- **Collaboration** — the polling cadence for the near-real-time sync
  (`pollinterval`, `pollmin`, `pollmax`, `polladaptive`) and the edit-lease
  timeout (`leasetimeout`). Optionally, low-latency push can be enabled via a
  standalone Mercure hub (`pushenabled`, `pushpublishurl`, `pushjwtkey`,
  `pushendpoint`); when it is off, polling remains the reliable fallback.
- **AI** — a global on/off switch for the AI feedback feature (`enableai`) and
  whether AI prompts/outputs are stored for accountability (`storeprompts`). The
  feature uses Moodle's AI subsystem and never sends AI text to learners without a
  teacher explicitly accepting it.

When creating an activity, a teacher chooses the diagram profile (or a set of
allowed profiles), the working mode (individual, group or course), the starting
template, required/forbidden terms, the submission and grading method, whether AI
support is available and the export options.

If you want to learn more about using activity plugins in Moodle, please see
<https://docs.moodle.org/en/Activities>.

## Capabilities

This plugin introduces these additional capabilities:

- **mod/vimipad:addinstance** — Add a new ViMi Pad activity to a course.
- **mod/vimipad:view** — View a ViMi Pad activity and its map.
- **mod/vimipad:editown** — Edit one's own map.
- **mod/vimipad:editgroup** — Edit the shared map of one's group.
- **mod/vimipad:comment** — Add annotations to nodes, relations or areas.
- **mod/vimipad:submit** — Submit a map snapshot for grading.
- **mod/vimipad:grade** — Grade submitted snapshots and give feedback.
- **mod/vimipad:peerreview** — Take part in the peer-review phases.
- **mod/vimipad:useai** — Use the AI-assisted feedback drafting.
- **mod/vimipad:export** — Export maps (PNG/SVG/JSON, and PDF where available).
- **mod/vimipad:manageprofiles** — Manage templates and profile restrictions.

## Scheduled Tasks

This plugin introduces this additional scheduled task:

- **mod_vimipad\task\purge_expired_locks** — Releases stale collaboration edit
  locks whose lease has expired. By default, the task is enabled and runs on a
  frequent schedule.

## How this plugin works [ / Pitfalls]

At its core the plugin implements one shared domain model — map instance,
workspace, node, relation, container, membership, layout state, operation log,
revision, snapshot, annotation, submission, grade/feedback and AI feedback draft.
Every diagram type is a **profile** on top of this model that declares its allowed
node shapes, connector style, relation types and — declaratively — how the
"arrange" refiner should lay it out (preferred direction, ordering, 1D line
confinement, rank layering, cluster cohesion, per-branch or per-relation-type
direction, and so on).

The following display types (vimipadform subplugins) are provided:

- **Concept map**, **Mind map / Radial map**, **Tree map**, **Semantic network**
  and **Bubble / Word map** (the core set),
- plus **Timeline**, **Argument map**, **Flow chart**, **Affinity board**,
  **Fishbone / Ishikawa**, **Causal / system map**, **Venn / sets** and
  **Ontology**.

Several profiles carry **typed relations** with meaning and, where useful, layout
effect: argument maps (support/attack, attacks repel), causal maps (positive/
negative polarity), the ontology and semantic network (is-a, instance-of,
part-of, has-property, associated — part-of binds tighter, is-a forms a
taxonomy), and flow charts (sequence/yes/no decision branches).

Grading is **snapshot-based**: a submission freezes nodes, relations, containers,
layout, revision, author information and metadata, so later editing never changes
the graded object. Teachers can grade with points, a Moodle-core rubric or a
marking guide, run automatic structural checks (via the vimipadassess scorers),
annotate the artefact and, optionally, generate an elaborated feedback draft that
they review and accept before it is stored.

**Editor pitfall / for developers:** the React editor is delivered as a committed
esbuild bundle at `amd/build/editor_lazy.min.js` (+ `.map`), alongside the Moodle
AMD modules `init.min.js`/`revision.min.js`. These build artefacts are checked
into the repository and verified for byte-reproducibility in CI. If you work on
the front end, rebuild with `node build.mjs` and Moodle's Grunt and commit the
resulting `amd/build/*` files, including the source maps.

## Theme support

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic
theme. However, we can't support any other theme than Boost.

## Plugin repositories

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on GitHub:
<https://github.com/ralferlebach/moodle-mod_vimipad>

## Bug and problem reports / Support requests

This plugin is carefully developed and thoroughly tested, but bugs and problems
can always appear.

Please report bugs and problems on GitHub:
<https://github.com/ralferlebach/moodle-mod_vimipad/issues>

We will do our best to solve your problems, but please note that due to limited
resources we can't always provide per-case support.

## Feature proposals

Due to limited resources, the functionality of this plugin is primarily
implemented for our own local needs and published as-is to the community. We are
aware that members of the community will have other needs and would love to see
them solved by this plugin.

Please issue feature proposals on GitHub:
<https://github.com/ralferlebach/moodle-mod_vimipad/issues>

Please create pull requests on GitHub:
<https://github.com/ralferlebach/moodle-mod_vimipad/pulls>

We are always interested to read about your feature proposals or even get a pull
request from you, but please accept that we can handle your issues only as feature
*proposals* and not as feature *requests*.

## Moodle release support

Due to limited resources, this plugin is only maintained for the most recent
major release of Moodle as well as the most recent LTS release of Moodle.
Bugfixes are backported to the LTS release. However, new features and
improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work
in legacy major releases of Moodle are still available as-is without any further
updates.

There may be several weeks after a new major release of Moodle has been published
until we can do a compatibility check and fix problems if necessary. If you
encounter problems with a new major release of Moodle - or can confirm that this
plugin still works with a new major release - please let us know on GitHub.

## Translating this plugin

This Moodle plugin is provided with English and German language packs only.
Translations into other languages must be managed through AMOS
(<https://lang.moodle.org>), where they will become part of Moodle's official
language pack.

As the plugin creator, we continue to maintain the German translation. For all
other languages, we kindly ask you to contribute your translations directly in
AMOS. These contributions will be reviewed by Moodle's official language pack
maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!

## Right-to-left support

This plugin has not been tested with Moodle's support for right-to-left (RTL)
languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you
are free to send us a pull request on GitHub with modifications.

## Maintainers

The plugin is maintained by  
Ralf Erlebach

## Copyright

The copyright of this plugin is held by  
Ralf Erlebach

Individual copyrights of individual developers are tracked in PHPDoc comments and
Git commits.
