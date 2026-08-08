# Session 011 — Lastnachweis, Performance, Audit-P1 und Beta-Schnitt (0.8.32 → 0.9.0)

> Versionierter Report der Sitzung. Kurzfassung für den Einstieg in Session 012
> steht in `sessionstart-012.txt`.

## Ergebnis in einem Satz

Der Lastnachweis wurde erbracht, die drei teuersten Performance-Befunde an
`get_workspace` behoben, die P1-Befunde eines externen Audits geschlossen — und
am Ende der **Beta-Schnitt `0.9.0` / `MATURITY_BETA` / `2026080800`** gesetzt,
konsistent über Kern und alle 19 gebündelten Subplugins.

## Releases dieser Sitzung

| Release | Version | Inhalt |
| --- | --- | --- |
| 0.8.32 | 2026072802 | Container-Drift-Fix, DRY-Test-Refactor, Lasttest-Fixes |
| 0.8.33 | 2026072803 | `get_state()`-Spalten-Pruning (Performance) |
| 0.8.34 | 2026072804 | Paginiertes Laden großer Maps (validierte Seiten) |
| 0.8.35 | 2026072805 | Audit-P1: `empty_state`-Bugfix, POST-Härtung, Ansichts-Paginierung, k6-Nulltoleranz, Versions-/Paketierungshygiene |
| **0.9.0** | **2026080800** | **Beta-Schnitt** (Reifegrad + einheitliche Versionierung) |

## 1. Laufzeit-Bugfix: Container-Drift beim Neuladen (0.8.32)

Ralfs Bildschirmvideo zeigte, dass Container nach jedem Laden ~30 s durch ihre
Bearbeitungshistorie „wanderten". Ausgeschlossen wurden: gespeicherte
`geometryjson` (stabil), `computeLayout` (rührt Container nicht an, deterministisch),
`refineArrangement` (nur per Knopf) und der RevisionPlayer (kein Autoplay).

**Ursache:** `PollClient` startet bei `revision = 0`, und `poll.setRevision()`
wurde nach dem Laden nie aufgerufen. Der erste Poll holte damit das **gesamte**
Op-Log ab Revision 0 und spielte jede historische Container-Bewegung erneut ab.

**Fix:** `use_collaboration` erhielt einen `baseRevision`-Parameter und setzt
`poll.setRevision()` vor `poll.start()`; `EditorApp` übergibt `state.revision`
(sicher, weil `{kind:'load'}` Workspace-Id und Revision atomar setzt).
Regressionstest `use_collaboration_baserevision.test.ts`.

## 2. Lastnachweis

- **jMeter** nach Korrektur des Samplers (`sincerevision` → `torevision` +
  `fromrevision`): 2000 Samples, **0 Fehler**, 141/s.
- **k6**: `http_req_failed 0.00 %` (0/10144), 182 req/s, 25 VUs/60 s.
- Einziger Ausreißer: `get_workspace` (avg 352 ms, p95 556 ms) — der Grund für
  die Performance-Arbeit unten. Werkzeuge: `seed_large.php`, `make load-seed`
  mit automatisch gelesener `.load-env`, `make k6-setup`.

## 3. Performance an `get_workspace` — erst messen, dann optimieren

Profiling (1000 Knoten / 2000 Relationen / 200 Container):

| Posten | Zeit |
| --- | --- |
| `get_state` (DB) | 8,6 ms |
| Mapping | 1,3 ms |
| `get_layout_json` | 0,7 ms |
| formconfig / collab | 0,1 / 0,3 ms |
| **`clean_returnvalue`** | **~70 ms** |
| Ende-zu-Ende (warm) | ~101 ms |

Kein N+1, Indizes vorhanden. Die 4,89-s-Spitzen stammen **nicht** von einem Lock
(`get_or_create_for_user` sperrt im Normalfall nicht), sondern von Speicher-/
Ressourcendruck unter Nebenläufigkeit.

**0.8.33 — Spalten-Pruning:** `get_state()` lud `SELECT *`, obwohl die Mapper nur
6–8 Felder nutzen. Ergebnis: Query 8,3 → 5,4 ms (−40 %), Peak-Speicher −13 %.

**0.8.34 — paginiertes Laden.** Der naheliegende Weg (Bulk-Arrays als
`PARAM_RAW`-JSON, um `clean_returnvalue` zu umgehen) wurde von Ralf abgelehnt.
Umgesetzt wurde stattdessen Paginierung **mit voll validierter Payload**:

- `get_workspace` erhielt den additiven Parameter `includeelements`
  (Default `true` = altes Verhalten); mit `false` liefert er Metadaten + `counts`.
- Neue Lesefunktion `mod_vimipad_get_workspace_elements(cmid, workspaceid, kind,
  offset, limit)` gibt validierte Seiten zurück (max. 500, `id ASC`).
- Der **API-Client** pagt und setzt die volle `WorkspaceState` zusammen —
  Editor, Reducer und Rendering blieben unverändert.
- Live verifiziert: eine 1000-Knoten-Map rendert über zwei Seiten vollständig
  im Browser (`RENDERED_NODES=1000`).

## 4. Externes Audit (Stand 0.8.32) — P1-Befunde

Alle Befunde wurden **gegengeprüft**, nicht blind übernommen.

**P1.1 `empty_state()` — bestätigt.** Zugriff auf ein dort nicht existierendes
`$workspace`. Behoben mit `collab_config(null)` (leeres Push-Topic/Token statt
eines Kanals für Workspace 0). Der Regressionstest wurde gegen den Bug validiert:
ohne Fix `Undefined variable $workspace` → ERROR.

**P1.2 POST-Härtung — bestätigt.** Zentraler, unit-testbarer
`local\policy\request_policy::is_mutating_request()` statt sechs Kopien der
Magic-String-Prüfung; gesetzt in `view.php` (Abgabe, Konsens), `grading_panel`
und `peer_review_panel`. Beleg: ohne Guard läuft ein GET bis in die
Annotations-Mutation. **Wichtige Korrektur im Verlauf:** der Guard stand zuerst
*vor* `require_capability`, wodurch ein unautorisierter GET stillschweigend
zurückkehrte statt die Exception zu werfen — Reihenfolge korrigiert
(Autorisierung vor Methodenprüfung).

**§12 Paginierung (von Ralf auf Beta-Blocker hochgestuft).**
- Submissionübersicht: lud jede Abgabe **und** dekodierte pro Zeile das
  Snapshot-JSON → 50/Seite.
- Journalhistorie: wächst unbegrenzt → 50/Seite, Service um Limit/Count erweitert.
- Statistik-Übersicht: eine Zeile je Teilnehmendem → 50/Seite.
- **Gegenbefund:** Reviewerlisten *nicht* paginiert — sie sind durch die
  Allokationen je Reviewer begrenzt (2–5), nicht durch die Kohorte.

**P1.3 k6-Nulltoleranz.** Fachliche Fehler werden nicht mehr in die globale
Check-Rate gemittelt: neue Metriken `vimipad_exceptions` / `vimipad_http_errors`
mit `rate==0`, Latenz bleibt statistisch. Beides real verifiziert — Normallauf
0,00 %, Negativtest (ungültiger Token) bricht den Threshold, **k6-Exit 99**.

**P1.4 Versionsdrift.** `package.json`/`-lock` (0.7.31) wieder synchron.

**Paketierung (§33/§34/§36/§40).** Neue `.gitattributes`, per `git archive`
verifiziert: `docs`, `tools`, `.github`, `tests/load`, `tests/playwright`,
`js/tests` raus; `amd/src`, `js/src`, PHPUnit-/Behat-Tests und die Source-Maps
(CI verlangt sie) drin. Release-ZIP mit Root `vimipad/`.

**CI-Befund (PHPDoc).** Der rote Lauf auf 0.8.34 kam vom fehlenden
`@param $includeelements`; in 0.8.35 behoben und mit `local_moodlecheck` über
das gesamte Plugin befundfrei nachgewiesen.

## 5. Beta-Schnitt 0.9.0

- Kern: `MATURITY_BETA`, Release `0.9.0`, Version `2026080800`.
- **Alle 19 Subplugins** auf denselben Reifegrad/Release/Version gezogen; ihre
  `mod_vimipad`-Abhängigkeit zeigt auf `2026080800` (zwei nutzten eine
  einzeilige Schreibweise und brauchten einen zweiten Durchgang).
- `package.json`/`-lock` auf `0.9.0`.
- README um einen Status-Abschnitt ergänzt, Roadmap und Backlog auf den
  Beta-Stand gezogen, Release-Checkliste korrigiert (die Upgrade-Prüfung
  beschreibt beim ersten Release eine leere Menge statt einer unerfüllbaren
  Bedingung).
- `tests/load/.gitignore` schließt `jmeter.log` / `*.log` aus (der jMeter-
  Startlog protokolliert jede `-J`-Property im Klartext, inklusive REST-Token).

## 6. Verifikation des Endstands

phpcs 0 · **moodlecheck 0** · validate/savepoints/mustache 0 · phpcpd „no clones" ·
**PHPUnit 575 / 2157** · tsc 0 · **Jest 70 Suiten / 434 Tests** · eslint 0 ·
`editor_lazy` und `init` byte-reproduzierbar · Lang-Parität OK ·
Upgrade auf 2026080800 für Kern und Subplugins erfolgreich ·
Frisch-Installation aus dem Release-ZIP erfolgreich.

## 7. Methodisches aus dieser Sitzung

- **Live-Playwright in der Sandbox** funktioniert nur über einen `setsid`-
  abgekoppelten Runner, der in eine Datei schreibt und per Marker gepollt wird;
  `$CFG->behat_wwwroot` muss sich von `$CFG->wwwroot` unterscheiden. Node-Skripte
  brauchen `NODE_PATH=…/tests/playwright/node_modules` oder müssen dort liegen.
- **Tests gegen den Bug validieren**, nicht nur „grün" melden: beide P1-Fixes
  wurden durch Zurückbauen des Fehlers geprüft.
- Der PHP-Built-in-Server überlebt keine Tool-Aufruf-Grenze — dasselbe
  Runner-Muster gilt für k6-Läufe.
