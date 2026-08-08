# Moodle-Verifikationsumgebung in der Sandbox aufsetzen

Diese Anleitung richtet sich an eine **KI-Instanz (Claude)**, die in der
Code-Execution-Sandbox arbeitet. Sie beschreibt Schritt für Schritt, wie eine
echte, lauffähige Moodle-4.5-Umgebung aufgebaut wird, um `mod_vimipad`
**real** zu verifizieren (PHPUnit, phpcs/moodle-cs, moodle-plugin-ci, Grunt/AMD,
Behat) statt nur statisch zu prüfen.

> **Warum das wichtig ist:** Ohne diese Umgebung lässt sich nur „statisch"
> prüfen (Syntax, Struktur). Erst ein laufendes Moodle beweist, dass Schema,
> Backup/Restore, Gradebook, Privacy-Löschpfade, AMD-Ladepfad usw. tatsächlich
> funktionieren. Mehrere echte Fehler (PHPUnit-Fails, AMD-Grunt-Diff,
> Editor-Loading) fielen erst hier auf.

Alle Pfade und Werte entsprechen dem tatsächlich aufgesetzten Stand.
Getestet mit: Moodle `MOODLE_405_STABLE`, PHP 8.3.6, PostgreSQL 16,
Node 22 / npm 10.

---

## 0. Wiederkehrende Fallstricke (ZUERST LESEN)

- **PostgreSQL schläft in der Sandbox ständig ein** („Connection refused",
  „Error reading from database"). Vor JEDEM DB-Kommando neu starten:
  `service postgresql start; sleep 2-3`. Die **Site-DB** (Haupt-Installation)
  verliert bei PG-Neustarts teils ihre Tabellen — deshalb ist die **PHPUnit-Env
  die zuverlässige Verifikationsbasis** (sie installiert das Plugin-Schema
  reproduzierbar). Ein voller Site-Install lief einmal durch, ist aber fragil.
- **Der PHP-Built-in-Server (`php -S`) ist instabil** und stirbt beim ersten
  cache-aufbauenden Request. Deshalb lassen sich **@javascript-Behat-Szenarien
  hier nicht live ausführen** — die laufen in der Projekt-CI mit Browser.
  Non-JS-Behat lässt sich strukturell/step-validieren (dry-run).
  **Nachtrag:** Für die **Playwright**-Kollaborationstests gilt das NICHT — mit
  `PHP_CLI_SERVER_WORKERS` und dem detachten Runner laufen sie real; siehe
  Abschnitt 15.
- **Nach jedem Plugin-Sync + neuen Dateien** muss die PHPUnit-Env
  re-initialisiert werden (neue Testdateien invalidieren die Config):
  `php admin/tool/phpunit/cli/init.php`.
- **Nach `str_replace`/Grunt/phpcbf** können sich Zeilen verschieben —
  vor erneutem Edit `view`/`grep`.

---

## 1. Systempakete installieren

PHP-Extensions (Moodle-Minimum) und PostgreSQL:

```bash
apt-get update
apt-get install -y php-pgsql php-mbstring php-curl php-xmlrpc php-soap \
                   php-intl php-zip php-gd
apt-get install -y postgresql postgresql-contrib
apt-get install -y locales
```

Prüfen:

```bash
php -m | grep -iE "pgsql|mbstring|curl|intl|xml|zip|gd|soap"
```

---

## 2. PHP für Moodle konfigurieren

Moodle verlangt `max_input_vars >= 5000`:

```bash
PHPINI=$(php -i | grep "Loaded Configuration File" | awk '{print $NF}')
echo "max_input_vars = 5000" >> "$PHPINI"
```

(Bei uns: `/etc/php/8.3/cli/php.ini`.)

Locale, die Moodles PHPUnit-Bootstrap erwartet:

```bash
locale-gen en_AU.UTF-8
update-locale
```

---

## 3. PostgreSQL starten und Moodle-DB anlegen

```bash
service postgresql start
sleep 3
su postgres -c "psql -c \"CREATE USER moodle WITH PASSWORD 'moodle';\""
su postgres -c "psql -c \"CREATE DATABASE moodle OWNER moodle;\""
su postgres -c "psql -c \"ALTER USER moodle CREATEDB;\""   # nötig für Test-DBs
```

---

## 4. Moodle-Quellcode holen

Shallow-Clone des Ziel-Zweigs (schnell, reicht für alle Checks):

```bash
git clone --depth 1 --branch MOODLE_405_STABLE \
  https://github.com/moodle/moodle.git /home/claude/moodle
```

> Für einen Gegencheck des aktuellen Entwicklungsstands (z. B. React-in-Core-
> Fragen) statt `MOODLE_405_STABLE` den `main`-Branch verwenden.

Plugin an die richtige Stelle spiegeln (nach JEDER Code-Änderung wiederholen):

```bash
rm -rf /home/claude/moodle/mod/vimipad
cp -a /home/claude/vimipad /home/claude/moodle/mod/vimipad
```

---

## 5. config.php schreiben (mit PHPUnit- und Behat-Blöcken)

```bash
cat > /home/claude/moodle/config.php << 'EOF'
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'moodle';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = ['dbpersist' => 0, 'dbsocket' => 0, 'dbport' => ''];
$CFG->wwwroot   = 'http://localhost';
$CFG->dataroot  = '/home/claude/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 0777;
require_once(__DIR__ . '/lib/setup.php');

// PHPUnit.
define('PHPUNIT_UTIL', false);
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/home/claude/moodledata_phpu';

// Behat.
$CFG->behat_dataroot = '/home/claude/behatdata';
$CFG->behat_prefix = 'bht_';
$CFG->behat_wwwroot = 'http://localhost:8000';
EOF

mkdir -p /home/claude/moodledata /home/claude/moodledata_phpu /home/claude/behatdata
```

---

## 6. PHPUnit-Umgebung initialisieren (die zuverlässige Basis)

```bash
cd /home/claude/moodle
php admin/tool/phpunit/cli/init.php
```

Häufige Stolpersteine beim ersten Lauf:
- „Required locale 'en_AU.UTF-8' is not installed" → Schritt 2 (locale-gen).
- „max_input_vars must be at least 5000" → Schritt 2 (php.ini).

Testkonfiguration bauen und Plugin-Tests laufen lassen:

```bash
php admin/tool/phpunit/cli/util.php --buildconfig
vendor/bin/phpunit --filter mod_vimipad
```

Erwartung: grün. Aktuelle Baseline (0.7.25): 264 `mod_vimipad` + 97
`vimipadassess` Backend-Tests plus 277 Jest-Tests (real auf Moodle 4.5.12 und
5.0.8 verifiziert). Die frühere Angabe "47 Tests / 809 Assertions" war der
MVP-Stand.
**Wichtig:** Nach jedem Plugin-Sync mit NEUEN Dateien vorher
`php admin/tool/phpunit/cli/init.php` erneut ausführen.

---

## 7. moodle-cs (aktueller Coding-Standard) installieren

Die in der CI verwendete, aktuelle moodle-cs separat via Composer:

```bash
mkdir -p /tmp/moodlecs && cd /tmp/moodlecs
composer require --dev "moodlehq/moodle-cs" --no-interaction
# Falls Composer nach der Plugin-Erlaubnis fragt:
composer config --no-plugins \
  allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer install --no-interaction
vendor/bin/phpcs -i   # muss "moodle" und "moodle-extra" listen
```

Prüflauf (severity=1 = CI-Härte; JS/Docs/node_modules ausschließen):

```bash
/tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1 --extensions=php \
  --ignore=tools/,*/node_modules/*,*/js/*,*/docs/* /home/claude/vimipad
```

Auto-Fix vieler Verstöße: `phpcbf` mit denselben Argumenten.

> **Achtung Lang-Ordering:** Der `LangFilesOrdering`-Sniff triggert je nach
> moodle-cs-Version unterschiedlich. Verlässlich ist, die Lang-Keys selbst
> streng nach `SORT_STRING` zu sortieren (byteweise). Prüfskript:
> ```bash
> php -r 'foreach (["lang/en/vimipad.php","lang/de/vimipad.php"] as $f){
>   $k=[]; foreach(file($f) as $l) if(preg_match("/^\\\$string\\[.(.+?).\\]/",$l,$m)) $k[]=$m[1];
>   $s=$k; sort($s,SORT_STRING); echo "$f: ".($k===$s?"OK":"NICHT sortiert")."\n";}'
> ```

---

## 8. moodle-plugin-ci (PHAR) für die übrigen Checks

```bash
curl -sSL \
  https://github.com/moodlehq/moodle-plugin-ci/releases/latest/download/moodle-plugin-ci.phar \
  -o /tmp/mpc.phar
php /tmp/mpc.phar --version   # z. B. "Moodle Plugin CI 4.5.10"
```

Statische Checks (brauchen die geladene Moodle-Umgebung → PG muss laufen):

```bash
cd /home/claude
php /tmp/mpc.phar phplint    vimipad
php /tmp/mpc.phar phpmd      vimipad
php /tmp/mpc.phar phpcpd     vimipad
php /tmp/mpc.phar phpdoc     --moodle /home/claude/moodle /home/claude/moodle/mod/vimipad
php /tmp/mpc.phar validate   --moodle /home/claude/moodle /home/claude/moodle/mod/vimipad
cd /home/claude/moodle && php /tmp/mpc.phar savepoints mod/vimipad
cd /home/claude/moodle && php /tmp/mpc.phar mustache   --moodle /home/claude/moodle mod/vimipad
```

Hinweise:
- `phpmd` ist informativ (nicht build-gating). Ein Rest wie „define_structure()
  146 lines" in der Backup-Stepslib ist ein akzeptierter Fehlalarm.
- phpdoc/validate geben viele „found …"-Zeilen aus — nur Zeilen mit
  `error/invalid/warning` sind echte Probleme.

---

## 9. Grunt/AMD-Build mit Moodles eigener Toolchain

Damit `amd/build/*.min.js` **exakt** dem entspricht, was die CI aus `amd/src`
baut, wird Moodles echtes Grunt benutzt.

```bash
cd /home/claude/moodle
npm ci        # installiert Moodles JS-Toolchain (einmalig; ggf. 'npm install')
```

ESLint auf die AMD-Quellen und Build:

```bash
npx grunt eslint --root=mod/vimipad
npx grunt amd    --root=mod/vimipad
```

Ergebnis nach `mod/vimipad/amd/build/` kopieren und ins Repo zurückspiegeln:

```bash
cp -a /home/claude/moodle/mod/vimipad/amd/build/. /home/claude/vimipad/amd/build/
```

**Reproduzierbarkeit prüfen (CI-Kriterium):** ein zweiter Grunt-Lauf muss ein
identisches `init.min.js` erzeugen:

```bash
cp /home/claude/vimipad/amd/build/init.min.js /tmp/b1.js
cd /home/claude/moodle && npx grunt amd --root=mod/vimipad
diff /tmp/b1.js /home/claude/moodle/mod/vimipad/amd/build/init.min.js \
  && echo "AMD reproduzierbar"
```

---

## 10. Behat (Setup + validierbarer Umfang)

```bash
cd /home/claude/moodle
service postgresql start; sleep 3
php admin/tool/behat/cli/init.php
```

Nach jedem Plugin-Sync die Behat-Config neu bauen, damit neue Features/Steps
erkannt werden:

```bash
php admin/tool/behat/cli/util.php --disable
php admin/tool/behat/cli/util.php --enable
```

Dry-run (validiert Gherkin + Step-Auflösung OHNE Browser/HTTP):

```bash
vendor/bin/behat --config /home/claude/behatdata/behatrun/behat/behat.yml \
  --dry-run --tags '@mod_vimipad' -f pretty > /tmp/dry.log 2>&1
grep -iE "undefined|snippet" /tmp/dry.log || echo "keine undefinierten Steps"
```

> **Live-Ausführung** von `@javascript`-Szenarien ist hier NICHT möglich
> (kein stabiler Browser/HTTP-Server). Der Seed-Pfad des Behat-Generators wird
> stattdessen per PHPUnit (`generator_test`) real abgesichert.
> (Die separaten **Playwright**-Kollaborationstests laufen dagegen real — siehe
> Abschnitt 15.)

---

## 11. Frontend (React/TS) ohne Moodle

Direkt im Plugin-Repo (`/home/claude/vimipad`), unabhängig von Moodle:

```bash
cd /home/claude/vimipad
npm install --no-audit --no-fund     # einmalig
./node_modules/.bin/tsc --noEmit     # Typecheck (NICHT 'npx tsc' -> Fremdpaket!)
node build.mjs                       # esbuild -> js/build/vimipad-editor.js
./node_modules/.bin/jest             # Unit-Tests
```

> **Wichtig:** NIE `npx tsc` verwenden — ohne lokale Installation zieht npx das
> falsche Fremdpaket `tsc@2.0.4`. Immer das lokale Binary
> `./node_modules/.bin/tsc`. Das `makefile` handhabt das bereits korrekt.

Mount-Pfad end-to-end via jsdom testen (Muster; simuliert Moodle-Seite):
siehe `docs/sessions/` (jsdom-Skript, das den Bundle ausführt, `M.cfg` stubt,
fetch faked und prüft, dass der Editor montiert und Strings auflöst).

---

## 12. Schnell-Referenz: kompletter Verifikationslauf

```bash
# 0) PG sicherstellen
service postgresql start; sleep 3

# 1) Plugin spiegeln
rm -rf /home/claude/moodle/mod/vimipad
cp -a /home/claude/vimipad /home/claude/moodle/mod/vimipad

# 2) PHP-Standard
/tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1 --extensions=php \
  --ignore=tools/,*/node_modules/*,*/js/*,*/docs/* /home/claude/moodle/mod/vimipad

# 3) PHPUnit (bei neuen Dateien vorher init.php!)
cd /home/claude/moodle
php admin/tool/phpunit/cli/init.php >/dev/null 2>&1
vendor/bin/phpunit --filter mod_vimipad

# 4) AMD reproduzierbar
npx grunt amd --root=mod/vimipad
diff /home/claude/vimipad/amd/build/init.min.js \
     /home/claude/moodle/mod/vimipad/amd/build/init.min.js && echo "AMD ok"

# 5) Frontend
cd /home/claude/vimipad
./node_modules/.bin/tsc --noEmit && node build.mjs && ./node_modules/.bin/jest
```

Zielzustand: PHPUnit grün, phpcs 0/0, AMD identisch, tsc/Jest grün,
phpdoc/validate/savepoints/mustache ohne Fehlerzeilen.

---

## 13. CI-Fallstrick: Build-Artefakte müssen eingecheckt sein

Die Projekt-CI (moodle-plugin-ci) validiert das Plugin **aus dem Git-Repository**
und baut React **nicht** selbst. Deshalb gilt:

- `amd/build/` darf NICHT in `.gitignore` stehen. Die gebauten Laufzeit-Artefakte
  `amd/build/init.min.js` und `amd/build/editor_lazy.min.js` (der esbuild-React-
  Bundle) müssen eingecheckt sein.
- `thirdpartylibs.xml` verweist auf `amd/build/editor_lazy.min.js`. `grunt
  ignorefiles` und der moodle-cs-Vendors-Check verlangen, dass jeder dort
  genannte Pfad existiert. Fehlt die Datei (weil ignoriert), brechen
  `install`/`grunt ignorefiles` mit ENOENT und `phpcs` mit „non-existent path" ab.
- phpcs der CI läuft OHNE `--ignore=tools/`. Alle Dateien unter `tools/` müssen
  daher ebenfalls sauber sein (u. a. korrekter `@package mod_vimipad`-Tag).

Prüfen, ob Git die Artefakte trackt:
```bash
git check-ignore amd/build/editor_lazy.min.js   # darf NICHTS ausgeben
```

---

## 14. Tests IMMER auch isoliert laufen lassen

`vendor/bin/phpunit --filter mod_vimipad` kann Fehler verbergen: Lädt eine frühe
Testdatei eine nicht-autoloadbare Basisklasse (z. B.
`externallib_advanced_testcase` aus `webservice/tests/helpers.php`), profitieren
spätere Dateien davon — bis die CI sie EINZELN lädt und es knallt.

Deshalb vor jeder Auslieferung jede Testdatei einzeln prüfen:
```bash
for t in mod/vimipad/tests/*.php; do
  vendor/bin/phpunit "$t" 2>&1 | grep -E "OK \(|FAILURES|ERRORS|not found"
done
```
External-Function-Tests MÜSSEN `require_once($CFG->dirroot.'/webservice/tests/helpers.php')`
enthalten und `externallib_advanced_testcase` aus dem globalen Namespace nutzen.


## Fallstrick: Composer-Self-Update-503 beim PHPUnit-Init

`php admin/tool/phpunit/cli/init.php` versucht ein Composer-Self-Update. Liefert
`https://getcomposer.org/versions` gerade HTTP 503, bricht init ab und die
PHPUnit-Env bleibt auf der alten Plugin-Version ("was initialised for different
version"), obwohl am Code nichts falsch ist. Umgehung:

```bash
php admin/tool/phpunit/cli/init.php --no-composer-self-update
```

## 15. Playwright-Live-Tests in der Sandbox (funktioniert — Technik)

Entgegen dem Hinweis in Abschnitt 0/10 lassen sich die Playwright-Kollaborations-
tests (`tests/playwright/`) **real** in der Sandbox ausführen — mit echtem
Chromium gegen ein laufendes Moodle. Der Behat-`@javascript`-Weg bleibt separat;
Playwright braucht Moodles Selenium-Stack NICHT.

### 15.0 Die zwei Kernhürden (ZUERST verstehen)

1. **Nichts überlebt zwischen Tool-Aufrufen — außer detachten Jobs und Dateien.**
   Hintergrundprozesse via `&`/`nohup` sterben am Aufruf-Ende. Ein per
   `setsid <script> </dev/null >/dev/null 2>&1 &` gestarteter Runner **überlebt**
   dagegen. Muster: der Runner schreibt seine Ausgabe in eine Datei
   (`/tmp/pwout.log`) und setzt am Ende einen Marker (`/tmp/pwdone`); über mehrere
   **kurze** Aufrufe wird die Datei gepollt.
2. **Der einzelne Tool-Aufruf hat ein Zeitlimit, und gepufferte stdout geht beim
   Timeout verloren.** Deshalb IMMER in eine Datei schreiben, nie auf die
   Live-stdout eines langen Laufs verlassen.

### 15.1 Site-DB installieren (PG-Neustarts leeren sie)

Prüfen und ggf. neu installieren (Kern + Plugin-Schema):

```bash
service postgresql start; sleep 2
# 0 Tabellen? -> installieren:
cd /home/claude/moodle
php admin/cli/install_database.php --agree-license \
  --adminpass='Admin!23456' --adminemail='admin@example.invalid' \
  --fullname='ViMi Test' --shortname='vimitest'
```

### 15.2 config.php: wwwroot-Port und behat_wwwroot

- `$CFG->wwwroot` MUSS exakt zum php -S-Port passen (Moodle leitet sonst um).
  Hier: `http://localhost:8000`.
- **`$CFG->behat_wwwroot` MUSS sich von `$CFG->wwwroot` unterscheiden**, sonst
  wirft Moodle bei JEDEM Web-Request einen Fatal ("Behat config error:
  behat_wwwroot ... must be different from wwwroot") und die Seite liefert 500.
  Beispiel: wwwroot `:8000`, behat_wwwroot `:8001`.

### 15.3 Webserver: php -S MIT Workern

Der Built-in-Server ist einzeln-threadig; Playwright öffnet aber mehrere Browser-
Kontexte (parallele Requests). Seit PHP 7.4 forkt `PHP_CLI_SERVER_WORKERS` mehrere
Worker — das behebt die im Guide beschriebene Instabilität:

```bash
PHP_CLI_SERVER_WORKERS=8 php -S localhost:8000 -t /home/claude/moodle
```

Smoke-Test: statische Datei muss 200 liefern, `login/index.php` ebenfalls 200
(nicht 500). Ein schnelles 500 ist meist die behat_wwwroot-Falle (15.2), KEIN
langsamer Cache-Build.

### 15.4 Playwright + Chromium installieren (einmalig, bleibt auf Platte)

```bash
cd /home/claude/moodle/mod/vimipad/tests/playwright
npm install --no-audit --no-fund
npx playwright install --with-deps chromium
```

### 15.5 Seed separat — braucht KEINEN Webserver

`seed.php` bootstrappt CLI und schreibt direkt in die DB. Deshalb in einem eigenen
(schnellen, hintergrundfreien) Aufruf seeden und die Exports in eine Datei legen:

```bash
cd /home/claude/moodle/mod/vimipad/tests/playwright
php seed.php > /tmp/vimipad_env.sh 2>/tmp/seed_err.log
cat /tmp/vimipad_env.sh    # enthält VIMIPAD_BASE_URL (aus wwwroot) + ACTIVITY_PATH + User
```

### 15.6 Der Runner (ein detachtes Skript, alles drin)

```bash
cat > /tmp/runpw.sh <<'RUNNER'
#!/bin/bash
exec >/tmp/pwout.log 2>&1
rm -f /tmp/pwdone
cd /home/claude/moodle
service postgresql start; sleep 2
pkill -f "php -S localhost:8000"; sleep 1
PHP_CLI_SERVER_WORKERS=8 php -S localhost:8000 -t /home/claude/moodle >/tmp/phpsrv.log 2>&1 &
SRV=$!
for i in $(seq 1 25); do
  c=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 15 http://localhost:8000/login/index.php 2>/dev/null)
  [ "$c" = "200" ] && { echo "server ready ($i)"; break; }
  sleep 1
done
cd /home/claude/moodle/mod/vimipad/tests/playwright
. /tmp/vimipad_env.sh
echo "=== RUN START $(date +%T) BASE=$VIMIPAD_BASE_URL ACT=$VIMIPAD_ACTIVITY_PATH ==="
npx playwright test --reporter=line
echo "=== EXIT=$? $(date +%T) ==="
kill $SRV 2>/dev/null
echo DONE > /tmp/pwdone
RUNNER
chmod +x /tmp/runpw.sh
setsid /tmp/runpw.sh </dev/null >/dev/null 2>&1 &
disown
```

### 15.7 Pollen bis fertig (kurze Aufrufe)

```bash
tail -30 /tmp/pwout.log; echo "---"; cat /tmp/pwdone 2>/dev/null || echo "läuft noch"
```

Ein `sleep 20; tail ...` zwischen den Polls ist ok, solange der Aufruf selbst
keinen eigenen Hintergrundserver hält (der würde den Shell-Exit blockieren und den
Aufruf ins `-1`-Timeout laufen lassen).

### 15.8 Diagnose-Gold: Screenshots, ARIA-Snapshot, DB

- Playwright legt bei Fehlern `test-results/<...>/test-failed-*.png` und
  `error-context.md` an. Den PNG per `view` ansehen, die `error-context.md`
  (enthält einen **ARIA-Snapshot** mit Rollen/Namen aller Elemente) per `cat`
  lesen — damit klärt man Selektor-/Zustandsfragen ohne Rätselraten.
- Persistenz/Sharing per SQL prüfen, z. B. „liegt der Knoten im geteilten
  Workspace?":
  ```bash
  su postgres -c "psql -tAc \"SELECT workspaceid, count(*), string_agg(label,' | ') \
    FROM mdl_vimipad_node GROUP BY workspaceid;\" moodle"
  ```

### 15.9 Fallstrick Test-Design: View-Tabs sind server-seitige Links

Die Editor-Tabs (Canvas/List/Journal/Tools) sind `view.php?...&tab=<x>`-Links —
ein Tab-Wechsel ist ein **voller Page-Reload**, der den laufenden Live-Poll-State
verwirft. Live-Kollaboration (ein Client fügt hinzu, der andere empfängt per Poll)
gehört daher auf dem **Canvas** geprüft (`page.locator('.vimipad-canvas')`), nicht
über einen Tab-Wechsel. Knoten-Labels erscheinen zusätzlich als `<option>` in den
Subject/Object-Selects — Assertions deshalb auf den Canvas-Container scopen, sonst
Strict-Mode-Verletzung.

Präsenz ist sperrbasiert (`PresenceMap` = Element→userid), es wird KEIN Name
gerendert. Der Präsenz-Test hält daher per Client A einen Knoten (pointer-down =
Lease) und prüft bei Client B die Klasse `vimipad-canvas-node-locked` — kein
namensbasiertes Assert.

### 15.10 Merke

- Sprache/Login: Das Login nutzt stabile IDs (`#username`/`#password`/`#loginbtn`)
  und KEINEN `?lang=en`-Param (der Sprach-Redirect kann sonst den Login-Token
  entwerten); Erfolg wird an „URL hat `/login/` verlassen" gemessen (NICHT an
  `#loginbtn`-Verschwinden — der kann auf eingeloggten Seiten weiter existieren,
  und `/index/` matcht fälschlich `login/index.php`). Englisch für den Editor
  kommt über die Aktivitäts-URL (`&lang=en`).
- `seed.php` gibt `VIMIPAD_BASE_URL` aus `$CFG->wwwroot` selbst aus — kein
  manuelles `:8000`.
- Reihenfolge im Zweifel: (1) Site-DB da? (2) behat_wwwroot ≠ wwwroot? (3) Server
  mit Workern + 200 auf /login? (4) Chromium installiert? (5) geseedet in Datei?
  (6) Runner detached + pollen.

---

## 16. local_moodlecheck lokal ausführen (der PHPDoc-Gate-Check der CI)

Die GitHub-CI lässt `moodle-plugin-ci phpdoc --max-warnings 0` laufen; darunter
steckt `local_moodlecheck`. `phpcs` findet dessen Befunde **nicht** — ein
fehlender `@param` nach einer Signaturänderung ist phpcs-sauber und bricht
trotzdem die CI. Deshalb gehört der Check in die lokale Kette:

```bash
cd /home/claude/moodle
git clone -q --depth 1 \
  https://github.com/moodlehq/moodle-local_moodlecheck.git local/moodlecheck
php local/moodlecheck/cli/moodlecheck.php \
  --path=mod/vimipad --exclude=mod/vimipad/tools --format=text \
  | grep -B1 '    Line' | grep -v '^--$'
```

Leere Ausgabe = keine Befunde. Häufigster Treffer nach einer Änderung:
„Phpdocs for function … has incomplete parameters list" — ein neuer Parameter
wurde der Signatur hinzugefügt, aber nicht dem Docblock.

---

## 17. k6 live in der Sandbox ausführen

Wie bei Playwright (Abschnitt 15) überlebt weder der PHP-Built-in-Server noch
ein laufender k6-Prozess die Grenze eines Tool-Aufrufs. Dasselbe Runner-Muster
verwenden: ein `setsid`-abgekoppeltes Skript startet Server und Lauf, schreibt
in eine Logdatei und setzt am Ende einen Marker, der dann gepollt wird.

```bash
curl -sSL https://github.com/grafana/k6/releases/download/v0.54.0/k6-v0.54.0-linux-amd64.tar.gz \
  -o /tmp/k6.tgz
tar xzf /tmp/k6.tgz -C /tmp --strip-components=1 k6-v0.54.0-linux-amd64/k6
```

Seed und Umgebungsvariablen kommen aus `tests/load/seed_large.php`
(`export BASE_URL/TOKEN/WORKSPACEID/CMID/REVISION`). Wichtig: `REVISION` muss
`<= currentrevision` sein, sonst wirft `get_revision_state`
„revision out of range".

**Schwellenwerte prüfen, nicht nur Zahlen lesen.** Ein Lasttest, der bei echten
Fehlern grün bleibt, ist wertlos. Die Nulltoleranz-Metriken
(`vimipad_exceptions`, `vimipad_http_errors` mit `rate==0`) lassen sich negativ
verifizieren, indem man mit einem ungültigen Token fährt: der Threshold muss
brechen und k6 mit **Exit 99** enden.

---

## 18. Paketierung real prüfen statt annehmen

`.gitattributes`-`export-ignore`-Regeln wirken nur bei `git archive`, nicht beim
Arbeitsbaum. Prüfen, was tatsächlich ausgeliefert würde:

```bash
git archive --format=tar --prefix=vimipad/ HEAD | tar -tf - > /tmp/ga.txt
grep -c '^vimipad/docs/'            /tmp/ga.txt   # muss 0 sein
grep -c '^vimipad/tests/load/'      /tmp/ga.txt   # muss 0 sein
grep -c '^vimipad/amd/src/'         /tmp/ga.txt   # muss > 0 sein
grep    'amd/build/.*\.map'         /tmp/ga.txt   # CI verlangt die Source-Maps
```

Die Source-Maps dürfen **nicht** aus dem Paket ausgeschlossen werden: die CI
prüft ihre Existenz mit `test -f` und ihre Reproduzierbarkeit mit
`git diff --exit-code`.
