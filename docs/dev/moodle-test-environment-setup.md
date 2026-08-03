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
