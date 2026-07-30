# Session 003 — Authoring-Tools & Brushing-Up Code (0.5.x-Closure)

**Chat:** „003 – Authoring-Tools and Brushing-Up Code"
**Bogen:** 0.5.32 (2026072712) → **0.6.24** (2026072738)
**Verifikation:** real auf Moodle 4.5.12 + PostgreSQL (PHPUnit); Frontend: tsc/Jest/esbuild + Byte-Reproduzierbarkeit (kein visuelles Rendering/Behat in der Sandbox).

> Fortsetzung von [`session-002.md`](session-002.md). Arbeitsgrundlage:
> [`session-003-arbeitsplan.md`](session-003-arbeitsplan.md) (Gate-Liste aus dem
> 0.5.32-Audit, real gegen den Code verifiziert).

---

### Was wurde erledigt?

**Rückwirkende Doku-Vervollständigung & Prozessfix**
- `session-002.md` angelegt (0.4.22→0.5.32); `session-001.md` um eine
  Scope-/Boundary-Notiz ergänzt (deckt Projektstart→0.2.15; die „Session 002"-
  Nachtrag-Marken sind Chat-Fortsetzung, kein zweites Dokument).
- **Ursache der fehlenden `session-002.md` gefunden:** die Session-Doku hing an
  einem manuellen `cat >> session-001.md`-Habit (belegt im 001-Chat), nicht an
  `sessionende.txt`. Als der Habit entfiel, erzwang nichts die persistierte
  Datei; das CHANGELOG absorbierte den Detailstand.
- **Fix in `docs/prompt-templates/sessionende.txt`:** persistierter
  `docs/sessions/session-<NR>.md` ist jetzt ein **verpflichtender, im ZIP
  mitgelieferter, per Gate verifizierter** Schritt (eine Datei je Session, nie an
  frühere anhängen, Existenz-/Inhalts-Check + ZIP-Check vor „fertig"). Die
  Carry-forward-Zeilen für `sessionstart.txt` ersetzen den Report ausdrücklich
  nicht.

**0.5.33 — 0.5.x-Closure (Concurrency-Gate + fail-closed + Doku)**
- **P0 Concurrency:** neuer `classes/local/lock/workspace_writelock.php` (Key
  `write_<workspaceid>`); alle semantischen Mutationen + Snapshot-Erzeugung
  serialisieren darauf: `operation_service::apply` (in Lock-Wrapper + lock-freies
  `apply_locked` gesplittet), `import_service` (hält den Lock einmal, nutzt
  `apply_locked`), `workspace_service::reopen`, `snapshot_service::begin_submission`
  (vorher separater `submit_`-Key → jetzt gemeinsamer `write_`-Key).
- **P1 fail-closed:** `workspace_service::create_unique` erzeugt bei Lock-
  Fehlschlag keine zweite Workspace mehr (re-read/Concurrency-Fehler).
- **E-Entscheide (B/A/B/A/B/A) eingearbeitet, soweit Doku/Contract:** E1-B
  (eine Referenz je Aktivität; Notiz in `assessment_architecture.md`), E2-A
  (Leases advisory; `lock_service`-Docblock), E6 (Import-Atomarität explizit:
  Semantik atomar, Layout best-effort).
- **Test:** `tests/workspace_writelock_test.php` (Acquire/Release, Key-Distinktheit
  je Workspace).
- Doku-Re-Baseline aus dieser + der Vorbereitungsrunde: roadmap/backlog/
  connector-styles/ui_reorder_plan/test-setup/visual-maps/README.

**0.5.34 — Closure Teil 2 (duedate/late, map_updated-Event, Peer-Scope)**
- `snapshot_service::is_late($instance, $submittedtime)`: soft `duedate`-
  Auswertung (0 = nie verspätet); harte Grenze bleibt `cutoffdate`. Badge
  „Verspätet" + Abgabezeit in der Bewertungsansicht (`grading_panel::render`).
- Neues Event `\mod_vimipad\event\map_updated` (crud `u`), je Operation in
  `apply_operation` gefeuert → Kurs-Logs/Reporting.
- Peer-Scope (E3-B) festgeschrieben: Kern bleibt schlanke Basis; 5-Phasen-
  Aktivitätsworkflow → Premium `vimipadreview_peerplus` nach 1.0 (Roadmap-Text).
- Lang: `event:map_updated`, `gradetab:late`, `gradetab:submittedon` (en/de 400/400).
- Test `tests/duedate_and_events_test.php` (is_late-Fälle + map_updated via Sink).

**0.6.1 — Autoren-Grundlage (Container-Vertrag + Import-Round-Trip)**
- 5 neue Operationstypen `container_create/update/delete`, `membership_add/remove`
  in `operation_type` (Validierung: `itemtype`-Enum, int-ähnliches `sortorder`)
  + Handler in `operation_service::mutate` (create revived soft-deleted;
  membership_add = Upsert; container_delete soft + Memberships weg). Gleicher
  Write-Lock/Revisions-Pfad wie Node/Relation.
- Import round-trippt jetzt Container + Memberships: Remap der Container-Stableids
  und Member-Referenzen (Node/Relation/Container); Relation-Stableids landen jetzt
  auch im idmap. XML-Parse um `containers`/`memberships` erweitert; `import_map`
  liefert die Zählungen. Kein Formatwechsel nötig (Export trug Container längst).
- Lang: `error:containernotfound` (en/de 401/401).
- Template-/Constraint-Policy spezifiziert (`docs/design/template_constraint_policy.md`,
  Umsetzung über 0.6.x): weiche Constraints zur Edit-Zeit via geteiltem Resolver,
  hartes Gate zur Abgabe; Template-Sperren per Element-`metadatajson` in `apply_locked`.
- Test `tests/container_operations_test.php` (Lebenszyklus + Import-Round-Trip).

**0.6.2 - Constraint-Policy-Engine + hartes Abgabe-Gate**
- Neues Paket `\mod_vimipad\local\policy`: `constraint_config`
  (`from_instance` liest Pflicht-/Verbotsbegriffe, erlaubte Typen, min Node/
  Relation - heute No-op, aktiv sobald die Felder da sind), reiner
  `constraint_policy::evaluate()` + `constraint_report` (`is_satisfied()`,
  lokalisierte `messages()`/`summary()`).
- Hartes Gate in `snapshot_service::create_submission` (unter dem Write-Lock):
  Abgabe wird mit `error:constraintsnotmet` (Verstossliste) verweigert.
- Lang: `error:constraintsnotmet` + `constraint:*` (en/de 407/407).
- Test `tests/constraint_policy_test.php`: alle Constraint-Arten + End-to-End-
  Gate (blockiert ungueltige Abgabe, laesst korrigierte durch).
- Eingabefelder (Schema/Form/Backup) fuer die qualitativen Constraints -> 0.6.3
  (dann bekommt das Gate produktiv Zaehne).

**0.6.3 - Constraint-Eingabefelder (Gate wird produktiv scharf)**
- 5 Instanzfelder: requiredconcepts, forbiddenconcepts, allowedrelationtypes
  (Textareas), minnodes, minrelations (Zahl) - in `mod_form` (eigene Sektion
  "Vorgaben an die Map"), `db/install.xml`, `db/upgrade.php` (Savepoint
  2026072717, guarded `add_field`) und Backup-Feldliste; Restore automatisch.
- `constraint_config::from_instance` las diese Felder bereits -> das 0.6.2-Gate
  ist ohne weitere Verdrahtung produktiv aktiv.
- Lang: Sektion + 5 Labels + 5 _help (en/de 418/418).
- `backup_restore_test` prueft jetzt den Round-Trip der 5 Felder;
  neuer Gate-Test aus real gespeicherten Einstellungen.

**0.6.4 - Template-Struktursperren (Durchsetzung) + Lint-Fix**
- Elemente mit metadata `{"locked": true}` sind gegen Loeschen geschuetzt und
  nur in den `editable`-Feldern aenderbar (sonst `error:elementlocked`).
  Durchgesetzt in `operation_service` fuer node/relation/container update+delete
  und relation_retarget; Create und ungesperrte Elemente unberuehrt, Import
  unberuehrt (nur Create).
- Lang: `error:elementlocked` (en/de 419/419).
- Lint-Fix: Inline-Kommentar in `version.php` beginnt gross; ganzes Plugin
  phpcs-clean (moodle-Standard, `--ignore=tools/`).
- Test `tests/element_lock_test.php` (Loeschen/Update gesperrt, Whitelist,
  ungesperrt frei, gesperrte Relation).

**0.6.5 - Nicht-blockierender Constraint-Status-Endpoint**
- Neue externe Funktion `mod_vimipad_get_constraint_status` (read, ajax):
  wertet die Live-Map mit `constraint_policy` aus und liefert `configured`,
  `satisfied`, lokalisierte `messages` sowie strukturiert `requiredmissing`/
  `forbiddenpresent`/`typeviolations` (fuer spaeteres Element-Highlighting).
  View-Capability; fremde Map -> grade. Mutiert nicht, blockiert nicht.
- Registrierung in `db/services.php`; Test `tests/get_constraint_status_test.php`
  (isoliert, Rueckgabe via `clean_returnvalue` gegen die Struktur validiert).
- Editor-Anzeige (Hinweis-Banner, ruft den Endpoint debounced) = Frontend-Folgeschritt.

**0.6.6 - Read-Zugriffskontrolle entdoppelt (phpcpd)**
- Neuer `helper::validate_workspace_for_read()` (Spiegel von
  `validate_workspace_for_edit`): cmid -> Kontext -> Instanz -> Workspace,
  Kontext-Validierung, `view`-Capability und die Eigen-oder-grade-Regel an einer
  Stelle. `get_revision_state` und `get_constraint_status` nutzen ihn statt eigener
  Kopie -> sicherheitsrelevanter Block kann nicht mehr driften.
- phpcpd (`--min-lines 5 --min-tokens 70`): **keine Klone** (vorher 1 Klon, 18 Zeilen).

**0.6.7 - Release-CI: Moodle-5.2-`public/`-Pfad (CI-only, kein Plugin-Code)**
- Merge in `main` triggert `moodle-release.yml` (Matrix inkl. MOODLE_502). 5.2
  hat den getrennten `public/`-Ordner -> Plugin unter `moodle/public/mod/vimipad/`.
  Die fest verdrahteten 4.x-Pfade liessen den 502-Job bei „Verify editor bundle
  installed" (`test -f`) fallen; der Dev-CI war grün, weil dort die AMD-Steps in
  einem 405-only-Job laufen.
- Fix: „Resolve plugin path"-Step erkennt `public/`; Bundle-Existenzpruefung
  nutzt den aufgeloesten Pfad (alle Versionen); `npm install`+`npx grunt amd`+
  Folge-Verify sind auf Nicht-`public` (4.x/5.0) begrenzt (dort stimmen die
  Pfade), auf 5.2 uebernimmt die AMD-Reproduzierbarkeit der versionsunabhaengige
  „Bundle reproducibility"-Job. `moodle-ci.yml` unveraendert.

**0.6.8 - Editor: weiche Constraint-Hinweise (Frontend-Autoren 1/3)**
- Erster der drei Frontend/Canvas-Punkte. Der Editor zeigt die Map-Vorgaben als
  weiche, nicht-blockierende Hinweise (Endpoint `get_constraint_status` aus 0.6.5).
- API-Methode `getConstraintStatus` + Typ `ConstraintStatus`; Hook
  `useConstraintHints` (600 ms debounced, Burst -> 1 Request, latest-wins,
  Fehler geschluckt; nur eigener, offener Map); Komponente `ConstraintBanner`
  (nichts, wenn nicht konfiguriert/erfuellt; sonst Warn-Alert mit backend-
  lokalisierten Messages), in `EditorApp` ueber dem Canvas.
- Lang `constraint:hintsheading` (en/de 420/420) + init.js-Keyliste + mount-Fallback.
- Verifiziert: tsc clean; Jest 25 Suites/155 (neu: constraint_status_api,
  use_constraint_hints [Fake-Timer], constraint_banner); Bundle reproduzierbar;
  PHP unveraendert (200+97), phpcs clean. Kein visuelles/Behat-Rendering in Sandbox.
- Naechste: Container auf dem Canvas (2/3), dann Template-Lock-Editor (3/3).

**0.6.9 - Canvas: Container zeichnen (Frontend-Autoren 2/3)**
- Backend: `get_workspace` liefert jetzt `containers` (stableid/type/label/
  geometryjson/metadatajson), `VALUE_OPTIONAL` (get_revision_state bleibt valide).
  Test `get_workspace_containers_test` (geliefert + soft-deleted ausgeschlossen).
- Frontend: Typ `VimiContainer` + `containers` im State; Reducer add/update/
  deleteContainer; reiner Geometrie-Codec `container_geometry.ts`. `CanvasView`
  rendert Container hinter dem Graphen + isoliertes Draw-Overlay (nur im Tool-
  Modus aktiv, stoert nie die Node/Connect-Gesten); Toolbar-Toggle, Ziehen
  erzeugt, Eck-Button loescht. Remote/Undo-Redo via Container-Cases in
  `operationToAction`.
- Lang: editor:containers/containerdelete/drawcontainer/drawcontainerdone
  (en/de 424/424) + init.js + mount-Fallbacks.
- Verifiziert: tsc clean; Jest 28 Suites/169 (neu: container_geometry,
  container_reducer, container_apply_remote); Bundle reproduzierbar;
  202 mod_vimipad + 97 vimipadassess; phpcs/phpcpd clean.
- Naechste: Template-Lock-Editor (3/3).

**0.6.10 - Template-Lock-Editor (Frontend-Autoren 3/3, Abschluss)**
- Backend: `operation_service` bekommt `bypasslocks`-Konstruktor-Flag; gesetzt ->
  Lock-Durchsetzung uebersprungen. `apply_operation` uebergibt
  `has_capability('mod/vimipad:manageprofiles')` -> Autoren/Manager koennen Sperren
  setzen/aendern/entfernen und gesperrte Geruste bearbeiten, Lernende bleiben
  gebunden. `get_workspace` liefert `canmanage` (VALUE_OPTIONAL). Tests: Manager-
  Bypass (element_lock_test), canmanage (get_workspace_containers_test).
- Frontend: reiner `element_lock.ts` (locked/editable lesen/schreiben, andere
  Keys erhalten); `LockPanel` (Node/Relation-Liste mit Sperr-Toggle + "Umbenennen
  erlauben", nur bei canmanage); Lock-Badge an gesperrten Nodes.
- Lang: editor:node/templatelocks/templatelockshint/lockallowlabel (en/de 428/428).
- Verifiziert: tsc clean; Jest 30 Suites/179 (neu: element_lock, lock_panel);
  Bundle reproduzierbar; 204 mod_vimipad + 97 vimipadassess; phpcs/phpcpd clean.
- Damit sind die drei Frontend/Canvas-Autoren-Punkte (0.6.8 Hinweise,
  0.6.9 Container, 0.6.10 Sperren) abgeschlossen.

**0.6.11 - Container bearbeiten: verschieben/skalieren/umbenennen/sperren + Undo/Redo**
- Container haben jetzt eine Titelleiste (Ziehen = verschieben, Doppelklick =
  umbenennen) und ein Eck-Handle (Ziehen = skalieren, min-geclamped); Body bleibt
  nicht-interaktiv (Nodes darunter klickbar), Pointer-Capture isoliert die Geste.
  Commit als `container_update`.
- Volles Undo/Redo (create/delete seit 0.6.9; move/resize/rename neu) via
  History + operationToAction - wie bei Nodes/Relationen.
- `LockPanel` listet jetzt auch Container. Gesperrte Container zeigen Lernenden
  keine Affordanzen (Server lehnt ohnehin ab).
- Reine Helfer moveBox/resizeBox (getestet). Kein PHP-Code geaendert.
- Verifiziert: tsc clean; Jest 30 Suites/182; Bundle reproduzierbar; 204+97 grün;
  phpcs clean.
- Naechste: SVG/PNG-Export inkl. Container-Bounds + SVG-Round-Trip-Import.

**0.6.12 - SVG/PNG-Export inkl. Container + SVG-Round-Trip-Import**
- computeContentBounds rahmt jetzt auch Container-Geometrie -> SVG/PNG/PDF
  klippen keinen ueber die Nodes hinausgezeichneten Container mehr. Interaktions-
  Chrome (Draw-Overlay, Delete/Resize-Handles) wird beim SVG-Export entfernt.
- Exportiertes SVG bettet das Map-JSON in <metadata id="vimipad-data"> ein
  (Text-Node, jsdom-sicher, XML-escaped, kein CDATA). Import-Button akzeptiert
  jetzt auch .svg: eingebettetes JSON wird extrahiert und durch das bestehende
  importMap gefuehrt -> verlustfreier Round-Trip. SVG ohne Daten wird abgelehnt.
- Reine Helfer extractMapData/MAP_DATA_ID; serializeCanvasSvg(embedJson?);
  computeContentBounds(containers). Lang editor:importnovimidata (429/429).
- Kein PHP-Logikchange. JSON-Roundtrip inkl. Container ist bereits per PHPUnit
  gedeckt (test_export_import_roundtrip + test_container_roundtrip).
- Verifiziert: tsc clean; Jest 30 Suites/185; Bundle reproduzierbar; 205+97 grün;
  phpcs/phpcpd clean.
- Offen 0.6.x: containerControls an canmanage binden + Autoren-Werkzeuge in
  eigenen Bereich (folgt als 0.6.13).

**0.6.13 - Autoren-Werkzeuge gebuendelt + Container-Zeichnen autorenseitig**
- Draw-Container + LockPanel jetzt in einem klar abgegrenzten Bereich
  "Autoren-Werkzeuge" (role=group, beschriftet) statt inline zwischen den
  lernendenseitigen Node/Relations-Controls.
- Der ganze Bereich rendert nur bei canmanage - schliesst die Luecke, dass
  Container-Zeichnen zuvor fuer jeden Bearbeiter offen war (jetzt autorenseitig,
  konsistent zu move/resize/delete gesperrter Container).
- Lang editor:authortools (430/430). Kein PHP-Logikchange.
- Verifiziert: tsc clean; Jest 30 Suites/185; Bundle reproduzierbar; 205+97 grün;
  phpcs clean.
- Hinweis: PHPUnit-Zahl stabil 205 ueber 0.6.12/0.6.13 (die frueher notierte 204
  bei 0.6.11 war ein Messartefakt).

**0.6.14 - Revisionsansicht rekonstruiert Container (Restarbeit)**
- reconstruction_service spielt jetzt container_create/update/delete mit
  (neben Nodes/Relationen) und liefert ueberlebende Container zur angefragten
  Revision. get_revision_state befuellt das bereits deklarierte containers-Feld.
  Alte Revisionen zeigen jetzt ihre Container.
- Kein Frontend-Change: getRevisionState reicht den State durch, RevisionViewer
  rendert state.containers via CanvasView read-only -> Backend-Fix schliesst die
  Luecke allein.
- Neuer Test test_reconstruct_containers (create->update->create->delete ueber
  Revisionen). 206 mod_vimipad (+1) + 97 vimipadassess grün; phpcs/phpcpd clean;
  Bundle byte-identisch (kein JS geaendert).

**0.6.15 - UI-Aufraeumung (T1, T2, T6, A4, A5, A2-Verdacht)**
- T1 Ursache: amd/build/init.min.js war stale (wird von Moodles Grunt gebaut,
  NICHT von build.mjs; seit 0.6.7 nicht neu gebaut). Browser fragte veraltete
  STRING_KEYS -> init.js gab Rohschluessel zurueck.
  CI-LUECKE: Reproducibility-Job diffte nur editor_lazy; der Grunt-Schritt
  verglich sein Ergebnis nie mit dem committeten Artefakt und baute revision.js
  gar nicht -> stale Build lief gruen durch. CI baut jetzt beide AMD-Quellen und
  hat ein git-diff-Gate. Haertung: init.js liefert undefined, Bundle faellt auf
  eigene englische Fallbacks zurueck. Neuer amd_string_keys_test.
- T6/T2: Autoren-Bereich unter dem Canvas entfernt; Container-Button in der
  Toolbar zwischen Neu-anordnen und Export; Lock-Mode-Toggle mit Vollbild in
  rechter Toolbar-Gruppe. Beide nur bei manageprofiles.
- T3-Kern: Lock-Button im Element-Dock bei aktivem Lock-Mode; gesperrtes Element
  zeigt nur noch diesen Toggle.
- A4: Farb-Zwischenmenue weg, Reset als dritte Aktion im Farbwaehler.
- A5: Confirm-Buttons als outline-success mit gruenem Hover.
- A2 (Verdacht): Dock-foreignObject (300x320) jetzt pointer-events:none.
  BROWSER-PRUEFUNG noetig.
- Verifiziert: 207+97 grün; phpcs clean; tsc clean; Jest 31/189 (neu color_field);
  beide Bundles reproduzierbar.
- Offen aus dem Issue-Satz: A1, A3, A6, T3-Restteil (Mod-Setting fuer Lernende),
  T4 (Container formatier-/beschriftbar), T5 (Neu-Ausrichten respektiert Container).

**0.6.16 - A1: Zeiger-Abbildung respektiert das Seitenverhaeltnis**
- Ursache (aus Ralfs Konsolenwerten belegt): svg hat viewBox, aber kein
  preserveAspectRatio -> Default xMidYMid meet (uniforme Skalierung + Zentrierung).
  toSvgPoint teilte aber getrennt durch rect.width/rect.height, nahm also Fuellung
  an. Folge: falscher Offset UND falsche Skala, je Achse verschieden.
  Gemessen: Element 1138x213, viewBox 826x551 -> x-Skala um Faktor ~3.6 daneben.
  max-height:60vh haelt das Element auch normal kuerzer als die viewBox -> Fehler
  auch im Alltag.
- Fix: toSvgPoint nutzt getScreenCTM().inverse() (deckt auch CSS-Transforms ab);
  neues reines Modul canvas/viewport.ts bildet meet nach (Fallback/jsdom).
- Tests auf die realen Werte gepinnt (Skala, Zentrum, Drag-Geschwindigkeit,
  Nachweis des >3.5x-Fehlers der alten Rechnung).
- Verifiziert: 207+97; phpcs clean; tsc clean; Jest 32/195.
- Beobachtung fuer spaeter: view-Aspekt ist fest an CANVAS-Seitenverhaeltnis
  gekoppelt -> Letterbox-Raender. Anpassung an das Element-Seitenverhaeltnis
  waere eine reine UX-Verbesserung, aber ohne Browser-Sicht riskant.

**0.6.17 - A3: Verbinderwinkel + parallele Mehrfachverbinder**
- Ursache der schraegen Pfeilspitzen: relLinePath baute bei 'curved' eine Bezier
  mit IMMER vertikalen Kontrollpunkten -> Verbinder verliess/betrat Nodes stets
  senkrecht; marker orient=auto folgte dieser Endtangente.
- Neu nach Spezifikation: direkte Linie klassifiziert horizontal/vertikal; Lot
  der betreffenden Seite wird mit dem Linienwinkel halbiert -> Abgangs-/
  Ankunftswinkel ausserhalb der Node-Form. Pfad laeuft ARROW_STUB (12) gerade,
  dann Kurve, deren Handles dieselbe Richtung fortsetzen (glatte Uebergaenge).
  Pfeilspitze erbt dadurch automatisch den richtigen Winkel.
- Mehrfachverbinder: Gruppierung je Node-Paar (siblingSlots) + offsetAnchors
  verschiebt beide Anker symmetrisch senkrecht -> parallele Verbinder, auch bei
  geraden Linien.
- Neue reine Helfer: orientationOf, bisectAngles, connectorExitAngle,
  offsetAnchors, freeConnectorPath. 12 neue Unit-Tests.
- makefile von Ralf uebernommen (check ruft jetzt build -> haette T1 gefangen).
- Verifiziert: Jest 33/207; 207+97; phpcs/phpcpd clean; Bundles reproduzierbar.

**0.6.18 - A6: Enter erzeugt Zeile statt Spalte**
- Zwei Fehler zusammen: (1) das contentEditable trug labelBox(...) mit
  display:flex (row); Browser fuegen bei Enter je Zeile einen Block ein -> jeder
  Block wurde Flex-Item -> Zeilen nebeneinander = Spalten. Jetzt traegt ein
  Wrapper das Flex-Zentrieren, das editierbare Element bleibt Block.
  (2) onInput las textContent -> Blockgrenzen verschluckt ("A⏎B" -> "AB"),
  Umbrueche wurden nie gespeichert. Neues canvas/editable_text.ts laeuft den
  Baum ab und macht Blockgrenzen/<br> zu echten \n.
- nodeWidth/nodeHeight splitten laengst an \n - sie sahen nur nie eines; die
  Box waechst jetzt beim Tippen mit.
- 8 neue Tests (br, div-je-Zeile, verschachtelte Inline-Formate, p, leer, plus
  Nachweis dass textContent die Umbrueche verliert). Jest 34/215; 207+97.
- Bekannt rau: nodeHeight schaetzt ~7px/Zeichen fix -> mit A+ vergroesserter
  Schrift kann Text weiter ueberlaufen. Bewusst nicht geraten.

**0.6.19 - Verbinder-Labels folgen dem eigenen Parallel-Verbinder**
- Ursache: Label-Ebene berechnete den Anker als Mitte der Node-ZENTREN
  (positionOf) - eigener Pfad, der die Kanten-Anker und den Sibling-Versatz aus
  0.6.17 nie sah. Alle Labels eines Mehrfach-Paars landeten auf der Mittellinie.
- Fix: Label-Ebene leitet dieselben Kanten-Anker und denselben siblingOffsets-
  Slot ab wie die Linien-Ebene und setzt das Label per labelPoint auf den
  Kurvengipfel. Jedes Label auf seinem Verbinder; Einzelrelation bleibt mittig.
- 3 neue Tests. Jest 34/218; 207+97; phpcs clean.
- Env-Fallstrick dokumentiert: init.php self-updatet Composer -> 503 bricht ab;
  --no-composer-self-update nutzen.

**0.6.20 - Hinzufuegen-Menues zweizeilig**
- Add concept / Add relation nutzten Bootstrap form-inline (alles in einer Zeile).
  Jetzt gestapelt: Legende Zeile 1, Bedienelemente in eigener flex-Zeile
  (vimipad-control-line, umbricht bei Enge). mr-2 durch gap ersetzt.
- Nur Markup/CSS. Jest 34/218; 207+97; phpcs clean.

**0.6.21 - Fix "klebender" Node beim zweiten Klick**
- Symptom: Node anklicken, warten, erneut klicken -> Node folgt dem Zeiger ohne
  gedrueckte Taste; genau einmal, danach normal.
- Ursache: onNodePointerDown awaitete den Kollaborations-Lock (beginEdit,
  Netzwerk) VOR setPointerCapture/setDragId. Beim ersten Mal nach Pause ist der
  Lock nicht warm -> await gibt einen Tick frei; wird der Pointer in der Luecke
  losgelassen, laeuft onPointerUp bei dragId===null und raeumt nichts auf. Node
  bleibt scharf -> naechstes blosses pointermove verschiebt sie. Naechstes
  pointerup nullt dragId -> tritt danach nicht mehr auf.
- Fix: Drag UND Resize-Handle (gleiche Race) armen jetzt synchron; Lock im
  Hintergrund, bricht den Drag nur ab wenn verweigert und noch keine Bewegung.
  onPointerCancel/onLostPointerCapture raeumen immer auf.
- Reines Modul canvas/drag_arm.ts + 6 Tests (inkl. reproduzierter Race).
  Jest 35/224; 207+97; phpcs clean.

**0.6.22 - T3-Rest: Sperrmodus per Mod-Setting auch fuer Lernende**
- Neues Aktivitaets-Setting lockmodeforlearners (Default 0). Lehrende immer;
  mit Setting an auch Lernende (Toggle + Sperren/Entsperren).
- Semantik: aus = nur manageprofiles verwaltet Sperren, Lernende gebunden
  (unveraendert); an = kooperativ, apply_operation gibt allen Editierenden den
  Lock-Bypass. Im Hilfetext dokumentiert.
- Schema-Feld (install.xml + upgrade 2026072736), Backup-Element + Restore-
  Roundtrip-Assertion. get_workspace meldet das Flag; Frontend gated Lock-Mode +
  Lock-Buttons auf canManage ODER lockmodeforlearners, Container-Zeichnen bleibt
  autorenseitig.
- Neuer lockmode_for_learners_test. 210 (+3)+97; phpcs/phpcpd clean; 435/435;
  Jest 35/224; beide Bundles neu gebaut+reproduzierbar.
- Merke: Schemaaenderung wird von PHPUnit-init nur bei Versionssprung neu
  gebaut - version.php zuerst hochziehen.

**0.6.23 - T5: Neu-Ausrichten erhaelt Container-Zugehoerigkeit + Stylelint-Fix**
- T5: Re-Arrange ignorierte Container -> Knoten konnten rausfallen. Jetzt:
  Mitglieder je Container raeumlich schnappen (Zentrum), neu layouten, dann jeden
  nicht-leeren Container auf die Bounding-Box seiner Mitglieder (+Pad 24) refitten.
  Mitglieder bleiben drin, Container folgt; leere Container bleiben. Layout +
  alle container_update in EINEM Undo/Redo-Eintrag (runOps mischt __layout und
  container_update).
- Reine Helfer centerInBox + boundingBox (clamped auf MIN_CONTAINER_SIZE),
  6 Tests.
- CI-Stylelint: styles.css nutzte margin-right:0 !important (declaration-no-
  important). mr-2 aus Markup entfernt (gap spaced), !important-Regel geloescht.
  Lokal mit Moodles stylelint-Config geprueft (exit 0). gherkinlint war gruen.
- Verifiziert: 210+97; phpcs+stylelint clean; Jest 36/232; Bundle reproduzierbar.

**0.6.24 - T4: Container formatier- und beschriftbar wie Nodes**
- Container tragen dasselbe Stil-Model wie Nodes (Form roundrect/rect/ellipse,
  Fuellfarbe, Textstil) in metadatajson. Selektierter Container zeigt denselben
  Format-Dock (NodeFormatToolbar: Form/Fuellung/Text/Loeschen); Label per
  Doppelklick auf die Titelzeile. Ohne Stil bleibt der gestrichelte Default.
- Vier-Ecken-Resize (vorher nur unten-rechts): neue reine Funktion
  resizeBoxCorner (nw/ne/sw/se, Mindestgroesse, Gegenkante fix), 5 Tests.
- Backend container_update akzeptierte label/geometryjson/metadatajson bereits;
  jetzt Frontend verdrahtet + Pfad bestaetigt.
- Neuer container_style_test (Stil-Roundtrip, Umbenennen loescht Stil nicht).
  211 (+1)+97; phpcs/phpcpd/stylelint clean; 435/435; Jest 37/237.
- Damit ist der komplette Issue-Satz A1-A6 / T1-T6 abgearbeitet.

**0.6.23 - T5: Neu-Ausrichten erhaelt Container-Zugehoerigkeit**
- Bisher layoutete Re-Arrange nur nach Graphstruktur und ignorierte Container ->
  Mitglieder verstreuten sich, Box blieb stehen.
- Jetzt: vor dem Layout raeumlich schnappen, welche Nodes in welchem Container
  sind (Mittelpunkt in Box = zugehoerig; keine separate Zuweisung noetig). Nach
  dem Layout jeden nicht-leeren Container auf die Bounding-Box seiner Mitglieder
  (+ Pad 24) refitten -> Mitglieder bleiben drin, Container folgt. Leere bleiben.
- Refit im selben Undo/Redo-Eintrag wie das Layout (ein Undo stellt beides her).
- Reine Helfer centerInBox + boundingBox (mit Min-Size-Clamp); 6 Tests.
  Rein Frontend: 210+97 unveraendert; phpcs clean; Jest 36/230; reproduzierbar.

**0.6.12 - SVG/PNG-Export inkl. Container + SVG-Round-Trip-Import**
- computeContentBounds bezieht Container-Boxen in die Export-viewBox ein (Container
  ausserhalb der Nodes wird nicht mehr beschnitten); Container-Chrome (Delete/
  Resize/Draw-Overlay) wird aus dem Exportbild entfernt.
- Export-SVG bettet das Map-JSON in <metadata id="vimipad-data"> ein (dasselbe
  Envelope wie export.php?format=json). .svg-Import extrahiert das JSON und nutzt
  den bestehenden Import-Pfad -> SVG ist Bild UND reimportierbare Map. Ohne
  eingebettete Daten: editor:importnovimidata.
- Reine extractMapData + Embed getestet; neuer test_container_roundtrip beweist,
  dass Container Export->Import ueberstehen (Basis des SVG-Round-Trips).
- Lang editor:importnovimidata (en/de 429/429) + init.js + fallback.
- Verifiziert: tsc clean; Jest 30 Suites/185; Bundle reproduzierbar; 205 mod_vimipad
  + 97 vimipadassess; phpcs/phpcpd clean.
- Damit sind die von Ralf gewuenschten "Reste" umgesetzt: Container-Bearbeitung
  + Undo/Redo (0.6.11) und SVG/PNG-Output + SVG-Import (0.6.12).

---

### Entscheidungen getroffen

| Thema | Entscheidung | Begründung |
|---|---|---|
| Concurrency-Modell | ein gemeinsamer Workspace-Write-Lock für alle Mutationen + Snapshot | verhindert Torn-Reads; einzige Serialisierungslinie |
| apply-Refactor | Lock-Wrapper `apply()` + lock-freies `apply_locked()` | Import darf den Lock nicht re-entrant nehmen |
| E1 Referenzen | genau eine je Aktivität bis nach 1.0 (Contract bleibt 0..n) | schlank, kein DB-Umbau vor 0.6 |
| E2 Leases | advisory (nicht enforced) | Korrektheit über Write-Lock + Revision, nicht über Leases |
| E3 Peer-Phasen | Roadmap auf realen Scope (Allokation+Reviews) korrigiert | kein Feature-Creep vor 0.6 |
| E4 `updated`-Event | akzeptiert; in 0.5.34 als `map_updated` in `apply_operation` umgesetzt (+Test) | Kontext liegt im External-Layer, kein Threading in den Service nötig |
| E5 Provenienz | Snapshot ohne `createdby` (Log trägt Provenienz); Lastenheft später präzisieren | Normalisierung/Datenschutz |
| E6 Import-Atomarität | Semantik atomar, Layout best-effort | billiger, Vertrag jetzt eindeutig |

### Entwurfsentscheidungen geändert / zurückgestellt

Keine der neun Schlüssel-Entscheidungen aus `sessionstart.txt` geändert.
In 0.5.34 nachgezogen: `duedate`/late-Auswertung und `map_updated`-Event (E4).
Zurückgestellt auf 0.6-Vorbereitung: Container-/Membership-Operationen,
Importformat v2 + Migration, Template-/Constraint-Policy.

---

### Offene Punkte für die nächste Session (0.6.0-Vorbereitung)

- 0.6.0 Autorenwerkzeuge auf der Grundlage: `constraint_policy`-Resolver
  (rein, testbar) + hartes Abgabe-Gate; Container auf dem Canvas zeichnen
  (Frontend); Template-Sperren (`locked`/`editable` in `apply_locked`,
  `error:elementlocked`); Template-Autorenoberfläche.
- Danach 0.6.0 Autorenwerkzeuge (Feedback-/Werkzeuge-Reiter, Container, Templates).

---

### Roadmap-Abgleich (zentrales Dokument)

- [x] `docs/design/roadmap.md` geprüft und auf realen Stand gebracht
      (Stand 0.5.32/33, Statusmarker, 0.7-Rewording, 0.5-Offenpunkte).
- [x] Weitere Doku (backlog, connector-styles, ui_reorder_plan, README,
      test-setup, visual-maps, assessment_architecture) abgeglichen.

---

### Testlauf-Ergebnis

```
PHPUnit:  OK - 211 mod_vimipad + 97 vimipadassess (real auf Moodle 4.5.12 + PostgreSQL, exit 0)
PHPCS:    OK - ganzes Plugin clean (moodle-Standard, severity=1, --ignore=tools/)
PHPCPD:   OK - keine Klone (--min-lines 5 --min-tokens 70)
Release-CI: moodle-release.yml pfad-robust fuer Moodle 5.2 public/ (YAML validiert, Resolver-Logik simuliert)
Frontend: tsc 0 · Jest 36 Suites/230 · esbuild- UND AMD-Bundle byte-reproduzierbar
Behat:    SKIP hier (kein Browser); @javascript + visuelles Rendering in CI
```

---

### Auslieferung

- [x] Version konsistent: version.php 2026072738 / 0.6.24, package.json + lock (inkl. Frontend).
- [x] CHANGELOG-Eintrag 0.5.33 ergänzt.
- [x] docs/sessions/session-003.md im Clean-Install-ZIP (Gate bestanden).
- [x] amd/build/ + js/build/ eingecheckt.

---

### Für die nächste Session einfügen in sessionstart.txt

**Aktueller Entwicklungsstand:**
> 0.6.10 — Autoren-Backend (0.6.1–0.6.6); Release-CI-Fix (0.6.7); Frontend-Autoren
> KOMPLETT: Constraint-Hinweise (0.6.8), Container (0.6.9), Template-Sperren (0.6.10).
> PHP real auf Moodle 4.5.12 (204+97 grün); Frontend tsc/Jest 30/179, reproduzierbar.

**Zuletzt abgeschlossen:**
> 0.6.1–0.6.6 Autoren-Backend; 0.6.7 Release-CI-Fix; 0.6.8–0.6.10 Frontend-Autoren
> (Hinweise/Container/Sperren) komplett. Konvention: 0.6.x ohne alpha-Suffix.

**Als nächstes geplant:**
> 0.6.x-Frontend abgeschlossen. Offen in 0.6.x laut Roadmap: Verknuepfung der
> Templates mit Import/Export (JSON/XML) und dedizierte Freigabe-Optionen.
