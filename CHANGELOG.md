# Changelog — mod_vimipad (ViMi Pad)

> **Versioning note.** The authoritative version is always `version.php`
> (`$plugin->release` / `$plugin->version`). Some early Session-002 entries below
> used an exploratory 0.5.0–0.9.1 numbering that was later reset to the 0.2.x
> line; those entries are kept for historical reference only. The current
> release is **0.6.3** (2026072717).

## 0.6.3 (2026072717) — teacher map-constraint fields (submission gate goes live)

Gives the 0.6.2 constraint engine its input: teachers can now define the map
requirements, and the hard submission gate enforces them from real settings.

- **Five new instance settings** on `mod_form` (own "Map requirements" section):
  required concepts, forbidden concepts, allowed relation types (free-text,
  one per line or comma-separated) and minimum concepts / minimum relations.
  Added to `db/install.xml`, `db/upgrade.php` (savepoint 2026072717, guarded
  `add_field`s) and the backup element list; restore is automatic. Since
  `constraint_config::from_instance()` already read these fields, the 0.6.2
  submission gate activates with no further wiring.
- New lang strings for the section, labels and help (en/de, 418/418 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **193 `mod_vimipad`**
  **+ 97 `vimipadassess`** green; `backup_restore_test` extended to assert the
  five new fields survive a backup/restore roundtrip; a new gate test confirms
  the block fires from settings saved through the form; phpcs clean; install.xml
  well-formed and the upgrade savepoint matches version.php.

## 0.6.2 (2026072716) — constraint-policy engine + hard submission gate

Adds the map-constraint engine that the authoring/assessment workflow needs and
wires the hard gate into submission. The teacher-facing input fields for the
qualitative constraints follow in 0.6.3; the engine and gate are complete and
fully tested now.

- **`\mod_vimipad\local\policy` package.** `constraint_config` (value object,
  `from_instance()` reads required/forbidden concepts, allowed relation types and
  min node/relation counts — no-op today, active the moment the fields land),
  `constraint_policy::evaluate()` (pure, deterministic; takes a normalized map,
  returns findings) and `constraint_report` (structured findings +
  `is_satisfied()` + localized `messages()`/`summary()`), so the same evaluation
  can back both the hard gate and future soft edit-time hints.
- **Hard submission gate.** `snapshot_service::create_submission` now evaluates
  the frozen map under the workspace write lock and refuses submission with
  `error:constraintsnotmet` (listing the violations) if it is not satisfied.
  Constraint kinds: missing required concepts, present forbidden concepts,
  disallowed relation types, too few concepts, too few relations
  (case-insensitive term matching).
- New lang strings: `error:constraintsnotmet` and `constraint:*` messages
  (en/de, 407/407 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **192 `mod_vimipad`**
  (incl. `constraint_policy_test`: all constraint kinds + an end-to-end gate test
  that blocks an invalid submission and lets a fixed one through) **+ 97
  `vimipadassess`** green; phpcs clean.

*Versioning note: the previous release (integer 2026072715) is 0.6.1 — the 0.6.x
line uses plain patch numbers without an -alpha suffix.*

## 0.6.1 (2026072715) — 0.6 foundation: container/membership operations + import round-trip

Opens the 0.6.x authoring line with the backend contract the authoring tools sit
on. No new UI yet; this makes containers first-class in the operation log and
closes the export/import asymmetry the 0.5.32 audit flagged.

- **Container & membership operations.** New operation types `container_create`,
  `container_update`, `container_delete`, `membership_add`, `membership_remove`
  in `operation_type` (validated: itemtype enum node|relation|container,
  int-like sortorder) and handled in `operation_service::mutate`
  (create revives soft-deleted rows; membership_add is an upsert; container_delete
  soft-deletes and drops its memberships). All go through the same shared write
  lock and revision path as node/relation operations.
- **Import now round-trips containers and memberships.** The export already
  emitted them (stable-id based); `import_service` now consumes them, remapping
  container stable ids and member references (nodes, relations and nested
  containers) onto the freshly created elements. Relation stable ids are now also
  tracked in the id map so memberships on relations remap correctly. XML parsing
  gained `containers`/`memberships`; `import_map` returns their counts. No format
  version bump was needed — the format already carried containers; only the
  import consumer was missing.
- New lang string `error:containernotfound` (en/de, 401/401 parity).
- **Template/constraint policy specified** (`docs/design/template_constraint_policy.md`):
  soft constraints at edit time via a shared `constraint_policy` resolver, hard
  gate at submission; template structural locks enforced per-operation via element
  `metadatajson` — implementation scheduled across 0.6.x.
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **186 `mod_vimipad`**
  (incl. new `container_operations_test`: lifecycle + import round-trip) **+ 97
  `vimipadassess`** green; phpcs clean on all changed/new files.

## 0.5.34 (2026072714) — 0.5.x closure part 2: due-date/late, map_updated event, peer scope

Second and final closure slice before 0.6.x, wiring up the two behaviours that
were stored but never evaluated and settling the peer-review scope.

- **Due-date lateness is now evaluated.** `snapshot_service::is_late($instance,
  $submittedtime)` marks a submission late when it was created after the (soft)
  `duedate`; a due date of 0 is never late. The due date only *marks* lateness —
  the hard block remains `cutoffdate` (already enforced at submission). The
  grading detail view shows the submission time and a "Late" badge when
  applicable.
- **New `\mod_vimipad\event\map_updated` event.** Fired once per applied
  operation in `apply_operation` (crud `u`, participating level), so course logs
  and reports see editing activity alongside the existing viewed/submitted/graded
  events.
- **Peer-review scope settled (E3-B).** The core keeps the lean base
  (allocation → reviews → aggregate on snapshots/annotations). The full 5-phase
  *activity* workflow is explicitly deferred to the post-1.0 premium
  `vimipadreview_peerplus`; the roadmap now states this rather than leaving it
  open.
- New lang strings: `event:map_updated`, `gradetab:late`, `gradetab:submittedon`
  (en/de, 400/400 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **182 `mod_vimipad`**
  (incl. new `duedate_and_events_test`) **+ 97 `vimipadassess`** green; phpcs
  clean on all changed/new files.

## 0.5.33 (2026072713) — 0.5.x closure: workspace concurrency + fail-closed uniqueness

Closure round before the 0.6.x authoring tools: no new features, but the open
0.5 concurrency guarantee is closed and several contracts/docs are re-baselined
against the real state.

- **Shared per-workspace write lock (concurrency gate).** A single
  `\mod_vimipad\local\lock\workspace_writelock` (key `write_<workspaceid>`)
  now serializes *every* semantic mutation and snapshot creation:
  `operation_service::apply`, `import_service`, `workspace_service::reopen` and
  `snapshot_service::begin_submission`/`finalize` all coordinate on the same
  lock. Previously `operation_service::apply` took no real lock (only checked the
  `locked` flag) and submission used a separate `submit_` key, so a snapshot
  could capture a torn read across the node/relation/container tables relative to
  its recorded revision. `apply` was split into a locking wrapper and a lock-free
  `apply_locked` so import (which fans out into many operations) holds the lock
  once without re-entrant acquisition.
- **Workspace creation is now fail-closed.** `workspace_service::create_unique`
  no longer creates a second workspace when the lock cannot be acquired; it
  re-reads and reuses an existing row or raises a concurrency error (the DB does
  not enforce uniqueness on `(vimipadid, userid|groupid)`).
- **Lease contract clarified (advisory).** `lock_service`'s per-element leases
  are documented as advisory collaboration/presence locks; `operation_service`
  does not enforce them. Concurrency correctness rests on the write lock plus
  optimistic revision checks, not on leases.
- **Import atomicity contract stated explicitly:** the semantic import
  (nodes + relations) is atomic; the layout is applied best-effort after commit.
- **Doc re-baseline.** `session-002.md` added (0.4.22→0.5.32 arc); `roadmap.md`
  (real stand + status markers, Privacy/Backup moved to re-audit), `backlog.md`,
  `connector-styles.md` (`vimipadform`, `formconfig` consumed),
  `ui_reorder_plan.md`, `moodle-test-environment-setup.md` (176+97 baseline),
  `visual-maps-requirements.md`, README (React 5.3 framing, public-API claim
  softened) and `assessment_architecture.md` (single reference per activity,
  E1-B) brought in line with the code. `sessionende.txt` fixed so the persisted
  `docs/sessions/session-<NR>.md` is a mandatory, ZIP-shipped, verified step
  (root cause of the previously missing session-002 doc).
- **Verified on a real Moodle 4.5.12 + PostgreSQL PHPUnit environment:** full
  suite **178 `mod_vimipad`** (incl. the new `workspace_writelock_test`) **+ 97
  `vimipadassess`** tests pass (exit 0); every affected service test green in
  isolation; phpcs clean on all changed/new files.

## 0.5.32 (2026072712) — configuration UI for assessment and peer review

- **Peer review settings in the activity form.** "Peer review" turns the feature
  on and "Reviews per submission" (1-5) sets the allocation target, the latter
  hidden until peer review is enabled. Both carry help text explaining that peer
  scores are advisory.
- **Per-activity scorer selection.** A new "Automatic scorers" multi-select lists
  every installed scorer; leaving it empty keeps the previous behaviour of running
  all of them. The choice is stored in `activescorers` and honoured by
  `assess_service` for the automatic run, the single-scorer path and the on-demand
  AI scorer alike.
- **New peer review tab.** Reviewers see their allocated submissions as
  "Submission 1", "Submission 2" — never the author's name — and open one to get
  the map read-only, the automatic scorers' hints, and a form for an optional
  score out of 100 plus written feedback. Opening a review allocated to someone
  else falls back to the reviewer's own list. A new `mod/vimipad:peerreview`
  capability governs access (students by default).
- **Teacher side.** The grading tab gains an "Allocate peer reviews" action, and a
  submission's grading detail shows the aggregated peer verdict (count, mean,
  median, outstanding) plus the review comments, without reviewer identities.
- **Fixed a Moodle schema rule:** `activescorers` was declared CHAR NOT NULL with
  an empty-string default, which Moodle rejects (it rewrites the default and the
  install.xml structure check fails). The column is now nullable with no default;
  null and empty both mean "all scorers".
- **Fixed: the scorer selection was lost on restore.** `activescorers` was not
  included in the backup field list, so a restored activity silently fell back to
  running every scorer. It is now backed up, and the backup/restore roundtrip test
  asserts that the matching mode, scorer selection and both peer-review settings
  survive.
- **Panel rendering is now covered by tests.** `peer_review_panel_test` drives the
  reviewer list, the empty-allocation case, the detail URL and the no-op action
  path with a real `cm_info` — the same guard added for the grading panel in
  0.5.31, so a parameter-type mismatch in a panel fails in PHPUnit instead of only
  in a browser.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** 176 `mod_vimipad` + 97
  `vimipadassess` tests pass on both (exit 0), every file also passing in
  isolation; phpcs, phpdoc, phpcpd, savepoints, validate and mustache clean. Every
  `get_string()` key referenced in the plugin was cross-checked against the
  language files.

## 0.5.31 (2026072711) — peer review backend + description (ROUGE) scorer

- **Behat fix: grading tab crashed in a browser.** `view.php` resolves the course
  module with `get_course_and_cm_from_cmid()`, which returns a `cm_info`, but
  `grading_panel` (and `consensus_notifier`) declared `stdClass $cm` — a fatal
  TypeError on every visit to the grading tab, and the cause of all four failing
  Behat scenarios on every Moodle version. Both now accept `cm_info|stdClass`. A
  new `grading_panel_test` drives `detail_url`, `render` and `handle_action` with
  a real `cm_info`, so this class of mismatch is caught by PHPUnit rather than
  only in a browser run.
- **New `vimipadassess_text` scorer (description comparison).** Concept labels say
  what a student named; descriptions say what they understood. For each described
  reference concept the scorer locates the matching submission concept (through
  the configured matcher, so fuzzy and word-overlap modes apply) and compares the
  two description texts with ROUGE. Thin descriptions are reported with their
  overlap percentage; absent ones as missing.
- **ROUGE core service** (`local\assess\rouge`): ROUGE-N (clipped n-gram overlap)
  and ROUGE-L (longest common subsequence) as F-measures, plus a combined
  similarity. HTML is stripped and case ignored; long texts are bounded so the
  LCS stays cheap.
- **Peer review backend.** New `peerreviewmode` / `peerreviewcount` activity
  settings and a `vimipad_peerreview` table, driven by `peer_review_service`:
  round-robin allocation over submitting students (nobody reviews their own map,
  idempotent so it can run whenever new submissions arrive), review recording
  (refused without an allocation), and aggregation reporting count, mean, median
  and outstanding reviews. Peer scores stay advisory — the service never writes
  to the gradebook.
  - **Fuzzy-capable:** `guidance()` runs the same synchronous scorers a teacher
    sees, through the activity's matcher, so reviewers get fuzzy-tolerant hints
    about what is present or missing instead of judging unaided.
  - Covered by privacy (metadata, per-user and context deletion) and
    backup/restore (reviewer remapped), both verified.
- This step deliberately completes the *functionality*; the activity-form and
  reviewer UI elements for peer review follow in the next step.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** 165 `mod_vimipad` + 97
  `vimipadassess` tests pass on both (exit 0), every test file also passing in
  isolation; phpcs, phpdoc, phpcpd, savepoints, validate and mustache clean.

## 0.5.30 (2026072710) — sub-map scorer + cross-version CI fixes

- **New `vimipadassess_sms` scorer (sub-map comparison).** Reads each container
  as a sub-map (a set of concepts) and matches every reference sub-map to the
  submission's best-overlapping one (concept-set F1 through the injected
  matcher), reporting which expected groupings were reproduced, missed or added.
  When no sub-maps are defined it returns an informational note, not a
  misleading zero. The `submission` model now carries sub-maps, rebuilt from a
  snapshot's containers and node memberships.
- **CI fix — subplugin plugininfo class.** The `vimipadassess` subplugin type
  had no `\mod_vimipad\plugininfo\vimipadassess` class, so a full site install
  emitted a debugging() notice and every PHPUnit/Behat matrix job failed at the
  install step. Added the class (mirroring `vimipadform`).
- **CI fix — Moodle 5.0 subplugins format.** `db/subplugins.json` now declares
  both `subplugintypes` (relative paths, the Moodle 5.0 form, MDL-83705) and
  `plugintypes` (full paths, for Moodle 4.5), matching core modules, so the 5.0
  deprecation notice is gone while 4.5 keeps working.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** install is clean on
  both; `mod_vimipad` (150) and `vimipadassess` (81) tests pass on both (exit 0;
  on 5.0 the only issues reported are framework-level PHPUnit deprecations, which
  do not fail the build). phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.29 (2026072709) — configurable matching + hierarchy scorer

- **Choice of label matching for automatic scoring.** A new activity setting
  ("Concept matching") selects how concept and proposition labels are compared:
  *exact* (normalised), *fuzzy* (edit distance, tolerates typos) or *word
  overlap* (Jaccard, ignores order and filler words). Implemented as
  `levenshtein_matcher` and `token_matcher` behind the existing `matcher`
  interface, chosen through a `matcher_factory`; every content scorer benefits.
  The choice (`matchmode`) is stored on the activity, shown in the form, and
  carried through backup/restore.
- **New `vimipadassess_tree` scorer** compares hierarchy rather than wording:
  it reads directed relations as parent → child links, identifies the root and
  measures how well the submission reproduces the reference's root and links
  (precision/recall F1). Suited to tree and mind-map profiles; relation labels
  are ignored. Uses the injected matcher, so fuzzy/word-overlap applies here too.
- The grading tab now shows each scorer's part scores generically (e.g. root and
  hierarchy for the tree scorer, concepts and propositions for the reference
  scorer).
- **Verified on a real Moodle 4.5.12 instance:** 150 `mod_vimipad` + 65
  `vimipadassess` tests pass; phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.28 (2026072708) — AI (LLM) scorer + tuple-to-text

- **New `vimipadassess_llm` scorer** assesses a map through Moodle's AI
  subsystem. It runs *on demand* (a "Run AI assessment" button in the grading
  tab), never automatically, because the call is slow and costs apply. Prompt
  building and reply interpretation are deterministic and unit-tested; only the
  model round-trip needs a configured AI provider.
- **Tuple-to-text core service** renders a map's concepts and propositions as
  plain sentences — the shared bridge between the structured map and any
  prompt-based scorer.
- **Contract additions:** scorers can declare `uses_ai()` (so `score_all` skips
  them on page load) and AI scorers implement a `prompt_scorer` interface
  (`build_prompt` / `interpret`); the assess service orchestrates the AI call
  and reuses `ai_feedback_service` for gating.
- **CI fix:** phpdoc rejected the generic array syntax (`array<...>`) in two
  `@param` tags; changed to plain `array`.
- **Verified on a real Moodle 4.5.12 instance:** 146 `mod_vimipad` + 49
  `vimipadassess` tests pass; phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.27 (2026072707) — reference-free structural scorer

- **New scorer `vimipadassess_structure`** — a reference-free structural overview
  of a submission: concept and proposition counts, links per concept, isolated
  concepts and well-connected hubs. It is explicitly *informational* (a new
  `informational` flag and `metrics` list on the assess `result`); the grading
  tab shows the numbers with a clear note that rich structure alone is not a
  grade. It needs no reference solution, so it always applies.
- **Grading tab runs every applicable scorer.** A new `assess_service::score_all`
  runs each scorer that supports the submission's profile — reference-free ones
  always, reference-based ones only when a reference is marked — and the tab
  renders each under its own name (structural metrics for `structure`, the
  match breakdown and suggested grade for `reference`).
- **Verified on a real Moodle 4.5.12 instance:** `mod_vimipad` 145 tests and
  `vimipadassess` 33 tests (incl. the new structure-scorer and score_all tests)
  pass; phpcs, phpcpd, savepoints, validate and mustache are clean.

## 0.5.26 (2026072706) — reference solution + scoring in the grading tab

- **Mark a submission as the activity's reference solution.** A new
  `referencesnapshotid` on the activity records which snapshot is the model
  answer; the grading detail offers "Mark as reference solution" / "Remove
  reference solution" and shows a badge on the reference itself. Backed up and
  restored (the pointer is remapped to the restored snapshot).
- **Automatic scoring suggestion in the grading tab.** When a reference is
  marked, other submissions show the `reference` scorer's suggestion — overall
  match percentage, a rough suggested grade, per-dimension part scores, and the
  matched / missing / extra concepts and propositions. It is explicitly a
  suggestion; the teacher still sets the grade. Wired through a new
  `assess_service` that turns a snapshot into a submission and runs the scorer.
- **Verified on a real Moodle 4.5.12 instance:** `mod_vimipad` 143 tests
  (incl. a new `assess_service` test and a reference-remap assertion in the
  backup/restore roundtrip) and `vimipadassess` 17 tests pass; phpcs, phpcpd,
  savepoints, validate and mustache are clean.

## 0.5.25 (2026072705) — assessment subplugin type + reference scorer

- **New subplugin type `vimipadassess`** for automatic scoring, alongside the
  display-oriented `vimipadform` type. The contract lives in
  `classes/local/assess/`: a `submission` value object (concepts + propositions
  distilled from a snapshot), an exchangeable `matcher` interface with a
  normalised `exact_matcher`, a `result` (suggested score + matched/missing/extra
  breakdown), an abstract `scorer`, and a `registry` that discovers installed
  scorers. Scoring always yields a *suggestion*, never a set grade.
- **First scorer `vimipadassess_reference`** — compares a submission against a
  reference solution by concept and proposition (source–relation–target)
  overlap, with precision/recall F1 per dimension, directional proposition
  matching and a relation-label contribution. The matcher is injected, so the
  same scorer will later work with fuzzy/semantic matching.
- **Verified on a real Moodle 4.5.12 instance**, not just statically: the full
  `mod_vimipad` suite (138 tests) and the `vimipadassess` tests (17, incl. the
  core privacy provider check and registry discovery) pass; phpcs, phpcpd,
  savepoints, validate and mustache are clean.
- Still to come (Ausbaustufe 3, part 2): marking a snapshot as the activity's
  reference solution and surfacing the scorer's suggestion in the grading tab.

## 0.5.24 (2026072704) — advanced grading fixes, verified on a real instance

Set up a real Moodle 4.5.12 + PostgreSQL PHPUnit environment and ran the suite,
which surfaced two bugs that syntax checks alone could not:

- **`gradeitems` now implements the correct interfaces.** 0.5.21/0.5.23 had the
  class *extend* `component_gradeitems`; the grading manager checks for classes
  that *implement* `core_grades\local\gradeitem\itemnumber_mapping` and
  `advancedgrading_mapping`. It now implements both (with the no-argument
  `get_itemname_mapping_for_component()` and `get_advancedgrading_itemnames()`),
  so the advanced-grading debugging notice is genuinely gone. The now-dead
  `grading_areas_list` callback and its string were removed.
- **No duplicate grading backup step.** `backup_activity_task` /
  `restore_activity_task` already add the grading structure step automatically,
  so the manual copy added in 0.5.22 produced a duplicate `grading.xml`
  ("file already exists") and broke backup. Removed; the plugin's own
  `vimipad_gradeinstance` backup/restore and itemid realignment remain.

Full plugin PHPUnit suite: 110 tests, 881 assertions, all passing.

## 0.5.23 (2026072703) — fix advanced grading fatal

- **Correct the `component_gradeitems` signature.** The class added in 0.5.21
  declared `get_itemname_mapping_for_component(): array`, but the core abstract
  method is `get_itemname_mapping_for_component(string $component): array`. The
  mismatched signature was a fatal error that aborted the whole PHPUnit run. The
  method now accepts the `$component` argument.

## 0.5.22 (2026072702) — advanced grading in backup & restore

- **Rubric / marking guide definitions survive backup and restore.** The backup
  now includes the core `backup_activity_grading_structure_step` and the restore
  the matching `restore_activity_grading_structure_step`, so the grading form
  itself is preserved across course backup, restore and import.
- **Per-submission filling links are carried over.** The `vimipad_gradeinstance`
  table is backed up and restored (rater remapped as a user, grading instance
  remapped via the grading_instance mapping), and each restored grading
  instance's itemid is realigned to its new snapshot in `after_execute`. The
  grading step is restored first so its mapping is available. Missing pieces are
  skipped defensively rather than left dangling.
- Needs a real backup → restore → verify test: the definition path follows the
  standard core steps, but the filling relink (instance/itemid remapping) can
  only be confirmed on a live restore. The numeric grade and gradebook value are
  preserved regardless.

## 0.5.21 (2026072701) — advanced grading CI fix

- **Implement `component_gradeitems`.** Enabling advanced grading in 0.5.19 made
  Moodle's grading manager emit a `debugging()` notice ("Components supporting
  advanced grading should be updated to implement the component_gradeitems
  class") whenever a module instance was created — which PHPUnit treats as a
  failure, so every test that created an activity errored. Adding
  `mod_vimipad\grades\gradeitems` (mapping grade item 0 to the `submissions`
  area) satisfies the grading manager and silences the notice. No behaviour
  change to the gradebook item itself.

## 0.5.20 (2026072700) — grading through the rubric

- **Grade with the rubric / marking guide.** When an advanced grading method is
  active, the submission's grading detail now shows the editable rubric (a
  moodleform grading element); saving derives the grade from the rubric filling
  and stores it as the grade. The filling is remembered per submission and
  grader (new `vimipad_gradeinstance` table, itemid = snapshot) so re-opening a
  grade shows the previous filling.
- **Additive and safe by design.** The rubric path engages *only* when a method
  is defined; with no method active the numeric grade path is unchanged. Privacy
  covers the new table (metadata, per-user and context deletion via cleanup).
- **Known limitation:** advanced-grading rubric fillings are not yet included in
  course backup/restore (the numeric grade and gradebook value are). Backup of
  the grading area is a later, separately tested step.
- Needs verification on a live instance (the advanced-grading instance lifecycle
  cannot be exercised without one). Run the DB upgrade and clear caches.

## 0.5.19 (2026072699) — advanced grading (define & preview)

- **Core advanced grading enabled.** The activity now declares
  `FEATURE_ADVANCED_GRADING` and a `submissions` grading area, so Moodle's
  standard "Advanced grading" administration appears and teachers can define a
  rubric or marking guide the usual way.
- **Rubric shown in the grading tab.** When an advanced grading method is active,
  its definition is rendered read-only in the submission's grading detail as a
  reference while grading.
- Grading *through* the rubric (storing the rubric filling and deriving the
  grade from it) is the next step — it needs a stored grading-instance reference
  (a small schema addition) and the grading-form submit lifecycle. For now the
  numeric grade still records the grade.

## 0.5.18 (2026072698) — grading in the tab + CI fixes

- **Grading moved into the Grading tab.** Selecting a submission now opens the
  full grading detail inside the tab — read-only snapshot, existing annotations,
  teacher-visible journal, add-annotation form, AI feedback draft and the grade
  form — with a "Back to submissions" link. The logic lives in a reusable
  `grading_panel`; the legacy `grade.php` now redirects to the tab so old links
  keep working.
- **CI fixes.**
  - phpcpd: removed four clones — the consensus externals now share a
    `consensus_context`/`consensus_result` helper, `get_revision_state` reuses
    `get_workspace`'s node/relation mappers, and the submit cut-off/lock/re-read
    is extracted to `snapshot_service::begin_submission` (shared by direct and
    consensus submission). No clones remain.
  - PHPUnit: the reconstruction test used malformed stable ids; it now uses
    valid `node_`/`rel_` ids so the operation replay validates.

## 0.5.17 (2026072697) — assessment architecture & grading metrics

- **Assessment architecture documented.** `docs/design/assessment_architecture.md`
  evaluates the established automatic concept-map assessment methods (Kit-Build
  FMS/SMS, NLP/LLM, graph metrics, reference-free indices, fuzzy weights,
  OpenIE, peer-matrix, and form-specific methods for mindmaps, argument maps,
  causal loops, knowledge graphs) against the plugin's constraints, and fixes
  the hybrid decision: manual workflow, annotations, AI draft, core
  `gradingform` and structure metrics stay fixed in core; automatic scorers
  become a `vimipadassess` subplugin type with a fuzzy-ready (0..1 weighted),
  profile-aware scorer contract and an exchangeable matcher. Staged: grading
  tab → gradingform → assess registry → `reference` scorer → further scorers.
- **Structure metrics in the grading tab.** The submissions list now shows the
  concept/relation counts per submission (batched queries) as a grading aid —
  deliberately an aid only: structural metrics never set a grade on their own.

## 0.5.16 (2026072696) — journal revision viewer

- **See the map as it stood.** Each journal entry now has a "Show editing state"
  button that renders the map read-only as it was at that entry's revision,
  reconstructed from the operation log. The viewer offers both the canvas and
  list views and lays the graph out automatically (past positions are not
  stored).
- Built as an isolated `mod_vimipad/revision` bootstrap that mounts a read-only
  `RevisionViewer` from the editor bundle, kept separate from the editor
  bootstrap so it cannot affect editing. Reuses the canvas and relation-list
  renderers. New `getRevisionState` client call, covered by tests.

## 0.5.15 (2026072695) — journal revision reconstruction (backend)

- **Rebuild a map at a past revision.** New `reconstruction_service` replays the
  operation log up to a target revision to reproduce the exact node/relation
  topology at that point (the log stores server-assigned stable ids, so the
  replay is faithful). Deleted nodes and their relations drop out.
- **Revision captured per journal entry.** Journal entries now record the
  workspace revision at the time of writing (the `revisionref` field is finally
  populated), and each entry shows which revision it refers to.
- **Web service.** `get_revision_state` returns the reconstructed state in the
  same shape as `get_workspace` (read-only, auto-laid-out), with own-vs-foreign
  access control. Covered by a unit test. The in-editor viewer that renders this
  state for an entry follows in the next stage.

## 0.5.14 (2026072694) — consensus UI & notifications

- **Consensus flow in the Journal & submission tab.** For group activities with
  consensus, the submission area now follows the state machine: when open, a
  "Start submission process" button; while voting, a member overview (avatar,
  profile and message links, confirmed/pending badge) with an "I agree" checkbox
  and a "Confirm submission" button — becoming "Submit for grading" for the last
  member — plus a red outline "Cancel process" button. Direct (non-consensus)
  submission keeps its single button.
- **System notifications.** A new `consensus` message provider notifies group
  members when the process is started, cancelled or completed, via Moodle
  messaging (`db/messages.php` + `consensus_notifier`). This carries the
  "liveness" so the overview itself stays server-rendered.

## 0.5.13 (2026072693) — consensus state machine (backend)

- **Explicit consensus state machine.** New `consensus_service` models group
  submission as `open → voting → submitted`, with cancel returning to `open`.
  The state is derived from existing data (a locked workspace is submitted, an
  existing confirmation means voting), so no schema change is needed. Starting
  records the initiator's confirmation, confirming records a member's and
  finalises the snapshot once everyone has, and cancelling clears confirmations.
- **Web services.** Four AJAX functions — `start_consensus`,
  `confirm_consensus`, `cancel_consensus` and `get_consensus_status` — expose the
  machine, returning the state plus a per-member confirmation list. Guards reject
  acting out of turn, acting as a non-member, or when consensus is not enabled.
- The snapshot-creation core is extracted (`snapshot_service::finalize`) and
  shared by direct submission and the completed consensus flow. Covered by unit
  tests. The member overview UI and system messaging follow in the next stage.

## 0.5.12 (2026072692) — fullscreen canvas height fix

- **Fullscreen uses the full height again.** The viewport cap added in 0.5.9
  (`max-height: 60vh`, so the insert bar and journal stay visible) also applied
  in fullscreen, limiting the canvas to about 60% of the screen. Both fullscreen
  rules (native and the fixed-overlay fallback) now reset `max-height`, so the
  canvas fills the screen as intended.

## 0.5.11 (2026072691) — CI fixes for the tabbed UI

- **Behat updated for the new tabs.** The editor's Canvas/List switch is now a
  server tab, so the editor scenario follows the "List" tab link instead of a
  button; the grading scenarios open the "Grading" tab before acting on
  submissions. The "Submissions" heading is restored on that tab.
- **Generator fix.** The test generator resolved the module context via a
  property that isn't present on a raw instance record, breaking the Behat
  "submissions" setup; it now resolves the course module explicitly.
- **CI matrix.** `MOODLE_503_STABLE` is removed from the PHPUnit, Behat and
  release matrices until that branch is cut upstream (its clone currently fails
  before any plugin code runs). It should be re-added once Moodle 5.3 branches.

## 0.5.10 (2026072690) — Journal & submission tab (stage 1)

- **Journal & submission tab.** The tab now renders the workspace journal
  server-side: entries in collapsible, growing time buckets (this week, last
  week, this month, this year, older), each with the author's avatar, profile
  and message links, and date. Owners see their own entries; teachers inspecting
  a learner see the teacher-visible ones.
- **Submit moved out of the editor.** The submit button is gone from the React
  editor and now lives at the top of this tab (own map only); a locked map shows
  a submitted notice. Group consensus still resolves server-side, with a pending
  notice when not all members have submitted.
- Pure bucketing logic extracted to a tested helper (`journal_buckets`). The
  consensus state machine and the per-entry revision view follow in the next
  stages.

## 0.5.9 (2026072689) — editor surface finalised

- **Full-width insert bar.** The submit button no longer sits among the insert
  controls; the concept/relation controls now use the full width.
- **Capped canvas height.** The canvas is limited to a share of the viewport so
  the insert bar and journal stay visible without scrolling the whole page.
- **Submit relocated.** The submit button moves to the foot of the editor for
  now; it becomes part of the dedicated Journal & submission tab in the next
  step (which is also where the group consensus flow will live).

## 0.5.8 (2026072688) — single-view editor tabs + learner inspection

- **Editor tabs removed from React.** The Canvas/List switch that lived inside
  the editor is gone: each view is now driven purely by the surrounding Moodle
  tab, so the editor renders only the view its tab selected.
- **Teacher inspection of learner maps.** In individual mode, a teacher can pick
  a learner from a user selector and view their map read-only and live. The
  `get_workspace` service accepts a target user (grade capability required) and
  resolves it without creating a workspace (`find_for_user`); a learner with no
  map yet shows an empty read-only view.

## 0.5.7 (2026072687) — read-only foreign viewing

- **Read-only live viewing.** When a user opens a map that is not their own
  (in group mode, a group they do not belong to), the editor loads it read-only:
  the API client blocks every state-mutating web-service call at a single choke
  point, the submit/insert/import/journal affordances are hidden, and a notice is
  shown — while polling keeps the view live. `view.php` determines the read-only
  state and passes it to the editor.
- Test tidy-up: the hook render test now uses `React.act` instead of the
  deprecated `react-dom/test-utils` export.

## 0.5.6 (2026072686) — tabbed activity UI (shell)

First step of the reorganised activity surface: the tabs become the primary
structure, rendered server-side directly under the activity heading and menu.

- **Server-rendered tab bar.** `view.php` now presents role-gated tabs (Canvas,
  List, Journal & submission, Grading, Feedback, Tools). The active tab travels
  in the URL alongside the native group selection, so both persist across tabs
  and are shareable. The Canvas and List tabs mount the editor with the matching
  initial view; Grading keeps the submissions list; the remaining tabs are
  placeholders filled by later steps.
- Groundwork only: the deeper editor rework (foreign read-only viewing via a
  user selector, dynamic canvas height, moved submit button) and the Journal,
  Feedback and Tools tab contents follow in subsequent 0.6.x steps.

## 0.5.5 (2026072685) — deadlines & group consensus submission

- **Due & cut-off dates.** Activities can set an optional due date (submissions
  after it count as late) and cut-off date (submissions are blocked after it).
  New `duedate`/`cutoffdate` settings with validation (cut-off not before due).
- **Group consensus submission.** In group mode, an activity can require every
  group member to submit before the shared map is submitted (as with group
  assignments). Each member's readiness is recorded; the snapshot is created
  only once everyone has submitted, and the editor shows a waiting notice while
  consensus is pending. New `requireallteamsubmit` setting and
  `vimipad_submissionintent` table, covered by backup and the privacy provider.

## 0.5.4 (2026072684) — polling bandwidth, hook extraction, test fixes

- **Layout only when changed.** `poll_changes` now sends the layout JSON only
  when it changed since the client last saw it (the client passes back a
  `layoutsince` timestamp and receives `layouttime`), so an unchanged layout is
  no longer re-sent and re-applied on every poll.
- **Reusable dismiss hook.** The export dropdown's outside-click / Escape
  dismissal was extracted from CanvasView into a tested `useDismiss` hook.
- **Query efficiency follow-through and test fixes.** Corrected the completion
  test setup (the min-nodes rule is now enabled at module creation), simplified
  three PHPDoc parameter types that the doc checker could not parse, and removed
  duplicated setup between the two import round-trip tests.

## 0.5.3 (2026072683) — query efficiency (view, report, grade)

- **No more per-row user lookups.** The submissions list (view), the by-user and
  overview tables (report) and the teacher-visible journal (grade) now fetch all
  the user records they need in a single batched query instead of one query per
  row.
- **SQL aggregation for the workspace report.** `workspace_summary` counts
  operations per type and per user with `GROUP BY` queries rather than loading
  every operation row into memory, so the report scales to large workspaces.

## 0.5.2 (2026072682) — layout import, canvas split, polling scale

- **Layout import.** Import now also restores the layout (node positions and
  sizes), remapped onto the freshly assigned stable ids, for both JSON and XML
  exports. Containers/memberships remain out of scope (a dormant schema feature
  nothing yet produces or consumes).
- **Canvas refactor.** The pure label/shape render helpers were extracted from
  CanvasView into `canvas/shapes.tsx` with their own unit tests, continuing the
  behaviour-preserving decomposition begun in 0.5.1.
- **Polling scalability.** `poll_changes` now returns operations in bounded
  batches with a `hasmore` flag (the client advances only to the last received
  operation and re-polls promptly, never skipping a backlog), and expired-lease
  cleanup runs occasionally rather than on every poll.

## 0.5.1 (2026072681) — import (XML, replace), reopen, refactor

- **XML import.** Import now accepts XML exports as well as JSON (the format is
  auto-detected); shared parsing/creation logic with a round-trip test.
- **Import modes.** An import can *append* (default) or *replace* the current
  map; replace removes the existing nodes/relations first, through the operation
  path. A "Replace existing map" checkbox sits next to the import control.
- **Reopen for revision.** A teacher can unlock a submitted workspace from the
  grading page so its owner can edit and submit again; the existing snapshot is
  kept. New `workspace_service::reopen`.
- **Internal refactor.** The pure canvas geometry helpers (node sizing, edge
  boundary points, connector routing) were extracted from CanvasView into
  `canvas/node_geometry.ts` with their own unit tests, trimming the component.

## 0.5.0 (2026072680) — 0.5.x line begins: import

First feature of the 0.5.x line, on top of the fully hardened 0.4.x base.

- **Import.** The counterpart to export: a JSON export document can be imported
  into a workspace. Nodes and relations are appended through the validated
  operation path (so revisions advance and collaborators see them), get fresh
  stable ids, and relations are remapped onto the imported nodes. The whole
  import is atomic. New `import_service`, `mod_vimipad_import_map` external, an
  "Import" control in the editor, and an export→import round-trip test.

## 0.4.22 (2026072679) — 0.4.x feature-complete, hardened + consolidated

Full editor and collaboration (0.4.3–0.4.16): real-time collaborative canvas and
relation list view, undo/redo, automatic layout, canvas overlays and full-screen
view, group/course workspace switcher, export (JSON, XML, SVG, PNG, PDF), an
edit-activity report, a learner journal with a teacher-visible view, annotations
targetable at the whole map or at individual concepts/relations, and an optional
companion-channel link.

Hardening (0.4.17–0.4.21), following an external review:

- **Security:** AI-feedback drafts can only be accepted scoped to their own
  snapshot; a foreign draft can no longer be overwritten.
- **Backup/restore:** activity grade/completion settings, grades (with snapshot
  remap) and journal entries are now backed up and restored; round-trip tested.
- **Privacy:** the provider discovers, exports and deletes/anonymises every
  personal reference it declares (nodes, relations, operations, snapshots,
  annotations, AI feedback, journal, layout, grades, locks), including shared
  contributions.
- **Concurrency:** workspace creation and snapshot submission are serialized via
  the core lock API; layout saves merge per node so concurrent moves no longer
  clobber each other.
- **Grading/completion:** course-wide grades reach all participants; the submit,
  minimum-concepts and graded completion rules resolve the user's workspace
  uniformly across individual, group and course modes.
- **Operation contracts:** every operation payload is validated per type (field
  types, the relation direction enum, relation metadata JSON) and unknown fields
  are rejected.
- **Consolidation (0.4.22):** version metadata aligned across `version.php` and
  `package.json`; README status updated; the diagram-profile list is now sourced
  from the subplugin registry instead of a hard-coded list; the release CI
  workflow unified with the development one (bundle reproducibility, typecheck
  and Jest gates, the bundle-preserving Grunt step) and the Moodle 4.5–5.3 test
  matrix (adding 5.2 and 5.3).

---


## 0.1.0 (2026072500) — Session 001

Initial installable plugin shell with full project infrastructure.

- Activity module skeleton: `version.php`, `lib.php`, `mod_form.php`, `view.php`,
  `index.php`, `db/install.xml` (instance table), `db/access.php` (capability set
  per Pflichtenheft 3.2).
- Settings form: diagram profile (5 MVP profiles), working mode
  (individual/group/course), AI assistance toggle.
- `course_module_viewed` event, view completion tracking.
- Backup/restore (moodle2) for the instance table incl. intro files and
  link en-/decoding.
- Privacy null provider (placeholder; must become a full provider with the first
  user-data table).
- lang/en + lang/de.
- Tests: generator, lifecycle test (create/delete), privacy test.
- Infrastructure from reference stub, renamed and adapted: makefile, phpcs.xml,
  CI workflows (matrix extended to Moodle 5.2 / PHP 8.3), tools/, docs/ session
  workflow, concept documents in `docs/materials/`.

### Doc-Nachtrag (Session 001)

- `docs/materials/erweiterungsideen_bewertung.md`: Wettbewerbsbefund und
  Machbarkeitsbewertung von vier Erweiterungsideen (qtype/datafield/block/
  format, Peer-Review-Phasenmodell, Journal/Backchannel, Mobile/Vollbild)
  samt Architekturkonsequenzen für M1/M2.

### Konventions-Nachtrag (Session 001)

- Architektur: keine local-Plugins; gemeinsamer Code via
  \mod_vimipad\api\* / \mod_vimipad\profile\* (stabil) und
  \mod_vimipad\local\* (intern). Satelliten via dependency.
- README.md auf das Moodle-an-Hochschulen-README-Template umgestellt;
  Template gilt verbindlich für alle künftigen READMEs des Projekts.

## 0.2.0 (2026072600) — Session 002 start: phpcs-Fix + M1 (Datenmodell)

### Fixed
- Alle 12 CI-gemeldeten phpcs-Verstöße behoben: Leerzeile nach
  Klassen-öffnender Klammer (PSR12.Classes.OpeningBraceSpace) in 11 Dateien;
  unnötige MOODLE_INTERNAL-Checks in lib.php und den beiden backup-stepslib-
  Dateien entfernt (moodle.Files.MoodleInternal.MoodleInternalNotNeeded).
  Verifiziert mit moodlehq/moodle-cs (Standard "moodle"): 0 Verstöße.

### Added (M1 — Domänenmodell, Schritt 1)
- Vollständiges Domänenschema in db/install.xml: vimipad_workspace, _node,
  _relation, _container, _membership, _layout, _operation, _snapshot,
  _annotation, _aifeedback sowie _journalentry (Journal-Entscheidung aus
  Session 001). Stable-IDs als eigene Spalten neben den DB-IDs; Soft-Delete
  für Knoten/Relationen; Snapshot-Status als Phasenfeld (0=draft .. 4=returned)
  für den späteren Peer-Review-/Workshop-Workflow.
- db/upgrade.php mit idempotentem M1-Schritt (install_one_table_from_xmldb_file
  je Tabelle) ab Vorversion 2026072500.
- Namespace-Architektur etabliert (keine local-Plugins):
  - \mod_vimipad\local\id\stable_id — interner Generator/Validator.
  - \mod_vimipad\api\ids — öffentliche, stabile Fassade für Satelliten-Plugins.
- Unit-Tests: tests/stable_id_test.php (Generierung, Eindeutigkeit,
  Validierung, Fassade).
- version.php auf 2026072600 / release 0.2.0.

## 0.3.0 (2026072601) — Session 002: Domänen-Services + External Functions + Voll-Privacy

### Added (M1 — Domänenmodell, Schritt 2)
- \mod_vimipad\local\service\workspace_service: löst/erzeugt Workspaces je nach
  Bearbeitungsmodus (individuell/Gruppe/Kurs) mit Capability- und
  Gruppenprüfung; get_state() liefert den vollständigen Bearbeitungsstand.
- \mod_vimipad\local\operation\operation_type: typisierter Operationskatalog
  (node/relation create/update/delete, relation_retarget) mit Payload-
  Schemavalidierung.
- \mod_vimipad\local\service\operation_service: serverautorisierte Anwendung in
  einer DB-Transaktion, Revisionsvergabe, optimistische Konfliktprüfung,
  Operation-Log, Soft-Delete inkl. Kaskade auf angehängte Relationen.
- External Functions mit voller Prüfkette (validate_context, Capability,
  Workspace-Zugehörigkeit, Gruppenzugriff, strikte Parametertypen,
  PARAM_RAW-Payload nur schema-validiert):
  mod_vimipad_get_workspace (read), mod_vimipad_apply_operation (write);
  registriert in db/services.php (ajax=true).
- Voll-Privacy-Provider: metadata (alle nutzerbezogenen Tabellen + core_ai-Link),
  get_contexts_for_userid, get_users_in_context, export_user_data,
  delete_data_for_all_users_in_context, delete_data_for_user,
  delete_data_for_users. Eigene Maps werden gelöscht, Beiträge zu geteilten
  Maps anonymisiert, Journaleinträge (persönlich) gelöscht.
- lib.php: vimipad_delete_instance kaskadiert nun über alle Domänentabellen.
- Sprachstrings EN/DE für Fehlermeldungen und alle Privacy-Metadaten.
- Tests: operation_service_test (Revision, Konflikt, Referenzprüfung,
  Node-Delete-Kaskade, Lock, unbekannter Typ).
- version 0.3.0.

### Verified
- moodlehq/moodle-cs (Standard "moodle"): 0 Fehler, 0 Warnungen über das
  gesamte Plugin.

## 0.4.0 (2026072602) — Session 002: Backup/Restore des Domänenmodells

### Added
- Backup/Restore erfasst nun das vollständige Domänenmodell (nicht mehr nur die
  Instanztabelle): workspaces, nodes, relations, containers, memberships,
  layouts, operations, snapshots, annotations, aifeedback, journalentries.
  User-generierte Inhalte nur bei aktivem userinfo-Setting.
- Korrektes ID-Mapping: Parent-FKs über Verschachtelung, User- und Gruppen-
  Remapping, Vorwärtsreferenz workspace.submittedsnapshotid in after_execute
  aufgelöst. Stable-IDs (source/target/targetstableid/itemstableid) bleiben
  bewusst unverändert — ein Kernvorteil des Stable-ID-Designs bei Restore.
- Tests: backup_restore_test (voller Roundtrip inkl. Snapshot-/Annotation-/
  Vorwärtsreferenz-Prüfung; Backup ohne userinfo).
- version 0.4.0.

### Verified
- moodlehq/moodle-cs (Standard "moodle"): 0 Fehler, 0 Warnungen.

## 0.5.0 (2026072603) — Session 002: M2 Frontend (Editor-Grundgerüst)

### Added
- React/TypeScript-Editor als einbettbare Komponente mit stabilem
  mount(element, config)-Kontrakt und injizierbarem Transport (der Schnitt für
  spätere qtype/datafield-Satelliten). Quellen unter js/src/:
  types, api/service (ApiClient + fetch-Transport gegen Moodle-AJAX),
  store/reducer (optimistische Anwendung), components/EditorApp +
  RelationListView, mount.tsx (Entry + Selbst-Bootstrap aus #vimipad-editor-root).
- MVP-Funktion: Workspace laden, Begriffe/Relationen anlegen, Relationen löschen
  über get_workspace/apply_operation; optimistische UI mit Server-Revisions-
  abgleich und Rollback (Reload) bei Konflikt/Fehler. Listenansicht als
  gleichberechtigte, tastatur- und mobiltaugliche Editoroberfläche; grafischer
  Canvas als Platzhalter (spätere Milestone).
- Dev-Toolchain (nur Entwicklung, NICHT auf Produktion nötig): package.json,
  tsconfig.json, build.mjs (esbuild → js/build/vimipad-editor.js, React
  gebündelt). Vorgebauter Bundle wird mit ausgeliefert.
- view.php lädt den vorgebauten Bundle via $PAGE->requires->js; #vimipad-editor-root
  trägt data-cmid.
- thirdpartylibs.xml dokumentiert das gebündelte React (MIT).
- Editor-Sprachstrings EN/DE.
- Release-/Dev-Trennung: node_modules + Dev-Quellen via .gitattributes
  export-ignore aus dem Release-ZIP ausgeschlossen; js/build bleibt enthalten.
- version 0.5.0.

### Notes
- Uniformer Ladepfad (vorgebauter Bundle + Selbst-Bootstrap) deckt Moodle
  4.5-5.2 ab. Progressive Enhancement zu nativem React-Autoinit (5.2+) und
  Moodle-React-Runtime (5.3) ist ein späterer, additiver Schritt.
- moodle-cs (PHP): 0 Fehler/0 Warnungen. TypeScript: tsc --noEmit sauber.

## 0.6.0 (2026072604) — Session 002: M3 Canvas + Drag-and-drop-Listenansicht

### Added
- Grafischer Canvas (js/src/components/CanvasView): SVG mit verschiebbaren
  Knoten (Pointer-Drag, Speicherung erst bei Drop) und gerichteten Relationen;
  deterministisches Auto-Layout (js/src/graph/autolayout), gespeicherte
  Positionen haben Vorrang.
- Listenansicht mit Retarget: Relation per Dropdown-Editor (tastaturbedienbar)
  ODER per HTML5-Drag-and-drop (Begriffs-Chip auf Subjekt-/Objektzelle) auf ein
  anderes Subjekt/Objekt umhängen. Erfüllt die Accessibility-Pflicht
  „jede DnD-Operation braucht Tastaturalternative". Nutzt die bestehende
  relation_retarget-Operation.
- View-Umschalter Canvas/Liste in EditorApp; gemeinsamer optimistischer State.
- Layout-Persistenz als eigener, NICHT revisionierter Pfad (Designentscheidung:
  Layout ist Präsentationszustand, gehört nicht ins Operation-Log):
  * \mod_vimipad\local\service\layout_service (Upsert je workspace+profile).
  * External Function mod_vimipad_save_layout; get_workspace liefert nun
    profile + layoutjson.
  * \mod_vimipad\local\access: gemeinsamer Edit-Zugriffs-Helper (apply_operation
    und save_layout nutzen ihn; Duplikat aus apply_operation entfernt).
- Privacy: vimipad_layout in Metadaten und Anonymisierung aufgenommen.
- styles.css für Canvas/Listenansicht (von Moodle automatisch geladen).
- Tests: PHPUnit layout_service_test (save/upsert/per-profile);
  JS/Jest reducer.test (Reducer + Auto-Layout, 6 Tests, grün).
- Dev-Toolchain um Jest erweitert (js/tests, nur Entwicklung).
- version 0.6.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc --noEmit sauber. Jest: 6/6 grün.

## 0.7.0 (2026072605) — Session 002: M4 Abgabe, Bewertung, Gradebook, Completion

### Added
- Snapshot-Abgabe: \mod_vimipad\local\service\snapshot_service erzeugt aus dem
  Workspace einen unveränderlichen, normalisierten Snapshot (nodes, relations,
  containers, layout, profile, revision, metadata), sperrt den Workspace und
  setzt submittedsnapshotid. Statusmodell 0..4 (draft..returned).
- External Function mod_vimipad_create_snapshot (Submit-Button im Editor);
  Completion-Update bei Abgabe.
- Teacher Snapshot Viewer + Bewertung als serverseitige Seite grade.php
  (barrierefrei, ohne JS-Abhängigkeit): Snapshot read-only als Relationstabelle,
  Annotationen hinzufügen, Note + Feedback erfassen. view.php zeigt Lehrenden
  eine Abgabenliste, Lernenden den Editor.
- Gradebook-Integration: FEATURE_GRADE_HAS_GRADE aktiviert; lib.php mit
  vimipad_grade_item_update/_delete, vimipad_get_user_grades, vimipad_update_grades;
  Grade-Item bei add/update/delete_instance. \mod_vimipad\local\service\grading_service
  (Upsert vimipad_grade, Push ins Gradebook, Snapshot-Status graded;
  Gruppenmodus: Note an alle Gruppenmitglieder).
- Completion-on-submit: FEATURE_COMPLETION_HAS_RULES; custom_completion-Klasse;
  mod_form add_completion_rules/completion_rule_enabled; Bewertungssektion via
  standard_grading_coursemodule_elements.
- Schema: Instanzfelder grade + completionsubmit; neue Tabelle vimipad_grade;
  upgrade.php-Schritt 2026072605.
- Privacy: vimipad_grade in Metadaten, Lösch- und Anonymisierungspfaden.
- Backup/Restore: vimipad_grade inkl. User-/Grader-Mapping und snapshotid als
  Vorwärtsreferenz (after_execute).
- Frontend: Submit-Button mit Bestätigung; api.createSnapshot.
- Sprachstrings EN/DE; version 0.7.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc --noEmit sauber. Jest 6/6 grün.

## 0.8.0 (2026072606) — Session 002: M5 KI-Feedback (Teacher-in-the-loop)

### Added
- \mod_vimipad\local\service\ai_feedback_service: erzeugt Feedbackentwürfe
  ausschließlich über das Moodle-AI-Subsystem (\core_ai\manager->process_action
  mit generate_text-Action), nie über Provider direkt. Strikt Teacher-in-the-loop:
  Entwurf wird erst nach aktiver Prüfung und Übernahme durch Lehrende zum Feedback.
  * build_prompt: datenminimierter Prompt (Aufgabe, Profil, kompakte
    Relationstabelle, Punkte, Lehrernotizen, Zieltonalität, explizites
    Halluzinationsverbot); KEINE Lernenden-Namen/-IDs, keine Stable-IDs.
  * generate_text: defensiver Aufruf mit Fehlerbehandlung; extrahiert
    generatedcontent + Provider-Info aus get_response_data().
  * store_draft/accept_draft/get_latest; Prompt-Speicherung nur bei Admin-Opt-in.
  * is_available (core_ai vorhanden, Site-Setting, Aktivitäts-Toggle, useai);
    policy_accepted (AI-User-Policy via \core_ai\manager::get_user_policy_status).
- grade.php: KI-Sektion (Entwurf generieren mit Notizen, editieren, übernehmen);
  übernommenes Feedback belegt das Feedback-Feld der Bewertung vor.
  Alle KI-Aktionen capability- (useai), context- und sesskey-geprüft, Policy-Gate.
- settings.php: Admin-Schalter enableai (global) und storeprompts (Prompt-
  Speicherung, Default aus).
- Sprachstrings EN/DE inkl. Prompt-Bausteine; version 0.8.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc/Jest unverändert grün.
- AI-API gegen moodledev.io/4.5 verifiziert (process_action → response_base,
  get_response_data, AI-User-Policy at point of use).

### MVP-Status
Der MVP-Kernworkflow ist damit vollständig: Bearbeiten (Canvas + Liste, DnD) →
Abgeben (Snapshot) → Bewerten (Annotation + Note + Gradebook) → KI-Feedback.

## 0.8.1 (2026072607) — Bugfix: Editor wurde nicht geladen

### Fixed
- Der React-Editor erschien nicht: view.php band das Bundle mit
  $PAGE->requires->js(url, true) (in den <head>) NACH dem bereits ausgegebenen
  Header ein — Moodle verwarf das Head-Script, der Platzhalter blieb stehen.
  Fix: js()-Registrierung vor $OUTPUT->header(), ohne inhead-Flag.
- Editor wird jetzt auch Lehrenden angezeigt (Vorschau unter der Abgabenliste),
  nicht nur Lernenden — erleichtert Test/Abnahme.
- Mount robuster: Fehler beim Start werden sichtbar im Platzhalter angezeigt
  (statt stummem Verbleib); fehlende data-cmid wird gemeldet.
- Fallback-Strings vervollständigt (Tab-Labels etc. wurden zuvor als rohe Keys
  angezeigt). Editor liest Moodle-Strings via M.str.mod_vimipad
  (strings_for_js in view.php), mit englischem Fallback → DE-Lokalisierung wirkt.

### Verified
- Bundle real gegen jsdom getestet: montiert, ersetzt Platzhalter, rendert
  Canvas/Tabs/Knoten; DE-Strings werden aufgelöst. moodle-cs 0/0, tsc/Jest grün.

## 0.8.2 (2026072608) — CI-Härtung: alle lokalen Checks grün

### Fixed (auf echter Moodle-4.5-Umgebung mit PostgreSQL verifiziert)
- PHPUnit (3 echte Fehler behoben, jetzt 43 Tests / 791 Assertions grün):
  * privacy_test: an den Voll-Provider angepasst (der alte get_reason()-Test
    stammte noch vom Null-Provider); prüft nun get_metadata,
    get_contexts_for_userid, Export und Löschung. Typrobuster Kontext-Vergleich.
  * backup_restore_test: nach dem kanonischen Core-Muster neu geschrieben
    (restore_dbops::create_new_course, MODE_IMPORT, users-Setting NOT_LOCKED +
    set_value); behebt restore_controller_exception cannot_precheck_wrong_status.
- phpcs (moodle, --severity=1): Lang-Strings EN/DE streng nach SORT_STRING
  sortiert (21 Ordering-Warnungen behoben); Formatierung in den neuen Tests
  bereinigt. 0 Fehler / 0 Warnungen.
- phpcpd: Kaskadenlöschung von Workspaces in \mod_vimipad\local\cleanup
  extrahiert; der 20-Zeilen-Klon zwischen lib.php und provider.php ist entfernt
  ("No clones found").
- phpmd: ungenutzten Parameter $revision aus operation_service::mutate() und
  ungenutzte $course-Variablen aus get_workspace/apply_operation/save_layout
  entfernt.

### Verified toolchain
- Echte moodle-plugin-ci 4.5.10 gegen Moodle 4.5.12+ (PostgreSQL 16):
  phplint, phpcs(severity=1), phpmd, phpcpd, phpdoc, validate, savepoints,
  mustache, PHPUnit — alle ohne build-brechende Befunde.

## 0.9.0 (2026072609) — Behat: End-to-End-Absicherung der Kernworkflows

### Added
- Behat-Feature tests/behat/grading.feature (server-gerendert, ohne @javascript):
  Lehrende sehen die Abgabenliste, bewerten einen Snapshot, fügen Annotationen
  hinzu; Lernende sehen die Bewertungsoberfläche nicht.
- Behat-Feature tests/behat/editor.feature (@javascript): der React-Editor
  montiert (Platzhalter verschwindet), Canvas/Listen-Tabs sichtbar, Begriff
  hinzufügen und in der Liste wiederfinden. Läuft in der CI mit Browser.
- Behat-Datengenerator tests/generator/behat_mod_vimipad_generator.php:
  "the following mod_vimipad > submissions exist" seedet einen abgegebenen
  Snapshot ohne den JS-Editor.
- Generator um create_workspace() erweitert (Nodes + optional gesperrter,
  abgegebener Snapshot); PHPUnit generator_test verifiziert den Seed-Pfad.
- version 0.9.0.

### Verified (echte Moodle-4.5-Umgebung)
- Behat-Konfiguration gebaut, beide Features registriert; Dry-run: 6 Szenarien /
  58 Steps, KEINE undefinierten Steps (alle gegen Core-Steps auflösbar).
- Generator-Seed-Pfad real über PHPUnit getestet.
- PHPUnit 44 Tests / 796 Assertions grün. phpcs severity=1: 0/0.
  phpdoc/validate/phpcpd: sauber. Behat-Tags von moodle-plugin-ci erkannt.

### Hinweis
Die @javascript-Editor-Szenarien brauchen Chrome/Selenium und laufen in der CI;
die server-gerenderten Grading-Szenarien sind hier strukturell/step-validiert.
Der Built-in-PHP-Server ließ sich in der Sandbox nicht stabil für eine
Live-Behat-Ausführung halten — die Ausführung erfolgt in der Projekt-CI.

## 0.9.1 (2026072610) — AMD-Architektur für Nicht-React-Teile; Zielspanne 4.5–5.3

### Changed
- Zielspanne korrigiert: supported = [405, 503] (4.5 LTS bis 5.3). Ab Moodle 5.3
  bringt der Core die React-Runtime mit (react_autoinit); 4.5–5.2 nutzen weiter
  den mitgelieferten Editor-Bundle. Gegen moodle/main gegengecheckt (core/import,
  core/component appendToDom/prependToDom, grunt react/esbuild, "external"
  Runtime-Pakete).
- Idiomatische AMD-Architektur für alle Nicht-React-Teile: neues ES6-Modul
  amd/src/init.js (geladen via $PAGE->requires->js_call_amd), das Strings über
  core/str und einen AJAX-Transport über core/ajax auflöst und dann den separat
  gebündelten React-Editor lädt und montiert. amd/build/init.min.js mit Moodles
  echtem Grunt gebaut (reproduzierbar; CI-Diff-Prüfung besteht).
- view.php nutzt js_call_amd statt requires->js + strings_for_js. React-Bundle
  ohne Selbst-Bootstrap; die Initialisierung steuert nun das AMD-Modul
  (saubere Trennung, kein Doppel-Mount).

### Build & Packaging
- makefile: neue Targets `react` (esbuild → js/build), `build` (React + AMD),
  `lint-react` (tsc --noEmit), `test-react` (Jest); `amd` (Grunt → amd/build)
  greift jetzt, da amd/src gefüllt ist. `fix` baut beide Frontends, `check`
  prüft tsc + Jest + PHPUnit.
- .gitattributes: amd/src, js/src, js/tests und die gesamte Toolchain als
  export-ignore. Im Release verbleiben nur die Laufzeit-Artefakte
  amd/build/ und js/build/.

### Verified (echte Moodle-4.5-Umgebung)
- Grunt ESLint auf amd/src: sauber. Grunt amd-Build reproduzierbar (identisch).
- jsdom: AMD-getriebener Mount montiert den Editor, DE-getString wirkt,
  kein Auto-Mount ohne AMD-Aufruf.
- PHPUnit 44 Tests / 796 Assertions grün; phpcs severity=1: 0/0.
- makefile-Targets react/amd/build/lint-react/test-react real ausgeführt.

## 0.2.0 (2026072611) — MVP

Nominal-Version auf die MVP-Stufe gesetzt. Versionslogik: 0.2 = MVP (vollständiger
Kernworkflow), 1.0 = fertig getestetes, nutzerfreundliches, stabiles Produkt.
Der interne $plugin->version-Integer steigt weiter monoton (saubere Upgrades).

### Added
- MVP-Integrationstest (tests/mvp_integration_test.php): verifiziert reproduzierbar,
  dass Capabilities und External Functions installiert werden, eine Aktivität
  inkl. Gradebook-Item angelegt wird, die MVP-Feature-Flags greifen und das
  Löschen sauber aufräumt.

### MVP-Stand (vollständig, auf echter Moodle-4.5-Umgebung verifiziert)
- Editor (Canvas + Listenansicht, DnD + Tastaturalternative), idiomatisch über
  AMD (mod_vimipad/init) geladen, React separat gebündelt.
- Abgabe als unveränderlicher Snapshot; Lehrenden-Bewertung (Note, Feedback,
  Annotationen) mit Gradebook- und Completion-Anbindung.
- Teacher-in-the-loop KI-Feedback über das Moodle-AI-Subsystem.
- Vollständige Integration: Gruppen, Gradebook, Completion, Privacy, Backup/Restore.
- Tests: PHPUnit 47/809 grün, Jest 6/6, phpcs severity=1 0/0, AMD reproduzierbar,
  phpdoc/validate/savepoints/mustache sauber, Behat-Kernworkflows (Steps validiert).
- CLI-Site-Install lief erfolgreich durch (Plugin installiert sich sauber).

### Bekannte Grenzen bis 1.0
- @javascript-Behat-Editorszenarien laufen in der CI (Browser erforderlich).
- Roadmap 1.x: Rubric-Bewertung, Annotationen an Knoten/Relationen, React-Grading-UI
  (get_snapshot/save_annotation als External Functions), Canvas-Tastaturmodus,
  profil-spezifische Auto-Layouts, Peer-Review.

## 0.2.1 (2026072612) — Linting- und Build-Fixes

### Fixed
- version.php: zu lange Kommentarzeile (158 > 132 Zeichen) umgebrochen; phpcs
  severity=1 jetzt 0/0.
- makefile lint-react/test-react: riefen `npx tsc`/`npx jest` ohne node_modules-
  Prüfung auf. Ohne vorheriges `npm install` zog npx das falsche Fremdpaket
  (tsc@2.0.4) statt des lokalen TypeScript. Jetzt: node_modules wird bei Bedarf
  installiert und die lokalen Binaries (node_modules/.bin/tsc, .../jest) werden
  direkt aufgerufen — nie wieder ein Fremdpaket-Download.
- makefile lint-mustache: überspringt sauber, wenn kein templates/-Verzeichnis
  existiert (statt "directory not found"-Rauschen).

### Verified
- Frisches Auspacken ohne node_modules: `make lint-react` installiert die echten
  Dev-Abhängigkeiten und der Typecheck läuft grün; zweiter Lauf ohne Neuinstall.
- PHPUnit 47/809 grün, phpcs 0/0, AMD reproduzierbar.

## 0.2.2 (2026072613) — Entwickler-Doku: Verifikationsumgebung

### Added
- docs/dev/moodle-test-environment-setup.md: detaillierte, reproduzierbare
  Schritt-für-Schritt-Anleitung zum Aufsetzen einer echten Moodle-4.5-
  Verifikationsumgebung in der Sandbox (Systempakete, PHP/Locale-Konfig,
  PostgreSQL, Moodle-Clone, config.php, PHPUnit-Env, moodle-cs,
  moodle-plugin-ci, Grunt/AMD, Behat, Frontend) inkl. Fallstricke und
  Schnell-Referenz. Anleitung real durchgespielt und verifiziert.

### Changed
- docs/prompt-templates/sessionstart.txt: neuer verpflichtender Abschnitt E
  „Verifikationsumgebung ZUERST aufsetzen" (verweist auf die Anleitung); frühere
  Abschnitte umbenannt (Ziel = jetzt F). Zielspanne/Entwurfsentscheidung 5 auf
  den aktuellen Stand gebracht (4.5–5.3; React ab 5.3 im Core; AMD für alle
  Nicht-React-Teile).

## 0.2.3 (2026072614) — Bugfix: Behat @javascript (Editor lud nicht im Browser)

### Fixed
- CI-Behat-Fail "Javascript code and/or AJAX requests are not ready after 10
  seconds (mod_vimipad/init)": Ursache war das dynamische Nachladen des React-
  Bundles per injiziertem <script>-Tag. Das lief AUSSERHALB von Moodles JS-
  Tracking, sodass wait_for_pending_js nie auflöste. Zusätzlich ein toter
  onload-Selbstbezug (script.onload = script.onload.bind(...)).
- Fix: Das React-Bundle wird jetzt als benanntes AMD-Modul
  (mod_vimipad/editor_lazy) unter amd/build/ ausgeliefert und im init-Modul per
  require(['mod_vimipad/editor_lazy']) über Moodles Loader geladen — vollständig
  im JS-Tracking. mount.tsx exportiert sauber (default export) statt window-Global.
- build.mjs: baut das Bundle als AMD-Modul (esbuild-IIFE + define-Wrapper) nach
  amd/build/editor_lazy.min.js (kein js/build/ mehr). thirdpartylibs.xml und
  .gitattributes entsprechend angepasst.
- editor.feature: explizite Wartebedingungen ("I wait until 'Add concept'
  'button' exists") für robustes Warten auf den React-Mount.

### Verified
- AMD-Ladepfad (require editor_lazy -> mount) via jsdom: Modul registriert,
  mount() verfügbar, Editor montiert, Strings/Knoten gerendert.
- Grunt: init.min.js reproduzierbar, editor_lazy.min.js (third-party) unangetastet,
  voller grunt-Lauf exit 0. Behat dry-run: 6 Szenarien/59 Steps, keine undefinierten.
- PHPUnit 47/809, phpcs 0/0, tsc/Jest grün.
- HINWEIS: Der eigentliche @javascript-Browserlauf ist nur in der CI möglich
  (kein Browser in der Sandbox; Chromium nur als Snap-Wrapper vorhanden).

## 0.2.4 (2026072616) — Kollaboration Schicht 2: External Functions

### Added
- operation_service::get_operations_since(workspaceid, sincerevision): liefert
  Operationen nach einer Revision (aufsteigend) — Delta-Basis fürs Polling.
- External Functions (db/services.php):
  * mod_vimipad_poll_changes (read): Operationen seit Revision N + aktuelles
    Layout + aktive Leases (Presence) in einem Round-Trip.
  * mod_vimipad_acquire_lock / renew_lock / release_lock (write): Element-Leases.
- classes/external/helper.php: gemeinsame Workspace-/Edit-Access-Validierung und
  Lease-TTL-Lookup (hält die External Functions schlank, vermeidet Duplikate).

### Tested (real, Moodle 4.5 + PostgreSQL)
- collaboration_external_test: 6 Tests — inkl. Kernszenario (B abgewiesen, sieht
  Halter A), Renew nur durch Halter, Release gibt frei, poll liefert Delta+Layout+
  Presence, abgelaufene Leases werden ausgefiltert, Zugriffskontrolle greift.
- operation_service_test um get_operations_since erweitert.
- Gesamt: 68 Tests / 885 Assertions grün. phpcs severity=1: 0/0. validate sauber.

### Offen (nächste Schichten)
- Client (js/src): adaptive Poll-Schleife, Positions-Tweening, Lock-on-drag +
  Heartbeat, visuelle Sperr-/Presence-Anzeige; Jest-Tests.
- Behat: Kollaborations-Workflow (server-prüfbare Teile).

## 0.2.5 (2026072617) — CI-Fix: fehlendes Build-Artefakt + falsche @package-Tags

### Fixed
- CI brach in zwei Schritten ab (install/grunt ignorefiles und phpcs), weil
  .gitignore `amd/build/` komplett ausschloss. Dadurch war das eingebundene
  React-Bundle amd/build/editor_lazy.min.js NICHT im Repository, während
  thirdpartylibs.xml darauf verweist. grunt ignorefiles und der moodle-cs-
  Vendors-Check verlangen aber, dass jeder thirdpartylibs-Pfad existiert
  -> ENOENT / "non-existent path".
  Fix: amd/build/ wird nicht mehr ignoriert; die gebauten Laufzeit-Artefakte
  (init.min.js + editor_lazy.min.js) gehören eingecheckt (der Produktivserver
  hat keine Build-Tools; die CI validiert aus dem Repo).
- tools/fix_phpdoc.php und tools/mustache_check.php trugen noch den @package-Tag
  local_instantcoursecompletion (Copy-Paste-Rest der Vorlage). phpcs plugin
  (ohne tools/-Ausschluss) meldete das als Fehler. Auf mod_vimipad korrigiert.

### Verified
- moodle-plugin-ci phpcs --max-warnings 0: 56 Dateien, 0/0 (thirdpartylibs-
  Pfad existiert, @package korrekt).
- grunt ignorefiles/eslint/amd: exit 0, kein ENOENT.
- git check-ignore bestätigt: amd/build-Artefakte werden getrackt, node_modules/
  package-lock.json bleiben ignoriert.
- Regression: PHPUnit 68/885 grün, phpcs severity=1 0/0, validate sauber.

## 0.2.6 (2026072618) — Fix: External-Test-Basisklasse + phpcpd-Klon

### Fixed
- collaboration_external_test brach mit "Class externallib_advanced_testcase not
  found" ab: Diese Basisklasse liegt in webservice/tests/helpers.php und wird
  NICHT autogeladen. Fix nach Core-Muster: require_once(webservice/tests/
  helpers.php) + use externallib_advanced_testcase (globaler Namespace).
  (Der Fehler blieb bei gefiltertem Lauf verborgen, weil eine andere Testdatei
  die helpers zuvor lud — isolierte Läufe decken das auf.)
- phpcpd-Klon (37 Zeilen) zwischen renew_lock.php und release_lock.php beseitigt:
  die gemeinsame Element-Lock-Parameterdefinition liegt jetzt in
  helper::lock_parameters(); acquire/renew/release_lock delegieren dorthin.

### Verified
- ALLE 12 Testdateien EINZELN (isoliert, wie die CI) grün. Gesamt 68/885 grün.
- phpcpd: No clones found. phpcs severity=1 + moodle-plugin-ci phpcs
  --max-warnings 0: 0/0.

### Prozess-Lehre
- Ab jetzt jede Testdatei auch ISOLIERT laufen lassen (nicht nur --filter),
  um Autoload-Reihenfolge-Abhängigkeiten aufzudecken.

## 0.2.7 (2026072619) — CI: Frontend-Build-Schritt + JS-Lint-Fix

### Fixed
- CI-Blocker (phpdoc/phpcs "Vendors.php: non-existent path ... editor_lazy.min.js"):
  Die thirdpartylibs.xml verweist auf das gebaute React-Bundle amd/build/
  editor_lazy.min.js. Fehlt es im ausgecheckten Repo, bricht JEDER Check ab, der
  die Vendors-Validierung durchläuft — und blockiert damit auch Behat.
  Robuster Fix: Der CI-Workflow baut das Bundle jetzt selbst. In allen vier Jobs
  (lint-php, lint-js, phpunit, behat) läuft direkt nach dem Checkout ein Schritt
  "Build front-end bundle (esbuild → amd/build)" (npm install + node build.mjs)
  im plugin/-Verzeichnis, BEVOR moodle-plugin-ci das Plugin kopiert/prüft. Damit
  ist die Pipeline unabhängig davon, ob das Artefakt eingecheckt ist.
- JS-Lint (ESLint, --max-lint-warnings 0): überflüssige
  "eslint-disable-next-line no-undef" vor require() in amd/src/init.js entfernt
  (require ist in Moodles ESLint-Config bereits als Globale bekannt; die
  ungenutzte Direktive war eine Warnung = Fehler bei max-warnings 0). init.min.js
  neu gebaut.

### Verified
- Bewiesen: OHNE Bundle -> "Vendors.php line 67" (der CI-Fehler); nach dem
  Build-Schritt -> phpdoc/phpcs laufen durch. Build in sauberer Umgebung
  (ohne node_modules) real ausgeführt: Bundle wird erzeugt.
- ESLint auf init.js: 0/0. AMD reproduzierbar. phpcs 56/56. PHPUnit 68/885 grün.
- Behat-Dry-run läuft an (6 Szenarien, keine undefinierten Steps).

## 0.2.8 (2026072620) — editor_lazy.min.js in Auslieferung + Kollaborations-Client (Schicht 3)

### Fixed (CI-Dauerproblem an der Wurzel)
- URSACHE des wiederkehrenden "Vendors.php: non-existent path editor_lazy.min.js":
  Das gebaute Bundle amd/build/editor_lazy.min.js lag in den lokalen
  Referenz-Snapshots, weshalb der Patch-Diff es bei JEDER Auslieferung als
  "bereits vorhanden" wertete und ausschloss — es hat die Zielcodebase nie
  erreicht. Diese Auslieferung enthält editor_lazy.min.js daher EXPLIZIT
  (force-include), zusätzlich zum ohnehin vorhandenen CI-Build-Schritt.
- Mitgeliefert werden auch die Quellen zum Selberbauen: build.mjs, package.json,
  package-lock.json, tsconfig.json und der komplette js/src-Baum. Build:
  "npm install && node build.mjs" erzeugt amd/build/editor_lazy.min.js.

### Added (Schicht 3 — Kollaborations-Client, Logik Jest-getestet)
- collab/adaptive.ts    — adaptives Poll-Intervall (RTT/Aktivität), 8 Tests.
- collab/tween.ts       — weiches Positions-Tweening A→B, 6 Tests.
- collab/poll_client.ts — Poll-Schleife, Deltas, Presence, Heartbeat, 7 Tests.
- collab/lock_client.ts — acquire/renew/release + Heartbeat, 8 Tests.
- collab/apply_remote.ts— gepollte Server-Op → Reducer-Action, 7 Tests.
- collab/use_collaboration.ts — React-Hook, verdrahtet Poll/Lock in den Editor.
- Reducer: updateNode/updateRelation ergänzt (ferne Label-Änderungen).
- EditorApp/CanvasView: Poll-Schleife, Lock-on-drag-start, Presence-Anzeige
  ("wird bearbeitet", fremd-gesperrte Knoten visuell markiert).
- get_workspace liefert collab-Settings (Poll-Intervalle in ms, Lease-Timeout,
  Push-Flags); helper::collab_config() bündelt sie.
- Neuer String editor:beingedited (EN/DE).

### Verified
- Jest 42/42 grün, tsc sauber. phpcs (geänderte PHP) 0/0. PHPUnit 68/885 grün.
  ESLint auf init.js 0/0. init.min.js reproduzierbar. Bundle frisch gebaut.
- HINWEIS: Das visuelle Zwei-Browser-Zusammenspiel (Presence/Locking im Look&Feel)
  ist in der Sandbox nicht verifizierbar; die Logik ist hart getestet, die Optik
  bestätigst du im Browser.

## 0.2.9 (2026072621) — Fix: "No define call" im Debug-Modus (editor_lazy.min.js.map)

### Fixed (Laufzeitfehler in der Moodle-Instanz)
- Fehler "No define call for mod_vimipad/editor_lazy" beim Öffnen des Editors
  im Entwickler-/Debug-Modus (jsrev = -1, cachejs aus). Ursache: Moodles
  lib/requirejs.php liefert die minifizierte amd/build/*.min.js im Debug-Modus
  nur dann direkt aus, wenn eine ".map"-Datei daneben liegt. Fehlt sie, rewritet
  Moodle den Pfad auf amd/src/editor_lazy.js (existiert nicht, da React nicht
  über Grunt gebaut wird) -> leere Antwort -> RequireJS meldet "No define call".
- FIX: build.mjs erzeugt jetzt eine echte Sourcemap (editor_lazy.min.js.map).
  Damit bleibt Moodle im Debug- UND Produktionsmodus auf dem Build-Datei-Zweig
  und lädt das Modul korrekt. Die define-Hülle wird über esbuild banner/footer
  gesetzt, sodass die Sourcemap zum gewrappten Output passt.
- Verifiziert: benanntes define('mod_vimipad/editor_lazy') vorhanden;
  Moodle-Auslieferungslogik nachgestellt (map vorhanden -> Build-Datei);
  requirejs_fix_define lässt das benannte define unangetastet; jsdom-Ladeprobe
  registriert das Modul und findet mount().

### Added (Dokumentation)
- docs/dev/visual-maps-requirements.md: geplante Map-Typen (Familienbaum,
  Evolutionsbäume, Organigramme, Strukturgleichungsmodelle, IT-Architektur-
  Modelle, Programmablaufpläne) und Interaktions-Anforderungen (Verbinden,
  Connection-Darstellung/-Beschriftung, Hover/Auswahl/Menüs, Tastatur,
  Text-Edit). Arbeitsdokument, wird ergänzt.

## 0.2.10 (2026072622) — amd/src/editor_lazy.js mitgeliefert (Debug-Modus, robust)

### Added / Fixed
- amd/src/editor_lazy.js wird jetzt ausgeliefert. Moodle serviert im
  Entwickler-Modus (jsrev = -1) je nach Punktrelease entweder die Build-Datei
  (wenn .map vorhanden) ODER amd/src/editor_lazy.js. Mit BEIDEN Dateien plus
  der .map ist jeder Ladeweg abgedeckt -> "No define call" tritt in keinem
  Szenario mehr auf.
- build.mjs erzeugt nun drei Artefakte: amd/build/editor_lazy.min.js (+ .map,
  minifiziert, Produktion) und amd/src/editor_lazy.js (unminifiziert, lesbar
  im Debug-Modus). Beide tragen die benannte define("mod_vimipad/editor_lazy").
- thirdpartylibs.xml deklariert nun beide Dateien, damit ESLint/phpcs sie
  überspringen.

### Verifiziert
- jsdom-Ladeprobe für amd/src/editor_lazy.js: define korrekt, mount() vorhanden.
- Empirisch geprüft: "grunt amd" baut aus amd/src/editor_lazy.js eine
  FUNKTIONIERENDE amd/build/editor_lazy.min.js (define + mount ok) — falls du
  doch mit Moodles Grunt baust, bricht nichts. Mit "node build.mjs" bleibt die
  esbuild-Version maßgeblich.
- phpcs 56/56 (Vendors-Check mit beiden thirdparty-Pfaden grün), PHPUnit 68/885,
  tsc sauber, Jest 42/42.

### Hinweis
- amd/src/editor_lazy.js, amd/build/editor_lazy.min.js und
  amd/build/editor_lazy.min.js.map gehören zusammen — bitte alle drei einchecken.

## 0.2.11 (2026072623) — elang-Vorlagenreste bereinigen + Versionsnummer

### Fixed
- Das Plugin wurde ursprünglich aus dem elang-Plugin ("Hör-Garten") als Vorlage
  erstellt. Im Zielverzeichnis blieben elang-Reste liegen, die die reinen
  Patch-Auslieferungen (cp, nur Überschreiben) NICHT entfernen konnten:
    - version.php mit @package mod_elang und elang-Versionsnummer 2026072531
    - lang/de/elang.php, lang/en/elang.php (mit @package mod_elang)
  Diese lösten die phpcs-Fehler aus und die falsche/rückläufige Versionsnummer
  ("Höhere Version bereits installiert", da 2026072531 < installiertem
  2026072619).
- Der ViMi-Pad-Code selbst ist und war sauber: alle @package-Tags mod_vimipad,
  keine elang-Referenzen, nur lang/*/vimipad.php. Verifiziert mit exakt dem
  gemeldeten Kommando: phpcs --standard=moodle --severity=1 --ignore=tools/ .
  -> 0 Fehler.
- version auf 2026072623 (0.2.11) angehoben, > installiertem 2026072619, damit
  Moodle sauber aktualisiert.

### Auslieferung
- Diesmal VOLLSTÄNDIGES, sauberes Paket (kein Patch), damit ein Clean-Replace
  alle elang-Reste beseitigt. Wichtig: altes Verzeichnis vorher entfernen (siehe
  README), sonst bleiben Reste wie bei einem Patch liegen.

## 0.2.12 (2026072624) — Canvas-Interaktion: Auswahl, Tastatur, Inline-Edit

### Added (Interaktions-Anforderungen, erste Ausbaustufe)
- Interaktions-Zustandsmodell (canvas/interaction.ts, 12 Tests): Auswahl von
  Node/Connection, ESC demarkiert, Entf löscht Markiertes (nie im Edit-Modus),
  Doppelklick öffnet Inline-Edit.
- Connection-Geometrie (canvas/connection_geometry.ts, 13 Tests): getrennte
  Offsets für parallele Connections, Rand-Anker (rectBorderPoint), Label-Position
  am Kurvenscheitel, Marker-Wahl (Pfeil gerichtet / Knubbel ungerichtet).
- CanvasView verdrahtet: Klick markiert (Node/Connection), ESC/Entf per Tastatur,
  Doppelklick auf Node-Text -> Inline-Edit (Enter bestätigt, Shift+Enter neue
  Zeile), Auswahl-Hervorhebung, Connection-Beschriftung mit weißer Outline.
- EditorApp: node_delete, node_update (Umbenennen), relation_update verdrahtet.
- Reducer: updateNode/updateRelation (bereits vorhanden) genutzt.

### Verified
- Jest 67/67 (inkl. 25 neue), tsc sauber, stylelint sauber, Mount-Rauchtest ok.
- HINWEIS: Optik/Look&Feel der Interaktion im Browser zu bestätigen; Logik hart
  getestet.

## 0.2.13 (2026072625) — CI/Build-Architektur bereinigt (Grunt-Konflikt behoben)

### Fixed (Ursache der wiederkehrenden CI-Fehler)
- amd/src/editor_lazy.js ENTFERNT. amd/src ist Moodles eigenes AMD-Quell-
  Verzeichnis; Moodles "grunt amd" (rollup/babel) verarbeitet ALLES darin.
  thirdpartylibs.xml steuert nur die Lint-Ignores, entfernt die Datei aber NICHT
  aus der Rollup-Eingabemenge. Das dort platzierte esbuild-Bundle wurde daher von
  Grunt erneut verarbeitet und amd/build/editor_lazy.min.js überschrieben — der
  eigentliche Auslöser der CI-Fehlerkette (Grunt-Fehler, durch continue-on-error
  verdeckt, danach "Bundle fehlt" bei PHPDoc).
  Empirisch bestätigt: nach dem Entfernen lässt "grunt amd" editor_lazy.min.js
  unverändert und baut nur init.min.js aus init.js.
- build.mjs erzeugt nur noch amd/build/editor_lazy.min.js (+ .map); der zweite
  (dev-)Build nach amd/src ist entfernt. Kommentare korrigiert.
- thirdpartylibs.xml deklariert nur noch amd/build/editor_lazy.min.js.
- Developer-Mode funktioniert weiterhin über die .map: lib/requirejs.php liefert
  die Build-Datei direkt aus, wenn die .map daneben liegt — amd/src/editor_lazy.js
  ist dafür nicht nötig (und war der Konfliktverursacher).

### CI
- Grunt-Step: continue-on-error entfernt — echte Fehler brechen die CI jetzt ab,
  statt verdeckt zu werden.
- Redundante "Build front-end bundle"-Steps aus allen Jobs entfernt (das Bundle
  ist committet und wird von Grunt nicht mehr angefasst).
- Neuer Job "Bundle reproducibility": npm ci + node build.mjs +
  git diff --exit-code stellt sicher, dass das committete Bundle exakt dem
  aktuellen esbuild-Output entspricht. Build ist deterministisch (verifiziert).

### Verified
- grunt amd fasst editor_lazy nicht mehr an; init.min.js reproduzierbar.
- phpcs 0 Fehler (Vendors-Check grün), PHPUnit 68/885, Jest 67/67, tsc sauber,
  Define-Ladeprobe (mount vorhanden), Build reproduzierbar (npm ci == Commit).

## 0.2.14 (2026072626) — CI: mpc-grunt-Löschverhalten umgangen + npm-ci-Lockfile

### Fixed — die WAHRE Wurzel der gesamten CI-Fehlerhistorie
- Im mpc-Quellcode (GruntCommand.php) nachgewiesen und lokal exakt reproduziert:
  "moodle-plugin-ci grunt" LÖSCHT vor dem amd-Task absichtlich amd/build/
  (um Reproduzierbarkeit aus amd/src zu prüfen). Damit verschwindet das
  committete esbuild-Bundle editor_lazy.min.js. Grunts amd-Task beginnt mit
  "ignorefiles", das jeden thirdpartylibs.xml-Pfad per fs.statSync prüft ->
  ENOENT -> Abbruch. Die anschließende Vendors-Prüfung wirft eine Exception,
  wodurch mpc sein Plugin-Backup NIE zurückspielt -> das Bundle bleibt gelöscht
  -> phpdoc und alle Folgeschritte scheitern am "fehlenden" File, obwohl es
  committet war. Ein Neu-Bauen VOR mpc grunt kann das prinzipbedingt nicht
  lösen — mpc grunt löscht die Datei danach wieder.
- FIX im Workflow (lint-js): der amd-Task läuft jetzt DIREKT über
  "npx grunt amd --files=mod/vimipad/amd/src/init.js" (keine Vorlöschung,
  beschränkt auf das echte Moodle-AMD-Modul init.js). gherkinlint/stylelint
  laufen weiter über "moodle-plugin-ci grunt --tasks ..." (diese Tasks löschen
  kein Build-Verzeichnis). Davor/danach "test -f"-Wächter, die sofort zeigen,
  falls je wieder ein Schritt das Bundle entfernt.
- Empirisch verifiziert: alte Sequenz reproduziert exakt den CI-Fehler
  (ENOENT + Vendors + Datei weg); neue Sequenz läuft end-to-end grün und das
  Bundle bleibt byte-identisch erhalten.

### Fixed — npm ci (zweites, unabhängiges Problem)
- package-lock.json stand in .gitignore und fehlte daher im CI-Checkout ->
  "npm ci" bricht ab (EUSAGE). .gitignore-Eintrag entfernt; das Lockfile liegt
  dem Paket bei. WICHTIG beim Committen: einmalig
      git add -f package-lock.json
  falls Git es wegen der alten Regel noch ignoriert. npm ci bleibt der richtige
  Befehl (reproduzierbare Toolchain-Versionen).
- Bundle-Reproduzierbarkeits-Job auf Node 22 (Node 20 ist deprecated).

### Verified
- Neue CI-Sequenz lokal end-to-end: bundle-installed-Check, npx grunt amd
  (init.js gebaut, editor_lazy unangetastet), bundle-survived-Check, mpc grunt
  gherkinlint/stylelint grün, phpdoc ohne Vendors-Fehler, npm ci + build ==
  committeter Stand. Jest 67/67, PHPUnit 68/885, tsc sauber.

## 0.2.15 (2026072627) — Behat: echter Feature-Bug behoben + Poll-Guard

### Fixed
- editor.feature wartete mit `I wait until "Add concept" "button" exists` auf
  einen Button, den es NIE gab: "Add concept" ist die Fieldset-Legende, der
  Button heißt "Add". Der Schritt lief in einen Timeout -> Behat-Fehlschlag.
  Das fiel bisher nie auf, weil der Behat-Job via needs:[lint-php,lint-js] bis
  0.2.14 nie startete (CI brach vorher am Bundle-Problem ab). Jetzt läuft Behat
  erstmals. Fix: Warte auf die existierende `"Add concept" "fieldset"` (beide
  Szenarien). "fieldset" ist ein gültiger Moodle-Behat-Partial-Selektor.
- Kollaborations-Poll-Schleife startet unter Behat nicht mehr
  (M.cfg.behatsiterunning). Die Szenarien sind Einzelnutzer-Tests; dauerhafte
  Hintergrund-fetch-Aufrufe wären reine Flakiness-/Last-Quelle und könnten die
  Seiten-Stabilitätserkennung stören. Locking (beginEdit/endEdit) bleibt aktiv.

### Verified (statisch, da @javascript nur in CI/Chrome läuft)
- Behat-Init erfolgreich (Moodle-Install ok), Dry-Run 6 Szenarien/59 Steps/0
  undefiniert. grading.feature vollständig gegen die UI geprüft: "Submissions",
  "Sam Student", "Submitted" (snapshotstatus_1), "View and grade",
  "View and grade snapshot", Feld "Grade (out of 100)" (grade-Default 100),
  "Feedback", "Save grade", "Grade saved.", Feld "Annotation" (for/id-verknüpft),
  "Add", "Annotation added." — alle Strings/Verknüpfungen stimmen.
- Jest 67/67, tsc sauber, Bundle reproduzierbar.
- HINWEIS: Die @javascript-Editor-Szenarien laufen nur in echtem Chrome (CI);
  Logik/Strings sind statisch verifiziert, das visuelle Verhalten bestätigt der
  CI-Lauf bzw. der Browser.
