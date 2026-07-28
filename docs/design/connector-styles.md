# Verbinderstile und Bifurkation je Darstellungstyp

Der **Linientyp (Verbinderstil) ist eine Eigenschaft der Darstellungsform**, nicht
der einzelnen Relation. Diese Tabelle ist die Abnahme-/Umsetzungsspezifikation.
Sie wird von den `vimipad_form`-Subplugins (geplant) getragen: jedes Subplugin
liefert die hier genannten Eigenschaften selbst.

## Begriffe

- **Verbinderstil:** gerade | gekrümmt | orthogonal (rechtwinklig) | keine.
- **Bifurkation – Richtung:** Wuchsrichtung der Struktur. `vertikal` = wächst nach
  unten (Kinder spreizen horizontal); `horizontal` = wächst nach rechts (Kinder
  spreizen vertikal); `radial` = vom Zentrum nach außen; `–` = keine Bifurkation.
- **Bifurkation – Art:** `gemeinsam` = mehrere Verbindungen eines Elternknotens
  teilen sich einen Abzweigpunkt/Sammelbus (Organigramm-Stil); `individuell` =
  jede Verbindung ist eigenständig.

## MVP (Version 1.0)

| Darstellungstyp | Verbinderstil | Bifurkation (Richtung) | Bifurkation (Art) |
|---|---|---|---|
| Concept Map (`conceptmap`) | gerade, gerichtet, mit Label | – | individuell |
| Semantic Network (`semanticnetwork`) | gerade, gerichtet, mit Label | – | individuell |
| Mindmap / Radial (`mindmap`) | gekrümmt | radial | individuell |
| Tree / Hierarchie (`tree`) | orthogonal | vertikal (Eltern oben → Kinder unten) | gemeinsam |
| Bubble / Word Map (`bubblemap`) | gekrümmte Speiche | radial | individuell |

## Spätere Darstellungsformen (Roadmap ≥ 1.2, Vorschlag – noch abzunehmen)

| Darstellungstyp | Verbinderstil | Bifurkation (Richtung) | Bifurkation (Art) |
|---|---|---|---|
| Argument Map (`argument`) | orthogonal, gerichtet (Stütze/Angriff) | vertikal (These → Belege) | gemeinsam |
| Flow / Process Map (`process`) | orthogonal, gerichtet | horizontal oder vertikal (Flussrichtung) | individuell |
| Fishbone / Ishikawa (`fishbone`) | gerade/schräg (Rippen an Mittelgräte) | horizontal (Mittelgräte) | gemeinsam |
| Timeline Map (`timeline`) | gerade, an gemeinsamer Zeitachse | horizontal (Zeitachse) | gemeinsam |
| Venn / Mengen (`vennsets`) | keine (Container/Überlappung, Mitgliedschaft) | – | – |
| Systems / Kausalschleifen (`systems`) | gekrümmt, gerichtet (Kausallinks, +/−) | – | individuell |
| Affinity Mapping (`affinity`) | keine/optional (Cluster/Container) | – | – |

## Umsetzungsstatus

- **Verbinderstil je Darstellungstyp:** umgesetzt (gerade/gekrümmt/orthogonal),
  abgeleitet aus dem Profil.
- **Kanten-Clipping:** Verbinder enden am Node-Rand, damit Pfeilspitzen sichtbar
  sind.
- **Bifurkations-Routing:**
  - `tree` (gemeinsam, vertikal): umgesetzt — Kinder eines Elternknotens teilen
    einen gemeinsamen Stamm und einen horizontalen Sammelbus (Organigramm-Stil).
  - übrige MVP-Typen: individuell (bereits über den Verbinderstil abgedeckt).
  - spätere Formen (Argument/Fishbone/Timeline gemeinsam, Systems individuell):
    offen, kommt mit den jeweiligen Subplugins.
- **Auslagerung in `vimipad_form`-Subplugins:** offen — nächster Schritt.
