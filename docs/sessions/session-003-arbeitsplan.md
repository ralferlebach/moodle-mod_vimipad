# Session 003 — Arbeitsplan: „Authoring-Tools & Brushing-Up Code"

**Ausgangsstand:** 0.5.32 (2026072712), real auf Moodle 4.5.12 und 5.0.8 grün.
**Grundlage:** externes 0.5.32-Audit, **real gegen die Codebase geprüft** — jede
konkrete Code-Behauptung des Audits trifft zu (Belege unten mit `Datei:Zeile`).

## Verifikation dieser Sitzung (bereits durchgeführt)

- 171 PHP-Dateien: `php -l` sauber (0 Syntaxfehler).
- Lang-Ordering (SORT_STRING) + EN/DE-Parität: Kern 397/397, alle 6 Assess-
  Subplugins paritätisch.
- Frontend: `tsc --noEmit` 0 Fehler; `node build.mjs` ok; Jest 22 Suites/147
  Tests; Bundle-Reproduzierbarkeit (2. Build byte-identisch).
- Volle Moodle-PHPUnit/Behat-Matrix hier **nicht** neu geklont (CI-Aufgabe;
  in-session bereits auf 4.5.12 + 5.0.8 real grün, s. CHANGELOG 0.5.30–0.5.32).

## Go/No-Go

- **0.6.x-Branch erzeugen: GO.** Keine grundlegende technische Schuld mehr.
- **Sofort mit Template-/Container-Code beginnen: nein.** Erst eine kurze
  **0.5.33-Closure-Runde** („Brushing-Up") — Ziel ausdrücklich *kein* neuer
  Funktionsausbau, sondern offene 0.5-Verträge schließen und die zwei neuen
  0.6-Verträge (Mutations-/Revisionsmodell, Importformat) vorab festziehen.

---

## Teil 1 — Entscheidungen, die Ralf VOR der Umsetzung treffen muss

Diese berühren Verträge/Roadmap; ich setze sie nicht eigenmächtig um.

| # | Frage | Optionen | Empfehlung |
|---|---|---|---|
| E1 | **Mehrfach-Referenzen** | A) relationales `vimipad_reference` (mehrere Musterlösungen, gewichtet) · B) bewusst **eine** Referenz bis nach 1.0, `array $references` bleibt zukunftsoffener Subplugin-Vertrag | B (schlank), Doku/Roadmap angleichen |
| E2 | **Lease-Semantik** | A) advisory (UX/Presence) · B) enforced (atomare Acquisition + Prüfung in Mutationsservices) | A für 0.6 ausreichend; sauber dokumentieren |
| E3 | **Peer-Phasenmodell** | A) 5-Phasen-Activity-Statemachine bauen · B) Roadmap auf realen Scope (Allokation + Reviews) korrigieren | B (ehrlicher, kein Feature-Creep vor 0.6) |
| E4 | **`updated`-Event** | A) fachliches `map_updated`/`workspace_updated` ergänzen · B) aus 0.4-Zusage streichen | A (klein, sinnvoll fürs Standard-Reporting) |
| E5 | **Snapshot-Provenienz** | A) `createdby`/`modifiedby` in den Snapshot · B) bewusst nicht (Normalisierung/Datenschutz), Lastenheft korrigieren | B, Lastenheft-Satz „Autorinformationen" präzisieren |
| E6 | **Import-Atomarität** | A) semantischer Import atomar, Layout best-effort · B) vollständiger Import atomar | A (billiger), Doku eindeutig machen |

## Teil 2 — Code-Fixes, die ich ohne Rückfrage umsetzen kann (0.5.33)

**Reihenfolge = Priorität.**

### P0 — Concurrency-Garantie schließen (echter Gate)
- Gemeinsamer **Workspace-Write-Lock** (Core-Lock, ein Key je Workspace) um
  **alle** semantischen Mutationen und die Snapshot-Erzeugung:
  `operation_service::apply`, `import_service`, `workspace_service::reopen`,
  `snapshot_service::begin_submission/finalize`.
- Muster innerhalb des Locks: Transaktion → Workspace frisch lesen → Revision
  prüfen → Operation/Snapshot → Revision/locked setzen → Commit.
- **Beleg:** `operation_service.php:57–60` prüft nur das `locked`-Flag, kein
  echter Row-/Write-Lock; `snapshot_service::build_normalized` liest
  nodes/relations/containers sequentiell → Mischzustand relativ zur Revision
  möglich.
- Test: paralleler Submit vs. Operation (Revision-Konsistenz des Snapshots).

### P1 — Workspace-Erzeugung fail-**closed**
- `workspace_service::create_unique`: bei Lock-Fehlschlag **nicht** trotzdem
  `create()`, sondern erneut lesen bzw. definierten Concurrency-Fehler werfen.
- **Beleg:** `workspace_service.php:165–166`. (Optional später: DB-eindeutige
  Owner-Identität.)

### P1 — `duedate`/„late" fertigstellen
- Late deterministisch aus `snapshot.timecreated` vs. `instance->duedate`
  ableiten (kein neues Feld), in Lehrenden-Übersicht + Bewertungsansicht (+ ggf.
  Export/API) anzeigen.
- **Beleg:** nur `cutoffdate` wird erzwungen (`snapshot_service.php:202`);
  `duedate` sonst nirgends ausgewertet.

### P1 — Lease-Vertrag angleichen (nach E2)
- Code **oder** Doku so, dass beide dasselbe behaupten. Bei „advisory":
  `lock_service`-Docblock „server-enforced" entschärfen; keine fachliche
  Restriktion auf Leases stützen.

### P1 — Entscheidungen E1/E3/E4/E5/E6 einarbeiten
- Je nach Beschluss: kleines Schema (E1-A) **oder** Doku/Roadmap-Angleich (E1-B,
  E3-B, E5-B); `map_updated`-Event (E4-A); Import-Docblock „atomic" eindeutig
  (E6).

### P2 — Doku-Re-Baseline abschließen (Rest des „Brushing-Up")
- ✅ bereits in dieser Sitzung: `session-002.md` angelegt; `roadmap.md`
  (Stand/Statusmarker/0.7-Rewording), `backlog.md` (Banner + Done-Marker),
  `connector-styles.md` (`vimipadform`, `formconfig` konsumiert),
  `ui_reorder_plan.md` (Status-Banner), `moodle-test-environment-setup.md`
  (Baseline 176+97), `visual-maps-requirements.md` (Historien-Marker), README
  (React-5.3-Framing, API-Zusage entschärft).
- Noch offen: README-„Development status" bleibt 0.5.32 → bei jedem Release
  mitziehen; `assessment_architecture.md` gegen den realen Scorer-Satz (6 Scorer)
  gegenprüfen.

## Teil 3 — 0.6-Verträge, die VOR dem ersten Feature spezifiziert werden

Kein Code, aber verbindliche Design-Docs (Grundlage, damit 0.6 keine zweite
Mutationslinie neben dem sauberen Operationsmodell schafft):

1. **Container-/Membership-Operationen:** `container_create/update/delete`,
   `membership_add/remove/move` ins Operation-Log + Revisionsrekonstruktion +
   Trennung Container-Semantik (label/type/membership/Regeln) vs. Layout
   (x/y/width/height/viewport). **Beleg:** Operation-Log kennt heute nur
   `node_*`/`relation_*` (`operation_type.php`).
2. **Importformat v1/v2:** `formatversion` beim Import **auswerten**;
   Migrationspfad v1→v2 (nodes/relations/layout → + containers/memberships/
   template/constraints); vollständiger Roundtrip. **Beleg:** Export schreibt
   Container/Memberships (`export_service.php:99–102`), Import ignoriert sie
   (`import_service.php` `parse_document` → `{nodes, relations, layout}`);
   `FORMAT_VERSION=1` geschrieben, nur `generator` geprüft.
3. **Template-/Constraint-Policy:** serverseitiges Enforcement (Was ist die
   Vorlage? Welche Elemente geschützt? Was darf der Lernende? Globale Regeln:
   erlaubte Node-/Relationstypen, Pflicht-/verbotene Begriffe, Mindestumfang) —
   als Policy-Schicht **vor** `operation_service`, nicht nur UI-Buttons
   verstecken.

## Teil 4 — Vorgeschlagener 0.6.x-Schnitt (nach 0.5.33)

- **0.6.0** — UI/Architektur fertigstellen: Feedback-Reiter, Werkzeuge-Reiter
  (heute `tab:comingsoon`), Container-/Membership-Operationsvertrag,
  Importformat v2, Template-/Constraint-Domänenmodell (wenig neue Fachfunktion).
- **0.6.1** — Container/visuelle Bereiche (Operationen, Canvas, Liste,
  Collaboration/Polling, Revision, Snapshot, Backup/Privacy, JSON/XML v2).
- **0.6.2** — Templates (Workspace/Snapshot → Vorlage → neue Instanz).
- **0.6.3** — Scaffolding & Constraints (serverseitige Policies).
- **0.6.4** — Import/Export- und Template-Integration (v1→v2, Roundtrip,
  Lehrerwerkzeuge, Massenexport).
- **0.6.5** — UX-/Accessibility-/Collaboration-Hardening (Container per Tastatur,
  Containersemantik in Listenansicht, Konflikttests, größere Maps).

## Teil 5 — Nicht-Blocker (bewusst später)

Feedback-/Werkzeuge-Tab-Implementierung, Container-/Template-UI, Lernenden-
Freigaben, Accessibility-Finalaudit (0.9.x), native 5.3-React-Integration,
weitere Diagrammprofile, Replay, große Analytics, Moodle-5.1-CI-Ergänzung.

---

## Konkreter Vorschlag für den Sitzungsstart

1. **Entscheidungen E1–E6 kurz bestätigen** (ich empfehle B/A/B/A/B/A).
2. Danach **0.5.33 „0.5 closure & 0.6 foundations"** in der Reihenfolge oben —
   je Schritt real verifiziert und als eigener Savepoint, ohne neuen
   Funktionsausbau.
3. Erst nach 0.5.33: **0.6.0** (Autorenwerkzeuge) beginnen.
