# Session 001 — Konzeptanalyse, Machbarkeit, installierbare Plugin-Hülle

## Ziel
`mod_vimipad` (ViMi Pad — Visual Mind Pad) als installierbare Hülle mit
vollständiger Projektinfrastruktur anlegen, auf Basis des Infrastruktur-Stubs
aus `local_instantcoursecompletion` und der Konzeptdokumente
(Lastenheft/Pflichtenheft, Blueprint, Roadmap) in `docs/materials/`.

## Fixierte Entscheidungen
- **Komponentenname `mod_vimipad`** (Produktname), nicht `mod_knowledgemap` aus
  den Konzeptdokumenten. Umbenennung ist jetzt trivial, später teuer — bei
  Bedarf in Session 002 final bestätigen. Subplugin-Präfixe entsprechend
  `vimipadprofile_*`, `vimipadgrade_*`, `vimipadexport_*`, `vimipadanalytics_*`.
- **Mindestversion Moodle 4.5 LTS** (2024100700), Zielspanne 4.5–5.2,
  `supported`-Obergrenze wird auf 503 angehoben, sobald 5.3 released und in CI ist.
- **React-Basis ist Moodle 5.2, nicht erst 5.3**: React-Mustache-Helper,
  Autoinit und Build-Tooling sind seit 5.2 (April 2026) in Core
  (MDL-87765, MDL-87759). 5.3 (Code Freeze 24.08.2026) verbessert dies weiter.
  Legacy-AMD-Bundle für 4.5–5.1 aus demselben Quellcode.
- **Kein Zusatzserver**: Kollaboration MVP über Operation Log + AJAX + Polling.
- **KI nur über Moodle-AI-Subsystem**, Teacher-in-the-loop, Entwurf ≠ Feedback.
- **FEATURE_GRADE_HAS_GRADE vorerst false** — wird mit dem Grading-Service
  (Meilenstein M4) aktiviert, zusammen mit grade_item-Callbacks.
- Privacy: Null-Provider nur solange keine Nutzerdaten-Tabellen existieren.

## Geliefert
- Installierbares Aktivitätsmodul: version.php, lib.php (add/update/delete,
  supports), mod_form.php (Profil, Modus, KI-Toggle), view.php (Event,
  Completion, Editor-Platzhalter `#vimipad-editor-root`), index.php.
- db/install.xml (Tabelle `vimipad`), db/access.php (10 Capabilities gem.
  Pflichtenheft 3.2).
- Event `course_module_viewed`, Backup/Restore moodle2, Privacy-Null-Provider.
- lang/en + lang/de vollständig für den Hüllenumfang.
- Tests: Generator, lib_test (create/delete), privacy_test.
- Infrastruktur: makefile, phpcs.xml, .phpcsignore, .gitattributes, .gitignore,
  tools/, CI-Workflows mit Matrix 4.5/5.0/5.2 × PHP 8.1–8.3 × MariaDB/PgSQL.
- Konzeptdokumente nach docs/materials/ übernommen.

## Offen (Session 002+)
- Namensentscheidung vimipad vs. knowledgemap final bestätigen.
- M1: Domänentabellen (workspace, node, relation, container, membership,
  layout, operation, snapshot) + install.xml + Upgrade-Pfad.
- M1: workspace_service + operation_service (serverautorisierte Operationen,
  Revisionen), External Functions mit Capability/Group/sesskey-Prüfung.
- Privacy-Provider auf Voll-Provider heben, sobald erste Nutzerdaten-Tabelle da.
- Frontend-Toolchain (Vite/esbuild → ESM- und AMD-Legacy-Build) im Repo, nicht
  auf Produktivservern.

## CI-Matrix
Moodle 4.5 / 5.0 / 5.2 × PHP 8.1–8.3 (versionsgerecht excluded) × MariaDB/PostgreSQL.

## Nachtrag (gleiche Session): Wettbewerbsvergleich und Erweiterungsideen

- Wettbewerbsanalyse durchgeführt (mod_mindmap, mod_advmindmap,
  qtype_conceptmap, datafield/atmin, format_mindmap, block_mymindmap_overview):
  keine gepflegte Konkurrenz mit Bewertungsworkflow; Befund und Lehren in
  docs/materials/erweiterungsideen_bewertung.md, Abschnitt 0.
- Vier Erweiterungsideen bewertet und dokumentiert
  (docs/materials/erweiterungsideen_bewertung.md):
  qtype/datafield/block/format-Ableger, Peer-Review-Phasenmodell,
  Journal/Backchannel, Mobile/Vollbild.
- Neue Architekturentscheidungen für M1/M2 (siehe Dokument, Abschnitt 5):
  1. Editor als einbettbare Komponente (mount()-API, Persistenz-Adapter).
  2. Journaltabelle im M1-Datenmodell vorsehen (inkl. Privacy).
  3. Abgabe-Statusmodell M4 phasenfähig (Workshop-Pattern).
  4. Listenansicht touch-first; Browser-Vollbildmodus ab M2.
- Backlog aus Wettbewerb: Sichtbarkeitsoption "eigene Map, Peers einsehbar";
  Tastaturkonventionen Insert/Delete; Verzeichnistext-Positionierung.
- Ausgeschlossen bestätigt: eigener Echtzeit-Audiokanal (No-Server),
  Offline-Sync.

## Nachtrag 2 (gleiche Session): Architektur- und Doku-Konventionen fixiert

- **Keine local-Architektur.** Gemeinsamer Code für Satelliten-Plugins
  (qtype, datafield, Review) wird von mod_vimipad per Namespace
  bereitgestellt; Satelliten deklarieren dependency auf mod_vimipad.
  Konvention: \mod_vimipad\api\* und \mod_vimipad\profile\* = öffentliche,
  stabile API; \mod_vimipad\local\* = intern, keine Stabilitätsgarantie.
  Die frühere Option local_vimipadcore ist ersatzlos gestrichen
  (erweiterungsideen_bewertung.md entsprechend angepasst).
- **README-Konvention:** Alle READMEs (Kernplugin und künftige
  Satelliten/Subplugins) richten sich am Template von Moodle an Hochschulen
  e.V. aus: https://github.com/moodle-an-hochschulen/moodle-readmetemplate
  README.md des Kernplugins wurde vollständig auf dieses Template umgestellt
  (Abschnitte: Requirements, Motivation, Installation, Usage & Settings,
  Capabilities einzeln, Scheduled Tasks, How it works/Pitfalls, Theme
  support, Repositories, Bug reports, Feature proposals, Release support,
  Translating/AMOS, RTL, Maintainers, Copyright).

## Nachtrag 3 (Session 002 start): phpcs-Fix und M1-Datenmodell

- CI-phpcs-Report vollständig abgearbeitet; mit echtem moodlehq/moodle-cs
  (Standard "moodle") lokal auf 0 Verstöße verifiziert (phpcbf-Autofix +
  Wurzelbehebung MOODLE_INTERNAL).
- M1 Schritt 1 geliefert: Domänenschema (11 neue Tabellen inkl. journalentry),
  upgrade.php, Namespace-Architektur (\local intern, \api stabil),
  stable_id-Utility + Tests. version 0.2.0.
- Offen (M1 Fortsetzung): workspace_service + operation_service unter
  \mod_vimipad\local\ (serverautorisierte Operationen, Revisionsvergabe);
  External Functions (get_workspace, apply_operation) mit
  Capability/Group/sesskey/Schema-Validierung; danach Voll-Privacy-Provider
  (sobald erste Nutzerdaten geschrieben werden).

## Nachtrag 4 (Session 002): Services, External Functions, Voll-Privacy

- M1 Schritt 2 abgeschlossen: workspace_service + operation_service (\local),
  operation_type mit Schemavalidierung, External Functions get_workspace/
  apply_operation (db/services.php), Voll-Privacy-Provider. lib.php-Kaskade.
  EN/DE-Strings. operation_service_test. version 0.3.0.
- phcs/phpcbf mit echtem moodlehq/moodle-cs: 0 Fehler/0 Warnungen.
- Bewusst noch offen (Folgeschritte):
  * Prüfung/Anpassung: get_course_and_cm_from_cmid-Nutzung in External
    Functions gegen 4.5-API bestätigen (CI).
  * create_snapshot/get_snapshot + Grading-Anbindung (M4); dann
    FEATURE_GRADE_HAS_GRADE aktivieren.
  * Backup/Restore um Domänentabellen erweitern (aktuell nur Instanztabelle).
  * Frontend (M2): Editor-Mount + Services gegen die zwei External Functions.

## Nachtrag 5 (Session 002): Backup/Restore Domänenmodell

- Backup/Restore auf alle Domänentabellen erweitert (größte verbliebene
  Serverlücke geschlossen). ID-/User-/Gruppen-Mapping, submittedsnapshotid als
  Vorwärtsreferenz in after_execute aufgelöst. Roundtrip-Test. version 0.4.0.
- Serverkern damit konsistent (Datenmodell + Services + External Functions +
  Privacy + Backup/Restore). Nächster großer Block: M2 Frontend
  (Editor-Mount + Service-Layer gegen get_workspace/apply_operation).
- Weiterhin offen: create_snapshot/get_snapshot + Grading (M4);
  FEATURE_GRADE_HAS_GRADE dann aktivieren.

## Nachtrag 6 (Session 002): M2 Frontend-Grundgerüst

- React/TS-Editor mit mount()-Kontrakt + injizierbarem Transport; ApiClient
  gegen get_workspace/apply_operation; optimistische UI + Konflikt-Rollback;
  Listenansicht funktional, Canvas als Platzhalter. esbuild-Bundle real gebaut
  (js/build/vimipad-editor.js, React gebündelt, ~150 KB). view.php lädt Bundle.
  thirdpartylibs.xml. EN/DE-Editorstrings. Dev/Release-Trennung. version 0.5.0.
- Bewusst offen / nächste Schritte:
  * Grafischer Canvas (Knoten/Relationen zeichnen, Auto-Layout) — M2/M3.
  * Drag-and-drop in der Listenansicht + Tastaturalternativen (M3).
  * i18n: getString-Anbindung an Moodle-Strings statt Fallback-Map.
  * Progressive Enhancement: nativer React-Autoinit (5.2+), Runtime (5.3).
  * JS-Unit-Tests (reducer/api) via Jest — separater Dev-Schritt.
  * Erst-CI bestätigt: get_course_and_cm_from_cmid in External Functions,
    Backup/Restore-Roundtrip, Asset-Laden via requires->js.

## Nachtrag 7 (Session 002): M3 Canvas + DnD-Listenansicht

- Canvas (SVG, verschiebbare Knoten, Auto-Layout), Listenansicht-Retarget per
  Dropdown UND DnD mit Tastaturalternative, View-Tabs. Layout als eigener
  nicht-revisionierter Pfad: layout_service + save_layout + access-Helper;
  get_workspace liefert profile/layoutjson. Privacy um layout ergänzt.
  PHPUnit layout_service_test, Jest reducer.test (6/6). styles.css. version 0.6.0.
- Designentscheidung fixiert: Layout ≠ Operation-Log (keine Revisionskonflikte
  beim Verschieben). In Session-Doku als Architekturprinzip vermerken.
- Offen / nächste Schritte:
  * Node-Retarget/Reposition auch per Tastatur direkt am Canvas (aktuell
    Canvas = Pointer; Tastaturpfad läuft über die Listenansicht).
  * Auto-Layout-Strategien je Profil (radial/hierarchisch) — M3-Ausbau.
  * i18n: getString gegen Moodle-Strings statt Fallback-Map.
  * M4: Snapshot-Abgabe/Bewertung/Gradebook (dann FEATURE_GRADE_HAS_GRADE).

## Nachtrag 8 (Session 002): M4 Abgabe/Bewertung/Gradebook/Completion

- snapshot_service (unveränderliche Abgabe + Lock), grading_service
  (vimipad_grade → Gradebook, Gruppennote an Mitglieder), create_snapshot
  External, grade.php (Teacher Viewer + Annotation + Note, serverseitig),
  view.php Lehrenden-Abgabenliste. Gradebook-Callbacks in lib.php,
  FEATURE_GRADE_HAS_GRADE aktiv. Completion-on-submit (custom_completion +
  mod_form). Schema: grade/completionsubmit + vimipad_grade + upgrade.
  Privacy + Backup/Restore erweitert. Submit-Button im Editor. version 0.7.0.
- PHPUnit snapshot_grading_test (Abgabe-Lock, Immutabilität, Gradebook-Push).
- Offen / nächste Schritte:
  * M5: KI-Feedbackentwurf über Moodle-AI-Subsystem (generate_text),
    Teacher-in-the-loop, auf Snapshot+Rubric+Notizen; aifeedback-Tabelle steht.
  * Rubric/Advanced grading statt einfacher Punktzahl (Roadmap 1.x).
  * Annotationen an einzelne Knoten/Relationen (aktuell map-Ebene in grade.php).
  * get_snapshot/save_annotation als External Functions für eine spätere
    React-Grading-Oberfläche (aktuell serverseitig gelöst).

## Nachtrag 9 (Session 002): M5 KI-Feedback — MVP komplett

- ai_feedback_service (nur core_ai/generate_text, Teacher-in-the-loop,
  datenminimierter Prompt, Halluzinationsverbot, Policy-Gate), grade.php
  KI-Sektion (generieren/editieren/übernehmen, Feedback-Vorbelegung),
  settings.php (enableai/storeprompts). PHPUnit ai_feedback_test
  (Prompt-Datenminimierung, Store/Accept, Prompt-Speicher-Opt-in). version 0.8.0.
- AI-API gegen offizielle 4.5-Doku verifiziert.
- MVP-Kern vollständig (M1–M5). Offene/nächste Punkte:
  * Rubric/Advanced Grading; Reference Model (Roadmap 1.1/Premium).
  * get_snapshot/save_annotation als External Functions + React-Grading-UI.
  * Annotationen an Knoten/Relationen (derzeit Map-Ebene).
  * Erst-CI-Lauf bestätigt: get_course_and_cm_from_cmid in External Functions,
    Backup/Restore-Roundtrip, core_ai-Response-Schlüssel (generatedcontent),
    Behat-Kernworkflows.
  * i18n des React-Editors an Moodle-Strings; progressive React-Autoinit (5.2+).

## Nachtrag 10 (Session 002): Bugfix Editor-Loading

- Ursache gefunden: $PAGE->requires->js(url, true) nach Header -> Head-Script
  verworfen. Fix: vor Header, ohne inhead. Editor auch für Lehrende sichtbar.
  Mount mit sichtbarer Fehlerausgabe. Fallback-Strings komplettiert +
  Moodle-String-Anbindung (strings_for_js / M.str). version 0.8.1.
- Mounten mit jsdom real verifiziert (Platzhalter ersetzt, Canvas/Tabs/Knoten,
  DE-Lokalisierung). Damit ist die frühere "nur statisch geprüft"-Lücke beim
  Frontend für den Mount-Pfad geschlossen.

## Nachtrag 11 (Session 002): CI-Härtung — lokale Fails behoben

- Vollständige Moodle-4.5-Testumgebung aufgebaut (PostgreSQL, PHPUnit,
  moodle-plugin-ci 4.5.10, aktuelle moodle-cs). Erstmals ECHTE Verifikation.
- 3 PHPUnit-Fails behoben: privacy_test (Voll-Provider + Typvergleich),
  backup_restore_test x2 (Core-Muster restore_dbops::create_new_course).
  -> 43 Tests / 791 Assertions grün.
- phpcs severity=1: Lang-Ordering (SORT_STRING) + Test-Formatierung -> 0/0.
- phpcpd-Klon via \mod_vimipad\local\cleanup entfernt.
- phpmd: unused $revision / $course entfernt.
- phpdoc/validate/savepoints/mustache: sauber. version 0.8.2.
- Damit ist die früher offene "nur statisch geprüft"-Lücke geschlossen: die
  gesamte Serverlogik ist jetzt real gegen ein laufendes Moodle getestet.

## Nachtrag 12 (Session 002): Behat-Kernworkflows

- Behat-Features grading.feature (non-JS) + editor.feature (@javascript);
  Behat-Datengenerator (submissions) + generator->create_workspace();
  PHPUnit generator_test. version 0.9.0.
- Real verifiziert: Behat-Config gebaut, 6 Szenarien/58 Steps, keine undefinierten
  Steps; Seed-Pfad per PHPUnit; 44 Tests grün; phpcs 0/0.
- Offen: Live-Ausführung der @javascript-Szenarien (braucht Browser -> CI).
  Nächste Produktschritte weiterhin: Rubric-Bewertung, Annotationen an
  Knoten/Relationen, React-Grading-UI (get_snapshot/save_annotation External).

## Nachtrag 13 (Session 002): Version 0.2.0 (MVP) + MVP-Restarbeiten

- Nominal-Version auf 0.2.0 gesetzt (MVP; 1.0 = fertig/stabil). version-Integer
  monoton weiter (2026072611).
- MVP-Vollständigkeit geprüft: README (Hochschulen-Template, inhaltlich aktuell),
  LICENSE, CHANGELOG, alle Pflichtdateien/-Strings, monologo, index.php, alle
  lib.php-Callbacks, MOD_PURPOSE, Completion (custom_completion) — vollständig.
  Keine fehlenden Strings/Profile, keine TODO/knowledgemap-Reste im Code.
- MVP-Integrationstest ergänzt (Install-Artefakte, Aktivitätsanlage+Gradebook,
  Löschung). CLI-Site-Install lief erfolgreich (PG in Sandbox danach instabil).
- Voll verifiziert: PHPUnit 47/809, phpcs 0/0, AMD reproduzierbar, tsc+Jest.

## Nachtrag 14 (Session 002): Linting- + Build-Fixes (0.2.1)

- version.php Zeile 30 umgebrochen (<=132). makefile lint-react/test-react nutzen
  jetzt lokale Binaries mit node_modules-Guard (kein npx-Fremdpaket tsc@2.0.4).
  lint-mustache überspringt ohne templates/. version 2026072612 / release 0.2.1.
- Verifiziert: frisches make lint-react ohne node_modules -> installiert + grün;
  PHPUnit 47/809, phpcs 0/0, AMD reproduzierbar.

## Nachtrag 15 (Session 002): Kollaboration Schicht 1 — Server-Grundlage

Design bestätigt (Nutzer): Layout an Element-Locking gekoppelt (nur Lease-Halter
verschiebt); Lock bei drag-START (nicht -end) + Presence, damit B sofort sieht,
dass A hält; adaptives Polling clientseitig gemessen, Min/Max als Settings;
vollständige Umsetzung mit Unit+Behat (TDD/BDD) für Nutzer-Abnahme.

[FERTIG + real getestet]
- Schema: vimipad_lock (workspaceid, targettype, targetstableid, userid,
  timeacquired, timeexpires); Unique-Index (workspaceid,targettype,targetstableid)
  = genau ein Lease pro Element. install.xml + upgrade 2026072615. version 2026072615.
- Settings (settings.php + Strings EN/DE): pollinterval(1s), polladaptive(1),
  pollmin(1s), pollmax(10s), leasetimeout(15s), pushenabled(0), pushendpoint('').
- lock_service.php: acquire (frei/eigen/abgelaufen -> ok; fremd+gültig -> refuse
  + Halter melden), renew (nur Halter, Heartbeat), release (nur Halter),
  get_active_leases (Presence), purge_expired. Race-sicher via Unique-Index.
- Privacy: vimipad_lock (userid) deklariert + Strings; cleanup-Kaskade erweitert.
- lock_service_test.php: 10 Tests/19 Assert. GRÜN (inkl. Szenario "B refused,
  sieht Halter", Timeout-Übernahme, Renew, Presence, purge).
- Regression: Gesamt 57 Tests/835 Assert. GRÜN.

[NÄCHSTE SCHICHTEN — offen]
- External Functions: acquire_lock/renew_lock/release_lock/poll_changes
  (poll liefert Operationen seit Revision + Layout-Deltas + aktive Leases).
- Client js/src: adaptive Poll-Schleife (RTT/leer-basiert, min/max), Positions-
  Tweening (A->B ueber ~1 Poll-Periode), Lock-on-drag-start + Heartbeat, visuelle
  Sperr-/Presence-Anzeige. Reducer-Erweiterung.
- Jest: adaptives Intervall, Tweening-Mathematik, Reducer.
- Behat: Kollaborations-Workflow (server-pruefbare Teile; @javascript in CI).
- Push-Client: spaeterer eigener Meilenstein (Settings sind vorbereitet).

## Nachtrag 16 (Session 002): Kollaboration Schicht 2 — External Functions

[FERTIG + real getestet]
- operation_service::get_operations_since (Delta seit Revision, aufsteigend).
- External: poll_changes (read: Ops+Layout+Presence), acquire/renew/release_lock
  (write). helper.php (gemeinsame Validierung + Lease-TTL). services.php + version
  2026072616.
- collaboration_external_test: 6 Tests (Kernszenario B->Halter, Renew/Release,
  poll Delta+Presence, expired ausgefiltert, Access). Gesamt 68/885 grün, phpcs 0/0.

[NÄCHSTE SCHICHT] Client js/src (adaptives Polling, Tweening, Lock-on-drag +
Heartbeat, Presence-UI) + Jest; danach Behat. Ab jetzt nur noch Patch-ZIPs.

## Nachtrag 17 (Session 002): CI-Fix (0.2.5)

- Ursache CI-Fail: .gitignore schloss amd/build/ aus -> editor_lazy.min.js nicht
  im Repo, aber in thirdpartylibs.xml referenziert -> grunt ignorefiles + phpcs
  ENOENT. Fix: amd/build/ nicht mehr ignorieren (Build-Artefakte einchecken).
- tools/fix_phpdoc.php + tools/mustache_check.php: @package von
  local_instantcoursecompletion (Vorlagen-Rest) auf mod_vimipad korrigiert.
- Verifiziert: mpc phpcs --max-warnings 0 = 0/0; grunt ignorefiles exit 0;
  git check-ignore ok; PHPUnit 68/885 grün. version 2026072617 / release 0.2.5.
- WICHTIG fuer kuenftige Instanzen: amd/build/ MUSS eingecheckt sein; die CI
  validiert das Plugin aus dem Repo und baut React nicht selbst.

## Nachtrag 18 (Session 002): Fix External-Test-Basisklasse + phpcpd (0.2.6)

- collaboration_external_test: externallib_advanced_testcase not found ->
  require_once($CFG->dirroot.'/webservice/tests/helpers.php') + use (global ns),
  extends ohne Backslash. Fehler war bei --filter verdeckt (andere Datei lud
  helpers). LEHRE: Testdateien ISOLIERT laufen lassen.
- phpcpd-Klon renew/release_lock -> helper::lock_parameters(); acquire/renew/
  release delegieren. No clones found.
- Alle 12 Testdateien isoliert grün; 68/885 gesamt; phpcs 0/0. version 2026072618/
  release 0.2.6.

## Nachtrag 19 (Session 002): CI Frontend-Build + JS-Lint (0.2.7)

- Ursache CI-Fail (phpdoc/phpcs Vendors non-existent path): editor_lazy.min.js
  fehlte im ausgecheckten Repo (Branch development). ROBUSTER Fix statt "bitte
  einchecken": CI baut das Bundle selbst -> Build-Schritt (npm install + node
  build.mjs) in ALLEN 4 Jobs nach checkout, vor moodle-plugin-ci install/checks.
- JS-Lint: eslint-disable no-undef vor require() in init.js entfernt (require ist
  bekannte Globale) -> ESLint 0/0. init.min.js neu.
- Bewiesen: ohne Bundle Vendors-Fehler, mit Build-Schritt läuft durch. 68/885,
  phpcs 56/56, Behat dry-run an. version 2026072619 / release 0.2.7.

## Nachtrag 20 (Session 002): editor_lazy.min.js-Lücke geschlossen + Schicht 3 (0.2.8)

- WAHRE URSACHE des CI-Dauerfehlers gefunden (Nutzer-Diagnose): editor_lazy.min.js
  lag in den lokalen Snapshots -> Patch-Diff schloss es immer als "unverändert" aus
  -> Zielcodebase bekam es nie. FIX: force-include in dieser Auslieferung + Quellen
  zum Selberbauen (build.mjs, package.json, package-lock.json, tsconfig.json, js/src).
- Schicht 3 Kollaborations-Client fertig integriert (adaptive/tween/poll/lock/
  apply_remote/use_collaboration + EditorApp/CanvasView + get_workspace collab).
  Jest 42/42, tsc sauber, phpcs 0/0, PHPUnit 68/885, ESLint 0/0, AMD reproduzierbar.
- version 2026072620 / release 0.2.8.

## Nachtrag 21 (Session 002): "No define call"-Fix + Anforderungen (0.2.9)

- Laufzeitfehler in Moodle (Debug-Modus): "No define call for mod_vimipad/
  editor_lazy". Ursache in lib/requirejs.php gefunden: ohne .map neben der
  Build-Datei rewritet Moodle auf amd/src/editor_lazy.js (fehlt) -> leer.
  FIX: build.mjs erzeugt editor_lazy.min.js.map (define via banner/footer).
  Verifiziert per Moodle-Logiksimulation + jsdom-Ladeprobe (mount vorhanden).
- Anforderungen/Roadmap aufgenommen: docs/dev/visual-maps-requirements.md
  (Map-Typen + Interaktions-Anforderungen, Arbeitsdokument).
- version 2026072621 / release 0.2.9.

## Nachtrag 22 (Session 002): amd/src/editor_lazy.js ausgeliefert (0.2.10)

- Nutzer benötigt amd/src/editor_lazy.js (Debug-Modus lädt je nach Moodle-
  Punktrelease src ODER build). Jetzt liefert build.mjs BEIDE: amd/build/
  editor_lazy.min.js (+.map, minifiziert) und amd/src/editor_lazy.js
  (unminifiziert). Beide mit benanntem define. thirdpartylibs deklariert beide.
- Empirisch geprüft: grunt amd überschreibt zwar amd/build aus amd/src, ABER
  das Ergebnis lädt korrekt (define+mount). Kein Bruch. node build.mjs bleibt
  maßgeblich. phpcs 56/56, PHPUnit 68/885, Jest 42/42, tsc sauber.
- version 2026072622 / release 0.2.10.

## Nachtrag 23 (Session 002): elang-Reste + Versionsnummer (0.2.11)

- Nutzer meldet phpcs-Fehler (@package mod_elang in version.php, lang/de/elang.php,
  lang/en/elang.php) und falsche Versionsnummer (Disk 2026072531 < DB 2026072619).
- Ursache: elang-Vorlagenreste im Zielverzeichnis, die Patch-cp nie löschen kann.
  MEIN Code ist sauber (phpcs mit Nutzer-Kommando: 0 Fehler; keine elang-Refs).
- FIX: vollständiges Clean-Paket (kein Patch) + Anleitung "altes Verzeichnis weg,
  dann extrahieren". version 2026072623 (>DB). LEHRE: Patch-cp kann keine Reste
  entfernen -> bei Vorlagen-Bootstrap Clean-Install anbieten.

## Nachtrag 24 (Session 002): CI/Build-Architektur bereinigt (0.2.13)

- Externe Experten-Analyse bestätigt + umgesetzt: amd/src/editor_lazy.js war der
  Fehler. amd/src wird von Moodles grunt (rollup) IMMER verarbeitet; thirdpartylibs
  entfernt es NICHT aus der Rollup-Eingabe -> Grunt überschrieb editor_lazy.min.js
  -> CI-Fehlerkette (durch continue-on-error verdeckt).
- FIX: amd/src/editor_lazy.js entfernt; build.mjs nur amd/build (+.map);
  thirdpartylibs nur amd/build; CI: continue-on-error weg, Build-Steps raus,
  neuer Reproduzierbarkeits-Job (npm ci + build + git diff). Dev-Mode via .map ok.
- LEHRE (eigener Fehler): ich hatte gesehen, dass grunt die Build-Datei
  überschreibt, und es als "läuft ja" abgetan. Die .map allein war der richtige
  Dev-Mode-Fix. Empirisch bestätigt: grunt amd lässt editor_lazy jetzt in Ruhe.
- version 2026072625 / release 0.2.13.

## Nachtrag 25 (Session 002): mpc-grunt-Löschverhalten = wahre Wurzel (0.2.14)

- Experten-Analyse (Teil 2) verifiziert: mpc GruntCommand löscht amd/build vor
  dem amd-Task; Vendors-Exception verhindert restorePlugin -> Bundle bleibt weg
  -> phpdoc-Folgefehler. LOKAL EXAKT REPRODUZIERT (identisch zum CI-Log, inkl.
  "Datei danach weg"). Erklärt die GESAMTE Fehlerhistorie seit 0.2.5.
- FIX: lint-js nutzt npx grunt amd --files=init.js direkt (keine Vorlöschung);
  mpc grunt nur noch --tasks gherkinlint/stylelint; test-f-Wächter davor/danach.
  npm-ci-Fix: package-lock.json aus .gitignore (git add -f nötig). bundle-Job
  auf Node 22. Alles end-to-end lokal grün.
- LEHRE: Tools nicht nur "direkt" testen (npx grunt), sondern EXAKT den
  CI-Wrapper (mpc grunt) — der Wrapper hatte eigenes Verhalten (Backup/Löschen/
  Validate), das der direkte Aufruf nicht zeigt.
- version 2026072626 / release 0.2.14.

## Nachtrag 26 (Session 002): Behat-Feature-Bug + Poll-Guard (0.2.15) — SESSION-ENDE

### Behat
- ECHTER Bug in editor.feature: Warte-Schritt auf nicht existierenden Button
  "Add concept" (ist Fieldset-Legende; Button = "Add"). Lief nie auf, weil Behat
  bis 0.2.14 nie startete (CI-Abbruch am Bundle). Fix: Warte auf
  "Add concept" "fieldset". Poll-Schleife unter Behat gegated
  (M.cfg.behatsiterunning).
- grading.feature statisch vollständig gegen die UI verifiziert (alle Strings,
  Feld-for/id-Verknüpfungen, grade-Default 100, snapshotstatus_1=Submitted).
- @javascript-Editor-Szenarien nur in echtem Chrome/CI prüfbar (Sandbox hat
  keinen Browser).

### Stand am Session-Ende (Release 0.2.15 / version 2026072627)
FERTIG & getestet:
- MVP-Kern (M1-M5): Domänenmodell, Editor (React/AMD), Snapshot/Grading,
  KI-Feedback (Teacher-in-the-loop), Backup/Restore, Privacy.
- Schicht 1-3 Kollaboration: Server-Locks + External Functions + Client
  (adaptives Polling, Tweening, Lock/Poll-Client, React-Integration).
- Canvas-Interaktion (erste Stufe): Auswahl, ESC, Entf, Node-Inline-Edit,
  Connection-Label mit weißer Outline, Connection-Geometrie-Modul.
- CI-Architektur bereinigt (der lange Kampf): amd/src/editor_lazy.js entfernt,
  build.mjs nur amd/build+.map, thirdpartylibs korrekt, mpc-grunt-Löschverhalten
  umgangen (npx grunt amd --files=init.js), package-lock committet, Node 22,
  Reproduzierbarkeits-Job. Behat-Feature-Bug behoben.
- Tests: Jest 67/67, PHPUnit 68/885 (12 Dateien, isoliert grün), phpcs 0,
  Behat-Dry-Run 6/59/0-undefiniert.

OFFEN (nächste Sessions):
- Behat @javascript-Editor-Szenarien im echten CI-Lauf bestätigen.
- Schicht 4 (weitere Behat-Kollaborations-Workflows).
- Interaktions-Anforderungen: Connection-Erzeugung (Zusammenschieben/Connect-
  Zone), Rendering mehrerer getrennter Connections (Geometrie liegt bereit),
  Inline-Edit für Connection-Labels, Hover-/Auswahl-Menüsymbole.
- Roadmap-Map-Typen: Familienbaum, Evolutionsbäume, Organigramme,
  Strukturgleichungsmodelle, IT-Architektur, Programmablaufpläne.
- Weiter Richtung 1.0 (voll getestet, nutzerfreundlich, stabil).

### Auslieferungs-Lehren (wichtig für künftige Sessions)
- Patch-cp kann keine Dateien löschen -> bei Vorlagen-Bootstrap-Resten
  Clean-Install (volles Paket) anbieten.
- Referenz-Snapshots dürfen keine Dateien enthalten, die die Zielcodebase nie
  bekam (sonst Diff-Blindheit).
- CI-Tools EXAKT über ihren Wrapper testen (mpc grunt != npx grunt), nicht nur
  den direkten Aufruf.
