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
