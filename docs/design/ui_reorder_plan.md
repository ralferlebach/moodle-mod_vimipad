# UI-Neuordnung — Arbeitsplanung

> **Status-Abgleich (Stand 0.5.32).** Große Teile dieser Planung wurden bereits
> in der 0.5.x-Linie vorgezogen und sind **umgesetzt**: Reiter-Gerüst + Selektor
> + Canvas/Liste (Schritt 1, 0.5.6–0.5.9), Journal-&-Abgabe-Reiter + Konsens-
> Zustandsautomat (Schritt 2, 0.5.10–0.5.16), Bewertungs-Reiter mit `gradingform`
> (Schritt 3 „Bewertung", 0.5.18–0.5.24). **Offen (→ 0.6.0):** die Reiter
> **„Feedback"** und **„Werkzeuge"** (in `view.php` derzeit `tab:comingsoon`)
> sowie der optionale Replay (Schritt 5). Dieser Plan wird in 0.6.0 mit der
> zentralen Roadmap (Container/Templates/Constraints/Import-Export) zu **einem**
> 0.6-Plan zusammengeführt.

Reiter-zentrierte Aktivitätsoberfläche. Diese Planung ersetzt den früheren
„React-SPA-Shell"-Ansatz durch eine **Islands-Architektur** und schneidet die
Umsetzung in wenige, große, je einzeln verifizier- und auslieferbare Schritte.

## Architektur-Entscheidung: Islands statt SPA

Die Reiter sind das tragende Ordnungselement und werden **server-seitig** von
Moodle gerendert (`$OUTPUT->tabtree()`), direkt unter Überschrift und
Aktivitätsmenü. `view.php` wählt anhand von Rolle und Zustand den aktiven Reiter
und rendert dessen Inhalt; **React wird nur als „Insel" dort eingebettet, wo es
wirklich dynamisch ist**.

- **React-Inseln:** Editor (Zeichenfläche + Liste), Journal (Zeit-Buckets +
  Zustandsansicht), Konsens-/Abgabe-Status.
- **Server-gerendert (PHP/Moodle-Output):** Bewertung, Feedback, Werkzeuge —
  überwiegend Formulare und Anzeige, aus `grade.php`/`report.php` ableitbar.

Begründung: deutlich weniger Neubau und Risiko, Moodle-native Barrierefreiheit,
Deep-Links, Back-Button und Rollen-Gating; der gemeinsame Selektor wird trivial.

## Querschnitts-Konzepte (gelten für alle Reiter)

- **Selektor = URL-Parameter.** Ziel (`target=self` | `user:<id>` | `group:<id>`)
  und aktiver Reiter (`tab=…`) stehen in der URL und werden so automatisch über
  alle Reiter mitgeführt, sind teilbar und bookmarkbar. Der Server validiert das
  Ziel und die Sichtbarkeit.
- **Selektor-Sichtbarkeit:** Lehrende immer; Lernende nur bei Gruppenmodus
  „sichtbare Gruppen"; nie bei Kurs-ViMi. Default ist stets das eigene ViMi.
- **Fremde ViMis:** read-only, aber **live** — Laden + Polling/Presence aktiv,
  alle Mutationen und Locks deaktiviert.
- **Rollen-Gating der Reiter:** Bewertung nur Lehrende (bzw. Peer-Bewertung auch
  Lernende); Feedback für Lernende (Einsicht für Lehrende); Werkzeuge v. a.
  Lehrende. Gating server-seitig.
- **Auto-Open nach Zustand/Rolle:** in Bearbeitung → Zeichenfläche; abgegeben und
  in Bewertung → Bewertung (Lehrende) bzw. Journal/Abgabe (Lernende); bewertet →
  Feedback (Lernende). Genaue Matrix in Schritt 1 festgelegt.

## Bestätigte Detailentscheidungen

1. **Konsens als Zustandsautomat.** Zustände einer Gruppen-Fläche:
   `offen → in_abstimmung → abgegeben`, plus Abbruch zurück nach `offen`.
   - „Einreichungsprozess starten" (ein Mitglied) → `in_abstimmung`.
   - Übersicht aller Mitglieder (Profilbild, Profil-/Mail-/Message-Link): wer hat
     bestätigt, wer nicht.
   - Je Mitglied: Checkbox „Ich stimme der Abgabe zu" + Button „Einreichung
     bestätigen"; „Einreichungsprozess abbrechen" (rot, outline).
   - Bei der letzten bestätigenden Person: statt „Einreichung bestätigen" →
     „zur Bewertung abgeben" → `abgegeben`.
   - Ohne Konsens-Erfordernis: direkt nur „zur Bewertung abgeben".
   - Bei Start / Abbruch / Ende: **Systemnachricht** an alle Mitglieder
     (Moodle-Messaging, `message_send` + `db/messages.php`).
   - Baut auf `vimipad_submissionintent` (0.5.5) auf; ergänzt einen expliziten
     Prozess-Status je Workspace.
2. **Journal-Eintrag → Bearbeitungsstand des ViMis.** Jeder Eintrag merkt sich
   die **Revision** bei Erstellung; die Ansicht rekonstruiert den Kartenzustand
   read-only durch Abspielen des Operationslogs bis zu dieser Revision.
   - Optional zusätzlich ein leichter **Snapshot** (JSON oder SVG-Export) je
     Eintrag als schneller, robuster Fallback.
   - **Gold-Nugget (späterer, optionaler Schritt):** animierter
     **Revisions-Replay** — die Entstehung des ViMis im Schnelldurchlauf.
3. **Systemnachrichten** ausschließlich über Moodle-Bordmittel (Messaging-API,
   Benachrichtigungsanbieter in `db/messages.php`).

## Die großen Schritte

### Schritt 1 — Reiter-Gerüst + Selektor + Zeichenfläche/Liste
Das Fundament und die ersten beiden Reiter.
- `view.php`: server-gerendertes `tabtree` mit allen Reitern, rollen-gegated,
  Auto-Open nach Zustand; `tab`/`target` als URL-Parameter.
- Selektor-Datenquelle (auswählbare Ziele + Sichtbarkeitsregeln) server-seitig
  bzw. per External.
- Editor-Insel überarbeitet: Ziel-gesteuertes Laden (eigenes vs. fremdes,
  fremd = read-only + live), **Zeichenfläche** und **Liste** als zwei Reiter
  (Insel liest die Startansicht aus `tab`).
- Zeichenfläche: Selektor oben; Canvas mit **dynamisch reduzierter Höhe**, sodass
  darunter die Einfüge-Leiste (Begriff + Relation, **Abgabebutton entfernt**,
  volle Breite) und die Journal-Eingabe passen.
- Liste: Begriff + Relation nebeneinander (Überschrift darüber), darunter die
  Liste, darunter die Journal-Eingabe.

### Schritt 2 — Reiter „Journal und Abgabe" + Konsens-Zustandsautomat
- Journal-Insel: Einträge chronologisch absteigend, in einklappbaren, größer
  werdenden Zeit-Buckets (diese Woche, letzte Woche, Monat, Jahr); je Eintrag
  Datum, verfassende Person mit Profilbild + Profil-/Mail-/Message-Link; Button
  „Bearbeitungsstand anzeigen" (Revisions-Rekonstruktion, s. o.).
- Lehrenden-Selektor für TN/Gruppen auch hier.
- Abgabe-Block am Seitenende: der vollständige **Konsens-Zustandsautomat** inkl.
  Mitglieder-Übersicht, Bestätigen/Abbrechen, „zur Bewertung abgeben" und den
  Systemnachrichten. Neue Externals (Start/Bestätigen/Abbrechen/Status), Ausbau
  der 0.5.5-Abgabelogik.

### Schritt 3 — Reiter „Bewertung" + „Feedback"
- Bewertung (server-gerendert, aus `grade.php`): nur Lehrende (bzw. Peer), mit
  Einsichts-Selektor am Bereichsanfang; alle bewertungsbezogenen Inhalte/Prozesse.
- Feedback (server-gerendert): für Lernende die Rückmeldungen/Bewertungen;
  Einsicht für Lehrende über den Selektor.

### Schritt 4 — Reiter „Werkzeuge"
- Import und (Massen-)Export gebündelt; Massen-Export mehrerer Gruppen/TN.

### Schritt 5 (optional, Gold-Nugget) — Animierter Revisions-Replay
- Abspielen der ViMi-Entstehung als Animation über das Operationslog.

## Vorbehalte

- Nahezu alles ist **Browser-Verhalten** und hier nicht render-verifizierbar:
  Logik wird über tsc/Jest, Externals über phpcs/PHPUnit-Review abgesichert;
  Optik/Interaktion prüft die Zielinstanz.
- Jeder Schritt ist eine eigene, verifizierte Release; der Umfang erstreckt sich
  über mehrere Runden. Bei Bedarf wird ein großer Schritt (z. B. Schritt 2) in
  Journal-Teil und Konsens-Teil aufgeteilt.
- `grade.php`/`report.php` werden zu Reiter-Inhalten umgebaut, nicht doppelt
  gepflegt; alte Einstiegspunkte bleiben nur so lange nötig.
