# Nicht-CI-Tests — Schritt-für-Schritt (Linux)

Diese Tests laufen **nicht** in der `moodle-plugin-ci`-Pipeline, weil sie eine
laufende Moodle-Site (mit echtem Browser bzw. echtem Web-Service) brauchen. Sie
werden manuell oder geplant ausgeführt:

| Test | Zweck | Ort | Trigger |
|------|-------|-----|---------|
| **JMeter** | Last-/Antwortzeiten der Read-Endpoints, N+1-Regressionen | `tests/load/vimipad-read-endpoints.jmx` | manuell |
| **k6** | dasselbe, skript-/CLI-basiert, mit p95-Schwellen | `tests/load/vimipad-read-endpoints.k6.js` | manuell |
| **Playwright** | Echt-Browser-Kollaboration (mehrere Clients, Presence) | `tests/playwright/` | `.github/workflows/playwright.yml` (workflow_dispatch + wöchentlich) |

> Voraussetzung für **alle**: eine erreichbare Moodle-Site mit installiertem und
> upgegradetem `mod_vimipad`. Für lokale Läufe genügt ein Dev-Moodle unter
> `http://localhost:8000`.

---

## 0. Gemeinsame Vorbereitung

### 0.1 Site & Plugin
```bash
# Plugin ins Moodle spiegeln und Upgrade fahren
rsync -a --exclude=node_modules --exclude=.git ./ /pfad/zu/moodle/mod/vimipad/
php /pfad/zu/moodle/admin/cli/upgrade.php --non-interactive
```

### 0.2 Große Map seeden (für JMeter/k6)
Der Plugin-Generator hat ein `large`-Profil (1000 Knoten / 2000 Relationen /
200 Container). In einem Wegwerf-CLI oder PHPUnit-Bootstrap:
```php
$gen = $generator->get_plugin_generator('mod_vimipad');
$ws  = $gen->create_map_profile($instance, $userid, 'large');
$gen->create_collaboration_history($ws, 20000); // langes Op-Log für get_operations
```
Notiere die **Workspace-ID** und die **Course-Module-ID (cmid)**.

### 0.3 Web-Service & Token (für JMeter/k6)
1. *Website-Administration → Server → Web-Services → Übersicht*: REST-Protokoll
   aktivieren.
2. Einen externen Dienst anlegen und die vier Read-Funktionen hinzufügen:
   `mod_vimipad_get_workspace`, `mod_vimipad_get_operations`,
   `mod_vimipad_get_layout_history`, `mod_vimipad_get_revision_state`.
3. Für eine eingeschriebene Nutzer:in mit `mod/vimipad:view` ein **Token**
   erzeugen und kopieren.

Endpoint-Form (REST):
`POST {BASE}/webservice/rest/server.php` mit
`wstoken`, `wsfunction=mod_vimipad_<fn>`, `moodlewsrestformat=json` + Funktions-
parametern. Parameter je Funktion: `get_workspace(cmid)`,
`get_operations(cmid, workspaceid, torevision)`,
`get_layout_history(cmid, workspaceid)`,
`get_revision_state(cmid, workspaceid, revision)`.

---

## 1. JMeter

### 1.1 Installation
```bash
sudo apt-get update && sudo apt-get install -y default-jre
# Apache JMeter (Binaries) laden und entpacken:
curl -sSLO https://downloads.apache.org/jmeter/binaries/apache-jmeter-5.6.3.tgz
tar xzf apache-jmeter-5.6.3.tgz
export PATH="$PWD/apache-jmeter-5.6.3/bin:$PATH"
jmeter --version
```

### 1.2 Lauf (headless)
```bash
cd tests/load
jmeter -n -t vimipad-read-endpoints.jmx \
  -Jbase_url=http://localhost:8000 \
  -Jtoken=YOUR_TOKEN \
  -Jworkspaceid=WORKSPACE_ID \
  -Jcmid=CMID \
  -Jrevision=2000 \
  -Jthreads=25 -Jrampup=10 -Jloops=20 \
  -Jmaxduration=2000 \
  -l vimipad-load-results.jtl
```
Alle Parameter haben Defaults (User Defined Variables im Plan). Ein Sample **failt**,
wenn die Antwort `"exception"` enthält oder `maxduration` (Default 2000 ms)
überschreitet.

### 1.3 Auswertung
```bash
# HTML-Report aus dem .jtl erzeugen
jmeter -g vimipad-load-results.jtl -o report/
xdg-open report/index.html
```
Beobachte die **95%-Latenz** von `get_operations`/`get_revision_state`, während
das Op-Log wächst: ein mit der History-Größe skalierender Sprung deutet auf ein
N+1 oder einen fehlenden Index hin, nicht auf reine Datenmenge.

---

## 2. k6

### 2.1 Installation
```bash
# Debian/Ubuntu (offizielles Paket)
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install -y k6
k6 version
```

### 2.2 Lauf
```bash
cd tests/load
k6 run \
  -e BASE_URL=http://localhost:8000 \
  -e TOKEN=YOUR_TOKEN \
  -e WORKSPACEID=WORKSPACE_ID \
  -e CMID=CMID \
  -e REVISION=2000 \
  -e VUS=25 -e DURATION=60s -e MAXMS=2000 \
  vimipad-read-endpoints.k6.js
```

### 2.3 Auswertung
k6 druckt am Ende die Schwellen-Ergebnisse. Relevant:
- `http_req_duration p(95) < MAXMS` (Gesamt-Budget) — muss grün sein.
- Die Custom-Trends `vimipad_get_operations`, `vimipad_get_revision_state` etc.
  zeigen die Latenz **pro Endpoint**; so lässt sich eine Regression auf eine
  Funktion festnageln.
- `checks rate>0.99` — <1 % Fehlversuche (Status ≠ 200, `"exception"` im Body,
  oder über Budget).

Für Zeitreihen/Dashboards optional `k6 run --out json=out.json ...` oder
`--out influxdb=...`.

---

## 3. Playwright (Kollaboration)

Deckt ab, was Behat nicht kann: **mehrere Clients editieren gleichzeitig dieselbe
Map**. Zwei Nutzer öffnen eine Kurs-Modus-ViMi-Pad; eine Änderung des einen muss
den anderen über den Polling-Sync erreichen.

### 3.1 Installation
```bash
cd tests/playwright
npm install
npm run install-browsers   # lädt die Playwright-Browser
```

### 3.2 Fixture seeden
`seed.php` ist ein Moodle-CLI-Skript: es legt Kurs, eine Kurs-Modus-Aktivität und
drei Nutzer mit bekannten Passwörtern an und druckt `export VIMIPAD_*`-Zeilen,
die die Specs erwarten.
```bash
# aus dem Moodle-Root oder mit dem gezeigten relativen Pfad
eval "$(php seed.php)"
```

### 3.3 Lauf
```bash
export VIMIPAD_BASE_URL="http://localhost:8000"
npm test
# gezielt / mit sichtbarem Browser:
npx playwright test collaboration.spec.ts --headed
npx playwright show-report
```

### 3.4 Was geprüft wird
- Ein von Nutzer A hinzugefügter Begriff erscheint bei Nutzer B.
- Beide sehen einander in der Presence-Liste.
- Gleichzeitige Edits beider konvergieren für alle.

### 3.5 Als GitHub-Action
`.github/workflows/playwright.yml` läuft per `workflow_dispatch` (manuell) und
wöchentlich (`cron`). Startet einen Postgres-Service, installiert Moodle +
Plugin, seedet und führt die Specs aus — bewusst getrennt von der schnellen
PR-CI. Auf Push umstellbar, indem man den `push`-Trigger ergänzt.

---

## 4. Troubleshooting

- **Alle Read-Aufrufe 200, aber `"exception"` im Body** → Token/Capability prüfen
  (`mod/vimipad:view`, Nutzer im Kurs eingeschrieben, Funktion im externen Dienst
  gelistet).
- **p95 explodiert mit History-Größe** → `get_operations`/`get_revision_state`
  auf N+1 / fehlenden Index untersuchen (nicht Datenmenge).
- **Playwright: „Änderung erscheint nicht"** → Polling-Intervall in den
  Plugin-Settings (`pollinterval`/`pollmax`) und Moodle-Cron prüfen; die
  Kollaboration ist Polling-basiert (kein WebSocket, siehe Handbuch).
- **`php -S` als Site** → instabil; für Playwright/Last besser echtes Apache/nginx
  + PHP-FPM verwenden.
