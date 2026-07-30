# ViMi Pad — Roadmap

Stand: **0.5.32** (Backend fachlich vollständig; real auf Moodle 4.5.12 und
5.0.8 verifiziert). Vor 0.6.x ist eine kurze **0.5.33-Closure-Runde** geplant
(offene 0.5-Verträge entscheiden, Concurrency-Garantie schließen, Importformat/
Container-/Template-Verträge vorab festziehen). Status-Marker unten:
✅ umgesetzt · ◐ teilweise · ○ geplant · → verschoben.

Diese Roadmap ordnet die geplanten Ausbaustufen bis zur
Version 1.0. Reihenfolge und Zuordnung sind bewusst gewählt; **Barrierefreiheit**
und **Sicherheit** ziehen sich als Querschnitt durch alle Stufen (Schwerpunkt-
bzw. Prüfpunkt in 0.3.x bzw. 0.7.x, aber bei jedem Feature mitgedacht).

Grüne CI (phpcs/PHPUnit/Behat) ist ein Hygiene-Kriterium, kein Meilenstein.

## 0.2.x — MVP (laufend)

> **Status: ✅ abgeschlossen** (fünf Darstellungstypen als `vimipadform`-Subplugins, Listen-Ansicht, Kollaboration/Locking, Snapshot/Abgabe/Bewertung).

Fünf Darstellungstypen (conceptmap, mindmap, tree, semanticnetwork, bubblemap)
mit typabhängigem Verbinderstil und Bifurkation; gemeinsames Baum-Routing;
symmetrische Kurven; hierarchisches Baum-Layout; automatische Node-Höhe;
Listen-Ansicht mit Inline-Bearbeitung; Kollaboration/Locking/Presence;
Abgabe/Snapshot/Bewertung; Auslagerung der Darstellungstypen in
`vimipadform`-Subplugins (Registry im Backend, Konsum im Frontend).

## 0.3.x — Barrierefreiheit & Editor-Politur

> **Status: ◐ teilweise** — Undo/Redo ✅, „Layout neu anordnen"/Auto-Layout ✅, Completion-Detailregeln ✅ (min. Knoten, bewertet), Hilfetexte ◐. Offen: systematisches WCAG-2.1-AA-Audit (Canvas-Tastatur/ARIA/Fokus) → 0.9.x-Audit; Mobile/Touch-Politur ◐.

- Barrierefreiheit prüfen und umsetzen (Ziel WCAG 2.1 AA): Tastaturbedienung des
  Canvas, Screenreader/ARIA, Fokusführung, Kontraste — Stand und offene Punkte in
  [barrierearmut.md](barrierearmut.md)
- Undo/Redo im Editor
- Mobile-/Touch-Politur
- Completion-Detailregeln (z. B. „mindestens n Knoten", „bewertet")
- Hilfetexte/Doku für Lehrende
- „Layout neu anordnen"-Button (gespeicherte Positionen fürs aktive Profil verwerfen)

## 0.4.x — Gruppenarbeit, Export & Logging

> **Status: ✅ weitgehend** — Gruppen-/Kursmodus, PDF/PNG/SVG/JSON/XML-Export **und** Import, Journal + Bearbeitungslogs/Report, Annotationen an Map/Knoten/Relation, Begleitkanal-Link, Backup/Restore + Voll-Privacy (in 0.4.x umgesetzt, **nicht** erst 0.7.x). ◐ Offen: fachliches `updated`-Event fehlt noch.

- Gruppenmodus-Verdrahtung mit Moodle (keine/getrennte/sichtbare Gruppen)
- Bearbeitungsmodus: Einzel, Gruppe, ganzer Kurs
- PDF-Export der Maps
- PNG/SVG-Export
- Export **und** Import von ViMis als JSON/XML-Datei
- Bearbeitungslogs (fachlich) + statistische Auswertung der Mitarbeit
- Moodle-Events/Logging-API (viewed/updated/submitted/graded) fürs Standard-Reporting
- Annotationen an Knoten und Relationen (Grundlage für Bewertung und Peer-Review)
- Lernjournal im Workspace (eigene Tabelle, privat per Default, opt-in in den
  KI-Kontext; Audio über Moodle-Kern `tiny_recordrtc`) — Datentabelle früh
  einplanen, Privacy muss sie später vollständig abdecken
- Begleitkanal-Verlinkung (Forum/Chat/BigBlueButton) als lose Kopplung

## 0.5.x — Bewertung gegen Musterlösung

> **Status: ✅ weitgehend, mit offenen Verträgen** — Struktur-/Semantikabgleich (`vimipadassess`, 6 Scorer + Matcher-Wahl), `gradingform`-Rubric, KI-Feedback + On-demand-KI-Scorer, Gradebook/Completion, Reopen, Konsens-Abgabe, Peer-Review-Basis (Allokation/Reviews/Aggregat). **Closure 0.5.33/0.5.34 erledigt:** gemeinsamer Workspace-Write-Lock (Concurrency-Gate) + fail-closed Eindeutigkeit; `duedate`/late-Markierung (soft; `cutoffdate` bleibt harte Grenze); `map_updated`-Event fürs Reporting; Mehrfach-Referenzen als *eine je Aktivität* festgelegt (E1-B); Peer-**Phasenmodell** bewusst auf realen Scope reduziert (E3-B, s. u.); Leases advisory (E2-A); Import-Atomarität dokumentiert (E6). **0.6-Grundlagen (0.6.0-alpha1) gelegt:** Container-/Membership-Operationen im Operation-Contract + voller Import-Round-Trip (kein Formatwechsel nötig); Template-/Constraint-Policy spezifiziert (`template_constraint_policy.md`, Umsetzung über 0.6.x).

- Struktur- und Semantikabgleich gegen eine oder mehrere Musterlösungen
- Import (JSON/XML) von Musterlösungen (auf Basis der Exporte aus 0.4.x)
- Rubrik — Option: Moodle-Kern `gradingform` (Rubric/Marking Guide) statt Eigenbau
- KI-Unterstützung (über das Moodle-AI-Subsystem) → Feedback-Entwurf
- Bepunktung → Gradebook-Anbindung (`grade_item`/`grade_update`, Skalen/Notenstufen)
- Aktivitätsabschluss an die Bewertung koppeln
- Lehrende können eingereichte ViMis zur Weiterbearbeitung wieder freigeben
- Gruppenkonsens zur Abgabe: jedes Gruppenmitglied muss auf „Einreichen" klicken
  (analog Gruppenabgaben in `mod_assign`)
- Abgabe-/Nachfristlogik (Fristen, verspätete Abgaben)
- Peer-Review-Basis (✅ realisiert als Allokation → Reviews → Aggregat auf
  Snapshots/Annotationen, kein eigenes Modul). **Entscheid 0.5.34 (E3-B):** das
  vollständige 5-Phasen-*Aktivitäts*-Workflowmodell (Einrichtung → Bearbeitung →
  Begutachtung → Bewertung → geschlossen) wird NICHT im Kern gebaut, sondern dem
  Premium-Ausbau `vimipadreview_peerplus` (nach 1.0) zugeordnet; der Kern bleibt
  bei der schlanken Basis.
- Relevante Events für Observer bereitstellen

## 0.6.x — Autoren-Werkzeuge & Import/Export-Integration

> **Status: ◐ Grundlage gelegt (0.6.1–0.6.3).** Backend-Vertrag: Container-/
> Membership-Operationen + voller Import-Round-Trip (0.6.1). Constraint-Policy:
> Engine + hartes Abgabe-Gate (0.6.2) und die Lehrenden-Eingabefelder
> (Pflicht-/Verbotsbegriffe, erlaubte Typen, Mindestumfang) in Form/Schema/
> Backup (0.6.3) → **Gate produktiv aktiv**. Offen: die Canvas-Oberflächen
> (Container zeichnen, Template-Autorenwerkzeug), weiche Edit-Zeit-Hinweise (Backend-Endpoint `get_constraint_status` ✅ 0.6.5, Editor-Anzeige offen); die Template-Struktursperren sind serverseitig **durchgesetzt** (0.6.4, `error:elementlocked`); offen bleibt die Autorenoberflaeche, die die Sperren setzt.

- Vorlagen/Templates durch Lehrende mit einschränkbaren Änderungen
- Hintergründe zeichnen (Kästen/Abschnitte/Container auf dem Canvas) — Backend
  (Container-Operationen) ✅; Canvas-Oberfläche offen
- Verknüpfung mit der Import/Export-Funktion (JSON/XML) — Container/Memberships
  round-trippen jetzt ✅
- Oberflächen und Menüs hierfür
- Dedizierte Freigabe-Optionen dieser Möglichkeiten an Lernende

## 0.7.x — Hardening, Datenschutz & Persistenz

- Systematischer Security-Review: XSS beim Label-Rendering, `sesskey`/Capability-
  Checks in jeder External-Function, Eingabevalidierung. Sicherheit wird schon
  vorher laufend umgesetzt; hier wird der Umsetzungsstand geprüft und vervollständigt.
- Privacy API (`classes/privacy/provider.php`): **bereits in 0.4.x umgesetzt** —
  in 0.7.x nur noch vollständiges Re-Audit/Härtung, sobald das Datenmodell nach
  Container/Templates final ist (Metadaten, Export, Löschung)
- Backup & Restore (`backup/moodle2/…`) inkl. Kurs-Import/-Duplizierung:
  **bereits umgesetzt** (Domänenmodell, Grades mit Snapshot-Remap, Journal,
  Grading-Instanzen, Peer-Reviews) — 0.7.x prüft/vervollständigt nur die nach
  Container/Templates neu hinzukommenden Felder
- Allgemeines Code-Hardening (Fehlerbehandlung, Performance, Determinismus)
- Stabile öffentliche API als Voraussetzung für abgeleitete Plugins (0.9.x):
  Namespace-Trennung `\mod_vimipad\api\*` / `\mod_vimipad\profile\*` (stabil,
  öffentlich) gegenüber `\mod_vimipad\local\*` (intern), einbettbarer Editor mit
  `mount(element, config)`-Entrypoint und austauschbarem Persistenz-Adapter,
  kontextfrei aufrufbare Profilvalidierung

## 0.8.x — Feldtests & weitere Darstellungsformen

- Feldtestungen in echten Kursen
- Ausweitung auf die weiteren bereits erfassten Formen (argument, process/flow,
  fishbone, timeline, vennsets, systems, affinity) — jeweils als `vimipadform`-Subplugin

## 0.9.x — Kerncode für abgeleitete Zusatz-Plugins & Barrierefreiheits-Audit

- Bereitstellung und Stabilisierung des Kerncodes (öffentliche APIs, Events,
  Contracts) für abgeleitete Zusatz-Plugins: Questiontype, Database, Peer-Review
- **Barrierefreiheits-Audit** (Stand/Umsetzung siehe `barrierearmut.md`):
  systematische Prüfung gegen WCAG 2.1 und 2.2, Stufe AA (wo sinnvoll AAA),
  gegen EN 301 549 sowie Prüfung der Einhaltung der BITV 2.0; Prüfung mit echten
  Hilfsmitteln (NVDA/JAWS/VoiceOver, Tastatur-only, Vergrößerung), Behebung der
  Befunde und Dokumentation des Konformitätsstands

## Richtung 1.0

Nach erfolgreichen Feldtests: Politur, vollständige Doku, Stabilität.

## Nach 1.0 (Ausblick)

Die strategische/geschäftliche Ausbauplanung liegt in
`docs/materials/roadmap_zusatzplugins_knowledgemap.md` und
`docs/materials/erweiterungsideen_bewertung.md`. Grobe Themenblöcke:

- **1.1 — Bewertungs-/Feedbackausbau:** AI Feedback Studio für Lehrende,
  Feedbackvorlagen (Tonalität/Fachkontext), strukturelle Auto-Checks, Teacher
  Reference Model (light), Kommentar-Threads, Peer-Review-Vollausbau
  (`vimipadreview_peerplus`).
- **1.2 — weitere Diagrammprofile:** siehe 0.8.x (argument, process/flow,
  fishbone, systems, vennsets, timeline, affinity) plus profilabhängige
  Lehrerchecks.
- **1.3 — Kollaboration & Kursanalyse:** Beitragsanalyse, Activity Events/
  xAPI-nahe Verlaufsdaten, kursweite Heatmaps, Vergleich mehrerer Gruppen-Maps.
- **2.0 — Plattform:** Knowledge Graph mit typisierten Knoten/Relationen,
  Reference-Graph-Matching, KI-gestützte Fehlvorstellungsmarkierung, semantische
  Import-/Exportprofile, erweiterte Subplugin-Schnittstellen, optionaler
  Marketplace.
- **Abgeleitete Plugins (auf Basis 0.9.x):** `qtype_vimipad` (Fragetyp,
  wertvollster Ableger), `datafield_vimipad`, Block-/Kursformat-Viewer
  (read-only); Moodle-App-Handler (`db/mobile.php`, Ionic) als eigener
  Workstream.

## Bezug zu weiteren Dokumenten

Diese Roadmap (`docs/design/roadmap.md`) ist das **zentrale, versionierte
Planungsdokument**. Sie ordnet die detaillierte Umsetzung bis 1.0 in `0.x`-Stufen
und fasst den Post-1.0-Ausblick zusammen. Die übrigen Dokumente bleiben Quelle
für Strategie, Machbarkeit und Fachanforderungen:

- `docs/materials/roadmap_zusatzplugins_knowledgemap.md` — strategische
  Ausbau-/Geschäftsplanung und Premium-Subplugin-Katalog (Meilensteine
  MVP/1.0/1.1/1.2/1.3/2.0 als *fachliche* Schichtung; die *technische*
  Versionsvergabe erfolgt hier).
- `docs/materials/erweiterungsideen_bewertung.md` — Machbarkeit und Einordnung
  ergänzender Produktideen (qtype, Peer-Review, Journal, Mobile).
- `docs/materials/pflicht_lastenheft_knowledgemap.md`,
  `docs/materials/technisches_blueprint_knowledgemap.md` — Fach- und
  Technikanforderungen.

## Querschnittsthemen

- **Barrierefreiheit** (Schwerpunkt 0.3.x) und **Sicherheit** (Prüfpunkt 0.7.x)
  werden bei jedem neuen Feature mitgedacht, nicht erst am Ende.
- **Datenschutz & Backup** hängen bewusst hinten (0.7.x), weil das Datenmodell
  erst nach Bewertung und Autoren-Werkzeugen vollständig ist; sie müssen aber vor
  den Feldtests (0.8.x) fertig sein.
