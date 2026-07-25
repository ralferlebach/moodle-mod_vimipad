# Einordnung

Das Vorhaben sollte nicht als einzelner Mindmap-Editor geplant werden, sondern als Moodle-Aktivitätsmodul für visuelle Wissenskonstruktion. Kern ist ein gemeinsames Datenmodell für Knoten, Relationen, Container, Mitgliedschaften, Revisionen, Snapshots, Annotationen und Bewertungen. Die unterschiedlichen Darstellungsformen sind darauf aufsetzende Profile mit eigenen Regeln, Layouts und Constraints.

Die neuen Anforderungen verschieben den Schwerpunkt deutlich in Richtung professioneller Lehr-/Bewertungsworkflow:

- **Snapshot-basierte Bewertung** macht den Bewertungsgegenstand stabil und nachvollziehbar.
- **KI-gestützte Feedbackformulierung** sollte Lehrende unterstützen, nicht automatisch bewerten. Sie arbeitet auf einem eingefrorenen Snapshot, Rubric-Kriterien, Lehrernotizen und optional einem Referenzmodell.
- **Listenansicht mit Drag-and-drop** ist nicht nur Barrierefreiheit, sondern eine zweite vollwertige Editoroberfläche. Eine Relation kann als Zeile bearbeitet, verschoben, mit anderem Subjekt/Objekt verbunden und anschließend in der grafischen Map neu angeordnet werden.
- **React ab Moodle 5.3** kommt dem Projekt stark entgegen, weil ein komplexer, zustandsreicher Canvas-Editor sehr gut zu komponentenbasierter UI, TypeScript, State Management und isolierbaren Tests passt. Für Moodle 4.5 bis 5.2 kann derselbe React-Quellcode als Legacy-Bundle ausgeliefert und über AMD/Mustache-Initialisierung eingebunden werden.
- **Keine zusätzliche Server-Software** ist realistisch, wenn Echtzeit-Kollaboration zunächst über serverautorisierte Operationen, Revisionen, Autosave und kurzes Polling umgesetzt wird. WebSocket-/CRDT-Server, Python-, Rust- oder Node-Dienste gehören dann nicht in die Basisarchitektur.


# Roadmap und bezahlte Zusatz-(Sub)Plugins

## 1. Roadmap

### MVP: produktionsfähiger Kern

Ziel: Ein stabiles Moodle-Aktivitätsmodul für individuelle und gruppenbasierte Concept Maps.

Umfang:

- Aktivitätsmodul `mod_knowledgemap`.
- Diagrammprofile: Concept Map, Mindmap, Tree, Semantic Network, Bubble/Word Map.
- Canvas-Editor mit Knoten, Relationen, Labels, automatischem Layout.
- Listenansicht als Relationstabelle mit Bearbeitung und Drag-and-drop.
- Individuelle und gruppenbasierte Workspaces.
- Operation Log, Autosave, Undo/Redo-Grundlage.
- Snapshot-Abgabe.
- Teacher Snapshot Viewer.
- Annotationen an Knoten und Relationen.
- Rubric-/Gradebook-Anbindung.
- KI-Feedbackentwurf über Moodle-AI-API.
- Export: JSON, PNG/SVG.
- Backup/Restore, Privacy API, Completion.
- PHPUnit/Behat-Basisabdeckung.

Geschätzter Aufwand: 80-120 Personentage.

### Version 1.0: belastbare Releasefassung

Ziel: institutionell einsetzbare, dokumentierte und testbare Erstversion.

Zusätzlich zum MVP:

- Verbesserte Teacher-Übersicht.
- Abgabe-/Nachfristlogik.
- Bewertungsworkflow mit Status: nicht abgegeben, abgegeben, in Bewertung, bewertet, zurückgegeben.
- Erweiterte Rubric-Unterstützung.
- Vollständiger Import/Export JSON.
- Kursweite Vorlagenbibliothek.
- Accessibility-Härtung.
- Performance-Härtung für große Kurse.
- Administrationssettings.
- Dokumentation für Lehrende und Admins.

Geschätzter Zusatzaufwand: 30-50 Personentage.

### Version 1.1: Bewertungs- und Feedbackausbau

- AI Feedback Studio für Lehrende.
- Feedbackvorlagen nach Tonalität und Fachkontext.
- Strukturelle automatische Checks.
- Teacher Reference Model light.
- Kommentar-Threads.
- Peer-Review-Modus.
- Bewertungsübersicht je Gruppe/Nutzer.

Geschätzter Zusatzaufwand: 35-55 Personentage.

### Version 1.2: zusätzliche Diagrammprofile

- Argument Map.
- Flow / Process Map.
- Fishbone / Ishikawa.
- Causal / System Map.
- Brace / Whole-Part Map.
- Timeline Map.
- Profilvalidierung und profilabhängige Lehrerchecks.

Geschätzter Zusatzaufwand: 45-70 Personentage.

### Version 1.3: Kollaboration und Kursanalyse

- Verbesserte Near-Realtime-Kollaboration über Polling.
- Objekt-Locking für konfliktträchtige Operationen.
- Beitragsanalyse: Wer hat was beigetragen?
- Activity Events und xAPI-nahe Verlaufsdaten.
- Kursweite Heatmaps: häufige Begriffe, isolierte Knoten, typische Fehlrelationen.
- Vergleich mehrerer Gruppen-Maps.

Geschätzter Zusatzaufwand: 40-65 Personentage.

### Version 2.0: Plattform für visuelle Wissenskonstruktion

- Venn-/Mengenmodell.
- Affinity Board.
- Knowledge Graph mit typisierten Knoten/Relationen.
- Reference Graph Matching.
- KI-gestützte Fehlvorstellungsmarkierung.
- Semantische Import-/Exportprofile.
- Erweiterte Subplugin-Schnittstellen.
- Optionaler Marketplace für Templates, Profile und Bewertungsmodelle.

Geschätzter Zusatzaufwand: 80-140 Personentage.

## 2. Bezahlte Zusatz-(Sub)Plugins

Die Basis sollte didaktisch voll nutzbar sein. Bezahlte Erweiterungen sollten professionelle Spezialfunktionen liefern, nicht die Grundfunktionalität künstlich beschneiden.

### 2.1 `knowledgemapprofile_argument`

Argument Maps mit Claim, Evidence, Warrant, Counterargument, Rebuttal. Besonders geeignet für wissenschaftliches Arbeiten, Ethik, Recht, Diskussionen und Quellenkritik.

### 2.2 `knowledgemapprofile_systems`

Causal Loop und System Maps mit positiven/negativen Wirkbeziehungen, Feedback-Loops und einfachen Systemindikatoren.

### 2.3 `knowledgemapprofile_process`

Flow Maps, Prozessketten, Entscheidungsbäume und einfache Ablaufvalidierung.

### 2.4 `knowledgemapprofile_fishbone`

Ishikawa-/Ursachenanalyse mit vordefinierten Kategorien, Ursachenebenen und Diagnosevorlagen.

### 2.5 `knowledgemapprofile_vennsets`

Venn-, Mengen- und Klassifikationsdiagramme mit Containerlogik, Schnittmengen und Mitgliedschaften.

### 2.6 `knowledgemapprofile_affinity`

Affinity Mapping mit Sticky Notes, Clusterbildung, Gruppenmoderation, Phasenlogik und Sortierworkflows.

### 2.7 `knowledgemapgrade_aiassistant`

Erweiterter KI-Feedback-Assistent: Feedbackstile, Fachkontexte, Mehrsprachigkeit, Rubric-Ausrichtung, Batch-Unterstützung für Lehrende.

### 2.8 `knowledgemapgrade_referencemodel`

Reference-Graph-Vergleich mit erwarteten, optionalen und problematischen Relationen. Keine Vollautomatik, sondern Bewertungsunterstützung.

### 2.9 `knowledgemapanalytics_courseinsights`

Kursweite Analyse: Begriffshäufigkeiten, Relationstypen, typische Fehlkonzepte, Vergleich zwischen Gruppen, Lernfortschritt über Versionen.

### 2.10 `knowledgemapexport_pro`

Erweiterte Exporte: PDF-Report, DOCX-Handout, SVG mit Ebenen, CSV-Relationstabelle, GraphML, RDF/Turtle light.

### 2.11 `knowledgemaptemplate_stem`

Vorlagenpaket für MINT-Fächer: Energie, Kräfte, Differentialrechnung, Thermodynamik, Programmierkonzepte.

### 2.12 `knowledgemaptemplate_language`

Vorlagenpaket für Sprachdidaktik: Wortfelder, semantische Karten, Kollokationen, Begriffsdefinitionen, Argumentationsstrukturen.

### 2.13 `knowledgemapreview_peerplus`

Erweiterter Peer-Review-Workflow mit Zuweisungslogik, anonymisiertem Review, Review-Rubrics und Review-Completion.

## 3. Geschäftsmodell

### 3.1 Basisvariante

Empfehlung: kostenpflichtiger Core mit fairer Campuslizenz oder Open-Core mit professionellen Premium-Modulen.

Core enthält:

- zentrale Editorfunktionen.
- Basisprofile.
- Snapshot-Bewertung.
- einfache KI-Feedbackhilfe.
- Moodle-Integration.

Premium liefert:

- fortgeschrittene Profile.
- Analytics.
- Reference Models.
- Export Pro.
- Template-Bibliotheken.
- erweiterte KI-Workflows.

### 3.2 Lizenzmodelle

- Einzellizenz pro Moodle-Instanz.
- Campuslizenz.
- Support- und Updatevertrag.
- Entwicklungskooperation mit Hochschulen.
- Template-/Profilpakete als Add-ons.

### 3.3 Positionierung

Nicht Konkurrenz zu Miro oder draw.io, sondern Moodle-native didaktische Aktivität mit:

- Kurskontext.
- Gruppen.
- Bewertung.
- Completion.
- Snapshots.
- Feedback.
- Datenschutz.
- Backup/Restore.
- KI über Moodle-AI-API.


## Technische Bezugspunkte / Quellen

- Moodle Developer Resources: React, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react
- Moodle Developer Resources: Frontend Development, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/frontend
- Moodle Developer Resources: React Build Tools, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/buildtools
- Moodle Developer Resources: Mustache Helper and React Autoinit, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/reactautoinit
- Moodle Developer Resources: AI Subsystem, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/ai
- Moodle Developer Resources: AI Subsystem, Moodle main/5.3: https://moodledev.io/docs/5.3/apis/subsystems/ai
- Moodle Developer Resources: Privacy API, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/privacy
- Moodle Developer Resources: Backup API, Moodle 4.5+: https://moodledev.io/docs/4.5/apis/subsystems/backup
