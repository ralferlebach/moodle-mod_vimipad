# Arbeitsplanung / Backlog

> **Stand: 0.9.0 (MATURITY_BETA).** Autoritativ für den *Umsetzungsstand* ist das
> [`CHANGELOG.md`](../../CHANGELOG.md), für die *Versionsplanung* die
> [roadmap.md](roadmap.md). Dieses Dokument fasst nur die *nächsten*
> Arbeitsschritte zusammen; erledigte Historie steht im Changelog.

Lebendes Planungsdokument. Reihenfolge = grobe Priorität, nicht fix.

## Laufend: 0.9.x — Beta-Validierung

Der Beta-Schnitt `0.9.0` ist erfolgt (Kern und alle 19 gebündelten Subplugins auf
`MATURITY_BETA`). Der Härtungs-Arc (0.7.x) und der Darstellungsform-Arc (0.8.x)
sind code-seitig abgeschlossen; die P1-Befunde des externen 0.8.32-Audits sind
in 0.8.33–0.8.35 geschlossen (Details im CHANGELOG). Offen ist überwiegend
**empirischer** Reifenachweis, kein Code-Befund:

1. **Feldvalidierung in echten Kursen** — der eigentliche Zweck der Beta.
2. **Barrierefreiheits-Audit** (siehe [barrierearmut.md](barrierearmut.md)):
   Tastaturbedienung, Fokusführung, Dialog-/Escape-Verhalten, Listenansicht ohne
   Canvas — geprüft mit echten Hilfsmitteln (NVDA/JAWS/VoiceOver), nicht nur axe.
3. **Browser-Matrix für Playwright**: derzeit nur Chromium/Moodle 4.5. Nightly
   um Firefox und WebKit sowie um Moodle 5.2 erweitern.
4. **Echte Collaboration-Races in Playwright**: stale-revision-Update, Delete
   vs. Update, Relation-Retarget vs. Node-Delete, Lease-Übernahme nach
   Netzausfall, Offline-/Resync-Verhalten.
5. **Realistischeres Lastprofil (k6)**: `poll_changes` fehlt bislang völlig;
   sinnvoll sind getrennte Profile für Classroom-Start, Idle-Polling, aktive
   Kollaboration, Spike und Soak sowie eine Map-Größen-Matrix (small/medium/
   large). Der bestehende Read-Endpoint-Test bleibt als Stress-Test bestehen.
6. **jMeter-Plateauphase**: statt fester Loop-Zahl mit Scheduler und Dauer
   fahren, damit wirklich 25 VUs gleichzeitig arbeiten.
7. **Arrange-Engine unter Last messen** (Playwright, 100/250/500/1000 Knoten):
   Zeit bis zur Bedienbarkeit, keine NaN/Infinity, fixierte Knoten unverändert,
   zweiter Lauf konvergiert. Jest kann das Einfrieren des Event-Loops nicht sehen.
8. **Stored-XSS-Matrix** über alle Textfelder (Node/Relation/Container/Journal/
   Peer-Review/Annotation/Feedback/KI/Import) — PHPUnit für den Speicher-/
   Formatvertrag, Playwright oder Behat für „wird nirgends ausführbar gerendert".
9. **Moodle 5.1 im Release-/Nightly-Gate** — `supported = [405, 502]` schließt
   5.1 ein, die CI testet aber nur 4.5/5.0/5.2.
10. **Lasttest-Token als Secret** in `load.yml` (`::add-mask::`, kurzlebiger
    Token, Testsite nach dem Lauf verwerfen).

### Kann bis Stable warten

- Paginierung *aller* übrigen Lehrendenlisten (die produktionskritischen sind in
  0.9.0 paginiert: Submissionübersicht, Journalhistorie, Statistik-Übersicht).
- Gradebook-Ad-hoc-Task bei sehr großen Kohorten (`grading_service` iteriert
  bewusst über Empfänger) — erst messen, dann ggf. chunken.
- Vollständige Lokalisierung technischer Exception-Texte (Programmer-Errors
  dürfen englisch bleiben; benutzer-/API-ausgelöste Validierung nicht).
- Aufräumen historischer „legacy/pre-migration"-Kommentare und des zugehörigen
  Kompatibilitätscodes — vor 1.0 bewusst entscheiden, ob es ausgelieferte Daten
  gibt, mit denen Kompatibilität bestehen muss (vor dem ersten öffentlichen
  Release: nein).

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
