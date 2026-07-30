# Erweiterungsideen: Machbarkeit und Einordnung

> **Hinweis (Stand nach 0.2.x):** Zentrales, versioniertes Planungsdokument ist
> `docs/design/roadmap.md`. Die hier genannten Meilenstein-Marker (M1/M2/M4,
> 1.0/1.1, 2.x, „nach 2.0") sind fachliche Einordnungen; die konkrete
> Versionszuordnung (0.x → 1.0 und Post-1.0) steht in der Roadmap. Peer-Review-
> Basis, Lernjournal und Begleitkanal sind dort in 0.4.x/0.5.x eingeplant; die
> stabile öffentliche API und der einbettbare Editor (`mount()`/Adapter) für
> `qtype_vimipad` in 0.7.x als Voraussetzung für 0.9.x.

Stand: 25.07.2026 (Session 001, Nachtrag). Bewertet werden vier ergänzende
Produktideen sowie der Wettbewerbsbefund. Grundlage: Lastenheft/Pflichtenheft,
technisches Blueprint, Roadmap (alle in `docs/materials/`).

## 0. Wettbewerbsbefund (Kurzfassung)

Sechs untersuchte "Mindmap"-Plugins (mod_mindmap, mod_advmindmap,
qtype_conceptmap, datafield conceptmap/atmin, format_mindmap,
block_mymindmap_overview): kein gepflegtes Produkt verbindet visuelle
Wissenskonstruktion mit Bewertungsworkflow. Nur mod_mindmap (t6nis) ist aktiv
(reiner Zeichnungseditor, vis.js, Blob-Datenmodell, kein Gradebook/Privacy);
qtype_conceptmap validiert historisch die Idee "Map als bewertbares Artefakt",
ist aber seit Moodle 2.x tot. Lehren: (1) Renderer-Bibliothek nie als
Fachmodell verwenden; (2) Projekte ohne Tests/CI/Privacy sterben am
Moodle-Versionszyklus. Backlog-Übernahmen: Sichtbarkeitsoption
"eigene Map, Peers einsehbar" (advmindmap), Tastaturkonventionen
Insert/Delete (mod_mindmap), Verzeichnistext gegen veraltete Namensvettern
positionieren.

## 1. ViMi-Technologie in anderen Plugintypen (qtype, datafield, block, format)

**Machbarkeit: ja — unter einer Architektur-Vorbedingung.**
Moodle-Subplugins von mod_vimipad sind für fremde Plugintypen nicht nutzbar.
**Entschieden (Session 001): KEINE local-Architektur.** Wiederverwendung läuft
ausschließlich über deklarierte Abhängigkeit auf mod_vimipad
(`$plugin->dependencies = ['mod_vimipad' => ...]`); mod_vimipad stellt den
gemeinsamen Code per Namespace bereit:

- `\mod_vimipad\api\*` und `\mod_vimipad\profile\*`: öffentliche, stabile
  API für abhängige Plugins (qtype, datafield, Review-Erweiterungen).
- `\mod_vimipad\local\*`: interne Implementierung, keine
  Stabilitätsgarantie, darf von Fremd-Plugins nicht genutzt werden.

Zusätzlich entscheidend und **sofort zu fixieren**:

- Editor als einbettbare Komponente: ein `mount(element, config)`-Entrypoint,
  Persistenz über austauschbaren Adapter (Workspace-Adapter im
  Aktivitätsmodul; Attempt-Adapter im Fragetyp; Feldwert-Adapter im
  Datenbankfeld).
- Profilvalidierung kontextfrei aufrufbar (kein Zwang auf course_module).

Bewertung je Variante:

- **`qtype_vimipad`** (Fragetyp): wertvollster Ableger, Premium-Kandidat.
  Question Attempt ≙ eingefrorener Stand — passt strukturell auf das
  Snapshot-Konzept. Wiederverwendet werden Editor, Profile, Viewer; NICHT das
  Workspace/Operation-Log-Schema (Question Engine speichert selbst).
  Aufwand nach MVP: ~25-40 PT. Zeithorizont: 2.x.
- **`datafield_vimipad`**: mit einbettbarer Komponente günstig (~10-15 PT),
  didaktische Nische. Kann-Kategorie, nach 2.0.
- **Block / Kursformat** (Kursnavigation als Map): anderes Problem
  (Navigation statt Wissenskonstruktion); nutzt nur die Rendering-Schicht als
  Read-only-Viewer über Kursstruktur. Halo-/Marketingwert, bewusst nach 2.0,
  kein Kern-Fokus.

## 2. Peer-Review-/Bewertungstool nach Vorbild mod_workshop

**Machbarkeit: hoch. Entscheidung: Phasenmodell IN mod_vimipad, kein eigenes
Modul.** Der Snapshot ist bereits das stabile Review-Objekt, die
Annotationsebene existiert; ein separates Workshop-artiges Modul würde
Abgabe-, Zuteilungs- und Bewertungsinfrastruktur duplizieren.

Von mod_workshop zu übernehmende Patterns:

- Phasen: Einrichtung → Bearbeitung/Abgabe → Begutachtung → Bewertung →
  geschlossen (im Abgabe-Statusmodell von M4 von Anfang an vorsehen).
- Zuteilungsstrategien: zufällig/manuell, n Reviews pro Person,
  Anonymisierung.
- Getrennte Bewertung von Artefakt und Review-Qualität.

Umsetzung auf ViMi-Primitiven: Review = capability-beschränkter Annotations-
und Rubric-Zugriff auf fremde Snapshots.
Aufwand: Basis (Roadmap 1.1) 10-15 PT; Vollausbau `vimipadreview_peerplus`
(Zuteilung, anonym, Review-Rubrics, Review-Completion) 25-40 PT.
Optionale Brücke: Snapshot-Export (PDF/PNG) als Submission ins echte
mod_workshop (~3-5 PT) — verliert artefaktnahe Annotation, nur Übergang.

## 3. Backchannel und Journaling während der Bearbeitung

Drei Schichten, getrennt bewertet:

**(a) Lernjournal im Workspace — empfohlen, ggf. MVP-Kandidat.**
Eigene Tabelle (`vimipad_journalentry`: workspaceid, userid, entrytext,
entryformat, revisionref, timecreated), Einträge optional mit Revisionsbezug
zum Map-Stand. Sichtbarkeit: standardmäßig privat; Lehrendenzugriff nur per
Capability und Aktivitätseinstellung. Optional (opt-in, datensparsam) in den
KI-Feedback-Kontext einbeziehbar. **Audio ohne eigene Infrastruktur:**
Journaleinträge nutzen den Moodle-Editor (TinyMCE) inkl. Core-Plugin
`tiny_recordrtc` — Aufnahme, Speicherung (File API) und Wiedergabe liefert
Moodle; No-Server-Constraint bleibt gewahrt. Aufwand: 8-15 PT.
Datenmodell-Konsequenz: Tabelle bereits in M1 einplanen; Privacy-Provider
muss Journaldaten vollständig abdecken.

**(b) Kopplung an Forum/Chat/BigBlueButton — lose Kopplung, günstig.**
Aktivitätseinstellung "Begleitkanal" (cmid eines Forums/Chats/einer
BBB-Aktivität im Kurs), im Editor als Panel-Button/Link mit Kontext.
BBB-Server ist Sache der Institution und verletzt die No-Server-Constraint
des Plugins nicht. Aufwand: 2-4 PT (1.0/1.1). Tiefere Einbettung später als
Subplugin-Typ `vimipadchannel_*` offenhalten.

**(c) Eigener Echtzeit-Audio-/Präsenzkanal — ausgeschlossen.**
Widerspricht der No-Server-Constraint (WebSocket-/Medieninfrastruktur).
Delegation an BBB/Chat.

## 4. Mobile Ansicht (Vollbild)

**(a) Responsives Browser-Vollbild — ab M2 mitdesignen, nicht nachrüsten.**
Fullscreen API, Touch-Gesten (Pan, Pinch-Zoom, Long-Press statt Hover).
Struktureller Vorteil: die gleichberechtigte **Listenansicht ist die primäre
mobile Bearbeitungsoberfläche** — Canvas-DnD ist auf Smartphonebreite bei
allen Wettbewerbern unbenutzbar; ViMi Pad wird damit als einziges Tool der
Kategorie ernsthaft mobil bearbeitbar. Mehraufwand bei
Design-from-start: 5-10 PT.

**(b) Moodle-App-Unterstützung (db/mobile.php, Ionic-Handler) — eigener
Workstream, 1.x/Premium.** Der React-Editor läuft nicht in den
WS-getriebenen App-Templates; realistischer Pfad: Read-only-Viewer plus
listenbasierte Bearbeitung in der App. Offline-Synchronisation bleibt gemäß
Abgrenzungen ausgeschlossen.

## 5. Konsequenzen für Roadmap und Architektur

Sofort (M1/M2, Architekturentscheidungen):

1. Editor-Komponentenschnitt mit `mount()`-API und Persistenz-Adaptern.
2. Journaltabelle im Datenmodell M1; Privacy-Abdeckung.
3. Abgabe-Statusmodell M4 phasenfähig entwerfen (Workshop-Pattern).
4. Listenansicht touch-first entwerfen; Vollbildmodus einplanen.

Roadmap-Ergänzungen:

- 1.0/1.1: Begleitkanal-Verlinkung; Lernjournal; Peer-Review-Basis (bestehend).
- Premium-Katalog neu/bestätigt: `vimipadreview_peerplus` (bestehend),
  **`qtype_vimipad` (neu)**, Moodle-App-Handler (neu, 1.x+).
- Nach 2.0 (Kann): `datafield_vimipad`, Block-/Format-Viewer.
- Nicht-Ziele bestätigt: eigener Echtzeit-Audiokanal, Offline-Sync.
