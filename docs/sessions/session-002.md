# Session 002 — Frontend-Ausbau, Bewertungsarchitektur, Assessment-Subplugins, Peer Review

**Chat:** „002 – Moodle-Plugin Frontend, Assessment, Authoring"
**Bogen:** Release 0.4.22 (2026072679) → **0.5.32** (2026072712)
**Verifikation:** real auf Moodle 4.5.12 **und** 5.0.8 (PostgreSQL 16, PHP 8.3);
CI-Matrix 4.5/5.0/5.2 × MariaDB/PostgreSQL.

> Fortsetzung von `session-001.md` (dort kompaktiert bis 0.2.15). Die frühen
> „0.5.0–0.9.1"-Einträge im CHANGELOG waren eine explorative Nummerierung, die
> später auf die 0.2.x-Linie zurückgesetzt wurde; maßgeblich ist stets
> `version.php`. Die reale Progression dieser Session ist 0.4.22 → 0.5.32.

---

## Ziel der Session

Den 0.4.x-Stand (Editor + Kollaboration, extern reviewed und gehärtet) zur
**vollständigen Bewertungs- und Peer-Review-Funktionalität** ausbauen. Erklärtes
Nutzerziel: **die gesamte Backend-Funktionalität abschließen, bevor die
UI-Konfigurationselemente gebaut werden** — erreicht bei 0.5.32.

---

## Was wurde erledigt

### Block A — 0.4.x konsolidiert (0.4.22)

- 0.4.x als feature-complete markiert und nach externem Review gehärtet:
  Security (AI-Draft nur snapshot-gebunden übernehmbar), Backup/Restore
  (Grades mit Snapshot-Remap, Journal), Voll-Privacy über alle personenbezogenen
  Referenzen, Concurrency (Core-Lock für Workspace-Erzeugung + Abgabe,
  Layout-Merge pro Node), Grading/Completion kursweit, Operation-Contracts
  (Payload-Validierung je Typ). Profil-Liste aus der Subplugin-Registry statt
  hartcodiert; Release-CI mit Dev-CI vereinheitlicht (Bundle-Reproduzierbarkeit,
  Typecheck/Jest-Gates), Matrix um 5.2/5.3 erweitert.

### Block B — Import/Export & Query-Effizienz (0.5.0 – 0.5.4)

- **Import** als Gegenstück zum Export: JSON (0.5.0) und XML (0.5.1),
  Format-Autodetektion, Append- **oder** Replace-Modus, atomar über den
  validierten Operationspfad; Reopen einer abgegebenen ViMi durch Lehrende
  (`workspace_service::reopen`).
- **Layout-Import** (Positionen/Größen, auf neue stable ids remapped, 0.5.2).
  Container/Memberships bewusst außen vor (dormantes Schema-Feature).
- **Polling-Skalierung:** gebündelte Operations-Batches mit `hasmore`,
  Expired-Lease-Cleanup nur gelegentlich; Layout nur bei Änderung übertragen
  (`layoutsince`/`layouttime`, 0.5.4).
- **Query-Effizienz:** keine Per-Zeilen-User-Lookups mehr (View/Report/Grade),
  SQL-Aggregation im Workspace-Report (0.5.3).
- **Refactors:** Canvas-Geometrie/Shapes/`useDismiss` in getestete Module.

### Block C — Fristen, Konsens, Reiter-UI (0.5.5 – 0.5.14)

- **Fristen:** `duedate`/`cutoffdate` mit Validierung (0.5.5). ⚠️ *Nur
  `cutoffdate` wird real erzwungen; `duedate`/„late" bleibt offen — siehe
  „Offene Punkte".*
- **Gruppenkonsens-Abgabe:** `requireallteamsubmit` + `vimipad_submissionintent`;
  Snapshot erst nach Zustimmung aller Mitglieder (0.5.5). Explizite
  Zustandsmaschine `open → voting → submitted` (`consensus_service`, 0.5.13),
  Mitglieder-Overview + Systemnachrichten (`db/messages.php`,
  `consensus_notifier`, 0.5.14).
- **Reiter-zentrierte Aktivitätsoberfläche (Islands statt SPA):** server-
  gerenderte Tabs (Canvas, List, Journal & Abgabe, Bewertung, Feedback,
  Werkzeuge) unter Überschrift/Menü, aktiver Reiter in der URL (0.5.6). Editor-
  interne Tabs entfernt (0.5.8). Read-only-Live-Ansicht fremder Maps an einem
  einzigen Choke-Point im ApiClient (0.5.7); Lehrenden-Einsicht in Lernenden-
  Maps über User-Selektor (`get_workspace` mit Ziel-User, `find_for_user`, 0.5.8).
- **Journal & Abgabe-Reiter:** server-gerenderte Zeit-Buckets, Submit aus dem
  Editor herausgezogen (0.5.10); Editor-Oberfläche finalisiert, Canvas-Höhe
  gedeckelt und Fullscreen-Fix (0.5.9/0.5.12).

### Block D — Journal-Revisionsansicht (0.5.15 – 0.5.16)

- **`reconstruction_service`:** spielt das Operation-Log bis zu einer
  Zielrevision ab und rekonstruiert die exakte Node/Relation-Topologie (stable
  ids server-vergeben → treue Wiedergabe). `revisionref` je Journaleintrag
  endlich befüllt; `get_revision_state`-External.
- **Revisions-Viewer:** eigenes `mod_vimipad/revision`-Bootstrap montiert einen
  read-only `RevisionViewer` aus dem Editor-Bundle (getrennt vom Editor-
  Bootstrap), Canvas + Liste, Auto-Layout.

### Block E — Advanced Grading über `gradingform` (0.5.17 – 0.5.24)

- **Bewertung in den Reiter verlegt** (`grading_panel`, `grade.php` als
  Redirect, 0.5.18); Struktur-Metriken als Bewertungshilfe (0.5.17).
- **Core Advanced Grading:** `FEATURE_ADVANCED_GRADING` + `submissions`-Area
  (0.5.19), Bewertung *durch* Rubric/Marking Guide mit `vimipad_gradeinstance`
  (itemid = Snapshot, 0.5.20), Backup/Restore der Rubric-Definitionen und
  Fillings (0.5.22).
- **Vier reale Fatals gefunden und behoben, die nur ein laufendes Moodle zeigt**
  (0.5.21/0.5.23/0.5.24): `component_gradeitems`-Signatur; Klasse muss
  `itemnumber_mapping` + `advancedgrading_mapping` **implementieren** statt die
  Core-Klasse zu erweitern; kein doppelter Grading-Backup-Step.
  → Setup einer echten 4.5.12 + PostgreSQL PHPUnit-Umgebung mitten in der Session.

### Block F — `vimipadassess`-Subplugin-System (0.5.17 Konzept, 0.5.25 – 0.5.30)

- **Architektur dokumentiert** (`docs/design/assessment_architecture.md`,
  0.5.17): Kit-Build FMS/SMS, NLP/LLM, Graphmetriken, referenzfreie Indizes,
  Fuzzy-Gewichte etc. gegen die Plugin-Constraints abgewogen; Hybrid-Entscheid:
  manueller Workflow/Annotationen/AI-Draft/`gradingform`/Struktur-Metriken
  bleiben im Kern, **automatische Scorer werden ein `vimipadassess`-Subplugintyp**
  mit fuzzy-fähigem (0..1-gewichtet), profil-bewusstem Scorer-Contract und
  austauschbarem Matcher.
- **Contract** (`classes/local/assess/`): `submission`, `matcher` (+ `exact`,
  `levenshtein`, `token`, `matcher_factory`), `result`, abstrakter `scorer`,
  `registry`, Kern-Services `tuple_text` und `rouge`, `prompt_scorer`-Interface.
- **Sechs Scorer-Subplugins** unter `assess/`:
  - `reference` — Konzept-/Propositions-F1 gegen Musterlösung (0.5.25)
  - `structure` — referenzfreie Strukturmetriken, informational (0.5.27)
  - `llm` — On-demand-KI-Bewertung über das Moodle-AI-Subsystem, deterministisches
    `build_prompt`/`interpret` (0.5.28)
  - `tree` — Hierarchie-F1 (Root + Parent→Child), Labels ignoriert (0.5.29)
  - `sms` — Sub-Map-Vergleich über Container-Gruppierungen (0.5.30)
  - `text` — Beschreibungsvergleich per ROUGE (0.5.31)
- **Musterlösung markieren** (`referencesnapshotid` auf der Aktivität), Vorschlag
  im Grading-Reiter, `assess_service` mit `submission_from_snapshot`, `score`,
  `score_all`, `score_ai` (0.5.26/0.5.27). ⚠️ *Singular — siehe „Offene Punkte".*
- **Konfigurierbares Matching je Aktivität** (`matchmode`: exact/fuzzy/word-
  overlap) über den `matcher_factory` (0.5.29).

### Block G — Peer Review (0.5.31 – 0.5.32)

- **Backend** (0.5.31): `peerreviewmode`/`peerreviewcount`,
  `vimipad_peerreview`, `peer_review_service` (Round-Robin-Allokation über
  abgebende Lernende — nie die eigene Map, idempotent —, Review-Erfassung,
  Aggregation mit count/mean/median/outstanding). Peer-Scores bleiben advisory
  (kein Gradebook-Schreiben); `guidance()` liefert dieselben synchronen Scorer-
  Hinweise wie beim Lehrenden. Privacy + Backup/Restore abgedeckt.
- **Konfigurations-UI** (0.5.32): Peer-Review-Einstellungen in der Aktivität,
  **Scorer-Auswahl je Aktivität** (`activescorers`, leer = alle), Reviewer-Reiter
  (anonymisiert „Abgabe 1/2 …", `mod/vimipad:peerreview`), Lehrenden-Aggregat im
  Grading-Detail. Schema-Fix: `activescorers` nullable ohne Default; Backup-Feld
  ergänzt; Panel-Rendering testgedeckt.

### Block H — CI-Härtung (durchgehend)

- `vimipadassess`-`plugininfo`-Klasse ergänzt (voller Site-Install brach sonst
  in jedem Matrix-Job).
- `db/subplugins.json` deklariert **beide** Formen: `subplugintypes` (Moodle 5.0,
  MDL-83705) + `plugintypes` (4.5) — 5.0-Deprecation weg, 4.5 weiterhin ok.
- `grading_panel`/`consensus_notifier`: `stdClass $cm` → `cm_info|stdClass`
  (Fatal-TypeError, Ursache aller vier failenden Behat-Szenarien auf allen
  Versionen); durch `grading_panel_test`/`peer_review_panel_test` regressions-
  gesichert.
- Behat für neue Tabs aktualisiert; Generator-Kontext-Fix; `MOODLE_503_STABLE`
  bis zum Branch-Cut aus der Matrix.
- Codebase-Verdopplung (`mod/vimipad/classes/**` verschachtelt in der Nutzer-
  Repo) aus CI-Logs diagnostiziert; Aufräum-Skript. Ausgelieferte ZIPs sauber.
- phpdoc: generisches `array<...>`/`array{...}` in `@param`/`@return` verboten →
  plain `array`. `#[\Override]` vermieden (CI läuft PHP 8.2).

---

## Entscheidungen getroffen

| Thema | Entscheidung | Begründung |
|---|---|---|
| Bewertungsarchitektur | Hybrid: Kern-Workflow + `gradingform` fix, automatische Scorer als `vimipadassess`-Subplugintyp mit Matcher-Injektion | erweiterbar, fuzzy-/profilfähig, ohne Kernumbau |
| Peer-Scores | strikt advisory, nie im Gradebook | Lehrende bleiben in-the-loop; Roadmap-Konformität |
| KI-Scorer | ausschließlich on-demand, nie automatisch | Kosten/Latenz; nur `core_ai` |
| Islands statt SPA | Reiter server-gerendert, React nur als Insel | Barrierefreiheit, Deep-Links, weniger Neubau |
| 5.3 in CI | erst nach Branch-Cut aufnehmen | Clone von `MOODLE_503_STABLE` bricht derzeit vor Plugin-Code |
| Verifikation | reale 4.5.12- **und** 5.0.8-Instanz | mehrere Fatals waren statisch unsichtbar (Advanced-Grading-Interfaces) |

## Entwurfsentscheidungen geändert / zurückgestellt

Keine der neun Schlüssel-Entscheidungen aus `sessionstart.txt` geändert.
Zurückgestellt (bewusst offen gelassen, s. u.): Mehrfach-Referenzen,
Peer-Phasenmodell, `duedate`/late, `updated`-Event.

---

## Offene Punkte für die nächste Session

Aus dem 0.5.32-Audit als Gates vor 0.6.x bestätigt (real gegen Code geprüft):

- **[P0] Concurrency:** `operation_service::apply()` nutzt keinen echten
  Row-/Write-Lock; nur das `locked`-Flag wird geprüft (`operation_service.php:59`).
  `snapshot_service::finalize`/`build_normalized` liest Nodes/Relations/Container
  sequentiell → möglicher Mischzustand relativ zur gespeicherten Revision.
  Gemeinsamer Workspace-Write-Lock für Operation/Import/Reopen/Submit nötig.
- **[P1] Workspace-Erzeugung fail-open:** `create_unique` fällt bei Lock-
  Fehlschlag auf `create()` zurück (`workspace_service.php:165`) — DB-seitig
  keine Eindeutigkeit `(vimipadid,userid|groupid)`. Fail-closed machen.
- **[P1] Lease-Semantik:** `lock_service` dokumentiert „server-enforced", aber
  `operation_service` prüft keinen fremden Lease vor Mutation → faktisch
  advisory. Als advisory dokumentieren **oder** enforcen (für 0.6 vermutlich A).
- **[P1] Mehrfach-Referenzen:** Contract nimmt `array $references`, aber Schema
  (`referencesnapshotid` singular), `assess_service` (`[$reference]`) und alle
  Scorer (`reset($references)`) reduzieren auf genau eine. Relationales Modell
  bauen **oder** bewusst auf eine Referenz de-scopen.
- **[P1] `duedate`/late:** gespeichert + Hilfetext, aber nirgends ausgewertet;
  nur `cutoffdate` wird erzwungen. Late aus `snapshot.timecreated` vs.
  `duedate` ableiten (kein neues Feld nötig) und in Übersicht/Bewertung zeigen.
- **[P1] Peer-Phasenmodell:** Roadmap verspricht Einrichtung→Bearbeitung→
  Begutachtung→Bewertung→geschlossen; realisiert ist Allokation + Reviews
  (`STATUS_ALLOCATED/SUBMITTED`), kein activityweiter Phasenautomat.
  Implementieren **oder** Roadmap auf den realen Scope korrigieren.
- **[P1] `updated`-Event fehlt:** vorhanden sind `course_module_viewed`,
  `snapshot_submitted`, `snapshot_graded`. 0.4.x-Zusage `updated` ergänzen oder
  de-scopen.
- **[P1] Importformat versionieren:** `FORMAT_VERSION=1` wird geschrieben, beim
  Import nur `generator` geprüft; Import ignoriert Container/Memberships
  (Roundtrip unvollständig); „whole import is atomic" widerspricht dem
  Post-Commit-Layout. v1/v2 + Migrationsstrategie vor 0.6 definieren.
- **[P1] Container-/Membership-Operationen** fachlich spezifizieren
  (Operation-Log kennt nur `node_*`/`relation_*`) — Grundlage für „Hintergründe
  zeichnen" in 0.6.
- **[P1] Template-/Constraint-Enforcement** serverseitig konzipieren (nicht nur
  Buttons im Editor verstecken) — Policy-Schicht vor `operation_service`.
- **[P1] Öffentliche-API-Zusage korrigieren:** `classes/profile` existiert nicht;
  Stabilitätsgarantie erst 0.7.x (Roadmap) — README entschärfen.
- **[P2] Snapshot-Provenienz:** `createdby`/`modifiedby` nicht im Snapshot
  (nur im Operation-Log) — Lastenheft-Punkt „Autorinformationen" entscheiden.

## Roadmap-Abgleich

`docs/design/roadmap.md` in dieser Session als deutlich veraltet erkannt
(„Stand: nach 0.2.x"; Privacy/Backup als 0.7.x-Erstimplementierung, obwohl in
0.4.x umgesetzt). In Session 003 re-baselined (Status-Marker ✅/◐/○).

---

## Testlauf-Ergebnis (Stand 0.5.32)

```
PHPUnit: OK — 176 mod_vimipad + 97 vimipadassess (real auf 4.5.12 UND 5.0.8, exit 0)
PHPCS:   OK — 0/0 (severity=1)
PHPDoc:  OK — validate/savepoints/mustache/phpcpd sauber
Behat:   OK (dry-run + CI) — grading-Tab-Fatal behoben; @javascript nur in CI
Frontend: OK — tsc 0, Jest 22 Suites/147, Bundle reproduzierbar
```

## Für die nächste Session (in sessionstart.txt)

**Aktueller Entwicklungsstand:**
> 0.5.32 — Backend fachlich vollständig (Import/Export, Reiter-UI, Konsens,
> Journal-Revision, Advanced Grading via `gradingform`, `vimipadassess` mit 6
> Scorern + Matcher-Wahl, Peer-Review-Backend + Konfigurations-UI). Real auf
> 4.5.12 und 5.0.8 verifiziert.

**Zuletzt abgeschlossen:**
> Peer-Review-Konfigurations-UI (0.5.32) — erklärtes Ziel „gesamtes Backend vor
> UI-Konfiguration" erreicht.

**Als nächstes geplant:**
> 0.5.33 „0.5-Closure & 0.6-Foundations" (Gates oben: Concurrency-P0,
> fail-closed Workspace, Lock-Vertrag, Multi-Ref-/Peer-Phase-/`updated`-/late-
> Entscheidungen, Importformat v2 + Container-Ops + Template-Policy spezifizieren,
> Doku-Re-Baseline), **dann** 0.6.0 Autorenwerkzeuge (Container, Templates,
> Scaffolding/Constraints, Import/Export-Integration).
