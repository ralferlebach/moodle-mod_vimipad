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
