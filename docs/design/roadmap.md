# ViMi Pad — Roadmap

Stand: nach 0.2.x. Diese Roadmap ordnet die geplanten Ausbaustufen bis zur
Version 1.0. Reihenfolge und Zuordnung sind bewusst gewählt; **Barrierefreiheit**
und **Sicherheit** ziehen sich als Querschnitt durch alle Stufen (Schwerpunkt-
bzw. Prüfpunkt in 0.3.x bzw. 0.7.x, aber bei jedem Feature mitgedacht).

Grüne CI (phpcs/PHPUnit/Behat) ist ein Hygiene-Kriterium, kein Meilenstein.

## 0.2.x — MVP (laufend)

Fünf Darstellungstypen (conceptmap, mindmap, tree, semanticnetwork, bubblemap)
mit typabhängigem Verbinderstil und Bifurkation; gemeinsames Baum-Routing;
symmetrische Kurven; hierarchisches Baum-Layout; automatische Node-Höhe;
Listen-Ansicht mit Inline-Bearbeitung; Kollaboration/Locking/Presence;
Abgabe/Snapshot/Bewertung; Auslagerung der Darstellungstypen in
`vimipadform`-Subplugins (Registry im Backend, Konsum im Frontend).

## 0.3.x — Barrierefreiheit & Editor-Politur

- Barrierefreiheit prüfen und umsetzen (Ziel WCAG 2.1 AA): Tastaturbedienung des
  Canvas, Screenreader/ARIA, Fokusführung, Kontraste
- Undo/Redo im Editor
- Mobile-/Touch-Politur
- Completion-Detailregeln (z. B. „mindestens n Knoten", „bewertet")
- Hilfetexte/Doku für Lehrende
- „Layout neu anordnen"-Button (gespeicherte Positionen fürs aktive Profil verwerfen)

## 0.4.x — Gruppenarbeit, Export & Logging

- Gruppenmodus-Verdrahtung mit Moodle (keine/getrennte/sichtbare Gruppen)
- Bearbeitungsmodus: Einzel, Gruppe, ganzer Kurs
- PDF-Export der Maps
- PNG/SVG-Export
- Export **und** Import von ViMis als JSON/XML-Datei
- Bearbeitungslogs (fachlich) + statistische Auswertung der Mitarbeit
- Moodle-Events/Logging-API (viewed/updated/submitted/graded) fürs Standard-Reporting

## 0.5.x — Bewertung gegen Musterlösung

- Struktur- und Semantikabgleich gegen eine oder mehrere Musterlösungen
- Import (JSON/XML) von Musterlösungen (auf Basis der Exporte aus 0.4.x)
- Rubrik — Option: Moodle-Kern `gradingform` (Rubric/Marking Guide) statt Eigenbau
- KI-Unterstützung (über das Moodle-AI-Subsystem) → Feedback-Entwurf
- Bepunktung → Gradebook-Anbindung (`grade_item`/`grade_update`, Skalen/Notenstufen)
- Aktivitätsabschluss an die Bewertung koppeln
- Lehrende können eingereichte ViMis zur Weiterbearbeitung wieder freigeben
- Gruppenkonsens zur Abgabe: jedes Gruppenmitglied muss auf „Einreichen" klicken
  (analog Gruppenabgaben in `mod_assign`)
- Relevante Events für Observer bereitstellen

## 0.6.x — Autoren-Werkzeuge & Import/Export-Integration

- Vorlagen/Templates durch Lehrende mit einschränkbaren Änderungen
- Hintergründe zeichnen (Kästen/Abschnitte/Container auf dem Canvas)
- Verknüpfung mit der Import/Export-Funktion (JSON/XML)
- Oberflächen und Menüs hierfür
- Dedizierte Freigabe-Optionen dieser Möglichkeiten an Lernende

## 0.7.x — Hardening, Datenschutz & Persistenz

- Systematischer Security-Review: XSS beim Label-Rendering, `sesskey`/Capability-
  Checks in jeder External-Function, Eingabevalidierung. Sicherheit wird schon
  vorher laufend umgesetzt; hier wird der Umsetzungsstand geprüft und vervollständigt.
- Privacy API (`classes/privacy/provider.php`): Metadaten, Export, Löschung —
  jetzt, da alle Nutzerdaten feststehen
- Backup & Restore (`backup/moodle2/…`) inkl. Kurs-Import/-Duplizierung
- Allgemeines Code-Hardening (Fehlerbehandlung, Performance, Determinismus)

## 0.8.x — Feldtests & weitere Darstellungsformen

- Feldtestungen in echten Kursen
- Ausweitung auf die weiteren bereits erfassten Formen (argument, process/flow,
  fishbone, timeline, vennsets, systems, affinity) — jeweils als `vimipadform`-Subplugin

## 0.9.x — Kerncode für abgeleitete Zusatz-Plugins

- Bereitstellung und Stabilisierung des Kerncodes (öffentliche APIs, Events,
  Contracts) für abgeleitete Zusatz-Plugins: Questiontype, Database, Peer-Review

## Richtung 1.0

Nach erfolgreichen Feldtests: Politur, vollständige Doku, Stabilität.

## Querschnittsthemen

- **Barrierefreiheit** (Schwerpunkt 0.3.x) und **Sicherheit** (Prüfpunkt 0.7.x)
  werden bei jedem neuen Feature mitgedacht, nicht erst am Ende.
- **Datenschutz & Backup** hängen bewusst hinten (0.7.x), weil das Datenmodell
  erst nach Bewertung und Autoren-Werkzeugen vollständig ist; sie müssen aber vor
  den Feldtests (0.8.x) fertig sein.
