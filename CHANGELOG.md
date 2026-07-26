# Changelog — mod_vimipad (ViMi Pad)

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
