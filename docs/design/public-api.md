# Public API (stable contract)

> Stand: 0.7.27. Diese Oberfläche ist ab 0.7.27 als *stabil* zugesagt: die
> Signaturen werden über Minor-Releases hinweg nicht brechend geändert.
> Alles unter `\mod_vimipad\local\*` ist Implementierung ohne Stabilitäts-
> garantie und darf sich frei ändern.

Abgeleitete Plugins (`qtype_vimipad`, `datafield_vimipad`, Block-/Kursformat-
Viewer, eigenständige Einbettungen) dürfen ausschließlich gegen die hier
beschriebene Oberfläche programmieren und müssen
`$plugin->dependencies = ['mod_vimipad' => ...]` in ihrer `version.php`
deklarieren.

## PHP: `\mod_vimipad\api\*`

Dünne, stabile Fassaden, die an die internen Services delegieren.

### `\mod_vimipad\api\ids`

Erzeugung und Validierung stabiler Element-Ids (Präfix + 12 Hex).

- `ids::new_node_id(): string`
- `ids::new_relation_id(): string`
- `ids::new_container_id(): string`
- `ids::is_valid(string $value, ?string $kind = null): bool`

### `\mod_vimipad\api\map`

Rekonstruktion eines Map-Zustands aus dem Operation-Log. Führt bewusst *keine*
Zugriffskontrolle durch: wer die Fassade über das Web anbietet, muss vorher
selbst Kontext/Capability prüfen.

- `map::state_at(int $workspaceid, int $revision): array`
  liefert `['nodes' => [...], 'relations' => [...], 'containers' => [...]]`
  (jeweils lebende stdClass-Records bei dieser Revision).

### `\mod_vimipad\api\score`

Kontextfreie Bewertung einer Map gegen eine Referenz-Map. Die öffentliche Naht
über die interne Assessment-Engine (die `vimipadassess_*`-Subplugins): ein
abgeleitetes Plugin (Fragetyp, Peer-Review) bewertet damit identisch zur
Aktivität, ohne interne Klassen anzufassen und ohne Scoring zu duplizieren.
Beide Maps sind die serialisierte Snapshot-JSON, die der ViMi-Pad-Editor
exportiert (Knoten mit `stableid`/`label`/`content`, Relationen mit
`sourceid`/`targetid`/`label`, optional Container/Memberships). Kein Kontext,
keine Aktivitätsinstanz, kein DB-Zugriff nötig.

- `score::against_reference(string $responsejson, string $referencejson, string $scorerkey = 'reference', int $matchmode = self::MATCH_EXACT): ?array`
  liefert `['score' => float, 'partscores' => [...], 'concepts' => [...],
  'propositions' => [...], 'metrics' => [...], 'informational' => bool]` oder
  `null`, wenn die Eingaben unbrauchbar sind (ungültiges JSON oder Scorer nicht
  installiert).
- `score::fraction(string $responsejson, string $referencejson, string $scorerkey = 'reference', int $matchmode = self::MATCH_EXACT): ?float`
  Bequemer Wrapper, der nur die Gesamtzahl (0.0-1.0) zurückgibt.
- `score::reference_scorers(): string[]` - Keys der installierten,
  referenzbasierten Scorer (ohne referenzfreie und ohne KI-Scorer).
- Konstanten `MATCH_EXACT`, `MATCH_FUZZY`, `MATCH_TOKEN` wählen die
  Label-Matching-Strategie.

## PHP: `\mod_vimipad\profile\*`

Kontextfreie Profilvalidierung: benötigt *keinen* Moodle-Aktivitätskontext
und keine gespeicherte vimipad-Instanz.

### `\mod_vimipad\profile\profiles`

- `profiles::all(): string[]` - alle bekannten Profil-Keys.
- `profiles::exists(string $profile): bool`
- `profiles::form_config(string $profile): array` - vollständige Formkonfig
  (`profile`, `name`, `allowedshapes`, `defaultshape`, `line`, `bifurcation`);
  für unbekannte Profile ein sicherer Fallback.
- `profiles::is_shape_allowed(string $profile, string $shape): bool`
- `profiles::clamp_shape(string $profile, ?string $shape): string` - erzwingt
  eine für das Profil gültige Form (Default, wenn unzulässig/null).

## JavaScript: einbettbarer Editor

AMD-Modul `mod_vimipad/editor_lazy`, Default-Export mit drei Entrypoints. Jeder
nimmt ein DOM-Element und eine Config.

### `mount(element, config)`

Vollständiger Editor. `config`:

- `cmid: number` (erforderlich)
- `groupid?: number`, `initialView?: 'canvas' | 'list'`, `readonly?: boolean`,
  `targetUserid?: number`
- `callService?: ServiceTransport` - **austauschbarer Persistenz-Adapter**;
  fehlt er, wird der eingebaute fetch-Client gegen `service.php` genutzt.
- `getString?: (key: string) => string | undefined` - i18n-Resolver.

### `mountValue(element, config)`

Für Hosts, die die ganze Map als **einen selbst-enthaltenen Wert** speichern
(Fragetyp-Attempt, Datenbankfeld) statt als lebende Aktivitäts-Workspace. Der
Editor wird an einen In-Memory-Transport gebunden; der Host bekommt den
serialisierten Wert per `onChange` und spiegelt ihn typischerweise in ein
verstecktes Formularfeld. `config`: `value` (Start-Map), `onChange`
(nach jeder Änderung), optional `profile`, `readonly`, `initialView`,
`getString`. Rückgabe: ein Handle mit `getValue()` / `getState()`.

Der serialisierte Wert hat dieselbe Snapshot-Form wie der Aktivitaets-Export,
läuft also durch diesen Transport zurück und ist direkt ueber
`\mod_vimipad\api\score` bewertbar.

### `mod_vimipad/editor_strings`

Single source of truth der Editor-String-Schlüssel. Exportiert `STRING_KEYS`
(die Liste) und `load(): Promise<(key) => string>` — einen fertigen getString-
Resolver. Einbettende Hosts können ihn nutzen, statt die Liste zu duplizieren
(alternativ laden sie die Strings serverseitig via `strings_for_js`).

### `createValueTransport(valuejson, options)`

Die Primitive hinter `mountValue`: liefert `{transport, getValue, getState}`.
`options`: `profile`, `onChange`, `readonly`.

### `mountRevision(element, config)` / `mountPlayer(element, config)`

Schreibgeschützte Einzel-Revisions-Ansicht bzw. animierter Replay. `config`:
`cmid`, `workspaceid`, `revision` (Player zusätzlich `maxRevision`), plus
optional `callService`/`getString` wie oben.

### Persistenz-Adapter: `ServiceTransport`

```ts
type ServiceTransport = (methodname: string, args: Record<string, unknown>) => Promise<unknown>;
```

Ein Host, der den Editor außerhalb einer normalen Aktivitätsseite einbettet,
stellt diese eine Funktion bereit und beantwortet damit die
`mod_vimipad_*`-Serviceaufrufe (mindestens `get_workspace`; für schreibende
Nutzung zusätzlich `apply_operation`, `save_layout`, Lock-Aufrufe). So lässt
sich die Persistenz frei austauschen (eigener Endpoint, In-Memory, Test-Stub),
ohne den Editor selbst zu ändern.

## Teststatus

Der Contract ist durch Tests abgesichert: `api_profiles_test`, `api_map_test`,
`api_score_test`
(PHP) sowie `embed_mount.test.ts` und `value_transport.test.ts` (wertgebundene Persistenz).


**Peer review on the scoring facade.** Peer comparison needs no new entry point:
`\mod_vimipad\api\score::fraction($reviewermap, $authormap)` scores one map
against another (reviewer-vs-author or peer-vs-peer). To combine several peer
fractions into one grade, `score::aggregate_fractions(array $fractions, string
$method)` offers `AGG_MEAN`, `AGG_MEDIAN` and `AGG_TRIMMED` (drop one lowest and
one highest, then mean); values are clamped to 0.0-1.0 and non-numeric entries
ignored, empty input yields null.
