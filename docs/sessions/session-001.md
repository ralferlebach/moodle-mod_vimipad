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
