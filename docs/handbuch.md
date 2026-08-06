# ViMi Pad — Nutzerhandbuch

Handlungsorientiertes Handbuch zum Prüfen und Nutzen von `mod_vimipad`. Jeder
Abschnitt ist als **Schritt-für-Schritt-Checkliste** angelegt. Legende:
✅ umgesetzt · 🧪 experimentell/teilweise · 🔭 geplant.

## Inhalt
- [1. Einrichtung](#1-einrichtung)
  - [1.1 Plugin installieren (Linux)](#11-plugin-installieren-linux)
  - [1.2 Globale Admin-Einstellungen](#12-globale-admin-einstellungen)
  - [1.3 Kollaboration & „WebSocket"](#13-kollaboration--websocket)
  - [1.4 KI-Subsystem aktivieren](#14-ki-subsystem-aktivieren)
  - [1.5 Aktivität anlegen](#15-aktivität-anlegen)
- [2. Darstellungsformen](#2-darstellungsformen)
- [3. Bewertung](#3-bewertung)
  - [3.1 Bewertung einrichten](#31-bewertung-einrichten)
  - [3.2 Rubrik / Bewertungsleitfaden](#32-rubrik--bewertungsleitfaden)
  - [3.3 Automatische Bewertungsmechanismen](#33-automatische-bewertungsmechanismen)
  - [3.4 Peer-Review](#34-peer-review)
  - [3.5 KI-gestütztes Feedback](#35-ki-gestütztes-feedback)
- [Verwandte Dokumente](#verwandte-dokumente)

---

## 1. Einrichtung

### 1.1 Plugin installieren (Linux)

`mod_vimipad` ist ein reines Moodle-Aktivitätsmodul — **keine Zusatz-Server-
Software**, kein Node/Python/Rust-Dienst, keine Datenbank außer der von Moodle.

```bash
# 1. Plugin nach mod/vimipad legen (Git-Clone oder ZIP entpacken)
cd /pfad/zu/moodle/mod
git clone https://github.com/ralferlebach/moodle-mod_vimipad vimipad
# oder: unzip vimipad-0.8.x.zip -d /pfad/zu/moodle/mod/

# 2. Upgrade fahren (installiert Schema + Subplugins)
php /pfad/zu/moodle/admin/cli/upgrade.php --non-interactive

# 3. Moodle-Cron sicherstellen (Gradebook, Completion, Events)
php /pfad/zu/moodle/admin/cli/cron.php
```
Die Subplugins (`vimipadform_*` Darstellungsformen, `vimipadassess_*` Scorer)
werden beim Upgrade automatisch mitinstalliert.

**Prüfen:** *Website-Administration → Plugins → Plugin-Übersicht* listet
`mod_vimipad` und darunter die `vimipadform`- und `vimipadassess`-Subplugins.

### 1.2 Globale Admin-Einstellungen

*Website-Administration → Plugins → Aktivitätsmodule → ViMi Pad*:

| Einstellung | Wirkung |
|-------------|---------|
| `arrangeiterations` ✅ | Iterationsobergrenze des „Anordnen"-Solvers pro Klick (Default 500). |
| `arrangeshrink` ✅ | Ob „Anordnen" zu große Container auf ihre Mitglieder schrumpfen darf (Default an). |
| `pollinterval`, `polladaptive`, `pollmin`, `pollmax`, `leasetimeout` ✅ | Kollaborations-Polling (siehe 1.3). |
| `enableai` ✅ | Globaler KI-Schalter (siehe 1.4). |

### 1.3 Kollaboration & Push/WebSocket

**Basis: Polling, kein Zusatzserver nötig.** Die Zusammenarbeit läuft über
serverautoritative Operationen + adaptives Polling über den normalen
Moodle-Web-Service. Für den Regelbetrieb ist **nichts zusätzlich einzurichten**
— nur Moodle.

**So funktioniert die Zusammenarbeit:**
- Jeder Client fragt periodisch neue Operationen ab (`get_operations`) und wendet
  sie an; Positionen werden weich getweent, Presence/Locks über kurze Leases.
- Das Intervall passt sich an: aktiv → `pollmin`, ruhig → bis `pollmax`
  (wenn `polladaptive` an).

**Optionaler Echtzeit-Push über Mercure (SSE) ✅.** Für niedrige Latenz kann ein
**Mercure-Hub** angebunden werden: bei aktiviertem Push publiziert der Server nach
jeder Operation ein „neue Revision"-Ereignis an ein pro-Workspace-Thema; der
Editor abonniert es per `EventSource` und **weckt sofort ein Poll**, statt aufs
Intervall zu warten. Es ist rein additiv: fällt der Hub aus oder ist Push aus,
pollt der Editor normal weiter (Rückfallebene). Nur Server→Client — der Client
publiziert nie — daher **SSE, kein WebSocket**. Das Ereignis trägt nur die
Revisionsnummer; die eigentlichen Operationen holt der Client weiterhin über den
serverautoritativen `get_operations`-Pfad (dem Hub wird nicht vertraut).

**Warum Mercure:** ein einzelnes, eigenständiges Go-Binary (Caddy-basiert, mit
eigenem TLS) — **keine Abhängigkeit von nginx oder Apache**, keine mitgelieferte
Server-Software. Das Plugin publiziert per HTTP-POST, der Browser abonniert per
`EventSource`.

**Mercure unter Linux einrichten:**
```bash
# 1. Mercure-Binary holen (Beispiel; aktuelle Release-URL prüfen)
curl -sSL -o mercure.tar.gz \
  https://github.com/dunglas/mercure/releases/latest/download/mercure_Linux_x86_64.tar.gz
tar xzf mercure.tar.gz && sudo mv mercure /usr/local/bin/

# 2. Als systemd-Dienst betreiben (eigenes TLS via Caddy, eigener Port/Domain)
sudo tee /etc/systemd/system/mercure.service >/dev/null <<'EOF'
[Service]
Environment=MERCURE_PUBLISHER_JWT_KEY=DEIN_GEHEIMNIS
Environment=MERCURE_SUBSCRIBER_JWT_KEY=DEIN_GEHEIMNIS
Environment=SERVER_NAME=hub.example.com
Environment=MERCURE_EXTRA_DIRECTIVES=cors_origins https://moodle.example.com
ExecStart=/usr/local/bin/mercure run
Restart=always
[Install]
WantedBy=multi-user.target
EOF
sudo systemctl enable --now mercure
```
- **`MERCURE_PUBLISHER_JWT_KEY` = `MERCURE_SUBSCRIBER_JWT_KEY` = dasselbe Geheimnis**,
  das du unten als Plugin-`pushjwtkey` einträgst (HS256).
- **`cors_origins`** auf die Moodle-Herkunft setzen (für das `EventSource`-Abo mit
  Credentials).
- Der Subscriber-Token wird als Cookie `mercureAuthorization` gesendet: Der Hub
  sollte daher **unter derselben Site** erreichbar sein — entweder als
  Subdomain (`hub.example.com` bei Moodle `moodle.example.com`) oder unter der
  Moodle-Herkunft reverse-proxied. Die Wahl des Reverse-Proxys (falls genutzt)
  ist deine Infrastruktur; das Plugin ist davon unabhängig.

**Im Plugin konfigurieren** (*Website-Administration → … → ViMi Pad*):

| Einstellung | Wert |
|-------------|------|
| `pushenabled` | an |
| `pushendpoint` | öffentliche Hub-URL zum **Abonnieren**, z. B. `https://hub.example.com/.well-known/mercure` |
| `pushpublishurl` | optional: interne **Publish**-URL (z. B. `http://127.0.0.1:PORT/.well-known/mercure`); leer → nutzt `pushendpoint` |
| `pushjwtkey` | dasselbe Geheimnis wie die JWT-Keys des Hubs |

**Prüfen:** Zwei Nutzer in einer geteilten Map; eine Änderung des einen erscheint
beim anderen **nahezu sofort** (statt erst nach dem Poll-Intervall). Bei
gestopptem Mercure-Dienst funktioniert die Zusammenarbeit unverändert weiter,
nur wieder mit Poll-Latenz.

### 1.4 KI-Subsystem aktivieren

ViMi Pad ruft KI **ausschließlich über Moodles AI-Subsystem** auf (kein direkter
Provider-Zugriff im Plugin).

1. *Website-Administration → KI (AI) → Anbieter*: einen Provider einrichten
   (z. B. den von Moodle mitgelieferten) und die Aktion **generate text**
   erlauben.
2. Im Plugin `enableai` global anschalten (1.2).
3. Pro Aktivität `aienabled` setzen (1.5).

**Prüfen:** In einer Bewertung erscheint der Button für den KI-Feedback-Entwurf
(3.5). Ist eine der drei Ebenen aus (Provider / `enableai` / `aienabled`), bleibt
er verborgen.

### 1.5 Aktivität anlegen

*Kurs → Material/Aktivität anlegen → ViMi Pad*. Relevante Felder:

| Feld | Bedeutung |
|------|-----------|
| **Name** | Titel der Aktivität. |
| **Standard-Profil** (`defaultprofile`) ✅ | Darstellungsform (siehe [2](#2-darstellungsformen)). |
| **Bearbeitungsmodus** (`collaborationmode`) ✅ | Einzeln / Gruppe / ganzer Kurs. |
| **KI aktiv** (`aienabled`) ✅ | KI-Feedback-Entwurf in der Bewertung erlauben. |
| **Peer-Review** (`peerreviewmode`) ✅ | Peer-Review-Ebene einschalten (siehe [3.4](#34-peer-review)). |
| **Mindestknoten / -relationen** (`minnodes`, `minrelations`) ✅ | Umfangsvorgaben; Grundlage für Abschlussregeln. |
| **Verfügbarkeit** ✅ | Standard-Moodle: Fristen/Sichtbarkeit. |

Bewertungsmethode (Punkte/Rubrik/Leitfaden) wird wie bei anderen Aktivitäten im
Abschnitt **Bewertung** des Formulars bzw. über *Erweiterte Bewertung* gesetzt
(siehe [3.2](#32-rubrik--bewertungsleitfaden)).

---

## 2. Darstellungsformen

Jede Form ist ein `vimipadform`-Subplugin und deklariert Verbinderstil,
Bifurkation und ihr Layout-Verhalten (Richtung/Ordnung) selbst.

**Umgesetzt ✅**

| Profil | Charakter | Layout-Verhalten (Anordnen) |
|--------|-----------|-----------------------------|
| **Concept Map** | Begriffe + benannte Relationen | Geschwisterordnung horizontal, keine erzwungene Richtung |
| **Mindmap** | zentraler Knoten, radiale Äste | zyklischer Ordnungserhalt um Hubs |
| **Strukturbaum** | Hierarchie | gerichtet abwärts, Geschwister links→rechts |
| **Semantisches Netz** | frei vernetzt | frei (nur Kante + Abstoßung) |
| **Bubble Map** | zentraler Begriff, Attribut-Bläschen | zyklischer Ordnungserhalt |

**Geplant 🔭** (jeweils als eigenes `vimipadform`-Subplugin; Layout-Zusatzterme
in Klammern): Argument Map (typisierte Kanten Stütz/Angriff), Flow/Process
(starke Richtung, Rang-Layering), Fishbone/Ishikawa (zweigweise Richtung,
Rückgrat), Causal/System Map (gerichtet, Zyklen), Timeline (1D-Linien-
Confinement), Venn/Mengen (Mengen-Container), Affinity/Cluster (Cluster-
Anziehung). Details und Formeln: [`design/potential-arrange-1.1.md`](design/potential-arrange-1.1.md).

**So wählen/prüfen Sie eine Form:**
1. Beim Anlegen unter **Standard-Profil** wählen (1.5).
2. Im Editor Knoten/Relationen anlegen; **Anordnen** ordnet gemäß dem Profil.
3. In der **Container-Formleiste** lassen sich Container-Formen (Rechteck/
   abgerundet/Ellipse) einstellen.

---

## 3. Bewertung

Grundprinzip: **snapshot-basiert**. Bewertet wird ein eingefrorener Stand (Nodes,
Relationen, Container, Layout, Autor, Metadaten), damit spätere Bearbeitung den
Bewertungsgegenstand nicht verändert. KI bewertet **nicht** endgültig — sie
unterstützt die Formulierung.

### 3.1 Bewertung einrichten

1. **Bewertungsmethode** im Aktivitätsformular wählen: Punkte, Rubrik oder
   Bewertungsleitfaden (siehe [3.2](#32-rubrik--bewertungsleitfaden)).
2. Optional **Mindestumfang** (`minnodes`/`minrelations`) und **Abschlussregeln**
   (z. B. „bewertet") setzen.
3. Optional **KI** ([1.4](#14-ki-subsystem-aktivieren)) und/oder **Peer-Review**
   ([3.4](#34-peer-review)) einschalten.
4. Lernende bearbeiten und **reichen ein** → es entsteht ein Snapshot.

**Bewerten (Lehrende):** Registerkarte **Grading** der Aktivität → Einreichung
öffnen → Note + Feedback vergeben → speichern. Note fließt ins **Gradebook**;
mit den Abschlussregeln verknüpfbar. Lehrende können eine Einreichung zur
Weiterbearbeitung wieder **freigeben**.

### 3.2 Rubrik / Bewertungsleitfaden

ViMi Pad baut **keine eigenen Rubriken** — es nutzt Moodles Kern
`gradingform` (Rubric/Marking Guide):

1. Aktivität → *Bewertung* → **Bewertungsmethode = Rubrik** (oder
   Bewertungsleitfaden).
2. *Erweiterte Bewertung* → Rubrik-Kriterien und Punktstufen definieren (oder
   Vorlage laden).
3. Beim Bewerten (3.1) erscheint die Rubrik im Grading-Panel; die Gesamtpunktzahl
   geht ins Gradebook.

### 3.3 Automatische Bewertungsmechanismen

Automatische Verfahren sind `vimipadassess`-Subplugins („Scorer"). Sie liefern
**Hilfsanzeigen/Vorschläge**, keine endgültige KI-Benotung. Jeder Scorer
deklariert, welche Profile er unterstützt.

| Scorer (`vimipadassess_*`) | Verfahren | Braucht Musterlösung |
|----------------------------|-----------|----------------------|
| **reference** ✅ | F1 über Begriffe + Propositionen gegen eine Musterlösung | ja |
| **structure** ✅ | Strukturmetriken (Knoten/Tiefe/Cross-Links/isolierte Knoten) | nein |
| **tree** ✅ | Tree-Edit-Distanz (nur Baum-Profile) | ja |
| **text** ✅ | textbasierter Abgleich | ja |
| **sms** ✅ | SMS-nahe Struktur-Metrik | teils |
| **llm** 🧪 | KI-Scoring über Moodles AI-Subsystem (on demand) | optional |

**So nutzen Sie sie:**
1. Eine Einreichung als **Musterlösung markieren** (Referenz-Snapshot) — für die
   referenzbasierten Scorer.
2. Im Grading-Panel einen Scorer anstoßen (einzeln oder „alle bewerten"); das
   Ergebnis erscheint als Kennzahl neben der Einreichung.
3. Die Zahlen sind **Entscheidungshilfe** für die manuelle Note/Feedback, nicht
   die Note selbst.

Fachlicher Hintergrund und weitere geplante Verfahren:
[`design/assessment_architecture.md`](design/assessment_architecture.md).

### 3.4 Peer-Review

Ebenen-Modell auf Snapshots (kein eigenes Modul):

1. In der Aktivität **`peerreviewmode`** einschalten (1.5).
2. Nach der Einreichungsphase **Reviewer zuteilen** (Snapshots werden Gutachtenden
   zugewiesen; Status *allocated*).
3. Gutachtende vergeben je Snapshot **Score + Kommentar** (Status *submitted*).
4. Lehrende sehen das **Aggregat** (Anzahl, Mittelwert, Median, offene Reviews)
   im Peer-Review-Panel und beziehen es in die Note ein.

> 🔭 Der Vollausbau (5-Phasen-Modell Einrichtung→Bearbeitung→Begutachtung→
> Bewertung→geschlossen, Kalibrierung, Gewichtung) ist als Premium-Subplugin
> `vimipadreview_peerplus` nach 1.0 vorgesehen.

### 3.5 KI-gestütztes Feedback

Der KI-Workflow **unterstützt** das Schreiben elaborierter Rückmeldungen; die KI
sendet **nie** ungeprüft an Lernende.

1. Voraussetzung: Provider + `enableai` + `aienabled` aktiv ([1.4](#14-ki-subsystem-aktivieren)).
2. Lehrende öffnen einen Snapshot, vergeben ggf. Rubrik-Punkte/Notizen.
3. Das System extrahiert eine **datensparsame** Textrepräsentation der Map und
   erzeugt über Moodles AI-Subsystem einen **Feedback-Entwurf**.
4. Lehrende **prüfen, editieren, übernehmen** — erst die freigegebene Version wird
   als Feedback gespeichert.

**Prüfen:** Ohne konfigurierten Provider bzw. bei ausgeschaltetem `enableai`/
`aienabled` ist der Entwurfs-Button nicht verfügbar; mit allen drei aktiv liefert
er einen editierbaren Textvorschlag.

---

## Verwandte Dokumente
- Roadmap & Versionsplanung: [`design/roadmap.md`](design/roadmap.md)
- Bewertungs-Architektur (Scorer, Verfahren): [`design/assessment_architecture.md`](design/assessment_architecture.md)
- Öffentliche API / abgeleitete Plugins: [`design/public-api.md`](design/public-api.md)
- Anordnen-Umbau 1.1 (rein potenzialbasiert): [`design/potential-arrange-1.1.md`](design/potential-arrange-1.1.md)
- Nicht-CI-Tests (JMeter/k6/Playwright): [`testing/non-ci-tests.md`](testing/non-ci-tests.md)
- Testumgebung aufsetzen: [`dev/moodle-test-environment-setup.md`](dev/moodle-test-environment-setup.md)
- Barrierefreiheit: [`design/barrierearmut.md`](design/barrierearmut.md)
