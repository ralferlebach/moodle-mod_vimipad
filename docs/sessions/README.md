# Sitzungsdokumente

**Eine Sitzung = ein Chat = ein Report.** Die Zählung folgt den tatsächlich
geführten Sitzungen, nicht der Zahl der Releases oder Arbeitsschritte innerhalb
einer Sitzung. In einer einzigen Sitzung können durchaus mehrere Releases
entstehen (Session 005 etwa umfasst 0.8.32 bis 0.9.0).

## Dateien

| Muster | Bedeutung |
| --- | --- |
| `session-NNN.md` | Report der Sitzung NNN: Ergebnis, Releases, Entscheidungen, Gegenbefunde, Verifikation, Methodisches. Wird der Folgesitzung als Kontext-Dokument angehängt. |
| `session-NNN-*.md` | Optionale Zusatzdokumente einer Sitzung (z. B. Arbeitsplan). |
| `sessionstart-NNN.txt` | **Historisch.** Bis Sitzung 005 wurden sitzungsspezifische Startprompts abgelegt. Diese Dateien bleiben als Beleg erhalten, werden aber nicht fortgeschrieben. |

## Startprompt

Der Startprompt ist seit Sitzung 005 **generisch** und liegt in
[`docs/prompt-templates/sessionstart.txt`](../prompt-templates/sessionstart.txt).
Er wird fortgeschrieben, wenn sich Konventionen, Entwurfsentscheidungen oder die
Verifikationskette ändern. Der sitzungsspezifische Kontext kommt ausschließlich
aus dem angehängten `session-NNN.md` der Vorsitzung — es werden **keine** neuen
`sessionstart-NNN.txt` mehr angelegt.

Der Gegenpart für das Sitzungsende liegt in
[`docs/prompt-templates/sessionende.txt`](../prompt-templates/sessionende.txt).
