# Template- & Constraint-Policy (0.6-Grundlage)

Status: **Spezifikation** (Vertrag festgelegt, Implementierung in 0.6.x). Diese
Notiz legt fest, *wo* und *wie* Lehrenden-Vorgaben (Templates, Pflicht-/Verbots-
Begriffe, erlaubte Typen, Mindestumfang) wirken, bevor Autorenwerkzeuge und der
Template-Editor gebaut werden. Sie ist bewusst schlank und additiv zum
bestehenden `operation_service`.

## 1. Zwei Wirkebenen — bewusst getrennt

Constraints wirken auf **zwei** Ebenen mit unterschiedlicher Härte, weil ein
kollaborativer Editor nicht bei jeder Zwischenaktion blockieren darf:

1. **Edit-Zeit (weich, beratend).** Verstöße werden angezeigt (Badge/Hinweis im
   Editor und in der Listenansicht), blockieren die Operation aber **nicht**.
   Ausnahme: harte *Struktur*-Sperren aus einem Template (gesperrte Elemente,
   s. u.) — diese werden schon in `operation_service` durchgesetzt.
2. **Abgabe-Zeit (hart, gate).** Beim Snapshot wird die vollständige
   Profil-/Aktivitäts-Policy geprüft; ein Verstoß verhindert die Abgabe mit
   klarer Meldung. Das ist die verlässliche, bewertungsrelevante Prüfung.

Begründung: Echtzeit-Kollaboration + Autosave vertragen keine harten
Per-Operation-Verbote für inhaltliche Regeln (man würde mitten im Denken
geblockt). Der stabile Bewertungsgegenstand ist ohnehin der Snapshot.

## 2. Datenherkunft der Constraints

Die Aktivitäts-Constraints liegen bereits im Instanz-Datensatz bzw. der
`formconfig` (mod_form): Pflichtbegriffe, verbotene Begriffe, erlaubte
Relationstypen, Mindestumfang (Knoten/Relationen). Neu einzuführen ist **kein**
weiteres Schema für Regeln, sondern:

- ein reiner Resolver `\mod_vimipad\local\policy\constraint_policy`
  (kontextfrei, aus Instanz/`formconfig` gebaut), mit
  `evaluate(array $normalized): constraint_report` — nimmt die normalisierte
  Map (dieselbe Struktur wie `snapshot_service::build_normalized`) und liefert
  strukturierte Befunde (`required_missing`, `forbidden_present`,
  `type_violations`, `below_minimum`). Rein, deterministisch, unit-testbar.
- Aufrufer: die Abgabe (hartes Gate) und der Editor/Listen-Renderer (weiche
  Anzeige) nutzen **denselben** Resolver — eine Wahrheit, zwei Darstellungen.

## 3. Templates

Ein Template ist ein von Lehrenden erstellter **Start-Zustand** plus eine
**Sperr-Policy**, kein neues Modul:

- **Start-Zustand:** eine seed-Map (Knoten/Relationen/Container/Layout) — genau
  die Struktur, die Import/Export und die neuen Container-Operationen (0.6.0)
  schon beherrschen. Ein Template ist damit technisch ein
  Import-in-neue-Workspace beim ersten Öffnen.
- **Sperr-Policy:** eine Liste gesperrter Element-Stable-Ids (und optional
  „nur-Label-editierbar"/„nicht löschbar"-Flags), abgelegt in `metadatajson`
  des jeweiligen Elements (`{"locked": true, "editable": ["label"]}`), damit
  Backup/Restore und Export sie ohne Schemaänderung mittragen.

### Durchsetzung der Sperren (hart, in operation_service)

Anders als inhaltliche Regeln werden Template-Sperren **per Operation** in
`operation_service::apply_locked` durchgesetzt (vor der Mutation): eine
`node_delete`/`relation_delete`/`*_update` auf ein `locked`-Element wird mit
`error:elementlocked` abgelehnt; bei `editable: ["label"]` sind nur die
gelisteten Felder änderbar. Das ist billig (ein Metadaten-Check am Live-Element)
und schützt vorgegebene Gerüste zuverlässig, ohne kollaborative Freiheit an
nicht gesperrten Elementen einzuschränken.

## 4. Reihenfolge der Umsetzung (0.6.x)

1. `constraint_policy`-Resolver + `constraint_report` + Unit-Tests (rein).
2. Hartes Abgabe-Gate: Resolver an `create_submission` hängen; Meldungen.
3. Weiche Anzeige im Editor/Listen-Renderer (dieselben Befunde).
4. Template-Sperren: `locked`/`editable`-Check in `apply_locked`
   (+ `error:elementlocked`), Backup/Restore trägt Metadaten bereits.
5. Template-Autorenoberfläche (0.6.0-Werkzeuge): Lehrende markieren Elemente als
   gesperrt und speichern die seed-Map als Template.

## 5. Abgrenzung

- Keine serverseitige Regel-Engine, kein DSL — die Regeln bleiben die schon
  vorhandenen Instanzfelder.
- Keine harten Per-Operation-Inhaltsverbote (nur Struktursperren aus Templates
  sind hart).
- Ontologie-/Typsystem-Verwaltung bleibt außerhalb (siehe Roadmap 2.0).
