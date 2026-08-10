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
in 0.8.33–0.8.35 geschlossen (Details im CHANGELOG). Offen in dieser Stufe ist
zweierlei: der **empirische Reifenachweis** (Punkte 1–10, überwiegend Mess- und
Testarbeit) und eine Reihe konkreter **Code-Arbeiten** (Punkte 11–14), die
bewusst *nicht* auf Stable verschoben werden — eine Beta soll bereits nahe am
Produktionsniveau sein:

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

11. **Paginierung aller übrigen Lehrenden-/Übersichtslisten.** In 0.9.0 sind die
    produktionskritischen Ansichten paginiert (Submissionübersicht,
    Journalhistorie, Statistik-Übersicht). Der Rest folgt in dieser Stufe: eine
    Beta soll bereits Produktionsniveau haben, und unpaginierte Listen sind
    nicht produktionstauglich. Betroffen sind unter anderem Reviewer-/
    Allokationsübersichten, Annotationslisten pro Snapshot und alle künftig
    hinzukommenden Tabellen. Regel ab jetzt: **jede neue Liste, deren Länge mit
    Kohorte, Kursdauer oder Mapgröße wächst, wird paginiert geliefert** — nicht
    nachträglich.

12. **Gradebook bei sehr großen Kohorten.** `grading_service` ruft
    `vimipad_update_grades()` je Empfänger auf — kein N+1-Versehen, sondern eine
    bewusste Moodle-API-Schleife, aber bei 500–2000 Empfängern ein langer
    synchroner Request. Vorgehen in dieser Stufe: **zuerst messen** (Laufzeit
    bei 100 / 500 / 1000 / 2000 Empfängern), dann entscheiden zwischen Chunking,
    Ad-hoc-Task und Reconciliation. Ohne Messung nicht umbauen.

13. **Lokalisierung der Exception-Texte.** Zu unterscheiden:
    *Programmer-Errors* (z. B. „Unknown stable id kind") dürfen englisch und
    hartkodiert bleiben — sie erreichen keine Endnutzer. *Durch Benutzereingabe
    oder API ausgelöste Validierungsfehler* (z. B. „Invalid node shape",
    „Invalid relation direction", „layoutjson must be valid JSON") gehören in
    Moodle-Language-Strings. Betroffen v. a. `operation_type.php`,
    `node_style.php`, `save_layout.php`, `apply_operation.php`,
    `import_service.php`, `api/map.php`. Da diese Texte in einer Beta bereits
    Nutzern begegnen können, gehört das in 0.9.x, nicht nach Stable.

14. **Historische „legacy/pre-migration"-Kommentare und den zugehörigen
    Kompatibilitätscode auflösen.** Erst die inhaltliche Entscheidung, dann das
    Aufräumen: Gibt es **ausgelieferte Daten**, mit denen Kompatibilität bestehen
    muss? Vor dem ersten öffentlichen Release lautet die Antwort **nein** — es
    gab keine öffentliche Vorversion. Deshalb ist jetzt der richtige Zeitpunkt,
    diese Pre-Release-Kompatibilität **nicht künstlich zu konservieren**: nicht
    nur die Kommentare entfernen, sondern auch den Migrations-/Fallback-Code
    (u. a. „from the former timestamp scheme on upgrade", „legacy transport",
    „legacy bare layout"). Nach dem ersten öffentlichen Release ist dieses
    Fenster zu.

### Chatty-Review des 0.9.0-Release-ZIPs (Endurteil: GO nach drei P1-Punkten)

Externer Review des ausgelieferten Pakets. Urteil: keine P0, kein
Security-Grundfehler, kein Architekturumbau; Architektur/Security/Datenmodell/
Paketierung beta-reif. Drei P1-Punkte vor `0.9.0-beta1`, dazu P2-Politur und
0.9.x-Absicherungen.

**P1 — vor `0.9.0-beta1`:**

15. **Paginiertes Live-Laden ist unter Nebenläufigkeit nicht konsistent.**
    `get_workspace_elements` nutzt OFFSET-Pagination (`id ASC`, `$offset/$limit`).
    Wird zwischen zwei Seiten ein Element gelöscht, verschiebt sich das Fenster
    und **eine ID wird übersprungen** — der Poll ab Revision R liefert dafür
    keine Operation, das Element fehlt bis zum Reload. Fix: konsistenter
    Ladezeitraum über **Revision-Pinning** — Metadaten-Revision vor und nach dem
    Seitenladen vergleichen (R1==R2, sonst begrenzter Retry), Polling erst ab der
    bestätigten Revision starten. Architektonisch sauberer zusätzlich
    **Keyset-Pagination** (`WHERE id > :lastid ORDER BY id LIMIT n`), die den
    OFFSET-Shift grundsätzlich vermeidet; die Revisionsprüfung bleibt trotzdem,
    weil Nodes/Relations/Container parallel geladen werden. Tests: PHP-/JS-
    Contract (löschen zwischen Seite 1 und 2 → nach Retry konsistent) und
    Playwright (B löscht während A eine >500-Node-Map lädt → A konvergiert).

16. **Read-only-Lehrkraft kann fremde Map laden, aber nicht live pollen.**
    `poll_changes` nutzt `validate_workspace_for_edit()` und ist in
    `db/services.php` mit `editown/editgroup` deklariert, obwohl `type=read` —
    bei fremder Individual-Map führt das zu `error:notownworkspace`. Der
    Frontend-Vertrag (`readonlyGuard`: „reads and polling pass through") wird
    dadurch gebrochen. Fix: `poll_changes` auf `validate_workspace_for_read()`
    umstellen und die Service-Capability auf `mod/vimipad:view` setzen (die
    Read-Validierung verlangt bei fremder Individual-Map ohnehin zusätzlich
    `grade`); Locks/Schreiboperationen bleiben edit-geschützt. Tests: PHPUnit
    (Grader pollt Lernenden-WS erlaubt, fremder Student abgewiesen) + Playwright
    (Lehrkraft sieht Änderung live, kann selbst nicht schreiben).

17. **Gast-Policy vs. dokumentierte Sicherheitszusage entscheiden.**
    `helper.php` dokumentiert „cross-course/guest access is intentionally
    refused", aber `db/access.php` gibt `mod/vimipad:view` an `guest =>
    CAP_ALLOW`, und Course-Workspaces gelten in `validate_workspace_for_read()`
    als `isown`. Damit könnte ein Gast einen Course-Workspace lesen. Entscheidung
    nötig: Policy behalten → `guest => CAP_ALLOW` entfernen bzw. Gäste in der
    Read-Policy explizit sperren, plus Positiv-Test „Gast im Gastzugang-Kurs wird
    an get_workspace/get_operations/get_workspace_elements abgewiesen". Oder
    Gastzugriff gewollt → Kommentar + Privacy-Doku angleichen und expliziten
    Gast-Erlaubt-Test ergänzen. (Der vorhandene `test_guest_is_rejected` prüft
    nur den fremden Individual-WS, beweist die Course-WS-Aussage also nicht.)

**P2 — im selben Release mitnehmen:**

18. README-Verweis auf `docs/beta/beta-testing.md` reparieren (docs/ wird nicht
    ausgeliefert): entweder vollständigen GitHub-Link setzen oder genau diese
    öffentliche Anleitung mitliefern.
19. `db/upgrade.php` auf echten 0.9.0-Urknall reduzieren (`return true;`);
    `install.xml` ist die Baseline `2026080800`, die reale Upgradehistorie
    beginnt mit `0.9.0 → 0.9.1`. Konserviert sonst die interne Vor-Geschichte,
    die ausdrücklich kein Produktvertrag ist.
20. Öffentlichen Changelog bei `0.9.0` neu beginnen lassen (First public beta);
    die vollständige experimentelle Vorhistorie bleibt im Git/den internen docs.
21. `phpunit.xml` aus dem Release ausschließen oder auf existierende Plugin-
    Dateien reduzieren (enthält Coverage-Pfade zu nicht vorhandenen Dateien wie
    `externallib.php`, `locallib.php`, `renderer.php`, `rsslib.php`).
22. `npm test`/ausgeschlossene Jest-Tests dokumentarisch auflösen: `package.json`
    referenziert `js/tests`, das nicht ausgeliefert wird — im README vermerken,
    dass `npm test` nur im Repository läuft.
23. Falschen Kommentar in `amd/src/init.js` korrigieren („bundled into
    js/build/" → tatsächlich `amd/build/editor_lazy.min.js`).
24. `version.php`-Kommentar präzisieren: „Current supported range: 4.5–5.2;
    planned target: 5.3" (deklariert ist `supported = [405, 502]`).

**0.9.x-Absicherungen (Erweiterung der bestehenden Testpunkte 1–10):**

25. Playwright-Muss-Szenarien: Pagination-Race, Read-only-Teacher-Liveview,
    echte stale-revision-Race (Conflict→Resync→Konvergenz), Lease-Takeover nach
    Offline, Network-Recovery (Poll-Timeout/500/offline→online/verzögerte
    Response) ohne Datenverlust/Duplikate/Endlosschleife.
26. Arrange-Performance-Fixtures S/M/L/XL (50–1000 Nodes) plus crossing-heavy:
    keine NaN/Infinity/absurde Koordinaten, Fixpunkte unverändert,
    Bewegungsbudget eingehalten, Container-Members erhalten, zweiter Lauf
    verändert weniger, terminiert, UI danach responsiv. Web Worker nur, falls die
    Messung zeigt, dass der Main Thread zu lange blockiert — nicht vorher.
27. k6-Betriebsprofile: Classroom-Start (100 Nutzer öffnen in 10–20 s →
    get_workspace + Elementseiten → Polling), Idle-Collaboration (100 ×
    poll_changes alle 2–5 s, 30–60 min), Mixed-Writer (Poller + aktive Editoren
    über apply_operation/Locks/save_layout), große Revisionshistorie
    (get_revision_state bei 2k/5k/10k/20k Operationen). Schwellen weiterhin:
    HTTP-Fehlerrate == 0, fachliche Exception == 0, Latenz statistisch (p95).
28. Property-/Fuzz-Tests (Jest) für den Solver: deterministisch geseedete
    Zufallsgraphen mit Invarianten (endliche Koordinaten, keine NaN/Infinity,
    Fixpunkte fest, movement ≤ Budget, keine akzeptierte Iteration erhöht die
    Gesamtenergie, gleiche Eingabe → gleiches Ergebnis) plus Worst-case-Crossing;
    Pagination-Aggregator (R1≠R2 → discard → retry) in Jest absichern.
29. Behat ergänzen ohne Playwright-Doppelung: Guest-Zugriff, Submission,
    Consensus, Completion, Peer Review, Capability-Overrides, ggf. AI-Konfig.

### Neue Feature-Wünsche (Ralf)

30. **Revision-Playback für Lehrende bei der Bewertung + Geschwindigkeitsstufen.**
    Der Revision-Player (bisher im Journal-Tab) soll Lehrenden auch im
    Bewertungskontext zur Verfügung stehen, damit die Entstehung einer Map
    nachvollzogen werden kann. Zusätzlich Geschwindigkeits-Buttons **1× / 10× /
    100×** (1, 10, 100 Schritte pro Sekunde), damit lange Bearbeitungen nicht
    Stunden Abspielzeit brauchen. Umsetzung: Player-Komponente
    kontextunabhängig verfügbar machen (Grading-Panel bindet sie an die
    Snapshot-Historie), Abspiel-Taktung konfigurierbar (Batch mehrerer Ops pro
    Tick bei 10×/100× statt schnellerem Timer, um die Renderlast zu begrenzen).
    Read-only, keine Mutation.

31. **Admin-Benachrichtigung bei der Installation.** Beim Installieren (bzw.
    beim ersten Upgrade auf die Beta) eine E-Mail an den Instanz-Admin senden,
    die (a) für die Installation der Beta dankt, (b) das Plugin kurz vorstellt,
    (c) ausdrücklich um Mithilfe beim Praxistest und um Issue-Rückmeldungen über
    GitHub bittet. Umsetzung Moodle-idiomatisch: Versand in einem
    Post-Install-Hook, Empfänger `get_admin()`, `email_to_user()` mit
    Language-Strings (EN/DE), einmalig (Config-Flag `installmailsent`), Link auf
    den GitHub-Issue-Tracker. Beim Reduzieren von `db/upgrade.php` auf den
    Urknall (Punkt 19) mitdenken: der Post-Install-Hook ist der saubere Ort.

## Offene Einzelthemen (nicht stufengebunden)

- **Bifurkations-Routing** gemäß `connector-styles.md`: gemeinsame Abzweige/
  Sammelbusse (Tree/Argument/Fishbone/Timeline) sowie radiale/individuelle
  Führung. Teilweise umgesetzt; der Vollausbau steht noch aus.

## Backlog (spätere Ausbaustufen)

- **Bewertung anhand *mehrerer* Musterlösungen:** Einzahl ist umgesetzt
  (`vimipadassess`, Scorer, `gradingform`, KI-Feedback). Der Contract ist
  mehrzahlfähig, DB/Orchestrierung derzeit singular (`referencesnapshotid`).
- **Abgeleitete Plugins** (nach 0.9.x, auf Basis der stabilen API):
  `qtype_vimipad`, `datafield_vimipad`, Block-/Kursformat-Viewer, Mobile-Handler.

## Bezug zu weiteren Dokumenten

Die versionierte Gesamtplanung bis 1.0 steht in [roadmap.md](roadmap.md); der
Umsetzungsstand im [CHANGELOG.md](../../CHANGELOG.md). Strategie und
Fachanforderungen liegen unter `docs/materials/`.
