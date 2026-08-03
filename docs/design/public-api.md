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

Der Contract ist durch Tests abgesichert: `api_profiles_test`, `api_map_test`
(PHP) sowie `embed_mount.test.ts` (Editor mountet mit selbst gestelltem
In-Memory-Transport, ohne Netzwerk).
