# Visual Maps – Anforderungen & Roadmap

Sammel- und Arbeitsdokument. Wird laufend ergänzt. Legt fest, welche
Diagrammtypen ViMi Pad langfristig unterstützen soll und wie die Interaktion
mit Nodes und Connections auszusehen hat. Der Status „geplant" bedeutet: noch
nicht umgesetzt, hier festgehalten zur späteren Umsetzung.

## Geplante Map-Typen (später umzusetzen)

Über die bereits vorgesehenen Profile (Concept Map, Mind Map …) hinaus sollen
folgende Diagrammtypen als weitere Profile auf dem gemeinsamen Domänenmodell
umgesetzt werden:

- Familienbaum
- Evolutionsbäume
- (Business-)Organigramme
- Strukturgleichungsmodelle
- IT-Architektur-Modelle
- Programmablaufpläne

Jeder Typ ist ein Profil über demselben Kern (Nodes, Relations, Container,
Revisionen, Snapshots, Locks); Unterschiede liegen in erlaubten Node-/
Relationstypen, Richtungssemantik, Layout-Regeln und Darstellung.

## Anforderungen an die Interaktion (geplant, später zu ergänzen)

Diese Anforderungen gelten übergreifend für die Visual Maps. Sie sind bewusst
als Zielbild formuliert; die konkrete Umsetzung erfolgt schrittweise und wird
hier verfeinert.

### Verbinden von Nodes
- Nodes verbinden sich, wenn sie an- bzw. aufeinander geschoben werden.
- Alternativ: Klick auf eine „Connect-Zone" eines Nodes und Ziehen auf einen
  anderen Node erzeugt eine Connection.

### Darstellung von Connections
- Bei gerichteten Graphen tragen Connections Pfeile.
- Ungerichtete Connections haben an ihren Enden kleine „Knubbel".
- Zwischen zwei Nodes sollen ggf. mehrere Connections möglich sein (gerichtet
  oder ungerichtet, jeweils benennbar), sauber voneinander getrennt geführt.

### Beschriftung von Connections
- Die Schrift der Connection-Benennungen erhält eine weiße Outline.
- Beschriftungen liegen immer über Connectors und Nodes, sodass die Schrift vor
  jedem Hintergrund gut lesbar bleibt.

### Auswahl, Menüs und Hover
- Mouse-Hover über einen Node oder über die Connector-Schrift blendet
  Menü-Symbole ein.
- Klick auf Nodes oder Connectors markiert/aktiviert diese und blendet
  Menü-Symbole ein.
- Die konkreten Menü-Symbole und ihre Funktionen sind noch im Detail zu klären.

### Tastatursteuerung
- Markierte/aktivierte Nodes und Connectors können mit Entf gelöscht werden.
- ESC hebt die Markierung/Aktivierung von Nodes und Connectors wieder auf.

### Text bearbeiten
- Doppelklick auf die Schrift in Nodes oder Connectors öffnet den Edit-Mode zum
  Ändern des Textes.
- Enter beendet die Eingabe, Shift+Enter fügt eine neue Zeile ein.

## Offene Punkte
- Menü-Symbole und deren Funktionen im Detail festlegen.
- Layout-Regeln und Richtungssemantik je Map-Typ ausarbeiten.
- Zusammenspiel dieser Interaktionen mit dem serverautorisierten Operation-Log
  und dem Element-Locking (Kollaboration) je Interaktion klären.

## Umsetzungsstand (wird fortgeschrieben)

Erledigt in 0.2.12 (Logik Jest-getestet, Optik im Browser zu bestätigen):
- Klick auf Node/Connection markiert/aktiviert diese.
- ESC demarkiert; Entf löscht das markierte Element (nie während des Text-Edits).
- Doppelklick auf Node-Text öffnet Inline-Edit; Enter bestätigt, Shift+Enter neue
  Zeile, ESC bricht ab.
- Connection-Beschriftung mit weißer Outline (paint-order: stroke), gut lesbar.
- Geometrie-Grundlage für getrennt geführte Mehrfach-Connections, Rand-Anker und
  Marker-Wahl (Pfeil gerichtet / Knubbel ungerichtet) vorhanden (connection_geometry).

Als Nächstes:
- Connection-Erzeugung (Nodes zusammenschieben / Connect-Zone + Ziehen).
- Rendering mehrerer getrennter Connections zwischen zwei Nodes inkl. Knubbel-Marker.
- Inline-Edit auch für Connection-Beschriftungen.
- Hover-/Auswahl-Menüsymbole (Funktionen noch im Detail zu klären).
