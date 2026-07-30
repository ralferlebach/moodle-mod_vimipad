# Arbeitsplanung / Backlog

> **Achtung — dieses Dokument ist bis 0.5.32 überholt und wird in der
> 0.5.33-Closure-Runde für 0.6.x neu aufgesetzt.** Autoritativ für den
> *Umsetzungsstand* ist das `CHANGELOG.md`, für die *Versionsplanung* die
> [roadmap.md](roadmap.md). Etliche unten als „später" gelistete Punkte sind
> längst umgesetzt (PDF-Export, Bearbeitungslogs, Musterlösungsbewertung,
> `formconfig`-Konsum) — siehe Annotationen. Erledigte Historie gehört ins
> Changelog, nicht in den Backlog.

Lebendes Planungsdokument. Reihenfolge = grobe Priorität, nicht fix.
Die versionierte Gesamtplanung bis 1.0 steht in [roadmap.md](roadmap.md).

## In Arbeit / als Nächstes

1. **Doppelpfeil als zwei Listen-Einträge** — umgesetzt: eine `direction = 2`-
   Relation erscheint in der Listen-Ansicht als zwei zusammenhängende Einträge
   (A→B und B→A) mit gemeinsamem Relationstext; Bearbeiten/Löschen wirkt auf die
   eine zugrundeliegende Relation.
2. **Bifurkations-Routing** gemäß `connector-styles.md`: gemeinsame Abzweige/
   Sammelbusse (Tree/Argument/Fishbone/Timeline) sowie radiale/individuelle
   Führung.
3. **Auslagerung in `vimipad_form`-Subplugins**: Verbinderstil, Bifurkation und
   erlaubte Formen wandern ins jeweilige Subplugin; Entdeckung über
   `core_component`. — PHP-Grundgerüst erledigt (Typ `vimipadform`, Registry,
   fünf MVP-Subplugins, `formconfig` in `get_workspace`). ✅ **Erledigt:** das
   Frontend konsumiert `formconfig` (`js/src/canvas/form_config.ts`, `CanvasView`,
   `NodeFormatToolbar`, Jest-getestet).
4. Automatisches Wachsen der Node-Höhe bei mehrzeiligem Text.

## Backlog (spätere Ausbaustufen)

- **Bewertung anhand von Musterlösungen:** ✅ **Einzahl** umgesetzt
  (`vimipadassess`, 6 Scorer, `gradingform`, KI-Feedback). ◐ **Mehrzahl offen** —
  Contract ist mehrzahlfähig, DB/Orchestrierung derzeit singular
  (`referencesnapshotid`); Entscheidung in 0.5.33.
- ~~**Export von Mindmaps als PDF.**~~ ✅ umgesetzt (0.4.x).
- ~~**Zugriff auf Bearbeitungslogs** + statistische Auswertung.~~ ✅
  umgesetzt (Journal + Report/`workspace_summary`, 0.4.x/0.5.3).
- **Vorlagen/Templates durch Lehrende** (○ 0.6.x) — erfordert vorab ein
  serverseitiges Constraint-/Policy-Modell (nicht nur UI-Buttons verstecken).
- **Hintergründe zeichnen (Container):** (○ 0.6.x) — erfordert vorab
  `container_*`/`membership_*`-Operationen im Operation-Log (heute nur
  `node_*`/`relation_*`).

## Erledigt (Auswahl, jüngste zuerst)

- Inline-Editing in der Listen-Ansicht (in derselben Zeile, gleiche Reihenfolge).
- Verbinderstil je Darstellungstyp + Kanten-Clipping (sichtbare Pfeilspitzen).
- Zweizeiliges Text-Menü, runde Buttons, grünes OK; Farb-Picker mit OK/Abbrechen
  und Diagnose.
- Overlay-Menüebene (immer nur ein Menü, ganz oben).
- Fix: Connector-Text verschwand beim Richtungswechsel; contentEditable-Editor
  (Multiline, korrekte Farbe/Ausrichtung); Fokus-Wettlauf beim Node-Editor.
