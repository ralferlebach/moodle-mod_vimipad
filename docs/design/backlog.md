# Arbeitsplanung / Backlog

Lebendes Planungsdokument. Reihenfolge = grobe Priorität, nicht fix.

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
   `core_component`.
4. Automatisches Wachsen der Node-Höhe bei mehrzeiligem Text.

## Backlog (spätere Ausbaustufen)

- **Bewertung anhand von Musterlösungen (Mehrzahl):** Struktur- und
  Semantikabgleich mehrerer hinterlegter Musterlösungen, Rubrik-Bewertung,
  optional KI-Unterstützung beim Formulieren des Feedbacks.
- **Export von Mindmaps als PDF.**
- **Zugriff auf Bearbeitungslogs**, ggf. statistische Auswertung der Mitarbeit
  an einem Diagramm (Beiträge pro Person/Zeit, Aktivitätsverlauf).
- **Vorlagen/Templates durch Lehrende**, mit der Möglichkeit, Änderungen durch
  Lernende einzuschränken (z. B. gesperrte Knoten/Bereiche, nur Ergänzungen).
- **Hintergründe zeichnen:** Kästen/Abschnitte/Container auf dem Canvas, um
  Bereiche visuell zu gruppieren.

## Erledigt (Auswahl, jüngste zuerst)

- Inline-Editing in der Listen-Ansicht (in derselben Zeile, gleiche Reihenfolge).
- Verbinderstil je Darstellungstyp + Kanten-Clipping (sichtbare Pfeilspitzen).
- Zweizeiliges Text-Menü, runde Buttons, grünes OK; Farb-Picker mit OK/Abbrechen
  und Diagnose.
- Overlay-Menüebene (immer nur ein Menü, ganz oben).
- Fix: Connector-Text verschwand beim Richtungswechsel; contentEditable-Editor
  (Multiline, korrekte Farbe/Ausrichtung); Fokus-Wettlauf beim Node-Editor.
