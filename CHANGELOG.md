# Changelog — mod_vimipad (ViMi Pad)

> **Versioning note.** The authoritative version is always `version.php`
> (`$plugin->release` / `$plugin->version`). Some early Session-002 entries below
> used an exploratory 0.5.0–0.9.1 numbering that was later reset to the 0.2.x
> line; those entries are kept for historical reference only. The current
> release is **0.5.17** (2026072697).

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
