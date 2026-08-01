# Arbeitsplanung / Backlog

> **Stand: 0.7.30.** Autoritativ für den *Umsetzungsstand* ist das
> [`CHANGELOG.md`](../../CHANGELOG.md), für die *Versionsplanung* die
> [roadmap.md](roadmap.md). Dieses Dokument fasst nur die *nächsten*
> Arbeitsschritte zusammen; erledigte Historie steht im Changelog.

Lebendes Planungsdokument. Reihenfolge = grobe Priorität, nicht fix.

## Laufend: 0.7.x — Hardening, Datenschutz & Persistenz

Der Datenschutz- (Privacy API) und Backup/Restore-Kern ist umgesetzt; 0.7.x
prüft und vervollständigt. Offen in dieser Phase:

1. **Systematischer Security-Review** (dokumentierter Durchgang):
   - XSS beim Label-/Text-Rendering (Nodes, Relationen, Container, Journal).
   - `sesskey`/Capability-/Kontext-Prüfung in *jeder* External-Function,
     inklusive Gruppen-/Fremdzugriff (IDOR).
   - Eingabevalidierung aller External-Function-Parameter.
   - Ergebnis als Stored-XSS/IDOR-Matrix dokumentieren.
2. **Allgemeines Code-Hardening**: Fehlerbehandlung, Performance (keine
   N+1-Abfragen in Übersichten/Dashboards), Determinismus.
3. **Stabile öffentliche API** als Voraussetzung für die abgeleiteten Plugins
   (0.9.x): Namespace-Trennung `\mod_vimipad\api\*` / `\mod_vimipad\profile\*`
   (stabil, öffentlich) gegenüber `\mod_vimipad\local\*` (intern); einbettbarer
   Editor mit `mount(element, config)`-Entrypoint und austauschbarem
   Persistenz-Adapter; kontextfrei aufrufbare Profilvalidierung.

## Voraussetzung für die Beta (0.8.0)

Der Code-seitige Härtungsarc (0.7.x) ist abgeschlossen; das abschließende Audit
gibt für kontrollierten Beta-/Pilotbetrieb GO. Offen ist der **empirische**
Reifenachweis (kein Code-Befund):

- Concurrency-/Last-Tests (nebenläufige Operationen, Poll/Lock unter Last).
- Replay-Benchmarks (1k/5k/10k/20k/100k Operationen, kleine/große Maps,
  sequentielles Scrubbing vs. zufällige Sprünge) zur empirischen Bestätigung des
  begrenzten Checkpoint-/Replay-Modells.
- Accessibility-Durchgang (siehe [barrierearmut.md](barrierearmut.md)):
  Tastaturbedienung, ARIA, Listen-/Tabellen-Alternative.
- Browser-/DB-Matrix sowie Upgrade-/Backup-Restore-Tests in der CI.

### Kleine, nicht-blockierende Restpunkte aus dem 0.7.30-Audit (für 0.8.x)

- **Heartbeat `renew()` vollständig CAS-sicher machen.** `acquire()` (inkl.
  Owner-Renewal) ist CAS-abgesichert; `renew()` nutzt nach dem Read noch ein
  unbedingtes `set_field()` nach ID. Bei sehr engem Ablauf-/Takeover-Interleaving
  könnte der alte Client fälschlich `acquired=true` erhalten. Betrifft nur den
  advisory Presence-Lock, nicht die semantische Datenintegrität (Workspace-Lock
  + Revision sichern diese zusätzlich ab).
- **Optionale Erweiterung der `get_operations`-Testabdeckung** um Gruppen-,
  Course-Workspace-, Cross-Activity-, Guest-, unenrolled- und suspended-Fälle
  (der grundlegende Contract-/Zugriffstest ist vorhanden).
- **Gastzugriff auf Course-Workspaces** als bewusste Produkt-/Datenschutz-
  entscheidung dokumentieren oder einschränken.

## Als Nächstes (0.6.x-Autoren-Werkzeuge, teils vorgezogen)

- **Vorlagen/Templates durch Lehrende** - erfordert vorab ein serverseitiges
  Constraint-/Policy-Modell (nicht nur UI-Buttons verstecken). Das differenzierte
  Element-Sperr-Modell (0.7.23) ist ein Baustein davon.
- **Bifurkations-Routing** gemäß `connector-styles.md`: gemeinsame Abzweige/
  Sammelbusse (Tree/Argument/Fishbone/Timeline) sowie radiale/individuelle
  Führung.

## Backlog (spätere Ausbaustufen)

- **Bewertung anhand *mehrerer* Musterlösungen:** Einzahl ist umgesetzt
  (`vimipadassess`, Scorer, `gradingform`, KI-Feedback). Der Contract ist
  mehrzahlfähig, DB/Orchestrierung derzeit singular (`referencesnapshotid`).
- **Weitere Darstellungsformen** (0.8.x): argument, process/flow, fishbone,
  timeline, vennsets, systems, affinity - jeweils als `vimipadform`-Subplugin.
- **Abgeleitete Plugins** (nach 0.9.x, auf Basis der stabilen API):
  `qtype_vimipad`, `datafield_vimipad`, Block-/Kursformat-Viewer, Mobile-Handler.

## Bezug zu weiteren Dokumenten

Die versionierte Gesamtplanung bis 1.0 steht in [roadmap.md](roadmap.md); der
Umsetzungsstand im [CHANGELOG.md](../../CHANGELOG.md). Strategie und
Fachanforderungen liegen unter `docs/materials/`.
