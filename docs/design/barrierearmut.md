# Barrierearmut (Accessibility)

Dieses Dokument hält den Stand der Barrierefreiheit von `mod_vimipad` fest:
umgesetzte Maßnahmen, die herangezogenen Richtlinien und Gestaltungsprinzipien
sowie offene Punkte, die sich (noch) nicht rein technisch adressieren lassen.
Barrierefreiheit ist ein Querschnittsthema (siehe `roadmap.md`); Schwerpunkt in
0.3.x, aber bei jedem Feature mitzudenken.

## Leitprinzip

Ein SVG-Canvas-Editor mit Ziehen/Ablegen ist für Tastatur- und
Screenreader-Nutzung strukturell schwierig. Grundlegende Entwurfsentscheidung
(#6): **Die Listen-Ansicht ist eine gleichberechtigte, vollständige Tastatur-
und Screenreader-Alternative zum Canvas.** Der Canvas wird assistiver Technik
als beschriebenes Bild präsentiert; die eigentliche zugängliche Bearbeitung
läuft über die Listen-Ansicht. Diese Trennung ist bewusst und wird kommuniziert
(Hinweistext am Canvas), statt den Canvas mit einer unzulänglichen
Tastaturschicht zu überladen.

## Herangezogene Richtlinien und Prinzipien

- **WCAG 2.1, Konformitätsstufe AA** als Zielmaß (Wahrnehmbarkeit,
  Bedienbarkeit, Verständlichkeit, Robustheit). Besonders relevant: 1.1.1
  (Textalternativen), 1.4.3 (Kontrast), 1.4.11 (Nicht-Text-Kontrast), 2.1.1
  (Tastaturbedienbarkeit), 2.4.7 (sichtbarer Fokus), 2.3.3/2.2 (Bewegung,
  Reduktion), 4.1.2 (Name, Rolle, Wert), 4.1.3 (Statusmeldungen).
- **WAI-ARIA Authoring Practices** für Rollen und Zustände (Buttons, Toolbar,
  Tabs, Statusmeldungen, `aria-pressed` für Umschalter).
- **Moodle Accessibility Guidelines** (moodledev.io) und die Boost-Theme-
  Konventionen (Bootstrap-Utilities, Fokusverhalten, Farbtokens).
- **SVG Accessibility** (native `<title>`/`<desc>`, `role="img"`,
  `aria-labelledby`/`aria-describedby`).
- **prefers-reduced-motion** als Nutzerpräferenz für reduzierte Bewegung.

## Umgesetzte Maßnahmen (Stand 0.3.x)

### Struktur und Alternative
- Listen-Ansicht als vollwertige, tastaturbedienbare Bearbeitungsoberfläche
  (Tabellensemantik mit `scope="col"`-Spaltenköpfen, screenreader-gerechte
  `<caption>`, beschriftete Inline-Bedienelemente für Subjekt/Relation/Objekt,
  Aktions-Buttons mit `aria-label`).
- Umschaltung Canvas/Liste als ARIA-Tabs (`role="tablist"`/`role="tab"`,
  `aria-selected`, mit `id`/`aria-controls` und verknüpftem `role="tabpanel"`
  über `aria-labelledby`).

### Canvas (SVG)
- `role="img"` mit nativem `<title>` (Name) und `<desc>` (Beschreibung),
  verknüpft über `aria-labelledby`/`aria-describedby`.
- `<desc>` enthält den Hinweis, dass zur Tastaturbearbeitung in die
  Listen-Ansicht gewechselt werden soll (`editor:canvashint`).
- Canvas ist fokussierbar (`tabIndex=0`) mit sichtbarem Fokusrahmen.

### Bedienelemente und Zustände
- Icon-Buttons tragen `aria-label`; Umschalter (Richtung, B/I/U) nutzen
  `aria-pressed`; Docks/Toolbars haben `role`/`aria-label`.
- Undo/Redo per Button und Tastatur (Strg/Cmd+Z, Strg+Shift+Z / Strg+Y); die
  Kürzel greifen nicht in Textfeldern/`contentEditable`, damit das native
  Text-Undo erhalten bleibt.

### Wahrnehmbarkeit
- Konsistente, kontrastreiche `:focus-visible`-Fokusringe für alle interaktiven
  Elemente, theme-unabhängig (plugin-eigene Regeln).
- Plugin-eigene `.vimipad-sr-only`-Utility (nicht auf Theme-Klassen angewiesen).
- Höfliche Statusmeldungen über eine `role="status" aria-live="polite"`-Region
  (z. B. bei Undo/Redo/Neuanordnen); Fehler über `role="alert"`, Sperrhinweis
  über `role="status"`.
- `prefers-reduced-motion`: „Marching-ants"-Auswahlrahmen und Canvas-Transitions
  werden abgeschaltet.

## Offene Punkte — technisch adressierbar (geplant)

- **Volle Canvas-Tastaturnavigation** (Knoten mit Pfeiltasten anfahren,
  auswählen, verschieben, verbinden) als optionale Ergänzung zur Listen-Ansicht.
- **Reichere Live-Ansagen** je Aktion (Knoten/Relation hinzugefügt/gelöscht,
  inkl. Bezeichnungen und aktualisierter Anzahl) statt nur der aktuell knappen
  Aktionsnamen.
- **Reduced-motion in JS-Tweens** (Knoten-Move/-Resize-Animation) explizit an
  `prefers-reduced-motion` koppeln (aktuell nur CSS-Animationen abgedeckt).
- **Farbkontrast-Audit** der Standard- und Nutzerfarben (Füllung/Text/
  Hintergrund, Verbinder) gegen 1.4.3/1.4.11; ggf. Mindestkontrast erzwingen
  oder warnen.
- **Fokus-Management** beim Öffnen/Schließen von Menüs und Editoren (Fokus in
  das Menü setzen, danach sinnvoll zurückgeben).
- **Touch-/Zoom-Bedienbarkeit** auf kleinen Geräten (Teil der Mobile-Politur).

## Offene Punkte — (noch) nicht rein technisch adressierbar

- **Sinnhaftigkeit der Diagrammstruktur für Screenreader.** Eine Concept Map
  ist ein Graph; ihre räumliche/semantische Aussage lässt sich nicht vollständig
  automatisch in eine lineare Ansage überführen. Eine wirklich gleichwertige
  nicht-visuelle Erfahrung erfordert didaktische Konventionen (z. B. wie
  Relationen vorgelesen und wie Teilgraphen zusammengefasst werden) und
  Nutzertests mit Betroffenen — nicht allein Code.
- **Manuelle Prüfung mit echten Hilfsmitteln.** Automatische Checks (axe,
  Moodle-Behat-a11y) fangen nur einen Teil ab. Aussagen zur tatsächlichen
  Bedienbarkeit brauchen Tests mit NVDA/JAWS/VoiceOver, Tastatur-only und
  Vergrößerung — organisatorisch, im Rahmen der Feldtests (0.8.x).
- **Autoreninhalte.** Von Lehrenden vergebene Bezeichnungen, Farben und Vorlagen
  können die Barrierefreiheit unterlaufen (z. B. Farbe als einziger
  Bedeutungsträger, unzureichender Kontrast). Technisch lassen sich nur Hinweise/
  Grenzen setzen; die Verantwortung bleibt teils bei den Autor/innen und
  erfordert Schulung/Doku.
- **Kognitive Zugänglichkeit.** Umfang der Karten, Dichte der Relationen und
  Aufgabenkomplexität betreffen die kognitive Last; das ist eine didaktische
  Gestaltungsfrage, die über technische Maßnahmen hinausgeht.

## Prüf- und Pflegehinweis

Bei jedem neuen Feature: Tastaturpfad, Fokus, Name/Rolle/Wert und Kontrast
mitdenken; neue interaktive Elemente brauchen `aria-label`/sichtbaren Fokus.
Vor 1.0 bzw. spätestens zu den Feldtests (0.8.x) steht ein systematisches
Accessibility-Audit mit Hilfsmitteln an.
