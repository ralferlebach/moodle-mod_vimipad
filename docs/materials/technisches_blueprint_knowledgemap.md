# Einordnung

Das Vorhaben sollte nicht als einzelner Mindmap-Editor geplant werden, sondern als Moodle-Aktivitätsmodul für visuelle Wissenskonstruktion. Kern ist ein gemeinsames Datenmodell für Knoten, Relationen, Container, Mitgliedschaften, Revisionen, Snapshots, Annotationen und Bewertungen. Die unterschiedlichen Darstellungsformen sind darauf aufsetzende Profile mit eigenen Regeln, Layouts und Constraints.

Die neuen Anforderungen verschieben den Schwerpunkt deutlich in Richtung professioneller Lehr-/Bewertungsworkflow:

- **Snapshot-basierte Bewertung** macht den Bewertungsgegenstand stabil und nachvollziehbar.
- **KI-gestützte Feedbackformulierung** sollte Lehrende unterstützen, nicht automatisch bewerten. Sie arbeitet auf einem eingefrorenen Snapshot, Rubric-Kriterien, Lehrernotizen und optional einem Referenzmodell.
- **Listenansicht mit Drag-and-drop** ist nicht nur Barrierefreiheit, sondern eine zweite vollwertige Editoroberfläche. Eine Relation kann als Zeile bearbeitet, verschoben, mit anderem Subjekt/Objekt verbunden und anschließend in der grafischen Map neu angeordnet werden.
- **React ab Moodle 5.3** kommt dem Projekt stark entgegen, weil ein komplexer, zustandsreicher Canvas-Editor sehr gut zu komponentenbasierter UI, TypeScript, State Management und isolierbaren Tests passt. Für Moodle 4.5 bis 5.2 kann derselbe React-Quellcode als Legacy-Bundle ausgeliefert und über AMD/Mustache-Initialisierung eingebunden werden.
- **Keine zusätzliche Server-Software** ist realistisch, wenn Echtzeit-Kollaboration zunächst über serverautorisierte Operationen, Revisionen, Autosave und kurzes Polling umgesetzt wird. WebSocket-/CRDT-Server, Python-, Rust- oder Node-Dienste gehören dann nicht in die Basisarchitektur.


# Technisches Blueprint: `mod_knowledgemap`

## 1. Grundarchitektur

```text
mod_knowledgemap
│
├── Moodle Activity Layer
│   ├── lib.php, mod_form.php, view.php
│   ├── capabilities, events, completion, gradebook
│   ├── backup/restore, privacy provider
│   └── external services / AJAX endpoints
│
├── Domain Layer
│   ├── map_service
│   ├── workspace_service
│   ├── operation_service
│   ├── snapshot_service
│   ├── annotation_service
│   ├── grading_service
│   └── ai_feedback_service
│
├── Diagram Profile Layer
│   ├── conceptmap
│   ├── mindmap
│   ├── tree
│   ├── semantic_network
│   ├── bubble_wordmap
│   └── später: argument, fishbone, venn, affinity, causal
│
├── Frontend
│   ├── React/TypeScript source
│   ├── Moodle 5.3 ESM build
│   ├── Moodle 4.5+ legacy AMD bundle
│   ├── Canvas/Graph view
│   ├── List/Table view
│   ├── Teacher grading view
│   └── AI feedback assistant UI
│
└── Optional Subplugins
    ├── knowledgemapprofile_*
    ├── knowledgemapexport_*
    ├── knowledgemapgrade_*
    └── knowledgemapanalytics_*
```

## 2. Server-Runtime-Constraint

Der Serverbetrieb darf ausschließlich Moodle/PHP und die Moodle-Datenbank voraussetzen. Es werden keine Python-, Rust-, Node-, Java-, Go- oder WebSocket-Dienste installiert. JavaScript-Build-Tools sind nur Entwicklungs- oder Release-Werkzeuge. Ausgeliefert werden fertige statische Assets.

Konsequenz:

- Kollaboration im MVP über Moodle AJAX/Webservice + Operation Log + Polling.
- Keine serverseitige CRDT- oder WebSocket-Infrastruktur.
- KI über Moodle-AI-Subsystem und konfigurierte Moodle-AI-Provider.
- Diagrammlayouts clientseitig oder als PHP-basierte Fallbacks, nicht über externe Layoutserver.

## 3. Datenmodell

### 3.1 Haupttabellen

```text
knowledgemap
  id, course, name, intro, introformat,
  defaultprofile, collaborationmode, gradingmode,
  aienabled, timecreated, timemodified

knowledgemap_workspace
  id, knowledgemapid, userid, groupid, name,
  currentrevision, submittedsnapshotid, locked,
  timecreated, timemodified

knowledgemap_node
  id, workspaceid, stableid, type, label,
  content, contentformat, metadatajson,
  createdby, modifiedby, timecreated, timemodified, deleted

knowledgemap_relation
  id, workspaceid, stableid, sourceid, targetid,
  type, label, direction, metadatajson,
  createdby, modifiedby, timecreated, timemodified, deleted

knowledgemap_container
  id, workspaceid, stableid, type, label,
  geometryjson, metadatajson, deleted

knowledgemap_membership
  id, containerid, itemtype, itemid, role, sortorder

knowledgemap_layout
  id, workspaceid, profile, viewportjson, layoutjson,
  modifiedby, timemodified

knowledgemap_operation
  id, workspaceid, revision, operationtype,
  payloadjson, userid, timecreated

knowledgemap_snapshot
  id, workspaceid, revision, snapshotjson,
  submittedby, timecreated, status

knowledgemap_annotation
  id, snapshotid, targettype, targetstableid,
  commenttext, commentformat, userid, timecreated, timemodified

knowledgemap_aifeedback
  id, snapshotid, graderid, promptcontextjson,
  drafttext, draftformat, acceptedtext, acceptedformat,
  providerinfo, timecreated, timemodified
```

### 3.2 Stable IDs

Interne Datenbank-IDs dürfen nicht in Snapshots oder Clientoperationen als dauerhafte Fachidentifikatoren dienen. Dafür werden stabile UUID-/Hash-IDs verwendet. So bleiben Import/Export, Backup/Restore und Snapshotvergleich robuster.

## 4. Operation-Log und Snapshots

Jede fachliche Änderung wird als Operation gespeichert:

```json
{
  "type": "relation_retarget",
  "stableid": "rel_8fd4",
  "oldsource": "node_a",
  "newsource": "node_b",
  "target": "node_c",
  "revision": 42
}
```

Der Server validiert:

- Context und Capability.
- Gruppenzugehörigkeit.
- Workspace-Zugriff.
- Revision und Konfliktstatus.
- Profilregeln.
- Parametertypen.

Snapshots enthalten einen vollständigen normalisierten Stand:

```text
snapshot = nodes + relations + containers + memberships + layout + profile + revision + metadata
```

Bewertung und KI-Feedback beziehen sich immer auf `snapshotid`.

## 5. React-Strategie

### 5.1 Moodle 5.3+

Moodle 5.3 unterstützt moderne Frontend-Entwicklung mit ESM, React und TypeScript. Für dieses Plugin ist das ideal, weil der Editor stark zustandsgetrieben ist:

- Canvas-Komponenten.
- Relationstabelle.
- Drag-and-drop.
- Inspector Panel.
- Rubric-/Feedbackansicht.
- AI-Assistent.
- Undo/Redo.
- Optimistische UI mit serverautorisierter Bestätigung.

Geplante Struktur:

```text
mod/knowledgemap/js/esm/src/
  app/
  components/
  graph/
  listview/
  grading/
  ai/
  stores/
  services/
  types/
```

### 5.2 Moodle 4.5 bis 5.2

Ältere Moodle-Versionen profitieren über eine Legacy-Auslieferung:

```text
React/TypeScript source
       │
       ├── Moodle 5.3 ESM build
       └── Moodle 4.5+ AMD-compatible legacy bundle
```

Der Legacy-Build wird als statische JS-Datei mit ausgeliefert. React wird für 4.5-5.2 als dokumentierte Third-Party-Library gebündelt. Das erhöht Paketgröße und Wartungsaufwand, vermeidet aber zusätzliche Server-Abhängigkeiten.

### 5.3 Progressive Enhancement

- Moodle 5.3 nutzt native React-Integration.
- Moodle 5.2 kann bei Verfügbarkeit des Mustache-React-Helpers davon profitieren.
- Moodle 4.5/5.0/5.1 nutzt Mustache-Container plus AMD-Initializer.
- Die PHP-Services und das Domänenmodell bleiben identisch.

## 6. Listenansicht mit Drag-and-drop

### 6.1 Grundmodell

Die Listenansicht zeigt Relationen als editierbare Zeilen:

```text
[Subjekt]  [Relation]  [Objekt]  [Status]  [Kommentar]
Energie    ist Form von Bewegung  offen     1
Wärme      entsteht bei Reibung   geprüft   0
```

Drag-and-drop-Fälle:

- Relation auf anderes Subjekt ziehen -> `relation_retarget_source`.
- Relation auf anderes Objekt ziehen -> `relation_retarget_target`.
- Zeile in andere semantische Gruppe ziehen -> Relationstyp oder Containerzuordnung ändern.
- Knoten in Relationstabelle ziehen -> neue Relation erzeugen.
- Reihenfolge ändern -> `sortorder` für automatisches Layout speichern.

### 6.2 Accessibility

Jede Drag-and-drop-Operation braucht eine Tastaturalternative:

- Zeile auswählen.
- Aktion wählen: Quelle ändern, Ziel ändern, Typ ändern, verschieben.
- Ziel über Suchfeld oder Liste auswählen.
- Bestätigen.

## 7. KI-Feedback auf Basis der Moodle-AI-API

### 7.1 Workflow

```text
Snapshot öffnen
  ↓
Rubric/Notizen erfassen
  ↓
Map normalisieren und kürzen
  ↓
AI Action generate_text über Moodle-AI-Manager
  ↓
Feedbackentwurf anzeigen
  ↓
Lehrende editieren und übernehmen
  ↓
Feedback speichern und optional an Gradebook koppeln
```

### 7.2 Prompt-Kontext

Der Prompt sollte enthalten:

- Aufgabenstellung.
- Diagrammprofil.
- Snapshot als kompakte Relationstabelle.
- Rubric-Kriterien und erreichte Punkte.
- Lehrer-Kurznotizen.
- Optionale automatische Strukturhinweise.
- Zieltonalität: konstruktiv, konkret, lernförderlich.
- Explizites Verbot, zusätzliche Fakten zu halluzinieren.

Beispielauszug:

```text
Verfasse ein individuelles Feedback für Lernende.
Bewerte nicht neu, sondern formuliere auf Basis der Lehrerentscheidung.
Nutze maximal 180 Wörter.
Nenne zwei Stärken, zwei konkrete Verbesserungen und einen nächsten Arbeitsschritt.
```

### 7.3 Datenschutz

- Nutzernamen in Prompts vermeiden, außer explizit erforderlich.
- Gruppen-ID statt personenbezogener Namensliste.
- Speicherung von Prompts optional administrativ konfigurierbar.
- AI-Nutzung capability-gesichert.
- AI-Policy-Akzeptanz prüfen.

## 8. Kollaboration ohne Zusatzserver

### 8.1 MVP-Variante

- Client speichert Operationen über AJAX/Webservice.
- Server vergibt Revisionen.
- Andere Clients pollen alle 2-5 Sekunden nach neuen Revisionen.
- Bei Konflikten wird eine Konfliktmeldung angezeigt.
- Kurzzeit-Locks für kritische Objekte sind möglich.

### 8.2 Grenzen

Nicht enthalten:

- echte gleichzeitige Textbearbeitung wie Google Docs.
- CRDT-Synchronisation.
- WebSocket-Präsenzanzeige in Echtzeit.

Diese Grenzen sind akzeptabel, weil das Plugin ohne zusätzliche Serverkomponenten installierbar bleiben soll.

## 9. Diagrammprofile

Ein Profil definiert:

- erlaubte Knotentypen.
- erlaubte Relationstypen.
- Strukturregeln.
- Layoutstrategie.
- Listendarstellung.
- Validierungsregeln.
- Teacher-Checks.

Beispiel:

```php
interface profile_interface {
    public function get_allowed_node_types(): array;
    public function get_allowed_relation_types(): array;
    public function validate_workspace(workspace $workspace): validation_result;
    public function get_default_layout(): layout_strategy;
    public function get_teacher_checks(): array;
}
```

## 10. Externe Funktionen / AJAX

Beispiele:

```text
mod_knowledgemap_get_workspace
mod_knowledgemap_apply_operation
mod_knowledgemap_create_snapshot
mod_knowledgemap_get_snapshot
mod_knowledgemap_save_annotation
mod_knowledgemap_get_grading_context
mod_knowledgemap_generate_ai_feedback_draft
mod_knowledgemap_accept_ai_feedback
mod_knowledgemap_export_snapshot
```

Jede Funktion prüft:

- `require_login` und Course Module Context.
- Capability.
- Gruppenzugriff.
- `sesskey` bei Schreibzugriff.
- strikte Parametertypen.

## 11. Performance

- Kein Speichern jeder Mousemove-Operation.
- Dragging clientseitig, Speicherung erst bei Drop.
- Batch-Operationen für Mehrfachänderungen.
- Paginierte Teacher-Übersichten.
- Aggregierte Fortschrittsdaten in separaten Queries.
- Caching von Profildefinitionen.
- Komprimierte Snapshotrepräsentation.
- Lazy Loading von Kommentar- und History-Details.

## 12. Teststrategie

### PHPUnit

- Operation Service.
- Profilvalidierung.
- Snapshot-Erstellung.
- Rechte- und Gruppenzugriff.
- AI-Feedback-Kontextaufbereitung.
- Backup/Restore-Strukturen.
- Privacy Provider.

### Behat

- Aktivität anlegen.
- Knoten/Relation erstellen.
- Listenansicht bearbeiten.
- Snapshot abgeben.
- Lehrender bewertet Snapshot.
- KI-Feedbackentwurf erzeugen und übernehmen.
- Gruppenmodus getrennte Gruppen.

### JS/React

- State Reducer.
- Drag-and-drop-Operationen.
- Undo/Redo.
- Canvas/Listensynchronisierung.
- Inspector- und Annotation-Komponenten.

## 13. Security-Checkliste

- Keine `PARAM_RAW` für fachliche Parameter, außer kontrolliert für JSON mit Schema-Validierung.
- Alle JSON-Payloads validieren.
- Alle Schreiboperationen mit `sesskey`.
- Kein direktes Vertrauen in Client-Revisionen.
- Keine ungeprüften HTML-Labels in Map-Rendering.
- File API für Medien.
- Fragetexte, Feedback und Kommentare mit Moodle-Format-APIs ausgeben.

## 14. Deployment

- Auslieferung als normales Moodle-Plugin-ZIP.
- Kein Composer/Node/npm auf Produktivserver erforderlich.
- Third-Party-JS sauber dokumentieren.
- CI baut Artefakte und prüft Standards.
- Releasepaket enthält nur gebaute Assets, Quellcode und Lizenzdateien.


## Technische Bezugspunkte / Quellen

- Moodle Developer Resources: React, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react
- Moodle Developer Resources: Frontend Development, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/frontend
- Moodle Developer Resources: React Build Tools, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/buildtools
- Moodle Developer Resources: Mustache Helper and React Autoinit, Moodle main/5.3: https://moodledev.io/docs/5.3/guides/javascript/react/reactautoinit
- Moodle Developer Resources: AI Subsystem, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/ai
- Moodle Developer Resources: AI Subsystem, Moodle main/5.3: https://moodledev.io/docs/5.3/apis/subsystems/ai
- Moodle Developer Resources: Privacy API, Moodle 4.5: https://moodledev.io/docs/4.5/apis/subsystems/privacy
- Moodle Developer Resources: Backup API, Moodle 4.5+: https://moodledev.io/docs/4.5/apis/subsystems/backup
