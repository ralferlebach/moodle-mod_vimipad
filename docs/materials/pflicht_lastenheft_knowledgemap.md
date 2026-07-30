# Einordnung

Das Vorhaben sollte nicht als einzelner Mindmap-Editor geplant werden, sondern als Moodle-Aktivitätsmodul für visuelle Wissenskonstruktion. Kern ist ein gemeinsames Datenmodell für Knoten, Relationen, Container, Mitgliedschaften, Revisionen, Snapshots, Annotationen und Bewertungen. Die unterschiedlichen Darstellungsformen sind darauf aufsetzende Profile mit eigenen Regeln, Layouts und Constraints.

Die neuen Anforderungen verschieben den Schwerpunkt deutlich in Richtung professioneller Lehr-/Bewertungsworkflow:

- **Snapshot-basierte Bewertung** macht den Bewertungsgegenstand stabil und nachvollziehbar.
- **KI-gestützte Feedbackformulierung** sollte Lehrende unterstützen, nicht automatisch bewerten. Sie arbeitet auf einem eingefrorenen Snapshot, Rubric-Kriterien, Lehrernotizen und optional einem Referenzmodell.
- **Listenansicht mit Drag-and-drop** ist nicht nur Barrierefreiheit, sondern eine zweite vollwertige Editoroberfläche. Eine Relation kann als Zeile bearbeitet, verschoben, mit anderem Subjekt/Objekt verbunden und anschließend in der grafischen Map neu angeordnet werden.
- **React ab Moodle 5.3** kommt dem Projekt stark entgegen, weil ein komplexer, zustandsreicher Canvas-Editor sehr gut zu komponentenbasierter UI, TypeScript, State Management und isolierbaren Tests passt. Für Moodle 4.5 bis 5.2 kann derselbe React-Quellcode als Legacy-Bundle ausgeliefert und über AMD/Mustache-Initialisierung eingebunden werden.
- **Keine zusätzliche Server-Software** ist realistisch, wenn Echtzeit-Kollaboration zunächst über serverautorisierte Operationen, Revisionen, Autosave und kurzes Polling umgesetzt wird. WebSocket-/CRDT-Server, Python-, Rust- oder Node-Dienste gehören dann nicht in die Basisarchitektur.


# Pflicht- und Lastenheft: Moodle Knowledge Map

## 1. Zielbild

Das Plugin soll Lernenden ermöglichen, Wissen grafisch, kollaborativ und reflexiv zu repräsentieren. Lehrende sollen Aufgaben erstellen, Vorlagen und Regeln definieren, Snapshots bewerten, Feedback direkt am Artefakt geben und KI-gestützt elaborierte Rückmeldungen formulieren können.

Arbeitstitel: `mod_knowledgemap`  
Plugin-Typ: Moodle-Aktivitätsmodul  
Referenzkompatibilität: Moodle 4.5 LTS und neuer; Moodle 5.3 mit nativer React-/ESM-Integration als Zielarchitektur.

## 2. Lastenheft: Anforderungen aus Auftraggebersicht

### 2.1 Zielgruppen

- Lernende: individuelle und kollaborative Wissensrepräsentation, Reflexion, Peer Review.
- Lehrende: Aufgabenstellung, Scaffolding, Bewertung, Feedback, Kursübersicht.
- Administratoren: einfache Installation ohne Zusatzserver, saubere Datenschutz-, Backup- und Updatefähigkeit.
- Institutionen: wiederverwendbare Vorlagen, Skalierbarkeit, Barrierefreiheit, Moodle-Integration.

### 2.2 Diagramm- und Repräsentationsformen

#### Muss für MVP

- Concept Map
- Mindmap / Radial Map
- Hierarchische Tree Map
- Semantisches Netz
- Bubble / Word Map

#### Soll für Version 1.x

- Argument Map
- Flow / Process Map
- Fishbone / Ishikawa
- Causal / System Map
- Brace / Whole-Part Map
- Timeline Map

#### Kann / Premium / Version 2

- Venn- und Mengendiagramme
- Affinity Map / Cluster Board
- Knowledge Graph mit typisierten Knoten und Relationen
- Double Bubble Map
- Reference-Graph-Vergleich
- KI-gestützte Fehlvorstellungsdiagnostik

### 2.3 Arbeitsmodi

- Einzelarbeit: eine Map je Nutzer.
- Gruppenarbeit: eine Map je Moodle-Gruppe.
- Kurs-Map: eine gemeinsame Map für den Kurs.
- Peer Review: Maps werden nach Abgabe kommentiert oder verglichen.
- Bewertungsmodus: Lehrende bewerten einen unveränderlichen Snapshot.

### 2.4 Lehrendenseitige Erstellung

Lehrende müssen beim Anlegen der Aktivität mindestens festlegen können:

- Diagrammprofil oder erlaubte Profile.
- Aufgabenstellung und erwartetes Produkt.
- Startvorlage: leer, teilgefüllt, stark scaffolded.
- Pflichtbegriffe, verbotene Begriffe, Relationstypen, Mindestumfang.
- Bearbeitungsform: individuell, Gruppe, Kursgemeinschaft.
- Abgabefrist, Nachfrist, Sperrverhalten nach Abgabe.
- Bewertungsmethode: Punkte, Rubric, Guide, unbewertet.
- KI-Unterstützung aktiv/inaktiv und zulässige Datenbasis.
- Exportoptionen für Lernende und Lehrende.

### 2.5 Bewertungsanforderungen

Die Bewertung muss snapshot-basiert erfolgen. Ein Snapshot ist ein unveränderlicher Stand aus Nodes, Relations, Containers, Layout, Revision, Autorinformationen und Metadaten. Nachträgliche Bearbeitung darf den Bewertungsgegenstand nicht verändern.

Bewertungsbestandteile:

- Strukturelle Checks: Knotenanzahl, Relationstypen, Tiefe, Cross-Links, Pflichtbegriffe, isolierte Knoten.
- Inhaltliche Lehrerbewertung: Rubric, Guide, Freitextfeedback.
- Annotationen direkt an Knoten, Relationen oder Bereichen.
- Optionaler Vergleich mit einem Reference Model.
- KI-gestützte Formulierung elaborierter Rückmeldungen auf Basis von Snapshot, Rubric und Lehrernotizen.

Nichtziel: vollautomatische, endgültige fachliche Benotung durch KI.

### 2.6 KI-gestütztes Feedback

Die KI-Funktion soll Lehrende beim Schreiben elaborierter individueller Rückmeldungen unterstützen. Der Workflow:

1. Lehrender öffnet Snapshot.
2. Lehrender vergibt Rubric-/Guide-Punkte und/oder macht Kurznotizen.
3. Das System extrahiert eine datensparsame Textrepräsentation der Map.
4. Über die Moodle-AI-API wird ein Textvorschlag generiert.
5. Lehrender prüft, editiert und übernimmt den Text.
6. Erst die freigegebene Version wird als Feedback gespeichert.

KI darf nicht ungeprüft an Lernende gesendet werden. Prompts und Antworten müssen protokollierbar, datenschutzkonform und für Lehrende transparent sein.

### 2.7 Listenansicht mit Drag-and-drop

Die Listenansicht ist eine gleichberechtigte Bearbeitungsansicht, nicht nur Export.

Mindestfunktionen:

- Darstellung als Relationstabelle: Subjekt - Relation - Objekt.
- Knotenliste mit Typ, Label, Status, Autor, Kommentarindikator.
- Drag-and-drop von Relationen zwischen Subjekt-/Objektgruppen.
- Drag-and-drop zur Änderung von Quelle, Ziel oder Relationstyp.
- Reihenfolge in der Liste steuert auf Wunsch die automatische Layout-Anordnung.
- Tastaturbedienbare Alternativen zu Drag-and-drop.
- Synchronisierung mit der grafischen Map in beide Richtungen.

### 2.8 Moodle-Integration

Muss-Anforderungen:

- Gradebook-Integration.
- Completion-API.
- Gruppenmodi: keine Gruppen, getrennte Gruppen, sichtbare Gruppen.
- Capabilities für ansehen, bearbeiten, kommentieren, bewerten, KI nutzen, exportieren, administrieren.
- Backup/Restore.
- Privacy API.
- Event API.
- Moodle Forms für Aktivitätseinstellungen.
- Mustache Templates und Moodle-Standard-Renderer.

### 2.9 Nichtfunktionale Anforderungen

- Keine zusätzliche Server-Software außer PHP/Moodle und Datenbank.
- Keine serverseitigen Python-, Rust-, Node- oder WebSocket-Dienste.
- Clientseitig dürfen statische JavaScript-Bundles ausgeliefert werden.
- Reaktionszeit für Standardoperationen unter 200 ms clientseitig.
- Autosave ohne Speicherung jeder Mausbewegung.
- Nutzbar mit 300 Knoten und 500 Relationen pro Map im MVP-Zielbereich.
- Barrierearme Bedienung über alternative Listen- und Tabellenansichten.
- Keine hart codierten Language Strings.
- Vollständige Capability- und Context-Prüfung bei allen externen Funktionen.

## 3. Pflichtenheft: Umsetzung aus Anbietersicht

### 3.1 Systemkern

Das Plugin implementiert ein gemeinsames Domänenmodell:

- Map Instance
- Workspace
- Node
- Relation
- Container
- Membership
- Layout State
- Operation Log
- Revision
- Snapshot
- Annotation
- Submission
- Grade/Feedback
- AI Feedback Draft

Diagrammtypen werden als Profile definiert. Ein Profil setzt Validierungsregeln, erlaubte Typen, Layoutstrategien und UI-Bausteine.

### 3.2 Rollen und Rechte

Beispielhafte Capabilities:

- `mod/knowledgemap:view`
- `mod/knowledgemap:editown`
- `mod/knowledgemap:editgroup`
- `mod/knowledgemap:comment`
- `mod/knowledgemap:submit`
- `mod/knowledgemap:grade`
- `mod/knowledgemap:useai`
- `mod/knowledgemap:export`
- `mod/knowledgemap:manageprofiles`

### 3.3 KI-Pflichten

- Nutzung der Moodle-AI-API, nicht direkter Provider-Zugriffe im Aktivitätscode.
- Primär `generate_text`, optional `summarise_text` für lange Maps.
- Einhaltung der Moodle-AI-Policy-Akzeptanz.
- Datenminimierung: keine unnötigen personenbezogenen Daten in Prompts.
- Feedback bleibt Entwurf, bis Lehrende es aktiv übernehmen.
- Speicherung von Prompt-Metadaten nur soweit für Nachvollziehbarkeit und Datenschutz nötig.
- Administrator kann KI-Funktion global und pro Aktivität deaktivieren.

### 3.4 Frontend-Pflichten

Für Moodle 5.3:

- React + TypeScript + ESM.
- Mounting über Moodle React Autoinit/Mustache-Helper, wo verfügbar.
- Nutzung gemeinsamer Moodle Runtime statt Bündelung von React, soweit Moodle 5.3 dies bereitstellt.

Für Moodle 4.5 bis 5.2:

- Legacy-Bundle aus demselben React-Quellcode.
- Initialisierung über AMD-Modul und Mustache-Placeholder.
- React und benötigte Bibliotheken als dokumentierte Third-Party-Assets im Pluginpaket.
- Keine serverseitige Build-Abhängigkeit beim Betrieb.

### 3.5 Abnahmefähige Kernfunktionen MVP

- Aktivität anlegen und konfigurieren.
- Map individuell oder als Gruppe bearbeiten.
- Knoten und Relationen in Canvas erstellen, verschieben, löschen, beschriften.
- Relationstabelle bearbeiten und per Drag-and-drop umstrukturieren.
- Automatisches Layout anwenden.
- Snapshot abgeben.
- Snapshot bewerten.
- Annotationen setzen.
- KI-Feedbackentwurf erzeugen und durch Lehrende übernehmen.
- Export als PNG/SVG/JSON, später PDF.
- Backup/Restore, Privacy API und grundlegende Tests.

### 3.6 Qualitätskriterien

- PHPUnit für PHP-Services, Validierung und Rechteprüfung.
- Behat für Kernworkflows.
- JavaScript-/React-Tests für zentrale Komponenten.
- Security Review: Parameter, sesskey, context, capabilities, group access.
- Performance Review: keine N+1-Abfragen in Übersichten, paginierte Teacher Dashboards.
- Accessibility Review: Tastaturbedienung, ARIA, Listenansicht, Tabellenalternative.

## 4. Abgrenzungen

Nicht Bestandteil des MVP:

- Echtzeit-Kollaboration über WebSocket.
- CRDT-Server.
- Vollautomatische KI-Benotung.
- Vollständig semantische Ontologieverwaltung.
- Venn-Diagramme mit geometrischer Mengenlogik.
- Mobile-App-Offlinesynchronisation.


## Technische Bezugspunkte / Quellen

- Moodle Developer Resources: React, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react
- Moodle Developer Resources: Frontend Development, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/frontend
- Moodle Developer Resources: React Build Tools, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/buildtools
- Moodle Developer Resources: Mustache Helper and React Autoinit, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/reactautoinit
- Moodle Developer Resources: AI Subsystem, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/ai
- Moodle Developer Resources: AI Subsystem, Moodle main/5.3: https://moodledev.io/docs/5.3/apis/subsystems/ai
- Moodle Developer Resources: Privacy API, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/privacy
- Moodle Developer Resources: Backup API, Moodle 4.5+: https://moodledev.io/docs/4.5/apis/subsystems/backup
