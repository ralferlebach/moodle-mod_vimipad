# Changelog — mod_vimipad (ViMi Pad)

> **Versioning note.** The authoritative version is always `version.php`
> (`$plugin->release` / `$plugin->version`). Some early Session-002 entries below
> used an exploratory 0.5.0–0.9.1 numbering that was later reset to the 0.2.x
> line; those entries are kept for historical reference only. The current
> release is **0.8.34** (2026072804).

## 0.8.34 (2026072804) — Paginiertes Laden gro{ß}er Maps

`get_workspace` liefert eine gro{ß}e Map bisher als einen einzigen Payload mit
Tausenden validierten Zeilen — unter Nebenläufigkeit der teuerste Endpunkt
(Speicher + Rückgabe-Validierung pro Request). Neu wird die Map **seitenweise**
geladen, mit **vollständig validierter** Payload (kein `PARAM_RAW`, jede Zeile
geht durch `clean_returnvalue`):

* `get_workspace` erhält einen additiven Parameter `includeelements` (Default
  `true` = unverändertes Verhalten, rückwärtskompatibel). Mit `false` liefert er
  nur Metadaten plus `counts` (Knoten/Relationen/Container).
* Neue Lesefunktion `mod_vimipad_get_workspace_elements(cmid, workspaceid, kind,
  offset, limit)` gibt **validierte** Seiten je Elementart zurück (max. 500 pro
  Seite), geordnet nach id (stabile Seiten).
* Der API-Client lädt zuerst die Metadaten (`includeelements=false`), pagt dann
  Knoten/Relationen/Container parallel und setzt daraus die volle
  `WorkspaceState` zusammen — **Editor, Reducer und Rendering bleiben
  unverändert**. Fällt `counts` (leerer Workspace/altes Backend), werden die
  inline gelieferten Arrays direkt genutzt.

Wirkung: Jeder einzelne Request ist in Grö{ß}e, Speicher und Validierungskosten
begrenzt — das senkt vor allem die Tail-Latenz unter gleichzeitigen
Ladevorgängen. Live verifiziert: eine 1000-Knoten-Map lädt über zwei
Element-Seiten und rendert vollständig im Browser.

## 0.8.33 (2026072803) — Performance: schlankeres get_workspace

`workspace_service::get_state()` — die Datenquelle von `get_workspace` — lud
bisher via `get_records(...)` **alle** Spalten der Knoten/Relationen/Container,
obwohl die externen Mapper nur einen Teil nutzen. Bei gro{ß}en Maps (Tausende
Zeilen) lädt es jetzt nur noch die tatsächlich benötigten Felder (Audit-IDs und
Zeitstempel entfallen). Messbar: Query-Zeit der Zustandsabfrage ≈ −40%, Peak-
Speicher pro Request ≈ −13% — was vor allem unter gleichzeitigen Ladevorgängen
den Speicherdruck und die Tail-Latenz senkt. Rückgabe-Struktur und Verhalten
sind unverändert.

Hinweis: Der grö{ß}te verbleibende Posten pro Aufruf ist Moodles Rückgabe-
Validierung (`clean_returnvalue`) über die volle Map; ihn zu senken hie{ß}e, die
Rückgabe-Struktur zu ändern (API-Freeze 0.7.27) und ist daher bewusst nicht
Teil dieser Änderung.

## 0.8.32 (2026072802) — Fix: Container wandern beim (Re-)Load

Behebt ein sichtbares Fehlverhalten beim Laden/Neuladen eines ViMis: Container
(und andere Elemente) „wanderten" mehrere Sekunden lang durch ihre
Bearbeitungshistorie — gro{ß} wurden, schrumpften und verschoben sich — bis sich
das Bild beruhigte.

**Ursache:** Der Poll-Client startete seinen Revisions-Cursor bei 0. Der erste
Poll holte damit `get_operations` ab Revision 0, also den **kompletten Op-Log**,
und wandte jede historische Container-Verschiebung/-Grö{ß}enänderung erneut an —
bei jedem (Re-)Load.

**Fix:** `useCollaboration` erhält die beim Laden bereits angewandte Revision und
setzt den Poll-Cursor **vor** dem Start darauf. Der erste Poll fragt dadurch nur
noch **neuere** Operationen ab; die Historie wird nicht mehr abgespielt. Regressions-
test (`use_collaboration_baserevision`) sichert die Verdrahtung ab.

## 0.8.31 (2026072801) — Typisierte Relationen für Flow und semantisches Netz

Rundet die typisierten Relationen über die Formen ab, bei denen ein festes
Typ-Set fachlich Standard ist. Bewusst NICHT typisiert bleiben Formen, deren
Modell freie oder gar keine Relationstypen vorsieht (Concept Map – freie
Verbindungswörter; Mindmap/Tree/Bubble/Timeline/Affinity/Fishbone/Venn).

- **Flow chart:** Relationstypen **Abfolge** (neutral), **Ja** und **Nein** – die
  Entscheidungszweige eines Flussdiagramms. Ja grün/durchgezogen, Nein
  rot/gestrichelt; Abfolge bleibt neutral. Kein Layout-Effekt (die Rang-Schichtung
  ordnet die Struktur bereits).
- **Semantisches Netz:** die klassischen typisierten Links **is-a**, **Instanz-von**,
  **part-of**, **hat-Eigenschaft**, **assoziiert**. part-of bindet enger
  (Ruhelänge 0.6); das Netz bleibt frei (keine globale Richtung erzwungen), is-a
  wird also eingefärbt, aber nicht in eine Hierarchie gezwungen (das leistet die
  Ontologie-Form).
- Neue Stile (instanceof türkis, hasproperty orange-gepunktet, yes grün, no
  rot-gestrichelt) im generischen Modul; Strings `editor:reltype_*` (en/de).
- **Tests:** Stilkarte (yes/no/sequence, instanceof/hasproperty) und PHPUnit-
  Deklarationen (flow sequence/yes/no; semantisches Netz mit part-of-Ruhelänge).

## 0.8.30 (2026072800) — Typisiertes Set MIT Layout-Effekt: Ontologie (deklarativ)

Kombiniert typisierte Relationen mit einem *per-Typ-Layout-Effekt* – deklarativ
über die Contract, sodass jede Form künftig pro Relationstyp Layout-Verhalten
erklären kann. Als Referenz die neue Ontologie-Form.

- **Neuer Contract-Baustein: per-Typ-Layout.** `base::get_relation_layout()`
  (Default `[]`) bildet Relationstypen auf Layout-Hinweise ab
  (`['isa' => ['directed' => true], 'partof' => ['restscale' => 0.6]]`); in
  `to_array` als Liste `relationlayout` und in der `get_workspace`-Struktur
  transportiert. Der Adapter wendet sie je Kante an: `directed` kann eine Kante
  unabhängig vom Pfeil gerichtet erzwingen, `restscale` skaliert ihre Ruhelänge.
- **Engine verallgemeinert:** `RefineEdge.restScale` (expliziter Multiplikator)
  löst den bisherigen Angriffs-Sonderweg ab – die interne Ruhelänge nutzt
  `edge.restScale ?? (edge.attack ? attackRestScale : 1)` (Argument-Verhalten
  unverändert).
- **Neues Subplugin `vimipadform_ontology`** (Ontologie / Wissensnetz): Typen
  **is-a**, **part-of**, **assoziiert**. is-a bildet eine nach oben gerichtete
  Taxonomie (gerichtet), part-of bindet Teile eng (Ruhelänge 0.6), assoziiert
  ist neutral. Typ-UI und -Farben kommen automatisch aus dem generischen Modul.
- Stile für isa/partof/associated (blau/violett-gestrichelt/grau) + Strings
  `editor:reltype_isa` / `_partof` / `_associated` (en/de).
- **Tests:** per-Typ-restScale (part-of enger als assoziiert), Ontologie-Resolver
  (is-a directed, part-of restscale<1; PHP-relationlayout transportiert) und
  PHPUnit-Deklaration.

## 0.8.29 (2026072799) — Typisierte Relationen verallgemeinert + Causal-Polarität (+/−)

Weitet die typisierten Relationen (bisher nur Argumentkarte) auf die Wirkungs-/
Systemkarte aus und macht die Typ-Darstellung generisch.

- **Gemeinsames Stilmodul `relation_types.ts`:** eine einzige Quelle für Farbe
  und Strich je Relationstyp. Relations-Menü, Listenansicht und Canvas lesen von
  hier; ein neuer typisierter Relationstyp braucht nur einen Eintrag plus einen
  Lang-String `editor:reltype_<key>` – kein Sonderfall je Komponente mehr. Die
  bisher hartcodierten support/attack-Farben wurden hierher gezogen (Verhalten
  unverändert).
- **Causal-/Systemkarte: Link-Polarität.** `vimipadform_causal` deklariert jetzt
  die Relationstypen **positiv (+)** und **negativ (−)** – der Klassiker im
  Causal-Loop-Diagramm. Positiv wird grün/durchgezogen, negativ rot/gestrichelt
  dargestellt; die vorhandene generische Typ-UI (Dock + Liste) bietet die Auswahl
  automatisch an, die Persistenz (relation_update) läuft unverändert.
- **Export-sicher:** die Polaritätsfarben nutzen CSS-Variablen mit Literal-
  Fallback wie die übrigen, erscheinen also korrekt im PNG/SVG-Export.
- Neue Strings `editor:reltype_positive` / `editor:reltype_negative` (en/de).
- **Tests:** `relation_types.test.ts` (Stilkarte: support/attack/positiv/negativ,
  Export-sichere Farben) und PHPUnit-Deklaration (causal relationtypes).

## 0.8.28 (2026072798) — Letzte geplante Formen: Causal/System und Venn/Mengen

Schließt den 0.8.x-Formenkatalog ab. Beide Formen kommen ohne neuen Engine-Term
aus — sie kombinieren vorhandene Bausteine.

- **`vimipadform_causal` (Wirkungs-/Systemdiagramm):** gerichtetes Netz mit
  erlaubten Rückkopplungen. Es wird bewusst KEINE globale Flussrichtung erzwungen
  (das würde gegen die Zyklen arbeiten), das Layout ist also frei wie ein
  semantisches Netz; Relationen sind standardmäßig gerichtet und geschwungene
  Verbinder lesen Feedback-Schleifen klar.
- **`vimipadform_venn` (Venn/Mengen):** Mengen sind (Ellipsen-)Container, Elemente
  sind Knoten. Die vorhandene Cluster-Kohäsion (0.8.26) zieht die Mitglieder jeder
  Menge zu ihrem Schwerpunkt, während die Container-Außenkuppel Nicht-Mitglieder
  fernhält; ein Element, das zu zwei überlappenden Mengen gehört, wird in beide
  gezogen und setzt sich in deren Schnittmenge. Mitgliedschaftsbasiert, nicht
  geometrische Mengenalgebra (Letztere ist bewusst außerhalb des Rahmens).
- Beide werden von der Registry automatisch erkannt und erscheinen in der
  Profilauswahl; `refineOptionsForProfile` kennt zusätzlich den venn-Fallback
  (clustered) für Aufrufe ohne transportierte Config.
- **Tests:** Resolver-Fallback (venn geclustert, causal frei) und PHPUnit-
  Deklaration (causal frei/geschwungen, venn geclustert/Ellipse).

## 0.8.27 (2026072797) — Sechste neue Darstellungsform: Fishbone (per-Kante-Richtung)

Das Fischgrätendiagramm als `vimipadform_fishbone`-Subplugin; der neue Baustein
ist eine **per-Kante-Richtung** im Refiner (statt einer einzigen globalen).

- **Neue Engine-Fähigkeit: per-Kante-Richtung.** `RefineEdge.dir` überschreibt je
  Kante die Vorzugsrichtung; intern trägt jede Kante `dirx/diry` (Default = globale
  Spine-Richtung). Der Richtungsterm nutzt jetzt die kantenweite Richtung. Rein
  additiv — ohne `dir` verhält sich alles wie zuvor (bestehende Richtungs-
  Gradiententests bleiben grün; neuer Gradient-Test für per-Kante-Richtung).
- **Fishbone-Zuweisung (`fishboneEdgeDirs`).** Kopf = Knoten mit höchstem
  Eingangsgrad (der Effekt); dessen direkte Quellen sind die Haupt-Gräten, die
  oben/unten alternieren; Unter-Ursachen erben die Seite per BFS. Jede Kante mit
  bekannter Seite erhält eine diagonale Richtung `(1, ∓slope)` → das typische
  alternierende Gräten-Muster. Spine-/unentschiedene Kanten behalten die globale
  Richtung.
- **Contract erweitert:** `base::get_layout_fishbone()` (Default false), in
  `to_array` als `fishbone` und in der `get_workspace`-Struktur transportiert;
  clientseitig über `resolveProfileRefine` in `ProfileRefine.fishbone` aufgelöst,
  das den Adapter die per-Kante-Richtungen bauen lässt.
- **Neues Subplugin `vimipadform_fishbone`** (Fischgrätendiagramm): gerichtete
  Spine +x, per-Zweig-Gräten. Von der Registry automatisch erkannt.
- **Tests:** Gradient-Test (per-Kante-Richtung), Adapter-Test (Haupt-Gräten
  alternieren, Unter-Ursachen erben die Seite), Resolver- und PHPUnit-Layout-
  Deklaration.

## 0.8.26 (2026072796) — Fünfte neue Darstellungsform: Affinity board (Cluster-Anziehung)

Das Affinitätsdiagramm als `vimipadform_affinity`-Subplugin, mit dem neuen
deklarativen Term Cluster-Kohäsion — zugleich ein Vorgriff auf den All-Paar-
Stress-Kern von 1.1 (hier block-diagonal je Container).

- **Neuer Engine-Term: Cluster-Kohäsion.** `refine_layout` bekommt
  `clusterStrength`. Ist sie positiv, werden die Mitglieder jedes Containers zu
  ihrem gemeinsamen Schwerpunkt gezogen (`E = ½k·Σ|x_i − Cluster-Schwerpunkt|²`);
  die Mean-Kopplung im Gradienten hebt sich innerhalb des Clusters exakt auf, die
  Kraft je Mitglied ist damit `k·(x_i − Schwerpunkt)`. Nicht-Mitglieder werden
  weiter von der bestehenden Container-Außenkuppel abgestoßen (intra anziehend,
  inter abstoßend). Gradient gegen Finite-Differenzen geprüft.
- **Contract erweitert:** `base::get_layout_clustered()` (Default false), in
  `to_array` als `clustered` und in der `get_workspace`-Struktur transportiert;
  clientseitig über `resolveProfileRefine` in `clusterStrength` aufgelöst.
- **Neues Subplugin `vimipadform_affinity`** (Affinitätsdiagramm): ungerichtet,
  ohne erzwungene Ordnung; Container = Cluster, deren Mitglieder beim Anordnen
  zusammenrücken. Von der Registry automatisch erkannt.
- **Tests:** Gradient-Test (Cluster-Term), Verhaltenstest (Cluster rückt enger
  zusammen; Term nur bei positiver Stärke gebaut), Resolver-Tests
  (affinity/clustered) und PHPUnit-Layout-Deklaration.

## 0.8.25 (2026072795) — Vierte neue Darstellungsform: Flow/Process (Rang-Layering)

Das Flussdiagramm als `vimipadform_flow`-Subplugin, mit einem neuen deklarativen
Potentialterm: Rang-Layering.

- **Neuer Engine-Term: Rang-Layering.** `refine_layout` bekommt `rankStrength` +
  `rankGap`. Bei positiver Stärke (mit Vorzugsrichtung als Flussachse) muss jede
  gerichtete Kante entlang der Flussachse mindestens `rankGap·L` vorrücken; eine
  softplus²-Strafe auf den Fehlbetrag ist ~0, sobald der Abstand erreicht ist,
  und wächst darunter glatt — so setzen sich die Knoten in diskrete Ebenen statt
  in einen kontinuierlichen Richtungsgradienten. Gradient gegen Finite-
  Differenzen geprüft.
- **Contract erweitert:** `base::get_layout_rank_layered()` (Default false), in
  `to_array` als `ranklayered` und in der `get_workspace`-Struktur transportiert;
  clientseitig über `resolveProfileRefine` in `rankStrength` aufgelöst.
- **Neues Subplugin `vimipadform_flow`** (Flussdiagramm): gerichtet top-down (+y),
  Geschwister links→rechts geordnet, rang-geschichtet, orthogonale Verbinder,
  Rechteck-Boxen. Von der Registry automatisch erkannt.
- **Tests:** Gradient-Test (Rang-Term), Verhaltenstest (gerichtete Kette bildet
  Ebenen; Term nur mit Flussachse + Stärke gebaut), Resolver-Tests
  (flow/rankLayered) und PHPUnit-Layout-Deklaration.

## 0.8.24 (2026072794) — Relationstyp: Listenansicht-Umschalter & Export-Parität

Rundet die typisierten Relationen (0.8.22/0.8.23) ab.

- **Listenansicht (barrierearme Zweitoberfläche):** Jede Relationszeile hat jetzt
  einen Typ-Auswahl-`<select>` (Stütze/Angriff), sichtbar wenn die Form typisierte
  Relationen anbietet. Damit ist der Relationstyp auch tastatur-/screenreader-
  freundlich über die Tabelle bearbeitbar, gleichwertig zum Canvas-Dock.
- **PNG/SVG-Export:** Die Typfarben und der Angriffs-Strich erscheinen im Export
  bereits korrekt — der Export klont das gerenderte SVG, und die Relationen tragen
  die Farbe als inline `stroke`/`strokeDasharray` mit CSS-Variablen-Fallback
  (`var(--vimipad-relation-attack, #c0392b)` bzw. `…-support, #2e7d32`), demselben
  etablierten Muster wie die Selektionsfarbe. Keine Codeänderung nötig; hier nur
  verifiziert und dokumentiert.
- Neuer String `editor:relationtype` (en/de).

## 0.8.23 (2026072793) — Relationstyp-Auswahl und -Darstellung (Argument Map nutzbar)

Vervollständigt die typisierten Relationen aus 0.8.22 um die fehlende Bedienung
und Darstellung — die Argumentkarte ist damit voll nutzbar.

- **Auswahl im Relations-Menü:** Bietet die aktive Darstellungsform typisierte
  Relationen an (deklariert über `relationtypes`), zeigt das Relations-Dock je
  Typ (außer `link`) einen farbigen Umschalter — für die Argumentkarte Stütze
  (grün) und Angriff (rot). Tastaturbedienbar (Buttons mit aria-pressed/label).
- **Darstellung auf dem Canvas:** Relationen werden nach Typ eingefärbt; Angriffe
  zusätzlich gestrichelt. Farben über CSS-Variablen `--vimipad-relation-support`
  / `--vimipad-relation-attack` überschreibbar.
- **Persistenz:** Der Typwechsel läuft über eine `relation_update`-Operation
  (mit Undo/Redo) — die gesamte Kette (Backend-Validierung, apply, reconstruct,
  Remote-Anwendung, Reducer) unterstützte `type` bereits; hier kommen Bedienung
  und Rendering hinzu.
- **Wirkung:** Ein als „Angriff" gesetzter Verbinder aktiviert die Zweig-
  Repulsion aus 0.8.22 (längere Ruhelänge) beim Anordnen.
- Neue Strings `editor:reltype_support`/`editor:reltype_attack` (en/de).

## 0.8.22 (2026072792) — Zweite neue Darstellungsform: Argument Map (typisierte Kanten)

Die Argumentkarte als `vimipadform_argument`-Subplugin — und die Einführung
*typisierter Relationen* (Stütze/Angriff) über die Contract, mit einem
zugehörigen Layout-Effekt.

- **Neuer Contract-Baustein: Relationstypen.** `base::get_relation_types()`
  (Default `['link']`) wird in `to_array` als `relationtypes` und in der
  `get_workspace`-formconfig-Struktur transportiert. Die Argumentkarte deklariert
  `['support','attack']`.
- **Typisierte Kanten im Refiner.** `RefineEdge` bekommt ein `attack`-Flag;
  Angriffskanten erhalten eine längere Ruhelänge (`attackRestScale`, Default 1;
  Argument 1.8) — eine minimale Zweig-Repulsion, sodass angreifende Äste weiter
  abstehen. `refine_arrange` markiert Angriffe aus `relation.type === 'attack'`,
  und `resolveProfileRefine` leitet `attackRepel` daraus ab, ob die Form einen
  `attack`-Relationstyp deklariert.
- **Neues Subplugin `vimipadform_argument`** (Argumentkarte): gerichtet aufwärts
  (Gründe zeigen zur Behauptung, Richtung -y), Geschwister links→rechts geordnet,
  Relationstypen Stütze/Angriff. Von der Registry automatisch erkannt.
- **Tests:** Verhaltenstest (Angriffskante ruht länger als Stützkante; bei
  Skala 1 gleich), Resolver-Tests (argument/attackRepel aus relationtypes) und
  PHPUnit-Layout-/Relationstyp-Deklaration.
- **Handbuch:** Darstellungsformen um Timeline und Argument (umgesetzt) ergänzt,
  inkl. Hinweis auf den noch folgenden Relationstyp-Auswahl-Dialog.

## 0.8.21 (2026072791) — Erste neue Darstellungsform: Timeline (mit 1D-Linien-Confinement)

Die erste der geplanten weiteren Darstellungsformen als eigenes
`vimipadform_timeline`-Subplugin — und zugleich die Referenz für einen neuen,
profil­abhängigen Potentialterm über die 0.8.16-Contract.

- **Neuer Engine-Term: 1D-Linien-Confinement.** `refine_layout` bekommt die
  Optionen `lineConfineStrength` + `lineConfineAxis`. Ist eine Achse gesetzt,
  werden alle Knoten auf eine gemeinsame Linie parallel zur Achse durch ihren
  Schwerpunkt gezogen (bestraft wird die Abweichung senkrecht zur Achse). Der
  Mean-Kopplungsterm im Gradienten hebt sich exakt auf (Summe der senkrechten
  Abweichungen = 0), sodass die Kraft je Knoten schlicht `k·d·perp` ist — ein
  sauberes Flach-auf-eine-Linie ohne absolute y-Vorgabe. Gradient gegen Finite-
  Differenzen geprüft.
- **Contract erweitert:** `base::get_layout_line_axis()` (Default null), in
  `to_array` als `layout.lineaxis` und in der `get_workspace`-Struktur
  transportiert; clientseitig über `resolveProfileRefine` in
  `lineConfineStrength`/`lineConfineAxis` aufgelöst. Nicht-lineare Formen sind
  unberührt.
- **Neues Subplugin `vimipadform_timeline`** (Zeitstrahl): gerichtet links→rechts
  (Richtung +x), Ereignisse auf eine horizontale Linie confined; Standardform
  roundrect, gerade Verbinder. Wird von der Registry automatisch erkannt und
  erscheint in der Profilauswahl.
- **Tests:** Gradient-Test (Linien-Confinement), Verhaltenstest (Ereignisse
  kollabieren auf eine Linie; Term nur bei gesetzter Achse gebaut),
  Resolver-Tests (timeline/lineAxis) und PHPUnit-Layout-Deklaration.

## 0.8.20 (2026072790) — Optionaler Echtzeit-Push über Mercure (SSE)

Setzt die zuvor nur als Einstellung angelegte Push-Funktion tatsächlich um — als
Server→Client-Push über einen **Mercure**-Hub (SSE, kein WebSocket), rein additiv
zum bestehenden Polling.

- **Server:** neues `push_service` publiziert nach jeder committeten Operation
  (`operation_service::apply_locked`) ein `{"revision":N}`-Ereignis an das
  pro-Workspace-Thema `vimipad/workspace/{id}`. Publish ist best-effort mit engen
  Timeouts (1 s Connect / 2 s), Fehler werden geschluckt — ein langsamer oder
  toter Hub beeinflusst die Bearbeitung nie. JWTs (Publisher `publish:["*"]`,
  Subscriber `subscribe:[topic]`) werden mit HS256 aus dem geteilten `pushjwtkey`
  signiert (kein Fremd-Lib).
- **Transport:** `collab_config` liefert pro Workspace `pushtopic` + einen
  gescopeten `pushtoken`; die `get_workspace`-Struktur ist entsprechend erweitert.
- **Client:** neuer `push_client` (EventSource) setzt den `mercureAuthorization`-
  Cookie, abonniert das Thema und **weckt bei jeder neuen Revision ein sofortiges
  Poll**; die eigentlichen Operationen kommen weiterhin über `get_operations`
  (dem Hub wird nicht vertraut). Fällt der Hub aus, bleibt der Poll-Loop die
  Transport- und Rückfallebene.
- **Einstellungen:** neu `pushpublishurl` (optionale interne Publish-URL) und
  `pushjwtkey` (geteiltes HMAC-Secret); `pushenabled`/`pushendpoint` wie gehabt.
- **Keine Server-Software-Abhängigkeit:** Mercure ist ein eigenständiges Go-Binary
  mit eigenem TLS; nginx/apache sind dem Plugin egal.
- **Tests:** `push_service_test` (JWT-Struktur/Signatur, Topic-Scope,
  publish_request, Gating) und `push_client.test.ts` (Wake-Logik, Verfügbarkeit,
  Cookie, robuste Fehlerbehandlung).
- **Handbuch:** Abschnitt 1.3 um die Mercure-Einrichtung (Installation als
  systemd-Dienst, JWT-Keys, CORS, Cookie-/Subdomain-Hinweis, Plugin-Settings)
  ergänzt.

## 0.8.19 (2026072789) — 0.7.30-Audit-Reste geschlossen

Schließt die drei nicht-blockierenden Restpunkte aus dem 0.7.30-Sicherheitsaudit.

**Heartbeat `renew()` vollständig CAS-sicher.** `lock_service::renew()` nutzte
nach dem Read ein unbedingtes `set_field()` nach ID; ein Lease-Takeover, der
zwischen Read und Write schlüpfte, wurde überschrieben, und der alte Halter
erhielt fälschlich `acquired=true`. `renew()` verwendet jetzt — wie `acquire()` —
ein bedingtes `UPDATE … WHERE id AND userid AND timeexpires` plus Re-Read und
meldet den echten aktuellen Halter. Betraf nur den advisory Presence-Lock, nicht
die Datenintegrität (Workspace-Lock + Revision sichern diese zusätzlich).

**`get_operations`-Zugriffsabdeckung erweitert** um Gruppen-Workspace (Mitglied
liest, Nicht-Mitglied braucht `:grade`), Course-Workspace (jede eingeschriebene
Person mit `:view` liest den geteilten Stand), Cross-Activity-Isolation (fremder
Workspace über die falsche cm wird abgewiesen) sowie Guest-, unenrolled- und
suspended-Nutzer (alle abgewiesen).

**Gastzugriff auf Course-Workspaces — bewusste Entscheidung, dokumentiert und
erzwungen.** Das Lesen jedes Workspaces (auch des geteilten Course-Workspaces)
erfordert `mod/vimipad:view`; Gäste, nicht eingeschriebene und suspendierte
Nutzer werden abgewiesen (Kurs-Map ist Kursinhalt, kein öffentliches Datum).
Dokumentiert im Code (`helper::validate_workspace_for_read`) und in
`security_review.md`.

Außerdem: Handbuch-Korrektur zum Push-/WebSocket-Status (die Einstellungen
`pushenabled`/`pushendpoint` existieren als Gerüst und werden zum Client
durchgereicht, aber der clientseitige Push-Verbinder ist noch nicht
implementiert — der Editor pollt weiterhin immer).

## 0.8.18 (2026072788) — Container shrink-on-arrange is now optional

The Arrange action shrinks an oversized container toward its members (damped, so
it converges over presses) so boxes hug their contents. That is the right
default, but where a teacher sizes a template container deliberately it should be
possible to keep the drawn size and only ever grow the box to contain overflow.
Until now the choice was all-or-nothing — resizing was either on (grow and
shrink) or the box was fully fixed (neither), with no "grow to contain but never
shrink" middle ground.

A new site setting, "Shrink containers on arrange" (on by default), provides
exactly that middle ground. When off, containers never shrink below their drawn
size but still grow to enclose an overflowing member; the damping is unchanged.
The setting flows through the same path as the existing arrange-iterations
setting — a `data-arrangeshrink` attribute on the editor mount, read in the AMD
init, passed to the refiner as a `shrinkContainers` option that forces the
container shrink rate to zero when off. No database or schema change is
involved (it is an admin config value, not a per-activity field). New tests
assert that with shrinking disabled an oversized box keeps its exact drawn size
yet an undersized box still grows to contain its member.

## 0.8.17 (2026072787) — Radial forms keep their cyclic order on arrange

Builds on the layout-potential contract from 0.8.16 to give radial display types
(mind map, bubble map) a proper order rule. The linear order axis used by tree
and concept map keeps a left-to-right sibling row, but it cannot express "around
a hub", so until now arranging a mind map could let its branches drift past one
another and scramble the fan. The refiner now preserves the cyclic (angular)
order of a hub's neighbours: for every node with three or more neighbours, the
neighbours are sorted by their initial angle about that node, the single largest
angular gap (the natural opening of the fan, or the wrap-around) is left free,
and the remaining consecutive pairs are chained so each keeps its
counter-clockwise adjacency. A pair is protected by the sign of the cross
product (a-h)x(b-h): an L^2-normalised softplus^2 penalty is ~0 while the order
holds and grows smoothly as the pair rotates toward an inversion, so the term is
dormant unless a fan starts to scramble and never fights an already-clean layout.

The behaviour is wired through the same PHP contract as the other layout
parameters: a new `get_layout_cyclic_order()` on the form base (default off,
transported in `to_array` and the `get_workspace` external structure), which mind
map and bubble map override to on, resolved on the client by `resolveProfileRefine`
into the refiner's new `cyclicStrength` option. Non-radial forms are unaffected.

The analytic gradient of the new term (on the hub and both neighbours) is checked
against finite differences; further tests cover the constraint construction (the
k-1 chain and the three-neighbour gate), that the term is engaged and firms up a
hub's ordering, that a radial hub keeps every neighbour in its initial cyclic
order after a full refinement, and that each bundled form declares the expected
cyclic-order flag.

## 0.8.16 (2026072786) — Layout potential moves into the form subplugin contract

Architectural step toward a stable public API for derived plugins: the arrange
refiner's per-profile behaviour is no longer hard-coded in the frontend. Each
`vimipadform_*` subplugin now declares its own layout-potential parameters in
PHP — whether relations are directed, the preferred flow direction, and the
cross-axis along which sibling order is preserved — through three new methods on
the `\mod_vimipad\local\form\base` contract (`is_layout_directed`,
`get_layout_direction`, `get_layout_order_axis`), with free-form defaults so a
new display type only overrides what it needs. Tree declares a directed,
downward, left-to-right-ordered layout; concept map keeps sibling order without
forcing a direction; the radial and free forms declare neither. These flow to
the editor through the existing form-config transport (`to_array` and the
`get_workspace` external structure gain a `layout` block), and the refiner reads
them via a new `resolveProfileRefine`, falling back to the built-in per-profile
switch only when no config is present (legacy transports, or a profile without
an installed subplugin). Behaviour is unchanged — the PHP declarations mirror the
former hard-coded values exactly — but the seam is now in place: a subplugin
controls how its display type is arranged, and the upcoming cyclic-order and
container-shrink refinements attach to the same contract rather than to a central
switch. New PHPUnit coverage asserts each bundled form's layout declaration and
the fallback; new Jest coverage asserts the resolver prefers the PHP config and
falls back correctly.

## 0.8.15 (2026072785) — Container format menu really stops jumping; Behat backup made reliable

Follow-up to Ralf's next round of testing on the same two-container map. Ships
together with the 0.8.14 arrange rework described below (0.8.14 was never
released on its own), plus two fixes.

**The container format menu still "spun around" — real cause found.** The
earlier fix anchored the floating menu to the live drag preview, but the menu
kept jumping and container shapes would not stick. The actual cause was a
revision race, not a positioning one: selecting a container starts a
zero-distance move drag through its title bar, and that was committed as a
`container_update` on every select-click — bumping the workspace revision each
time. The very next edit (picking a shape in the menu) then ran against the
now-stale revision captured in its closure, was rejected server-side, and forced
a full state reload that dropped the selection, so the menu appeared to jump and
the shape never applied. Two changes fix it: a select-click that does not move
the box now commits nothing (only a real move or resize emits an operation), and
every operation reads the current revision from a ref instead of a render-time
closure, so two edits fired in quick succession can no longer race. A new
regression test asserts that a container title-bar press-and-release with no
movement emits no operation, while a press-move-release emits exactly one.

**Behat: the interactive backup/restore scenario was removed.** The full
backup/restore *data* roundtrip — grade and snapshot reference remapped into the
restored course — is covered reliably by the PHPUnit `backup_restore_test`. The
UI-level scenario depended on the interactive backup wizard, whose completion
step races the asynchronous backup under moodle-plugin-ci and failed in every
driver and Moodle version (it was never green). It is dropped in favour of the
PHPUnit coverage; the activity-duplication scenario stays.

## 0.8.14 (2026072784, released within 0.8.15) — Arrange actually restructures; container menu drag-preview

Fixes from Ralf's testing on a small two-container map.

**Arrange was too weak and too short-ranged.** After the earlier drift fix, the
refiner had swung all the way to pure preservation: edges exerted no pull, so an
explicit "arrange" mostly let nodes drift apart under repulsion without ever
pulling connected nodes together, containers never resized, and a far node on a
long edge barely moved ("kaum Änderungen", "läuft auseinander", "Container ändern
die Größe nicht"). "Anordnen" is an explicit rearrange request, so it now pulls
the layout toward a clean force-directed state: a new gentle, non-saturating
spring pulls every edge toward the median length even in the far field (so long
edges finally contract — fixing "zu kurzreichweitig"), nodes are freer to move
(lower stability anchor), and containers hug their members (they resize instead
of only growing). Every term still converges, so repeated presses settle and a
clean layout is left essentially untouched. New tests assert a long edge is
contracted and that an oversized container shrinks to fit while converging and
never shrinking past its members; the gradient check now also covers the spring.

**The container shape menu "jumped around".** While a container body and its
resize handles followed the live drag preview, the floating format menu stayed
pinned to the container's committed geometry — so any stray nudge (common when
reaching for the menu on two overlapping containers) detached the menu and made
it snap back on release. The menu now tracks the same drag preview as the body.
A new component test also guards that picking a shape from the container dock
actually commits that shape.

**The container shape and lock sub-menus were unusable.** The container's
floating menu lived in a `foreignObject` that was only one row tall. A
`foreignObject` clips — and stops hit-testing — anything drawn outside its
geometric bounds, regardless of CSS `overflow`, so when the shape picker or the
per-group lock sub-menu expanded *below* the toolbar row, that sub-menu fell
outside the box: it rendered erratically and could not be clicked ("die
Form lässt sich nicht einstellen", "Lock-Menüs nicht nutzbar — ein z-Layer-
Problem"). The container menu's box is now tall enough to contain its expanding
sub-panels, exactly as the node menu already was; only the toolbar itself stays
interactive, so the taller (invisible) box remains fully click-through over the
elements beneath it.

**Behat.** The interactive backup/restore UI scenario is unreliable under
moodle-plugin-ci (the backup wizard's completion races the asynchronous backup,
failing under both drivers), while the backup/restore data roundtrip — grade and
reference-snapshot remap included — is covered reliably by the PHPUnit
`backup_restore_test`. The fragile UI scenario has been removed with a note; the
activity-duplication scenario stays.

## 0.8.13 (2026072783) — Elliptical containers use a true elliptical metric

The layout refiner previously treated every container as its axis-aligned
bounding box, so a node in the corner of an elliptical container's box counted
as "inside" even though it sat outside the visible ellipse. Elliptical
containers now use a radial elliptical metric throughout.

- **Membership.** A node is a member of an ellipse container only when its centre
  is inside the ellipse ((dx/a)^2 + (dy/b)^2 <= 1), not merely inside the box, so
  corner nodes are no longer spurious members.
- **Interior potential.** Members of an ellipse are confined by a flat-bottomed
  radial well (zero inside the inner ellipse, quadratic once the elliptical
  radius exceeds one) — they are kept inside the ellipse, not the box, without
  being pulled to the centre.
- **Exterior potential.** Non-members feel a radially-domed elliptical field that
  pushes them out of the ellipse and is force-free beyond the margin.
- **Fit.** An ellipse grows uniformly (centre fixed) until every member's box
  corner lies inside it; grow-only by default, so the human's size is preserved.
- Rectangular and rounded-rectangle containers keep the separable box metric.
  Gradient checks now cover the elliptical interior and exterior; behavioural
  tests cover ellipse confinement, non-member ejection, elliptical growth and the
  corner-node membership distinction.

## 0.8.12 (2026072782) — Behat CI green: three real failures fixed

The behat features added in 0.8.6 were only dry-run validated (the sandbox has
no browser); their first real CI run surfaced three genuine failures, identical
across Moodle 4.5 / 5.0 / 5.2. All three are fixed.

- **groups.feature — group selector.** The scenario set only Moodle's group mode,
  not the plugin's working mode, so the map stayed individual and no selector was
  shown. It now creates the activity with collaborationmode = group, which is
  what makes view.php render the group menu.
- **groups.feature — deprecated step.** "I add a ... to section ... and I fill
  the form with:" is deprecated in current Moodle; updated to the supported "I
  add a ... activity to course ... section ... and I fill the form with:".
- **backup.feature — backup/restore under the wrong driver.** The whole feature
  was @javascript, so the multi-page backup wizard ran under WebDriver and the
  final "Continue" button was clicked before the asynchronous backup finished.
  The grading form is a server-side moodleform and "View and grade" is a plain
  link, so the backup/restore scenario now runs under BrowserKit (synchronous,
  reliable); only the activity-duplication scenario, whose action menu needs JS,
  keeps @javascript. A full-course MODE_GENERAL backup with a gradebook grade was
  verified to succeed, confirming the backup logic itself was never at fault.

No production code changed; gherkinlint is clean and the dry-run resolves every
step.

## 0.8.11 (2026072781) — Journal viewer: no more empty canvas on legacy maps

Fixes the single-revision journal state view showing no nodes at all on some
(older) maps, even though the player showed them correctly.

- **Root cause (data, surfaced by the display).** For maps whose elements were
  created before the op-log existed (or were imported by a legacy path), the
  create-operations are not in the log, so the server-side replay reconstructs
  an empty map. The player already masks this by falling back to the live map
  when it detects the log cannot reproduce it; the single-revision viewer had no
  such fallback, so it rendered an empty canvas.
- **Fix.** The viewer now detects an incomplete op-log exactly as the player does
  (fingerprinting the full replay against the live map). When the history is
  incomplete it shows the current map with a clear notice instead of an empty
  canvas; when the history is complete it still shows a faithful reconstruction
  of the requested revision — even an empty one — so genuine early revisions are
  not misrepresented.
- New tests cover both paths (incomplete -> live map + notice; complete ->
  faithful reconstruction, no notice) and confirm the read-only viewport fit is
  unaffected.

## 0.8.10 (2026072780) — Arrange resizes containers (grow-to-contain, preserving)

The arrange now adjusts container boxes to keep their members enclosed, having
previously left every box untouched.

- **Grow-to-contain, no shrink by default.** A box grows just enough to hold its
  members with padding; it never shrinks below the human's chosen size, so a
  deliberately spacious container stays spacious. Repeated presses converge (the
  box settles at the padded member bounds) rather than drifting. A per-call
  override (containerShrinkRate > 0) can tighten oversized boxes if wanted.
- **Locks respected.** A move-locked container is never resized or moved — its
  geometry belongs to the locked "move" group, so a change would be rejected
  server-side; the arrange excludes it up front.
- **Revisioned + undoable.** Each grown box is persisted as a container_update
  operation (so collaborators receive it) and is part of the re-arrange undo/redo
  entry alongside the node layout.
- New adapter tests cover grow-only preservation of an oversized box, the
  locked-container guarantee, and convergence under repeated application.

Note: containers are still treated as axis-aligned boxes; for elliptical
containers the bounding box is used (a corner member counts as inside). A true
elliptical metric remains a possible follow-up.

## 0.8.9 (2026072779) — Arrange calibration: stop the drift, respect grouping and locks

Fixes three misbehaviours of the new arrange reported on real maps: a node
outside an inner container was slowly dragged into it, an unconnected outside
node ("was anderes") drifted steadily further out on every press, and the
spacing to a locked node was not respected.

- **Per-edge rest length (preservation).** The edge well now anchors to each
  edge's *current* length instead of a single global L, so an already-placed
  edge exerts no length force and the arrange is idempotent — repeated presses
  settle instead of pushing connected nodes apart. A new edgeTargetBlend option
  (default 0 = fully preserve) can gently normalise lengths toward L when wanted.
- **Flat-bottom container interior.** The interior potential is now exactly zero
  inside the wall (a quadratic hinge that only rises once a member crosses it),
  so a large outer container no longer pulls its members toward its centre — the
  mechanism that was dragging an outside node inward and reducing the spacing to
  the locked node. Members are still confined; non-members are still pushed out
  by the (now gentler, shorter-range) exterior dome.
- **Concept maps are no longer direction-forced.** Only the tree profile imposes
  a downward flow; concept maps keep their sibling order axis but are not rotated
  toward a global direction.

New regression coverage reproduces the reported layout (outer box with a locked
member, inner box, an outside node and an unconnected top node) and asserts the
outside node is not swallowed, the locked node never moves, its spacing is kept,
and repeated application converges instead of drifting. Gradient checks now
exercise the flat-bottom wall.

## 0.8.8 (2026072778) — Preservation-first "Arrange" (potential-driven refiner)

Replaces the old force-directed / re-seeding arrange with a layout **refiner**
that gently improves the existing human layout in place instead of rebuilding
it. This is the reliability fix: the tool no longer discards the arrangement a
person made.

- **No initialisation, warm start.** The refiner starts from the current node
  positions and only descends an energy; a clean layout is preserved, and the
  result can never be dramatically worse. Locked and pinned nodes are frozen.
- **One energy, profile-configurable potentials.** A C2-smooth bounded edge
  well (repulsive core, binding minimum at the ideal length L = the median edge
  length, force-free far field), anisotropic (elliptical) node repulsion from
  the box extents, a multiplicative directional term that aligns directed edges
  for hierarchical profiles, a warm-start stability anchor, cosh container
  interiors and domed exteriors that keep members in and non-members out (so
  overlapping-container membership is preserved), and a soft order-preservation
  term that protects the mental map. All parameters derive from L, so the
  behaviour is scale-invariant and deterministic.
- **Solver.** Monotone gradient descent with Armijo backtracking, a per-node
  step cap and a global movement budget, plus a conservative last-resort swap
  pass that only removes a crossing when it does not raise the energy beyond a
  small budget and never swaps connected nodes. An optional active set can
  restrict movement to a diagnosed neighbourhood.
- **Per-profile behaviour** is chosen by a resolver (hierarchical profiles flow
  down and keep sibling order; radial and free-form profiles impose no axis) —
  the pragmatic form of the layout-potential contract, ready to be sourced from
  the form subplugins later.
- Container boxes are **not** resized by the arrange in this release (membership
  is kept by the potentials against the fixed boxes); container minimal-fit is
  implemented in the engine and can be enabled next. Cyclic order preservation
  for radial profiles is likewise a follow-up.
- Extensively tested: every analytic gradient is verified against finite
  differences (edges, direction, repulsion, container cosh/dome, order term),
  plus behavioural tests for monotone descent, determinism, preservation,
  overlap separation, edge-length binding, directed alignment, container
  confinement, overlapping-container intersection preservation, restrictive
  swaps and the arrange adapter.

## 0.8.7 (2026072777) — Journal/revision replay shows the real map

Fixes the read-only state replay used by the journal and the revision viewer/
player, where nodes appeared to be missing entirely and containers looked
misplaced.

- **Root cause:** the reconstruction returned the nodes and containers correctly,
  but the canvas opened every read-only view with a fixed, canvas-centred
  viewport. Because a past revision's node positions come from the saved layout
  and can sit anywhere on the large (2400x1600) canvas, anything outside that
  fixed window was clipped — nodes vanished and containers, framed against the
  wrong window, looked out of place.
- **Fix:** in read-only views (revision viewer, journal replay, player) the
  canvas now fits its viewport to the actual content — every node at its recorded
  position plus every container box — so the whole reconstructed map is framed.
  Panning or zooming hands control back to the user; the auto-fit then steps
  aside. The live editor is unchanged.
- New Jest regression coverage: the RevisionViewer frames nodes placed far from
  the canvas centre, and the RevisionPlayer frames the reconstructed content
  (nodes at their recorded positions and the moved container) at the shown
  revision.

## 0.8.6 (2026072776) — Beta-testing infrastructure

Non-behavioural release that makes the plugin easier to beta-test and to keep
healthy in production. No schema change.

- **Expanded test-data generator (AP1).** The plugin generator gained granular
  creators — `create_node`, `create_relation`, `create_container`,
  `create_membership`, `create_operations`, `create_snapshot`, `create_grade`,
  `create_peer_review` — plus load profiles `create_map_profile` (small 20/30/5,
  medium 200/400/40, large 1000/2000/200) and `create_collaboration_history`.
  The Behat generator can now seed a sized map in one Given step
  (`mod_vimipad > maps` with a `size` column).
- **Operational status checks (AP4).** Three checks appear under Reports:
  ViMi Pad data integrity (orphaned child rows, including layout history),
  ViMi Pad subplugins (declared assess/form subplugins are installed and
  registered) and ViMi Pad history size (operation log and layout history within
  soft budgets), wired via a `mod_vimipad_status_checks()` callback.
- **Beta operations package.** A German beta-testing guide
  (`docs/beta/beta-testing.md`) covering how testers report, a P0–P3 triage
  table, the "every reclamation becomes an automated test" rule and how to seed
  demo data; plus GitHub issue templates (bug report, feature/change request).
- New tests: PHPUnit for the generator creators and small load profile, and for
  each status check.
- **Behat acceptance suite (AP3).** Four new feature files exercising the
  role/capability matrix (grading hidden from students; prohibiting
  mod/vimipad:grade or :view takes effect), Moodle group modes (group selector
  under separate/visible groups; the group-map/group-mode validation), a course
  backup-and-restore roundtrip that keeps the submitted map and its grade, and
  automated accessibility (axe) checks on the editor, grading and snapshot pages.
  All 20 mod_vimipad scenarios resolve with zero undefined steps and pass
  gherkinlint; the @javascript and live runs execute in CI.
- **Playwright collaboration harness (AP5).** A browser test suite under
  `tests/playwright/` for the multi-client behaviour Behat cannot reach: two
  users on one course-mode map, with propagation, presence and convergence
  checks. Includes a Playwright config, page helpers, a Moodle CLI seed
  (`seed.php`) and a manual/scheduled GitHub Actions workflow. Runs against a
  live site, kept out of the static pipeline and the plugin's own tsc/jest.
- **Load test and release process (AP6).** A JMeter plan
  (`tests/load/vimipad-read-endpoints.jmx`) driving the read-heavy web-service
  functions against a seeded large map to catch latency/N+1 regressions, with a
  README and a manual workflow; plus a release checklist
  (`docs/beta/release-checklist.md`) covering the CI matrix, Plugin Checker, RC
  pilot and the deliberate ALPHA→BETA maturity flip.

## 0.8.5 (2026072775) — Topology in replay/journal (R10)

Past revisions now replay with the node topology they actually had, instead of a
recomputed auto-layout, so moves and re-placements are visible in the revision
viewer and player.

- **Append-only layout history.** Node positions live in the (non-revisioned)
  layout channel, so historical topology was previously lost. A new
  `vimipad_layouthist` table records each saved layout tagged with the workspace
  semantic revision at that moment; a replay of revision N uses the newest entry
  with revision <= N. Consecutive identical layouts are de-duplicated and the
  history is capped per workspace/profile (oldest pruned) to bound growth.
- **Server.** `layout_service` appends history on every write and exposes
  `layout_at_revision` and `layout_history`. `get_revision_state` now returns the
  historical layout for its revision, and a new `get_layout_history` external
  feeds the player.
- **Client.** The revision viewer renders nodes at their recorded positions
  (falling back to the auto-layout when nothing was recorded that early); the
  revision player loads the history once and picks the right layout for each
  frame as the slider moves. History loading is best-effort — if it is
  unavailable the replay still works with the auto-layout fallback.
- **Backup/restore, privacy, cleanup.** The history table is backed up and
  restored with user info (workspace and user ids remapped), covered by the
  privacy provider (discovery, user list, and `modifiedby` anonymisation on
  delete), and removed with the workspace on bulk deletion. Schema upgrade step
  and a `null`-safe `layout_at_revision` included.
- New tests: PHPUnit for history append/dedup/query and the backup roundtrip of
  the history; Jest for the viewer rendering recorded positions and its
  auto-layout fallback.

## 0.8.4 (2026072774) — Profile-specific arrange + container membership (R7, R8)

The "re-arrange" action is now a real, profile-aware layout engine and preserves
container membership.

Layout quality (R7):
- **Re-arrange is now profile-specific and centrality-aware.** A new deterministic
  `arrangeLayout` replaces the old "tree or circle" split. Tree and concept maps
  get a tidy top-down hierarchy; mind maps and bubble maps get a radial hub with
  the most-connected node at the centre and even, equal-length spokes; semantic
  networks get a deterministic force-directed layout where high-degree nodes
  settle centrally, nodes spread out evenly, and edges come out roughly equal in
  length. Seeds and iteration counts are fixed, so a given map always arranges
  identically.
- The live render path (`computeLayout`) is unchanged and still cheap; the new
  engine runs only on the explicit re-arrange action.

Container membership (R8):
- **Re-arrange now preserves each node's container membership exactly.** When
  containers are present, every node is re-placed only within the region its
  membership demands — the intersection of the containers it belongs to, and
  outside the ones it does not — with the containers left where the author put
  them. So intersections and subsets survive: a node shared by two overlapping
  containers stays in both, a node in one stays only in that one, and a free node
  stays outside all of them. This replaces the previous bounding-box refit, which
  could grow a container over a non-member.
- New Jest coverage: determinism, radial centrality/even spokes, force-layout
  centrality and near-equal edge lengths, top-down tree, and the R8 example
  (A in C1∩C2, B in C1, C in C2, D outside) preserved across re-arrange.

## 0.8.3 (2026072773) — Lock badge placement + container label fixes (R6, R9)

- **The template-lock badge now sits outside the top-right corner, above every
  element.** It was a coloured padlock glyph inside a node's top-left corner and
  only on nodes. It is now a grey padlock with a white outline, drawn just
  outside the top-right corner of every locked node, relation and container, on
  a dedicated overlay layer that paints above all elements and their text but
  below the menu overlay.
- **An unlabelled container no longer shows a grey title bar.** The grey bar is
  drawn only when the container carries a label or is selected/being renamed, so
  an empty container shows just its dashed outline. The bar stays interactive
  (transparent) even when hidden, so the container can still be selected, moved
  and renamed.
- **Clearing a container's label keeps it empty.** The display no longer falls
  back to "Containers" when the label is blank; an empty label renders as empty
  and persists as an empty string.
- **A new container ships labelled "New container" / "Neuer Container"** instead
  of empty, so it reads as intentional and is easy to grab.
- New Jest coverage: the badge overlay layer renders one badge per locked
  element and paints after the node layer; an empty container has a transparent
  title bar and no fallback text; a labelled one shows the grey bar and label.

## 0.8.2 (2026072772) — Lock menu restructured (reclamations R1, R2)

The lock UI now matches what was ordered. The top-right lock-mode button no
longer switches the whole element menu to a lock menu; it only arms enforcement
(as of 0.8.1). Locking an element is a menu action instead:

- **Every element menu carries a lock button.** Nodes, relations and containers
  now show a lock button in their normal control dock. It opens a lock submenu
  of per-group toggles (move, colour, text — relations omit colour, having no
  fill), rather than the top-right button replacing the menu.
- **Lock toggles read as "no-parking" signs.** Each toggle shows the same icon
  as the function it locks, struck through with a diagonal slash, and lights up
  in the active state when that group is locked. The lock button itself shows a
  closed padlock when any group is locked, an open one otherwise.
- **Shared, consistent implementation.** The submenu is a single `LockSubmenu`
  component reused by the node/container dock (`NodeFormatToolbar`) and the new
  extracted `RelationMenu`, so the three element kinds behave identically. The
  previously inline relation dock in `CanvasView` is now that component.
- The top-right button's label now reads "Enforce locks (preview as learner)"
  to reflect its actual role. New Jest coverage: the lock button shows only when
  the user may lock, the submenu opens on click with struck-through toggles,
  toggling a group calls back with that group, and the relation submenu offers
  only move/text.

## 0.8.1 (2026072771) — Beta prep: lock enforcement made real + form-subplugin privacy

Starts the 0.8.x line. Closes the two most urgent beta reclamations around the
element-lock feature and a privacy-compliance gap found under a real Moodle run.

Lock enforcement (reclamations R3, R4, R5):
- **Locks are now enforced on the layout channel.** Node positions and sizes are
  saved through `save_layout`, which previously performed no lock checks — so a
  move-locked node could be freely repositioned or resized by anyone. The layout
  service now pins move-locked nodes to their stored geometry (in both merge and
  replace mode); a freshly created locked node can still be placed once. Server
  tests cover merge, replace, first placement and the bypass case.
- **The lock-mode toggle is a real enforcement switch.** `apply_operation` and
  `save_layout` take an `enforcelocks` flag mirroring the top-right lock-mode
  button. Server bypass is now `manageprofiles && !enforcelocks`, so a teacher
  can preview and run the activity bound by the same locks a learner sees. The
  flag only ever *tightens* enforcement — a non-manager can never loosen a lock.
- **Client gating matches the server.** Node drag/resize and container
  move/resize refuse to start on a move-locked element while enforcement is
  active for the current user (`!canManage || lockMode`). Re-arrange pins
  move-locked nodes and nodes inside move-locked containers under the same rule.
- **List view (R5).** Locked controls are shown *disabled* rather than hidden,
  with a lock badge and tooltip, so an editor can see the row is locked: a
  text-locked relation cannot be renamed, a move-locked one cannot be retargeted
  or reversed, and a fully locked one cannot be deleted — unless the user is a
  manager authoring with lock-mode off.

Privacy (blocker found under a real run):
- **The five `vimipadform_*` display-type subplugins now ship a privacy
  provider.** They store no data of their own, so each declares a
  `null_provider` with a `privacy:metadata` reason (EN/DE). Previously the core
  `provider_compliance` test failed for all five; it now passes.

## 0.7.31 (2026072770) — 0.7.x hardening close-out (final audit follow-ups)

The final 0.7.x audit gives GO for controlled beta/pilot. This release folds in
two small, non-blocking consistency follow-ups from that audit; the remaining
items are tracked for 0.8.x.

- **Layout schema is enforced at the service boundary.** `layout_policy`
  validation now runs inside `layout_service::save()` rather than only in the
  external endpoint, so the import path (which calls the service directly) is
  validated too. The redundant endpoint check is removed (single source of
  truth). A service-level test asserts an invalid payload is rejected there.
- **security_review.md conclusion corrected.** The summary no longer lists the
  layout schema and AI output limits as open — they are implemented and were
  already documented as such earlier in the same file; the wording is now
  consistent and reflects the 0.7.30 state.
- Backlog updated to 0.7.30 state: the code-side 0.7.x hardening arc is closed;
  remaining beta prerequisites are the empirical ones (concurrency/load tests,
  replay benchmarks, accessibility, browser/DB and upgrade/backup-restore CI).
  The heartbeat `renew()` full-CAS hardening, optional get_operations coverage
  expansion, and the guest-access product decision are recorded as 0.8.x items.

## 0.7.30 (2026072769) — Partial-history correctness, bounded checkpoints, AI & layout limits

Addresses the 0.7.29 audit. Fixes a P0 where a protection-truncated revision
history could be presented as complete, bounds the checkpoint model, and closes
the remaining AI/layout resource limits and subplugin-versioning items.

Revision player (P0):
- **A truncated history is no longer shown as complete.** When op-log loading
  stops at a protection limit, the player tracks `highestLoadedRevision`, builds
  the replay engine only up to it, clamps the slider to it, and shows an explicit
  truncation warning instead of implicitly treating unloaded revisions as empty
  up to the workspace's real maxRevision.
- **Completeness is checked by content fingerprint.** `isHistoryIncomplete` now
  compares a per-element fingerprint over rendering fields (type, label, content,
  endpoints, direction, geometry, metadata), not just stable-id sets, so a
  missing update/retarget on existing elements (same ids, different content) is
  detected. Tests cover truncation clamping, the warning, and content mismatch.

Replay scaling (P1):
- **Checkpoint count is bounded.** `ReplayEngine` now takes a `maxCheckpoints`
  budget and spaces checkpoints `ceil(maxRevision / budget)` apart, so retained
  checkpoints do not grow with history length. A test asserts a 2000-revision
  history keeps ≤ budget+2 checkpoints while staying correct.
- **No op-log rescan in stateAt.** Each checkpoint stores its op-array start
  index and `nearestCheckpoint` uses binary search, so reconstructing a frame
  starts at the checkpoint without a linear scan of the log.

Concurrency (P2 from the audit reply):
- **Owner-renewal is compare-and-swap too.** An owner extending its own lease now
  updates conditionally on the row still matching; an owner whose lease had
  expired is routed through the takeover CAS path, so it cannot overwrite a
  concurrent taker. Covered by a dedicated test.

Resource limits:
- **AI limits** in `ai_feedback_service`: teacher notes and the overall prompt
  are capped before the provider call; the generated draft and `providerinfo`
  (to the char(255) column) and stored prompt context are bounded before
  storage — no silent DB truncation. New `MAX_AI_*` limits.
- **Central `layout_policy`** enforces the layout/viewport schema server-side:
  object root, only known top-level fields, finite in-range coordinates,
  positive sizes, and a bounded object count — rejecting payloads like `42`,
  `true`, or `{"pos":{"n":{"x":"abc"}}}` that passed the JSON+byte checks.

Subplugins:
- **Concrete parent versions.** All assess (scorer) subplugins now depend on the
  frozen assess API version instead of `ANY_VERSION`; the form and assess
  subplugins that changed get a version bump so the Moodle upgrade contract is
  clean.

## 0.7.29 (2026072768) — Revision-player correctness & scaling, plus P1 follow-ups

Addresses the 0.7.28 audit: the new revision player introduced a historical
correctness bug and shifted scaling load unbounded onto the client. This release
fixes both and closes the remaining P1 points.

Revision player:
- **Immutable historical frames (P0 fix).** Frame elements are now copied out of
  the mutable accumulator, so a later node/relation/container update or a
  relation retarget can no longer retroactively change an earlier frame. A
  shallow copy is complete because element fields are primitives. Regression
  tests assert frame immutability for updates, retargets and container changes,
  and full deep-equality of `buildFrames(rev)` and `reconstructAt(rev)` — not
  just element counts.
- **Paginated `get_operations`.** The endpoint now takes `fromrevision` and
  `limit`, caps the batch server-side (`MAX_BATCH = 500`), and returns `hasmore`
  and `nextrevision`, so no single request pulls an unbounded op-log. A
  dedicated PHP contract/IDOR test covers own/foreign workspace, revision
  clamping, paging through all operations, and the cap.
- **Bounded, checkpoint-based replay.** A new `ReplayEngine` keeps periodic
  checkpoints plus a small LRU frame cache and reconstructs frames from the
  nearest checkpoint forward, instead of retaining one full frame per revision
  (which was O(N^2) in memory). The player pages the op-log in and reconstructs
  on demand.
- **Stronger history-completeness detection.** `isHistoryIncomplete` now compares
  the sorted stable-id sets, not just element counts, so a same-count-but-
  different-elements mismatch is detected.

Resource limits, concurrency and CSRF:
- **Byte-accurate payload limits.** New `limits::check_bytes` counts bytes (not
  characters); layout and viewport payloads are checked against
  `MAX_LAYOUT_BYTES` in bytes, so a multibyte payload cannot exceed the nominal
  cap.
- **Compare-and-swap lock takeover.** Taking over an expired lease now uses a
  conditional update on the prior holder + expiry, so two concurrent callers
  cannot both believe they acquired the lease. Covered by a concurrency test.
- **POST-only state changes.** `runai` and `allocatereviews` moved from GET links
  to POST forms with `sesskey` and a request-method check, so they cannot be
  triggered by prefetch, history navigation or re-opening the URL.

Correctness and packaging:
- **Snapshot-based submission metrics.** The grading overview computes node and
  relation counts from each submission's frozen snapshot JSON, not the live
  tables, so a reopened-and-edited workspace no longer misreports the graded
  submission's size.
- **Separately installable scorers.** An assess (scorer) subplugin uninstall now
  has a safety test: a missing scorer degrades to no automatic score rather than
  a fatal, and the uninstall semantics are documented on both plugininfo classes.
- `package-lock.json` regenerated so `jsdom` is a devDependency in the root
  entry, matching `package.json` (so `npm ci --omit=dev` excludes it).
- Documentation refreshed: `security_review.md` rewritten for 0.7.29, backlog
  and README updated, remaining duplicate changelog headings disambiguated.

## 0.7.28 (2026072767) — Pre-Beta hardening (security, performance, subplugin contract)

Addresses the findings of the 0.7.27 security / coding-standard / productivity
audit. No functional feature changes; hardening, performance and consistency.

Security & integrity:
- **Journal entries now require `mod/vimipad:comment`** (P0). `add_journal_entry`
  previously enforced only edit access; a role with editown but without comment
  could post entries. Covered by a role-matrix test.
- **Free-text length limits enforced** at five service boundaries (journal,
  peer-review comment, grade feedback, accepted AI feedback, annotation) using
  the multibyte-safe `limits::check_text` with `MAX_TEXT`.
- **Layout, viewport and revision validation.** `save_layout` now bounds the
  viewport payload and rejects unknown modes; `get_revision_state` enforces a
  valid revision range, and `api\map::state_at` rejects negative revisions.

Performance (N+1 and unbounded queries):
- **Peer-review allocation** loads existing pairs once instead of a
  `record_exists` per candidate (was O(submissions x reviewers) queries).
- **Privacy export** loads nodes/relations/journal for all of a user's
  workspaces in three IN-queries instead of three per workspace (3N+1).
- **Grading** loads existing grades for the whole cohort once and commits the
  plugin-table writes in a transaction.
- **Owner labels** in the overview and report pre-load group names instead of
  an uncached `groups_get_group_name` per row.
- **Expired lock cleanup** moved from a probabilistic branch in the poll request
  to a deterministic `purge_expired_locks` scheduled task (every 15 minutes).
- **Revision replay reduced from N server reconstructions to one op-log fetch.**
  A new `get_operations` endpoint returns the op-log; the player reconstructs
  frames on the client instead of one full server reconstruction per revision
  across N web-service calls. (0.7.29 further bounds client memory with
  checkpoints and a bounded frame cache; see below.)

Subplugin contract (separately installable/uninstallable):
- All five `vimipadform_*` subplugins now declare a concrete parent dependency
  (`mod_vimipad => 2026072766`, the release that froze the public profile API).
- **Uninstalling a form subplugin is safe:** it has no tables, the editor
  degrades to the fallback form definition, the built-in profiles stay offered,
  and a profile reaching a non-form path (backup/restore, web service) is
  normalised to the safe default. Covered by a dedicated safety test.

Release & documentation consistency:
- `$plugin->supported` corrected to `[405, 502]` (Moodle 5.3 is not yet
  released); package.json / package-lock.json / README / roadmap aligned to
  0.7.28; duplicate 0.7.20 and 0.6.12 changelog sections removed; `drag_arm.ts`
  module docblock completed; `amd/readme_moodle.txt` added for the bundled
  third-party libraries. The local `make check` target now includes `lint-js`.

## 0.7.27 (2026072766) — Stable public API for dependent plugins

- **Context-free profile validation** (`\mod_vimipad\profile\profiles`): a
  new stable facade to validate a profile and resolve its form configuration
  without a Moodle activity context or a stored instance — `all()`, `exists()`,
  `form_config()`, `is_shape_allowed()`, `clamp_shape()`. This is what a
  dependent plugin (question type, database field) needs to reuse the ViMi
  profiles standalone.
- **Public map-state API** (`\mod_vimipad\api\map`): `state_at(workspaceid,
  revision)` reconstructs the surviving nodes/relations/containers from the
  operation log through a stable entrypoint, without exposing the internal
  service layer. (Access control stays the caller's responsibility, as
  documented.) Joins the existing `\mod_vimipad\api\ids` facade.
- **Embeddable editor contract.** The `mount(element, config)` entrypoint (plus
  `mountRevision`/`mountPlayer`) is now a tested public contract: the host
  supplies a `callService` transport — the swappable persistence adapter
  (`ServiceTransport`) — and an optional `getString` resolver, so the editor
  mounts and renders with no Moodle service endpoint and no network fetch.
- **API documentation.** The README's API section is updated (the surface is no
  longer "not frozen"), and a new `docs/design/public-api.md` describes the full
  stable contract. Everything under `\mod_vimipad\local\*` remains internal
  with no stability guarantee.
- **Test coverage:** `api_profiles_test`, `api_map_test` (PHP) and
  `embed_mount.test.ts` (editor mounts with a caller-supplied in-memory
  transport) lock the contract in.

## 0.7.26 (2026072765) — Security review, external-function hardening, docs

- **Documented security review** (`docs/design/security_review.md`) covering the
  0.7.x hardening milestone: an access-control/IDOR matrix for all 17 external
  functions, output sanitisation (stored XSS), parameter validation, and CSRF.
  Result: no open finding. Every external function binds its `workspaceid` to
  the current activity and enforces the correct capability; the frontend uses no
  `dangerouslySetInnerHTML` (React auto-escapes), PHP output goes through
  `format_text`/`s()`, and state-changing forms carry `sesskey()`.
- **External-function hardening.** `apply_operation` and `save_layout` now route
  their access checks through the shared `helper::validate_workspace_for_edit()`
  instead of duplicating the context/workspace-binding/`require_edit` logic
  inline — same checks, one tested path. `create_snapshot` and `get_workspace`
  keep their bespoke checks by design (submit capability plus course/cm for
  completion; group/target-user resolution respectively), now noted in code.
- **Docs refreshed.** The test-environment baseline is updated to the current
  counts (264 `mod_vimipad` + 97 `vimipadassess` backend tests, 277 Jest), and
  `docs/design/backlog.md` is rewritten for the 0.7.x/0.8.x plan.

## 0.7.25 (2026072764) — Canvas menu click-through fix, journal replay fallback, and polish

- **Journal replay fallback for incomplete op-history.** The replay ("film")
  reconstructs each state from the operation log. Maps whose elements were
  created before the op-log existed (or via a legacy import path) have no create
  operations to replay, so those elements never appeared — the reported symptom
  where only containers showed. The player now detects this on mount by
  comparing the live current map (`get_workspace`) with the reconstruction at
  the final revision; when the live map has more elements, it shows the faithful
  live current state with a hint ("Earlier editing steps for this map are not
  recorded") instead of an unfaithful, partial animation, and hides the playback
  controls. Complete histories play as before. The single-revision viewer
  ("show editing state") deliberately does not fall back, since it must show the
  exact historical revision the journal entry refers to.
- **Fixed the misaligned journal buttons.** "Show editing state" and "Replay map
  development" sat at different heights because a top margin was applied to only
  one of them. They are now wrapped in a flex container that aligns both and
  spaces them consistently.

- **Fixed the recurring canvas menu click-through / z-order bug.** The floating
  element menus (node, relation, container) sit in the topmost canvas layer and
  their `foreignObject` boxes are deliberately larger than the visible toolbar.
  The container menu wrapper carried an inline `pointer-events: auto` override
  that made the whole box capture clicks, stealing them from the graph beneath.
  All three menus now render through one shared `MenuOverlay` component that
  enforces the invariant in a single place: the `foreignObject` and its
  `.vimipad-node-dock-fo` wrapper are `pointer-events: none` (click-through) and
  only the inner `.vimipad-node-dock` toolbar is `pointer-events: auto`. A
  pointer-down on the toolbar is stopped from reaching the canvas so choosing a
  menu button never pans or deselects.
- **Continuous test coverage** so the bug cannot silently return: a behavioural
  suite (`menu_overlay.test.ts`) renders the overlay and asserts the
  click-through attributes and that a menu pointer-down does not propagate to
  the canvas, and a structural guard (`canvas_menu_overlay_usage.test.ts`) fails
  if `CanvasView` ever hand-writes a menu wrapper again or reintroduces an inline
  `pointer-events: auto`.
- **Fixed a phpcs violation** in `grading_service.php` (multi-line `get_record`
  call formatting) reported by the project CI.

## 0.7.24 (2026072762) — Assessed map in the feedback tab, data export moved to Tools

Two workflow refinements: learners can now see the exact map that was graded
alongside their feedback, and all data import/export lives together in the
Tools tab.

- **Assessed map in the feedback tab.** When a submission has been graded, the
  learner's Feedback tab now offers a "View the assessed map" button that opens
  the exact graded snapshot in the read-only revision viewer — the same viewer
  the Journal tab uses, so no new viewer code. The snapshot is the stable
  assessment target, so later edits to the working map do not change what is
  shown. `grading_service::get_feedback_for_user()` now also resolves the
  snapshot's revision and workspace; a new `feedback_panel::needs_viewer()`
  lets `view.php` initialise the viewer JS only when the map is embedded.
- **Data export moved to the Tools tab.** JSON and XML export have been removed
  from the canvas export menu, which now offers only the graphical exports
  (SVG, PNG, PDF). The Tools tab gains a "Export data" section with the JSON and
  XML downloads, directly above the existing import — so data import and export
  are found together in one place.
- New strings `editor:exportdataheading` / `editor:exportjson` /
  `editor:exportxml` / `editor:exportdatahint` and `feedback:mapheading` /
  `feedback:showmap` (English and German).

## 0.7.23 (2026072761) — Differentiated element locking (move / colour / text)

Locks are no longer all-or-nothing. A teacher can now lock an element's
**movement, size and shape**, its **colour**, and its **text** independently,
and every part of the editor respects those groups so a locked action is never
offered and then silently discarded.

- **Semantic lock groups.** Lock state is stored as
  `{"locked": true, "locks": {"move": …, "color": …, "text": …}}`. The three
  groups cut across the database fields (shape, fill and text styling all live in
  the metadata JSON), so the lock is defined semantically, not per field. A new
  `\mod_vimipad\local\lock\element_lock` class is the single source of truth for
  which change belongs to which group; the frontend mirrors it in
  `element_lock.ts`. A legacy `{"locked": true}` with no `locks` map keeps its
  old meaning: everything is locked.
- **Server enforcement by group.** `operation_service` rejects an update only
  when it would change a field or metadata key of a *locked* group, and blocks
  deletion whenever any group is locked. Relation retargeting
  (`newsource`/`newtarget`) counts as movement and is protected accordingly — it
  previously slipped through.
- **Teacher lock menu.** In lock mode the element toolbar shows three
  independent toggles (move / colour / text) for nodes and containers, and the
  two applicable ones (move / text) for relations.
- **Locked actions are hidden, not discarded.** Outside lock mode the toolbar
  only offers a control when its group is unlocked: the shape button, colour
  field, text button and — for relations — the direction buttons disappear when
  their group is locked.
- **Re-arrange respects locks.** A move-locked node keeps its position; a node
  inside a move-locked container is pinned so it cannot be pushed out of the
  container's bounds; a move-locked container is neither moved nor resized.
- **List view cannot bypass locks.** Retargeting (selects and drag), reversing,
  renaming and deleting are each hidden in the relation table when the matching
  group is locked.
- New strings `editor:lockgroup_move` / `editor:lockgroup_color` /
  `editor:lockgroup_text` (English and German).

## 0.7.22 (2026072760) — CI lint fix and Tools tab moved to the far right

- **Fix the CI AMD lint failure.** `amd/src/init.js` used a nested ternary to
  pick the initial view (added in 0.7.14 for the tools view), which trips
  eslint's `no-nested-ternary`. The CI runs `grunt amd` with
  `--max-lint-warnings=0`, so that one warning aborted the build. The view is
  now resolved with an explicit lookup (`knownViews.indexOf(...)`), no nested
  ternary. Verified with the exact CI command
  (`grunt amd --files=…init.js,…revision.js --max-lint-warnings=0`) exiting 0.
- **Tools tab moved to the far right.** The `tools` tab now comes after
  feedback, peer and grade in the tab bar (it was fourth, right after journal).
  The default/auto-open tab stays canvas, so nothing routes to tools by
  accident.
- Verified: 254 backend tests green, 250 Jest tests green, tsc clean, esbuild
  bundle rebuilt, `init.min.js` reproducible through Grunt, phpcs clean.

## 0.7.21 (2026072759) — list view: full-width table, matched heading, merged double-arrow actions

- **Relation table spans the full page width.** The table previously used
  `display: block` (for mobile horizontal scrolling), which shrank it to its
  content width. Scrolling now lives on the `vimipad-listview` container, so the
  table itself is a real full-width table.
- **"Concepts and relations" heading matched to the others.** It was an `h5`;
  it now uses the same `h6` sizing/weight as the "Add concept" / "Add relation"
  legends.
- **Double-arrow rows render their buttons once.** For a double-arrow relation
  (shown as two connected rows, A→B and B→A), the action buttons
  (edit/reverse/delete) were rendered on both rows. They now span both rows with
  `rowSpan=2` and render once — the same treatment the shared relation-label
  cell already had. A single-direction relation is unaffected.
- New Jest suite `relation_list_view` (a single-direction relation renders one
  action cell; a double-arrow relation renders two rows but one action cell
  spanning both, plus the label cell). Verified: 254 backend tests green, 250
  Jest tests green, tsc clean, esbuild bundle rebuilt, `init.min.js`
  reproducible through Grunt, phpcs clean.

## 0.7.20 (2026072758) — test hygiene: remove duplicated test setup

Removes the three clones reported by phpcpd (`--min-lines 5 --min-tokens 70`),
all in test files, without changing any test's behaviour.

- **Shared workspace fixture.** The identical `setUp()` (course + module + empty
  workspace) in `element_lock_test`, `membership_integrity_test` and
  `reconstruction_roundtrip_test`, plus the shared `op()` helper, moved into a
  new `mod_vimipad\workspace_fixture` trait in `tests/fixtures/`. Each suite now
  calls `set_up_workspace()` from its `setUp()`. The trait file is loaded with an
  explicit `require_once` (it is not autoloaded) and is not itself a test class.
- **Internal clone removed.** In `membership_integrity_test`, the two tests that
  built the same two-nodes-plus-relation-plus-container scenario
  (`test_node_delete_purges_memberships` /
  `test_relation_delete_purges_memberships`) now share a
  `seed_nodes_relation_container()` helper.
- phpcpd now reports no clones. Verified: 254 backend tests green (unchanged —
  no test lost: element_lock 5, membership_integrity 6, reconstruction_roundtrip
  1), each test file green individually, phpcs / phpdoc / validate clean. No
  functional change, no frontend change.

## 0.7.19 (2026072757) — revision player: replay map development as a film

Adds the animated replay requested in point 4. The single-revision viewer
already existed (journal entries with a `revisionref` offer a "show editing
state" button that mounts the read-only `RevisionViewer`); this adds the
step-by-step film alongside it.

- **New `RevisionPlayer` component.** Steps through the revisions from 1 up to a
  target revision, reconstructing and rendering each state on the read-only
  canvas via the existing `get_revision_state` service. Play/pause button, a
  scrubber to jump to any step, a "revision N / total" counter, and an
  in-memory cache so scrubbing back and forth (and the animation itself) does
  not re-hit the service for a revision already seen. Restarts from the
  beginning when play is pressed at the end.
- **Wired into the journal tab.** Each journal entry that references a revision
  now offers a "Replay map development" button next to "show editing state";
  the `mod_vimipad/revision` AMD module mounts the player (new `mountPlayer`
  entry point on the bundled editor) into the same host element. No backend
  change — the player reuses the reconstruction service.
- New strings `revision:play` / `revision:pause` / `revision:playtitle` /
  `revision:scrubber` (EN/DE).
- **Test hardening:** the `amd_string_keys` test now also checks
  `amd/src/revision.js` (not just `init.js`), so a string requested by the
  revision/player bootstrap but missing from `lang/en` is caught.
- New Jest suite `revision_player` (loads the target revision on mount, the
  scrubber jumps to a chosen revision, play advances over time and stops at the
  end, using fake timers). Verified: 254 backend tests green, 248 Jest tests
  green, tsc clean, both `init.min.js` and `revision.min.js` reproducible
  through Grunt, phpcs / validate / savepoints clean.

## 0.7.18 (2026072756) — learner feedback visibility (beta blocker)

Closes the beta blocker from earlier analysis: participants had no reliable
in-plugin view of their grading feedback (the grade reached the gradebook, but
the feedback text was only visible to teachers behind `mod/vimipad:grade`).

- **New learner-facing Feedback tab.** A `feedback` tab appears for a learner
  once their own submission is graded (`get_feedback_for_user` returns a graded
  row), and is hidden otherwise. It shows the grade (as `x / max`), the written
  feedback text (rendered through `format_text` in the activity context) and
  the grading date. Before grading, the tab is absent; the panel itself shows a
  neutral "not graded yet" notice if reached directly.
- **Service support.** `grading_service::get_feedback_for_user()` returns a
  learner's grade, feedback text, graded snapshot id and grading date from the
  `vimipad_grade` source-of-truth table (per-member in group mode). No schema
  change — the fields already existed; this only surfaces them to the learner.
- The annotated-map view for the graded snapshot is intentionally deferred to
  the revision-viewer work (it reuses that renderer via the stored
  `snapshotid`).
- New strings `feedback:none` / `feedback:gradeheading` / `feedback:textheading`
  / `feedback:dategraded` (EN/DE); the previously unused `tab:feedback` string
  is now in use.
- New PHPUnit suite `feedback_visibility_test` (no feedback before grading, full
  feedback after grading, and the panel rendering both states). Verified: 254
  backend tests green, each test file individually, phpcs / phpdoc / validate /
  savepoints clean.

## 0.7.17 (2026072755) — block G: list view restructured

Restructures the list view from manual UI feedback.

- **Journal moved to the bottom of the list view.** In the list view the
  journal input (with its heading) now sits at the very end of the page, after
  the relation table, instead of between the add controls and the table. (The
  canvas view keeps the journal directly under the add controls.)
- **Heading over the tabular list.** A "Concepts and relations" heading
  (`editor:conceptsandrelations`) now sits above the list.
- **Concepts presented in a box.** The concept (node) chips are wrapped in a
  labelled full-width box above the relation table, instead of a bare inline
  list.
- **Full width.** The concept box and the relation table both span the full
  page width.
- New strings `editor:concepts` / `editor:conceptsandrelations` (EN/DE).
- Verified: 245 Jest tests green, tsc clean, esbuild bundle rebuilt,
  `init.min.js` reproducible through Grunt, phpcs clean, new strings resolve.

## 0.7.16 (2026072754) — block G: journal save button matches the add buttons

- The journal "Save entry" button dropped its `btn-sm` class, so it is now the
  same size as the concept/relation "Add" buttons, matching the two-column
  control layout. Frontend only: 245 Jest tests green, tsc clean, bundle
  rebuilt, `init.min.js` reproducible through Grunt.

## 0.7.15 (2026072753) — block G: journal layout corrected to the two-column design

Corrects the journal layout from 0.7.13, which did not match the requested
design.

- **Header spacing fixed.** The title and the revision reference were rendered
  inside a `<legend>` used as a flex container, which browsers do not lay out
  reliably — so the two ran together ("Learning journal*Revision: 149*"). The
  header is now a normal wrapping row (`vimipad-journal-head`) with the title on
  the left and the muted italic reference on the right, with a real gap.
- **Two-column body.** The textarea and the save button now sit side by side
  (textarea on the left, the save button — and the optional private-note
  checkbox — in a narrow column on the right) via a CSS grid, matching the
  concept/relation add-menu layout, instead of the button dropping full-width
  below the textarea. The columns collapse to a single column on narrow screens.
- The `<legend>` is replaced by a heading span with an `aria-label` on the
  fieldset, so removing the legend does not cost the accessible name.
- Verified: 245 Jest tests green, tsc clean, esbuild bundle rebuilt,
  `init.min.js` reproducible through Grunt, phpcs clean.

## 0.7.15b (2026072753) — block G: keep the add-concept/relation controls on one line

- **Fix: the add-concept and add-relation controls are two-line again** —
  heading on the first line, all controls (fields and the Add button) on a
  single row below it — instead of each field stacking onto its own line. The
  control line no longer wraps (`flex-flow: row nowrap`) and the fields shrink
  to share the row (`flex: 1 1 0; min-width: 0`) rather than forcing a wrap when
  the column is narrow; the Add button keeps its intrinsic width. On very narrow
  (phone) viewports the fields are allowed to wrap again so they stay tappable.
- CSS-only change; no bundle rebuild required.

## 0.7.14 (2026072752) — block G: import moved into a Tools tab

- **Import now lives in its own "Tools" tab** instead of a strip permanently
  shown beneath the editor. The server-side `tools` tab is re-enabled (edit
  access only) and loads the editor bundle with `data-view="tools"`; the editor
  renders only the tools section there (no canvas/list), keeping the group and
  user context so an import targets the correct map. The import control gains a
  heading and a short hint. This is the home for the bulk-export functions
  planned next. New strings `editor:importheading` / `editor:importhint`
  (EN/DE); the previously unused `tab:tools` string is now in use.
- Verified: 251 backend tests green, 245 Jest tests green, tsc clean, esbuild
  bundle rebuilt, `init.min.js` reproducible through Grunt, phpcs / phpdoc /
  validate clean, all tab labels and import strings resolve.

## 0.7.13 (2026072751) — block G: journal layout and re-arrange chain propagation

- **Journal input restored to the two-column control layout.** The journal
  input area now lines up in the same grid as the concept/relation add menus —
  concept and relation side by side, journal below with its textarea and save
  button — instead of appearing as a detached block. The journal legend shows
  the current map revision as a muted italic reference number on the right, and
  the redundant separate revision line beneath the editor is removed.
- **Re-arrange size propagation confirmed child-to-parent.** Re-arrange already
  processes containers deepest-child first and feeds each parent its children's
  already-refitted boxes, so a node that enlarges the innermost container
  propagates the growth outward through every ancestor to the root. Added a
  three-level propagation test (`a node enlarging the innermost container
  propagates out to the root`) that asserts each ancestor encloses its refitted
  child after a node forces the inner box to grow.
- Verified: 251 backend tests green, 245 Jest tests green, tsc clean, esbuild
  bundle rebuilt, `init.min.js` reproducible through Grunt, phpcs clean.

## 0.7.12 (2026072750) — block G: learning-journal rework and stable nested-container re-arrange

Reworks the learning journal from manual UI feedback (point 3 of a batch).

- **Journal is now an open input area**, placed directly under the
  concept/relation controls in both the canvas and list views, instead of a
  collapsed accordion at the bottom. The in-panel playback of past entries is
  removed (entries are reviewed elsewhere); the save button uses a fountain-pen
  icon (`fa-pen-fancy`) in the style of the concept/relation add buttons and
  confirms with a saved message.
- **Teachers can read the journal by default.** New activity setting
  `journalallowprivate` (default off): when off, every entry is visible to
  teachers; when on, learners may mark individual entries as a private note
  ("not visible to teachers"). The checkbox meaning is inverted accordingly
  (from "visible to teacher" to "private note"), and the private option only
  appears when the activity allows it. The gate is enforced server-side in
  `journal_service::add_entry()` — a client cannot hide an entry when the
  activity forbids it.
- **Visibility encoding made self-safe.** `journalentry.visibility` is now
  `0 = teacher-visible (default), 1 = private`, so the safe state is the column
  default; the service works through the `VISIBILITY_*` constants throughout, so
  graders (`get_teacher_visible`) and privacy export are unaffected.
- Backup/restore carries the new `journalallowprivate` setting (added to the
  activity backup element and covered by the real backup/restore test). The
  upgrade adds the field with a `field_exists` guard; a real 0.6.24 → 0.7.12
  upgrade on a fresh site was run to confirm the field is created (default 0,
  not null) and the journal visibility default is correct.
- **Nested-container re-arrange made stable (no runaway growth, no hierarchy
  flipping).** Re-arrange now builds an acyclic nesting forest from the current
  container boxes — a container is a child of another only if the other is
  *strictly larger* by area and encloses its centre — and refits inner
  containers before outer ones, each around its member nodes plus its direct
  children's already-refitted boxes. The previous centre-only rule treated two
  overlapping same-size containers as mutual children, which made the relation
  cyclic and caused both containers to inflate and swap inner/outer on each
  pass. New pure helpers `nestingParents` / `nestingOrder` / `boxArea` with
  tests for the tightest-encloser choice, the no-cycle case for equal boxes,
  the deepest-first ordering, and convergence of repeated refits.
- Verified: 251 backend tests green (journal service test rewritten for the new
  signature and the private-requires-allowprivate contract; backup/restore test
  extended and confirms `journalallowprivate` round-trips), 244 Jest tests green,
  tsc clean, esbuild bundle rebuilt (old playback markup gone), `init.min.js`
  reproducible through Grunt, phpcs / phpdoc / savepoints / validate clean.

## 0.7.11 (2026072749) — block G: nested-container re-arrange and container locking

- **Re-arrange keeps the container hierarchy.** Re-arrange refits each container
  around its members; previously it only counted member *nodes*, so an outer
  container holding an inner container (and the inner one's nodes) collapsed
  onto the inner box, losing the visual nesting. A nested container whose centre
  lies inside another now counts as a member of the outer one, and the outer
  container's refit includes the inner container's box — so the outer keeps
  enclosing the inner. (Containers are refitted, not moved, so a single pass
  preserves ordinary one- and two-level nesting.) New geometry tests cover
  `centerInBox` and the nested refit invariant (the outer box fully contains the
  child box).
- **Containers can be locked like nodes and relations.** The container format
  menu now shows the lock toggle (for users who may lock: authors, or everyone
  under cooperative lock mode). The server-side protection was already in place
  — `container_update` and `container_delete` run through
  `assert_element_editable` — and is now reachable from the UI: a locked
  container hides its move/resize/rename handles and its geometry/style edits
  and deletion are rejected with `error:elementlocked`, while an author
  (bypass) can still change it. New backend test asserts the locked-container
  contract for both the ordinary editor and the author.
- Frontend verified: tsc clean, 240 Jest tests green, esbuild bundle rebuilt,
  `init.min.js` reproducible through Grunt. Backend: 251 tests green.

## 0.7.10 (2026072748) — block G: remove the redundant container × button

- The small × delete button in the container title bar is removed: deleting a
  container is done from the trash-can button in the container format menu
  (reachable now that the menu sits in the top overlay, 0.7.9), so the title-bar
  × was redundant. Cleaned up the now-unused `vimipad-container-delete` style
  reference in the SVG export filter, the `editor:containerdelete` language
  string (EN/DE) and its entry in the editor string-load list. The SVG export
  chrome-stripping test now asserts against a still-present overlay class
  (`vimipad-container-resize`) so it keeps testing the export filter.
- Frontend verified: tsc clean, 238 Jest tests green, esbuild bundle rebuilt
  (the delete-button markup is gone from the bundle), `init.min.js`
  reproducible through Grunt.

## 0.7.9 (2026072747) — block G: container menu z-order and interactivity

Three UI defects reported from manual testing, all traced to a single root
cause: the container format menu was rendered inside the bottom container
layer, so any node overlapping the container drew on top of it.

- **Container menu moved to the top overlay (Layer 4).** The SVG canvas draws
  in DOM order (container → connectors → labels → nodes → menu overlay); the
  selected container's format toolbar is now rendered in that top overlay
  alongside the node and relation menus, instead of in the container layer
  beneath every node. A node overlapping the container can no longer occlude
  its menu — matching the intended stacking order.
- **Cursor / click-through fixed as a consequence.** Because the menu is no
  longer behind the node surface, pointer events reach the toolbar buttons
  (which already carry `cursor: pointer`) instead of the node underneath (which
  shows the grab cursor), so the buttons are recognised as buttons.
- **Colour and shape changes now take effect.** Same root cause: the clicks
  previously landed on the occluding node, not on the menu. The wiring itself
  was already correct (`onUpdateContainerStyle` → `container_update` with
  `metadatajson` → reducer/back end), and is now reachable. Added a Jest test
  asserting a container style (`metadatajson`) change is forwarded as an
  `updateContainer` action, closing the coverage gap for the colour/shape path.
- Frontend verified: tsc clean, 238 Jest tests green, esbuild bundle rebuilt
  with the license banner, `init.min.js` reproducible through Grunt.

## 0.7.8 (2026072746) — block G: complete the group-mode quadrant coverage

- Added an explicit test for the double-negative quadrant of the group-mode
  coupling (no group mode + non-group map), for both the individual and course
  map modes: this combination is already consistent and must be a strict no-op
  (neither the map mode nor the course-module group mode is touched). The
  enforcement logic already handled it correctly; this closes the test-coverage
  gap so all four quadrants — the two inconsistent ones (repaired) and the two
  consistent ones (untouched) — are asserted on the server side, matching the
  form rule which already covered all four.

## 0.7.7 (2026072745) — hardening block G: UI correctness

First fix from manual UI testing. Block G addresses UI states that the backend
could not actually support.

- **Group map now requires (and implies) a Moodle group mode.** Previously a
  "Group map" activity could be saved with the core group mode left at "No
  groups"; learners then hit `error:nogroup` on first access because no group
  could be resolved — a UI state with no working backend path. The coupling is
  now enforced bidirectionally:
  - **Form validation** blocks saving an inconsistent combination: a group map
    without a group mode flags the group-mode field (or, when the course forces
    the group mode, the map option), and a group mode without a group map flags
    the map option. New strings `error:groupmapneedsgroupmode` /
    `error:groupmodeneedsgroupmap` (EN/DE), pure decision function
    `mod_vimipad_mod_form::group_mode_error()`.
  - **Server-side reconciliation** in `vimipad_add_instance` /
    `vimipad_update_instance` via `vimipad_enforce_group_consistency()` repairs
    rather than rejects, so instances created by backup/restore, course import
    or web services cannot end up inconsistent and a restore never aborts: a
    group map without a group mode gets separate groups; a non-group map
    carrying a group mode has it cleared, unless the course forces the group
    mode, in which case the map is promoted to a group map instead.
  - New PHPUnit suite `group_mode_consistency_test` (form rule in all
    directions incl. the forced-mode case, and the three server-side repair
    outcomes plus the no-op case).

## 0.7.6 (2026072744) — hardening block F: limits, compliance, i18n

- **Template protection separated from collaboration lock mode.** Enabling
  `lockmodeforlearners` no longer bypasses teacher-authored element locks:
  template locks are only bypassed by `manageprofiles` holders. The
  cooperative lock mode remains a pure editing-lease feature. (The previous
  coupling let learners edit locked template elements whenever the activity
  used lock mode.)
- **Resource limits.** New `\mod_vimipad\local\policy\limits` enforced on every
  mutating operation (imports funnel through the same path): 1000 nodes /
  2000 relations / 200 containers per map, label ≤ 255 (the column size),
  content ≤ 50k, metadata JSON ≤ 20k, layout blob ≤ 1 MB, import documents
  ≤ 5 MB, and container geometry must be finite, positively sized and within
  the canvas envelope (protecting rendering, export and the spatial membership
  derivation). New `limits_test` covers the policy and the service enforcement.
- **XML import hardening.** Size cap before parsing and `LIBXML_NONET` on
  `simplexml_load_string()` (defence in depth at the import trust boundary).
- **No embedded fallback language layer.** The 80+ hard-coded English
  `FALLBACK_STRINGS` are removed from the React bundle; a missing Moodle
  language string now surfaces as its raw key, so gaps show up in testing
  instead of silently rendering mixed-language UI. The font choices
  (Sans/Serif/Mono) are localised (`editor:fmt_font*`) and loaded through
  `core/str` like every other editor string.
- **German language files for all five `vimipadform` subplugins** (previously
  EN-only), plus the missing `subplugintype_vimipadassess(_plural)` strings;
  the dead `editorplaceholder`/`editorpreview` strings are removed.
- **Placeholder tabs removed.** The Feedback and Tools tabs (which rendered a
  "coming soon" notice) are removed from the navigation together with the
  `tab:comingsoon` string; unknown tab URLs render nothing instead of a stub.
- **Third-party manifest completed.** `thirdpartylibs.xml` now declares React,
  ReactDOM **and Scheduler** (all MIT) as bundled components, and the esbuild
  bundle carries a preserved license banner listing them. `jsdom` moved to
  `devDependencies`.
- **Process comments cleaned.** The stale `workspace_service` docblock now
  describes the actual fail-closed behaviour; roadmap-style comments in
  `view.php`, `styles.css`, `drag_arm.ts` and `shape_catalog.ts` are replaced
  by functional invariants; `RevisionViewer.tsx` gained its `@module` docblock;
  `styles.css` and `build.mjs` carry proper license headers.
- **Docs consolidated.** The duplicate 0.6.23 changelog section is merged and
  the roadmap header reflects the 0.7.x feature-freeze state.
- Frontend verified: tsc clean, 237 Jest tests green, esbuild bundle rebuilt
  with the license banner, `init.min.js` rebuilt through Moodle's own Grunt
  and byte-identical on a second run (reproducibility criterion).

## 0.7.5 (2026072743) — hardening block E: grading lifecycle contracts

- **Points-only grading.** ViMi Pad grades in points; the activity form now
  rejects a Moodle scale selection (`error:scalesnotsupported`). The legacy
  scale branch in `lib.php` stays for any pre-existing data, but no new scale
  activity can be configured — resolving the half-implemented scale support.
- **Server-side grade domain.** `grading_service::save_grade()` validates
  `0 <= grade <= activity maximum` (and rejects any numeric grade when the
  activity is ungraded), throwing `error:gradeoutofrange`. UI limits are no
  longer the only guard; the advanced-grading path passes through the same
  check.
- **Grade recipients frozen at submission time.** The terminal submission step
  stores the resolved recipient cohort on the snapshot (`cohortjson`), and
  grading pays out to that cohort — group or course membership changes between
  submission and grading no longer shift who receives the grade. Legacy
  snapshots without a cohort fall back to resolving current membership. The
  cohort is backed up with the snapshot and user-remapped on restore.
- **Reopen lifecycle defined.** `STATUS_REOPENED` (deliberately `-1`, sorting
  below draft so every `status >= submitted` consumer excludes it without
  changes): reopening withdraws the submitted state of not-yet-graded
  snapshots, so completion-on-submit reverts until a fresh submission; a
  graded snapshot keeps its status and the awarded grade remains in the
  gradebook as history until a regrade replaces it. The whole transition runs
  under the workspace write lock.
- New PHPUnit suite `grading_lifecycle_test` covering the domain check, the
  frozen cohort under membership change, and the reopen contract.

## 0.7.4 (2026072742) — hardening block D: privacy matrix, reference decoupling, real upgrade proof

- **Privacy matrix completed.** Context discovery and userlist discovery now
  cover peer reviewers (`vimipad_peerreview.reviewerid`) and advanced-grading
  raters (`vimipad_gradeinstance.raterid`). `delete_data_for_users()` mirrors
  the single-user path exactly (own grade records, peer reviews written, rater
  links), so the deletion outcome no longer depends on which privacy pathway
  Moodle chose. The export now covers every declared personal-data field:
  node `content`, a new "Activity involvement" section (operations, submitted
  snapshots, layout edits, element locks, submit intents) and a "Grading and
  review activity" section (peer reviews, AI feedback authored, advanced
  gradings, grades given with the authored feedback text). New PHPUnit suite
  `privacy_matrix_test` (discovery, bulk/single parity, export completeness).
- **Reference solution decoupled from learner data.** New activity field
  `referencemapjson`: marking a snapshot as reference freezes its JSON on the
  activity record. Scoring reads the frozen copy
  (`assess_service::reference_submission()` / `has_reference()`, with a legacy
  snapshot-pointer fallback), the peer-review guidance and the grading-panel
  hints follow it, and workspace cleanup only clears the snapshot *pointer*.
  Contract: the model solution is course configuration; its JSON carries no
  user identifiers and deliberately survives (a) course backup **without user
  info**, (b) privacy deletion of the source learner, and (c) deletion of the
  source snapshot — all three proven in `reference_decoupling_test`. The
  upgrade migrates existing references by copying the marked snapshot's JSON.
- **Real upgrade path proven (0.6.24 → 0.7.4).** A fresh site install of the
  0.6.24 release was seeded with legacy fixtures (duplicate membership rows, a
  layout row with a plain timestamp, a marked reference snapshot) and upgraded
  via CLI: membership rows deduplicated to the oldest, the unique index active
  (a duplicate insert is rejected), `layoutrevision` seeded from
  `timemodified`, `referencemapjson` migrated — and the upgraded schema is
  **column- and index-identical** to a fresh install of the same version.

## 0.7.3 (2026072741) — hardening block C: correctness contracts

- **`relation_retarget` reconstruction fixed.** The historical reconstruction
  read `sourceid`/`targetid` while the operation payload carries
  `newsource`/`newtarget`, so revision views could show a retargeted relation
  with its old endpoints. New `reconstruction_roundtrip_test` proves for
  **every operation type** that applying operations and reconstructing at the
  final revision yields the identical normalized state. Memberships are
  intentionally not reconstructed: membership truth is spatial (0.7.2) and
  layout is not versioned, so membership exists only at materialization points.
- **Layout save is fail-closed with a monotonic revision.** When the
  per-workspace/profile serialization lock cannot be acquired, `save()` now
  throws `error:layoutbusy` instead of writing unserialized (lost-update risk).
  Layout change detection moves from the second-granular `timemodified` to a
  strictly monotonic `layoutrevision` (new field, upgrade seeds it from the
  existing timestamp so in-flight client tokens stay comparable; each save
  takes `max(revision + 1, now)`), so two saves within the same second can no
  longer be missed by a polling client. No frontend change needed: the client
  already treats the token as an opaque growing number.
- **Course-workspace read authorization fixed.** In course mode the single
  shared workspace is everyone's map: any enrolled user with `view` may read
  it (constraint/revision APIs included). Inspecting another learner's map
  still requires `grade`. Covered by a read-access matrix test.
- **Import `replace` replaces the whole map.** Previously only nodes were
  deleted, so containers, memberships and stale layout survived a "replace"
  import. Replace now deletes containers too (their memberships cascade via
  the 0.7.2 cleanup) and replaces the stored layout entirely (an import
  without layout clears it).
- **Import format version and mode are enforced.** `formatversion` > 1 (or
  malformed) is rejected with `error:importversion` for both JSON and XML;
  an unknown import mode string raises an error instead of silently acting
  as `append`.
- **Import stays idempotent against layout contention.** The semantic import
  commits as one transaction; the post-commit layout apply catches a layout
  lock timeout and logs it via `debugging()` instead of failing the request,
  because a user retry after commit would duplicate content in append mode.

## 0.7.2 (2026072740) — hardening block B: one membership truth (spatial)

**Architecture decision:** container membership truth is **spatial**. A node
belongs to a container when its centre lies inside the container's box — the
same rule the editor uses. Membership is derived server-side at the
materialization points (snapshot creation and export) from container geometry
plus the profile layout, so assessment and export always contain exactly what
the canvas shows. Previously the snapshot copied the `vimipad_membership`
table, which the editor never writes, so visually contained nodes were
invisible to the submap assessment (dual-truth P0 from the 0.6.24 review).

- New `\mod_vimipad\local\membership_resolver`: pure, unit-tested derivation
  mirroring `container_geometry.ts::centerInBox` (edges inclusive), reading
  both layout formats (versioned envelope and legacy bare position map),
  skipping non-finite positions and malformed geometry, deterministic output
  order. Only nodes participate; container nesting stays visual.
- `snapshot_service::build_normalized()` now derives memberships spatially and
  no longer consults the `vimipad_membership` table. Export inherits this via
  `build_envelope()`.
- The `vimipad_membership` table remains as a compatibility store for the
  import round-trip and the membership operations, with hardened integrity:
  - `membership_add` validates that the referenced item exists live in the
    workspace (node/relation/container) and rejects container self-membership.
  - `node_delete` purges membership rows of the node and of its soft-deleted
    attached relations; `relation_delete` purges the relation's rows;
    `container_delete` also purges rows where the container was itself a
    member of another container.
  - New **unique index** on (containerid, itemtype, itemstableid) replacing the
    non-unique (containerid, itemtype) index; the upgrade step deduplicates
    existing rows first (keeping the oldest).
- New PHPUnit suites: `membership_resolver_test` (pure derivation: rule, both
  layout formats, overlap, degenerate inputs) and `membership_integrity_test`
  (existence checks, self-membership rejection, delete cleanup in all three
  directions, and end-to-end proof that a snapshot contains the spatially
  contained node while a stale table row for an outside node does not leak in).
- Historical revisions (reconstruction) intentionally carry no membership:
  layout is not versioned, so spatial membership exists only at materialization
  points. The revision viewer does not display membership.

## 0.7.1 (2026072739) — hardening block A: capability contracts

Feature freeze: 0.7.x is the hardening line towards beta. This release closes
the capability gaps found in the 0.6.24 security audit.

- **AI scorer authorisation enforced at the service boundary.**
  `assess_service::score_ai()` now requires `mod/vimipad:useai` for the acting
  user, checks `ai_feedback_service::is_available()` (core AI present, site
  setting, activity `aienabled`) and the user's AI policy acceptance before
  building any prompt. Previously the on-demand "Run AI scorer" link in the
  grading panel bypassed all of these, so a non-editing teacher with `grade`
  but without `useai` could trigger AI calls.
- **AI scorer UI hidden when unavailable.** The grading panel no longer renders
  the AI scorer block for users without `useai` or when AI is disabled
  site-wide or on the activity.
- **`is_available()` accepts an acting user.** New optional `$userid` parameter
  (defaults to the current user) so service-side checks can evaluate the actual
  actor.
- **Submission paths in `view.php` require `mod/vimipad:submit`.** The direct
  `dosubmit` handler, all three consensus actions (start/confirm/cancel) and
  the submission UI block now require the `submit` capability in addition to
  edit access, matching the `create_snapshot` external function. Previously a
  role with edit access but without `submit` (e.g. a teacher) could submit via
  the page path.
- **`grade.php` enforces its own contract.** The legacy grading entry point now
  requires `mod/vimipad:grade` itself (defense in depth; the target tab already
  filtered).
- **`grading_panel::handle_action()` requires `mod/vimipad:grade`.** The
  mutating POST handler enforces its central precondition instead of relying on
  the calling page.
- New PHPUnit suite `ai_authorization_test` covering: teacher without `useai`
  rejected, activity AI off rejected, site AI off rejected, policy not accepted
  rejected, fully authorised call passes the gates, and `handle_action` rejects
  users without `grade`.

## 0.6.24 (2026072738) — T4: containers format and label like nodes

- Containers now carry the **same style model as nodes** — shape (rounded rect,
  rect, ellipse), fill colour and text styling (colour, bold, italic) — stored in
  their `metadatajson`. A selected container shows the same format toolbar a node
  does (shape, fill, text, delete), and its label is edited inline by
  double-clicking the title. With no style set a container keeps its default
  dashed look; once styled it renders as a shaped, filled box.
- **Four-corner resize.** Containers previously resized from a single
  bottom-right handle; they now have handles on all four corners like nodes, each
  keeping the opposite corner fixed. New pure helper `resizeBoxCorner`
  (nw/ne/sw/se, minimum-size clamped, opposite edge anchored).
- The backend already accepts `label`, `geometryjson` and `metadatajson` on
  `container_update`; this change wires the frontend to it and confirms the path.
- **Verification:** new `container_style_test` (create with shape+fill+text, then
  re-style via `container_update`, and confirm renaming does not clear the style)
  and 5 `resizeBoxCorner` tests. **211 `mod_vimipad`** (+1) + **97
  `vimipadassess`** green; phpcs + phpcpd + stylelint clean; lang 435/435; **Jest
  37 suites / 237 tests**; bundle reproducible. The visual result needs a browser.
- With this, the whole A1–A6 / T1–T6 issue set is addressed.

## 0.6.23 (2026072737) — T5: re-arrange keeps container membership; CI stylelint fix

- **T5.** "Re-arrange" recomputes node positions from the graph and previously
  ignored containers, so a node that sat inside a container could end up outside
  it. Re-arrange now snapshots which nodes are inside each container (spatially,
  by centre), computes the new layout, then refits each non-empty container to
  the bounding box of its members' new positions (plus padding). Members stay
  inside and the container follows them; empty containers are left in place. The
  node move and every container refit go into a single undo/redo entry, so one
  undo restores both the layout and the container boxes.
- New pure helpers `centerInBox` and `boundingBox` in `canvas/container_geometry.ts`
  (the latter clamps to the minimum container size); 6 unit tests cover
  membership detection, the padded fit, minimum-size clamping and the empty case.
- **CI stylelint fix.** `styles.css` used `margin-right: 0 !important` to override
  Bootstrap's `mr-2` on the add-menu fields, which trips `declaration-no-important`.
  The `mr-2` classes are dropped from the markup (the flex `gap` already spaces
  them) and the `!important` rule is removed. Verified with Moodle's stylelint
  config locally.
- **Verification:** 210 `mod_vimipad` + 97 `vimipadassess` green (T5 is
  frontend-only); phpcs + stylelint clean; **Jest 36 suites / 232 tests**;
  bundle reproducible. The visual result of the refit needs a browser.

## 0.6.22 (2026072736) — T3: offer lock mode to learners (activity setting)

- New activity setting **"Allow learners to use lock mode"**
  (`lockmodeforlearners`, default off). Teachers can always use lock mode; with
  this on, learners can toggle lock mode and lock/unlock elements too.
- **Semantics.** Off (default): only `mod/vimipad:manageprofiles` holders manage
  locks and learners are strictly bound — unchanged behaviour. On: locks become a
  cooperative tool; `apply_operation` grants the lock bypass to everyone with edit
  access in that activity, so any editor can lock or unlock. Documented in the
  setting's help text.
- Schema: `lockmodeforlearners` int(1) on the `vimipad` table (install.xml +
  upgrade step 2026072736); added to the activity backup element and covered by
  the restore roundtrip test. `get_workspace` reports the flag so the editor
  shows lock mode; the toggle and the per-element lock buttons are now gated on
  "can manage OR lock mode for learners", while container drawing stays
  author-only.
- **Verification:** new `lockmode_for_learners_test` (learner may edit a locked
  node when enabled; blocked when disabled; the flag is reported); backup roundtrip
  extended to assert the field survives. **210 `mod_vimipad`** (+3) + **97
  `vimipadassess`** green; phpcs + phpcpd clean; lang 435/435; Jest 35/224; both
  bundles rebuilt and reproducible.

## 0.6.21 (2026072735) — fix the "sticky node" drag on the second click

- **Symptom.** Click a node, wait, click again — the node starts following the
  cursor with no button held. It happened at most once, then behaved normally.
- **Root cause.** `onNodePointerDown` awaited the collaboration lock
  (`beginEdit`, a network round-trip) *before* capturing the pointer and setting
  the drag id. On the first interaction after a pause the lock isn't warm, so the
  await yields a tick; if the pointer was released in that gap, `onPointerUp` ran
  while `dragId` was still null and never cleared it. The node stayed armed, so
  the next bare `pointermove` moved it. The following `pointerup` finally cleared
  `dragId`, which is why it never recurred.
- **Fix.** The drag (and the resize handle, which had the identical bug) is now
  armed synchronously — capture and id set in the same event turn — and the lock
  is acquired in the background, cancelling the drag only if it is refused and no
  move has started yet. Added `onPointerCancel`/`onLostPointerCapture` handlers
  that always disarm and release, so an interrupted gesture can't latch either.
- **Verification.** New pure module `canvas/drag_arm.ts` models the sequence; 6
  tests pin it, including the exact race (pointer-up while the lock is in flight
  leaves nothing armed) and that a refusal never yanks a drag already in motion.
  **Jest 35 suites / 224 tests**; 207 `mod_vimipad` + 97 `vimipadassess` green;
  phpcs clean; bundle reproducible.

## 0.6.20 (2026072734) — add-menus stacked two-line

- The "Add concept" and "Add relation" menus used Bootstrap's `form-inline`,
  which laid the heading's controls out in a single row. They now stack: the
  legend on its own line, the controls on a second `vimipad-control-line` (a flex
  row that wraps on narrow widths but stays under the heading). The old `mr-2`
  spacing is replaced by a flex `gap`.
- No behaviour change, markup/CSS only. **Jest 34 suites / 218 tests**; 207
  `mod_vimipad` + 97 `vimipadassess` green; phpcs clean; bundle reproducible.

## 0.6.19 (2026072733) — connector labels follow their own parallel connector

Follow-up to 0.6.17: the lines were separated but the labels were not.

- **Root cause.** The label layer computed its anchor as the midpoint of the two
  node *centres* (`positionOf`), a separate code path that never saw the edge
  anchoring or the sibling offset introduced for parallel connections. So every
  label of a multi-relation pair piled onto the same centre line.
- **Fix.** The label layer now derives the same edge anchors and the same
  `siblingOffsets` slot as the line layer, then places the label at the curve
  peak via `labelPoint(anchors, offset)`. Each label rides its own connector; a
  lone relation still sits on the centre line.
- **Verification:** 3 new tests — siblings' labels straddle the centre line
  symmetrically, a single label stays centred, and the label offset matches the
  path's own vertical extent. **Jest 34 suites / 218 tests**; 207 `mod_vimipad` +
  97 `vimipadassess` green; phpcs clean; bundle reproducible. Visual result needs
  a browser.
- Env note: `phpunit/cli/init.php` self-updates Composer and aborts on a
  getcomposer.org 503, leaving the env on the old version; run it with
  `--no-composer-self-update`. Recorded in the environment setup doc.

## 0.6.18 (2026072732) — A6: Enter makes a line, not a column

Two faults compounded here, which is why multi-line labels never worked.

- **Enter produced columns.** The contenteditable element was styled with
  `labelBox(...)`, which sets `display: flex` (direction `row`). Browsers
  implement Enter by inserting a block per line, and inside a flex row each of
  those blocks becomes a **flex item**, so the lines were laid out side by side
  as columns. The centring wrapper now carries the flex layout and the editable
  element itself stays a block.
- **Line breaks were never saved.** `onInput` read `textContent`, which ignores
  that markup and concatenates: "A⏎B" came back as "AB". A new
  `canvas/editable_text.ts` walks the tree and turns block boundaries and `<br>`
  into real newlines. `innerText` would also do this but is not implemented
  consistently outside browsers, so the explicit walk is used and is fully
  covered by tests.
- **Node growth follows from the fix.** `nodeWidth`/`nodeHeight` already split on
  `\n`; they simply never saw one. With breaks preserved the box grows per line
  while typing, since the live edit value feeds the size.
- **Verification:** 8 new unit tests including `<br>`, Chrome/Firefox
  div-per-line markup, nested inline formatting, paragraphs, empty input and an
  explicit assertion that `textContent` would have lost the breaks. **Jest 34
  suites / 215 tests**; 207 `mod_vimipad` + 97 `vimipadassess` green; phpcs clean;
  bundle reproducible. Needs a browser to confirm the felt behaviour.
- Still rough: `nodeHeight` estimates characters per line at a fixed ~7px, so a
  label scaled up with A+ can still overflow. Left alone deliberately rather than
  guessed at without being able to measure text.

## 0.6.17 (2026072731) — A3: connector angles and parallel sibling connections

- **Root cause of the odd arrow heads.** For the curved style `relLinePath` built
  a cubic Bezier whose control points were *always vertical*
  (`from.x, from.y ± k` / `to.x, to.y ∓ k`), so a connection left and entered its
  nodes vertically no matter how the nodes were actually placed. Because the
  marker uses `orient="auto"`, the head followed that vertical end tangent and
  pointed the wrong way — most visibly on side-by-side nodes.
- **Departure/arrival angle, as specified.** The direct line now classifies the
  connection as basically horizontal or vertical; the axis-aligned perpendicular
  of that side (the "Lot") is bisected with the direct line's angle, and that
  bisector is the angle at which the connection leaves or arrives outside the
  node shape. The head uses the same angle because the path runs **straight for
  the arrow's length** (`ARROW_STUB`) before curving; the curve handles continue
  those directions, so the joins stay smooth.
- **Parallel connections.** Relations sharing a node pair are grouped into slots
  and their anchors are shifted symmetrically perpendicular to the direct line
  (`siblingOffsets` + new `offsetAnchors`), so multiple relations run parallel
  instead of overlapping. This applies to straight connections too, not just free
  ones.
- New pure helpers in `canvas/connection_geometry.ts`: `orientationOf`,
  `bisectAngles`, `connectorExitAngle`, `offsetAnchors`, `freeConnectorPath`.
- **Verification:** 12 new unit tests covering orientation, bisecting across the
  0/360 wrap, horizontal and diagonal departure angles, mirrored ends, symmetric
  offsets, parallelism (the direction vector is unchanged) and the straight run.
  **Jest 33 suites / 207 tests**; 207 `mod_vimipad` + 97 `vimipadassess` green;
  phpcs + phpcpd clean; bundles reproducible. The visual result needs a browser.
- Adopts the updated `makefile` (its `check` target now also runs `build`, which
  would have caught the stale AMD bundle behind T1).

## 0.6.16 (2026072730) — A1: pointer mapping now honours the aspect ratio

- **Root cause.** The canvas `<svg>` carries a viewBox but no
  `preserveAspectRatio`, so the browser applies the default `xMidYMid meet`:
  uniform scale plus centring. The screen-to-canvas conversion instead divided by
  the element's width and height separately, i.e. it assumed the viewBox was
  stretched to fill the element. That produced a wrong offset *and* a wrong
  scale, differing per axis — the pointer drifted from the cursor and drags ran
  at the wrong speed. In a reported session (element 1138x213, viewBox 826x551)
  the horizontal scale was off by a factor of ~3.6; even on a normal-height
  window `max-height: 60vh` keeps the element shorter than the viewBox implies,
  so the error is present in everyday use too.
- **Fix.** `toSvgPoint` now uses the browser's own `getScreenCTM().inverse()`,
  which also accounts for CSS transforms on ancestors (full view). A new pure
  module `canvas/viewport.ts` replicates the `meet` mapping as a fallback for
  environments without a CTM (jsdom, tests).
- **Tests** pin the mapping to the real reported geometry: uniform scale, centre
  maps to centre, a 100 px drag equals 100/scale viewBox units, and an explicit
  assertion that the previous stretch assumption was off by more than 3.5x.
- Affects selecting, moving, resizing and drawing connectors alike, since they
  all go through the same conversion.
- **Verification:** 207 `mod_vimipad` + 97 `vimipadassess` green; phpcs clean;
  `tsc` clean; **Jest 32 suites / 195 tests**; bundles reproducible. The felt
  behaviour needs confirming in a browser.

## 0.6.15 (2026072729) — UI cleanup: toolbar author tools, lock mode, colour menu

Addresses the reported UI issues T1, T2, T6, A4, A5 and (tentatively) A2.

- **T1 — raw string keys in the editor.** Root cause: `amd/build/init.min.js` was
  stale. It is rebuilt by Moodle's Grunt, not by `build.mjs`, and had not been
  rebuilt since 0.6.7, so the browser requested an outdated `STRING_KEYS` list and
  `init.js` echoed the raw key for anything missing. Both AMD artefacts are
  rebuilt. **CI gap closed:** the reproducibility job only diffed the esbuild
  bundle and the Grunt step never compared its output against the committed
  artefact (and never built `revision.js` at all), so a stale build shipped
  green. CI now builds both AMD sources and gates them with `git diff
  --exit-code`. **Hardened:** `init.js` returns `undefined` for unknown keys and
  the bundle falls back to its own English strings, so a stale build degrades to
  English rather than raw ids. New `amd_string_keys_test` guards the other
  direction (every requested key exists in `lang/en`).
- **T6 / T2 — author tools relocated.** The author area below the canvas is gone.
  Container drawing is now a toolbar button between re-arrange and export, and a
  new lock-mode toggle sits with the full-view button in a right-hand toolbar
  group. Both are shown only with `mod/vimipad:manageprofiles`.
- **T3 (core) — locking moved into the element dock.** With lock mode armed, a
  node's dock offers a lock toggle left of delete; a locked element's dock is
  reduced to that toggle, so no text, colour, shape or structural editing is
  offered.
- **A4 — colour sub-menu removed.** The palette button opens the picker directly;
  the formatting reset is now a third action inside the picker next to
  cancel/confirm.
- **A5 — confirm buttons** read as outline-success (green on white) and light up
  solid green on hover/focus, clearly distinct from neutral dock buttons.
- **A2 (tentative)** — the dock's `foreignObject` (300x320) is now
  `pointer-events: none`, so the empty area below a selected node no longer
  swallows clicks. Needs confirmation in a browser.
- **Verification:** **207 `mod_vimipad`** + **97 `vimipadassess`** green; phpcs
  clean; `tsc` clean; **Jest 31 suites / 189 tests** (new `color_field`); esbuild
  and AMD bundles both rebuilt and byte-reproducible. Visual result runs in CI /
  the browser.

## 0.6.14 (2026072728) — revision view reconstructs containers

Closes the container gap in the historical revision viewer.

- `reconstruction_service` now replays container operations (create / update /
  delete) alongside nodes and relations, returning surviving containers at the
  requested revision. `get_revision_state` populates the `containers` field it
  already declared (VALUE_OPTIONAL). Viewing a past revision now shows that
  revision's containers.
- **No frontend change needed:** `getRevisionState` passes the state through and
  `RevisionViewer` already renders `state.containers` via `CanvasView` in
  read-only mode — the backend fix alone closes the loop.
- **Verification:** new `test_reconstruct_containers` drives create → update
  (label + geometry) → create → delete across revisions on real Moodle; **206
  `mod_vimipad`** (+1) + **97 `vimipadassess`** green; whole-plugin phpcs + phpcpd
  clean. Frontend unchanged (bundle byte-identical, Jest 30/185).

## 0.6.13 (2026072727) — author tools grouped into one area, drawing author-gated

Discoverability/permissions cleanup for the 0.6 authoring tools.

- **One author area.** Draw-containers and the template `LockPanel` now live in a
  single, clearly delimited "Author tools" group (`role="group"`, labelled) instead
  of sitting inline among the learner-facing node/relation controls.
- **Author-gated.** The whole group renders only when `canmanage`
  (mod/vimipad:manageprofiles). This also closes the earlier gap where *drawing*
  containers was open to any editor — it is now author-only, consistent with
  moving/resizing/deleting locked containers.
- Learners see only the node/relation controls; authors additionally see the
  author area. Canvas view only (unchanged).
- Lang: `editor:authortools` (en/de, 430/430 parity) + init.js key + fallback.
- **Verification:** no PHP logic change; `tsc` clean; **Jest 30 suites / 185
  tests**; esbuild bundle rebuilt and **byte-reproducible**; **205 `mod_vimipad`**
  + **97 `vimipadassess`** green; whole-plugin phpcs clean. Visual placement runs
  in CI.

## 0.6.12 (2026072726) — SVG/PNG export with containers + SVG round-trip import

Rounds out import/export for the 0.6 authoring tools. SVG/PNG/PDF export already
existed (it serializes the live SVG, so containers have been drawn into it since
0.6.9); this makes the export container-aware and adds an SVG re-import path.

- **Containers in the export frame.** `computeContentBounds` now folds container
  boxes into the export viewBox, so a container drawn beyond the nodes is no longer
  clipped in the SVG/PNG/PDF output. Container interaction chrome (delete, resize
  handle, draw overlay) is stripped from the exported image.
- **SVG round-trip.** An exported SVG embeds the semantic map JSON in a
  `<metadata id="vimipad-data">` element (the same envelope `export.php?format=json`
  serves). Importing an `.svg` extracts that JSON and feeds it through the existing
  import path — so an SVG is both a viewable image and a re-importable map. If the
  SVG carries no embedded map, the import reports `editor:importnovimidata`.
- Pure `extractMapData` and the embed step are unit-tested; a new
  `test_container_roundtrip` proves containers survive export → import (the backend
  basis the SVG round-trip stands on).
- Lang: `editor:importnovimidata` (en/de, 429/429 parity) + init.js key + fallback.
- **Verification:** `tsc` clean; **Jest 30 suites / 185 tests** (extended
  `svg_export`); esbuild bundle rebuilt and **byte-reproducible**;
  **205 `mod_vimipad`** + **97 `vimipadassess`** green; whole-plugin phpcs + phpcpd
  clean. Actual download/upload flows run in the browser / CI.

## 0.6.11 (2026072725) — containers: move, resize, rename, lock + undo/redo

Completes the container authoring tool (the "Reste" of 0.6.9) — no PHP change;
container_update was already enforced and manager-bypassed.

- **Move / resize / rename on the canvas.** Each container now has a title bar
  (drag to move, double-click to rename) and a bottom-right handle (drag to
  resize, clamped to a minimum). The body stays non-interactive so nodes beneath
  remain clickable; the drag uses pointer capture, isolated from the node/connect
  gestures. Committed as `container_update` operations.
- **Full undo/redo** for containers — create, delete, move, resize and rename all
  push history entries and replay through `operationToAction` (create/delete since
  0.6.9; move/resize/rename now), matching how nodes and relations behave.
- **Lock containers.** The template `LockPanel` now lists containers too, so an
  author can lock a container like any node or relation.
- **Learner safety.** A locked container shows no move/resize/delete/rename
  affordances to non-managers (the server already rejects such edits; this avoids
  offering an action that would fail).
- New pure geometry helpers `moveBox` / `resizeBox` (clamped), unit-tested.
- **Verification:** `tsc` clean; **Jest 30 suites / 182 tests** (extended
  `container_geometry`, `lock_panel`); esbuild bundle rebuilt and
  **byte-reproducible**; **204 `mod_vimipad`** + **97 `vimipadassess`** green
  (PHP unchanged); whole-plugin phpcs clean. Canvas drag visuals and `@javascript`
  Behat run in CI.
- Next: SVG/PNG export includes containers in bounds, and SVG round-trip import.

## 0.6.10 (2026072724) — template lock editor (frontend authoring 3/3)

Third and final 0.6 frontend/canvas authoring piece, completing the three-point
frontend work. Authors can now lock individual elements so learners cannot
restructure or delete them; the enforcement has existed server-side since 0.6.4,
this makes it settable and adds the author bypass it needs.

- **Backend:** `operation_service` gains a `bypasslocks` constructor flag; when
  set, element-lock enforcement is skipped. `apply_operation` passes
  `has_capability('mod/vimipad:manageprofiles')`, so authors/managers can set,
  change and remove locks (and edit locked scaffolds) while learners stay bound.
  `get_workspace` returns `canmanage` (VALUE_OPTIONAL) so the editor shows the
  lock UI only to authors. Tests: manager bypass (`element_lock_test`), canmanage
  reflects capability (`get_workspace_containers_test`).
- **Frontend:** pure `element_lock.ts` (read/write the `locked` + `editable`
  metadata, preserving other keys); `LockPanel` lists nodes and relations with a
  per-element lock toggle and an "allow renaming" sub-toggle (keeps `label`
  editable), dispatched as update operations. Shown only when `canmanage`. Locked
  nodes render a small lock badge on the canvas for everyone.
- Lang: `editor:node`, `editor:templatelocks`, `editor:templatelockshint`,
  `editor:lockallowlabel` (en/de, 428/428 parity) + init.js keys + mount fallbacks.
- **Verification:** `tsc` clean; **Jest 30 suites / 179 tests** (new:
  `element_lock`, `lock_panel`); esbuild bundle rebuilt and **byte-reproducible**;
  **204 `mod_vimipad`** + **97 `vimipadassess`** green; whole-plugin phpcs + phpcpd
  clean. Visual rendering and `@javascript` Behat run in CI.

With 0.6.8–0.6.10 the three frontend/canvas authoring features are complete:
soft constraint hints, canvas containers, and template locks.

## 0.6.9 (2026072723) — canvas: draw containers (frontend authoring 2/3)

Second of the three 0.6 frontend/canvas authoring pieces. Authors can now draw
background containers (sections/boxes) directly on the canvas. Container
operations existed server-side since 0.6.1; this makes them usable and visible.

- **Backend:** `get_workspace` now returns `containers` (stableid, type, label,
  geometryjson, metadatajson), mapped from the state the service already loads.
  Added to the return structure as `VALUE_OPTIONAL` so `get_revision_state`
  (which shares the structure) stays valid. New test
  `get_workspace_containers_test` (returned + soft-deleted excluded, validated
  via `clean_returnvalue`).
- **Frontend:** `VimiContainer` type + `containers` on the workspace state;
  reducer actions add/update/deleteContainer; pure geometry codec
  `container_geometry.ts` (parse/serialise/normalise/box-from-drag). `CanvasView`
  renders containers behind the graph and hosts an isolated draw overlay (active
  only while the tool is on, so it never interferes with node/connect gestures);
  a toolbar toggle enters draw mode, a drag creates the container, a corner
  button deletes it. Remote sync + undo/redo covered by new container cases in
  `operationToAction`.
- Lang: `editor:containers`, `editor:containerdelete`, `editor:drawcontainer`,
  `editor:drawcontainerdone` (en/de, 424/424 parity) + init.js keys + mount fallbacks.
- **Verification:** `tsc` clean; **Jest 28 suites / 169 tests** (new:
  `container_geometry`, `container_reducer`, `container_apply_remote`); esbuild
  bundle rebuilt and **byte-reproducible**; **202 `mod_vimipad`** + **97
  `vimipadassess`** green; whole-plugin phpcs + phpcpd clean. Visual drawing and
  `@javascript` Behat run in CI, not verifiable in the sandbox.
- Next: the template lock editor (3/3) — set `locked`/`editable` metadata,
  enforced since 0.6.4.

## 0.6.8 (2026072722) — editor: soft constraint hints (frontend authoring 1/3)

First of the three 0.6 frontend/canvas authoring pieces. The editor now surfaces
the activity's map requirements as soft, non-blocking hints while editing, using
the 0.6.5 `get_constraint_status` endpoint. This never blocks; the hard gate
still runs only at submission.

- New API client method `getConstraintStatus(workspaceid)` and a `ConstraintStatus`
  type (`js/src/api/service.ts`, `js/src/types`).
- New hook `useConstraintHints(api, workspaceid, revision, enabled)`
  (`js/src/hooks/use_constraint_hints.ts`): debounced (600 ms), coalesces a burst
  of edits into one request, latest-request-wins, failures swallowed (advisory).
  Only active for the editing owner of an open map (not read-only, not submitted).
- New presentational `ConstraintBanner` (`js/src/components/ConstraintBanner.tsx`):
  renders nothing when no constraint is configured or the map is satisfied; else a
  warning alert listing the (backend-localised) hint messages. Wired into
  `EditorApp` above the canvas.
- Lang `constraint:hintsheading` (en/de, 420/420 parity); added to the editor's
  `core/str` key list (`amd/src/init.js`) and the mount fallbacks (`mount.tsx`).
- **Verification:** `tsc --noEmit` clean; **Jest 25 suites / 155 tests** green
  (new: `constraint_status_api`, `use_constraint_hints` with fake timers,
  `constraint_banner`); esbuild bundle rebuilt and **byte-reproducible**; PHP
  unchanged (**200 `mod_vimipad`** + **97 `vimipadassess`**), whole-plugin phpcs
  clean. Note: visual rendering and `@javascript` Behat run in CI, not verifiable
  in the sandbox.
- Next: container drawing on the canvas (2/3), then the template lock editor (3/3).

## 0.6.7 (2026072721) — CI: release workflow handles Moodle 5.2+ public/ layout

CI-only fix (no plugin code change). Merging to `main` runs the release workflow,
whose matrix includes MOODLE_502_STABLE. Moodle 5.2 introduced the separated
public directory, installing the plugin under `moodle/public/mod/vimipad/`
instead of `moodle/mod/vimipad/`. The bundle-verification and AMD-rebuild steps
hardcoded the 4.x path, so the 5.2 job failed at "Verify editor bundle installed"
(`test -f` on a non-existent path), turning the whole run red — even though the
0.6.x dev CI was green (its AMD steps run in a 405-only job).

- `moodle-release.yml`: added a "Resolve plugin path" step that detects the
  `public/` layout and exports the real plugin dir. "Verify editor bundle
  installed" now checks the resolved path (all Moodle versions). The
  `npm install` + `npx grunt amd` rebuild + its follow-up verify are gated to the
  non-public layout (4.x / 5.0), where the existing paths are correct; on 5.2 they
  are skipped, since AMD/bundle reproducibility is already covered by the
  version-agnostic "Bundle reproducibility" job.
- `moodle-ci.yml` unchanged: its AMD steps already run in a 405-only job, so the
  dev CI was unaffected.
- No plugin files changed; PHPUnit still **200 `mod_vimipad`** + **97
  `vimipadassess`** green, whole-plugin phpcs + phpcpd clean.

## 0.6.6 (2026072720) — de-duplicate read access control (phpcpd)

Small hardening: the access-control boilerplate shared by the read external
functions is now in one place, so it cannot drift — the kind of duplication that
matters because it is security-relevant.

- **New `helper::validate_workspace_for_read()`** mirrors the existing
  `validate_workspace_for_edit()`: it resolves cmid → context → instance →
  workspace, validates the context, requires `mod/vimipad:view`, and enforces the
  "own map, or grader for someone else's" rule in one spot. `get_revision_state`
  and `get_constraint_status` now call it instead of each carrying their own copy
  of the block.
- **phpcpd (`--min-lines 5 --min-tokens 70`) reports no clones** (was 1 clone, 18
  duplicated lines across the two external functions).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **200 `mod_vimipad`**
  **+ 97 `vimipadassess`** green (get_constraint_status and collaboration external
  tests exercise the shared helper); whole-plugin phpcs clean.

## 0.6.5 (2026072719) — non-blocking constraint status endpoint

Backend half of the soft, edit-time constraint hints: the editor can now ask for
the current map's constraint status without blocking anything. Same resolver as
the hard gate, so hints and enforcement never diverge.

- **New external function `mod_vimipad_get_constraint_status`** (read, ajax):
  given a workspace, it evaluates the live map with `constraint_policy` and
  returns `configured`, `satisfied`, localized `messages`, and the structured
  `requiredmissing` / `forbiddenpresent` / `typeviolations` lists (for future
  element highlighting). View capability required; inspecting another user's map
  needs grade capability, as elsewhere. It never mutates and never blocks.
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **199 `mod_vimipad`**
  (incl. new `get_constraint_status_test`, run in isolation, with the return value
  validated against the declared structure via `clean_returnvalue`) **+ 97
  `vimipadassess`** green; whole-plugin phpcs clean (`--ignore=tools/`).
- Frontend consumption (a hint banner in the editor that calls this endpoint,
  debounced) is the next slice.

## 0.6.4 (2026072718) — template structural locks + lint fix

Third 0.6 authoring-foundation piece: teacher-provided scaffolds can now be
protected element by element, enforced server-side. No schema change.

- **Element locks in the operation service.** An element whose metadata carries
  `{"locked": true}` can no longer be deleted, and can only be updated in the
  fields listed in its `editable` whitelist (e.g. `{"locked": true, "editable":
  ["label"]}`) — otherwise `error:elementlocked` is raised. Enforced for
  node/relation/container update and delete plus relation retarget, in
  `operation_service` (which every edit path goes through). Unlocked elements
  and element creation are unaffected, and import is unaffected (it only
  creates). This is the enforcement half of the template policy from
  `template_constraint_policy.md`.
- New lang string `error:elementlocked` (en/de, 419/419 parity).
- **Lint fix:** the `version.php` release-line inline comment now starts with a
  capital (Squiz.Commenting.InlineComment). Whole-plugin phpcs (moodle standard,
  `--ignore=tools/`) is clean.
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **197 `mod_vimipad`**
  (incl. new `element_lock_test`: locked delete/update rejected, whitelist
  honoured, unlocked elements free, locked relation protected) **+ 97
  `vimipadassess`** green.

## 0.6.3 (2026072717) — teacher map-constraint fields (submission gate goes live)

Gives the 0.6.2 constraint engine its input: teachers can now define the map
requirements, and the hard submission gate enforces them from real settings.

- **Five new instance settings** on `mod_form` (own "Map requirements" section):
  required concepts, forbidden concepts, allowed relation types (free-text,
  one per line or comma-separated) and minimum concepts / minimum relations.
  Added to `db/install.xml`, `db/upgrade.php` (savepoint 2026072717, guarded
  `add_field`s) and the backup element list; restore is automatic. Since
  `constraint_config::from_instance()` already read these fields, the 0.6.2
  submission gate activates with no further wiring.
- New lang strings for the section, labels and help (en/de, 418/418 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **193 `mod_vimipad`**
  **+ 97 `vimipadassess`** green; `backup_restore_test` extended to assert the
  five new fields survive a backup/restore roundtrip; a new gate test confirms
  the block fires from settings saved through the form; phpcs clean; install.xml
  well-formed and the upgrade savepoint matches version.php.

## 0.6.2 (2026072716) — constraint-policy engine + hard submission gate

Adds the map-constraint engine that the authoring/assessment workflow needs and
wires the hard gate into submission. The teacher-facing input fields for the
qualitative constraints follow in 0.6.3; the engine and gate are complete and
fully tested now.

- **`\mod_vimipad\local\policy` package.** `constraint_config` (value object,
  `from_instance()` reads required/forbidden concepts, allowed relation types and
  min node/relation counts — no-op today, active the moment the fields land),
  `constraint_policy::evaluate()` (pure, deterministic; takes a normalized map,
  returns findings) and `constraint_report` (structured findings +
  `is_satisfied()` + localized `messages()`/`summary()`), so the same evaluation
  can back both the hard gate and future soft edit-time hints.
- **Hard submission gate.** `snapshot_service::create_submission` now evaluates
  the frozen map under the workspace write lock and refuses submission with
  `error:constraintsnotmet` (listing the violations) if it is not satisfied.
  Constraint kinds: missing required concepts, present forbidden concepts,
  disallowed relation types, too few concepts, too few relations
  (case-insensitive term matching).
- New lang strings: `error:constraintsnotmet` and `constraint:*` messages
  (en/de, 407/407 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **192 `mod_vimipad`**
  (incl. `constraint_policy_test`: all constraint kinds + an end-to-end gate test
  that blocks an invalid submission and lets a fixed one through) **+ 97
  `vimipadassess`** green; phpcs clean.

*Versioning note: the previous release (integer 2026072715) is 0.6.1 — the 0.6.x
line uses plain patch numbers without an -alpha suffix.*

## 0.6.1 (2026072715) — 0.6 foundation: container/membership operations + import round-trip

Opens the 0.6.x authoring line with the backend contract the authoring tools sit
on. No new UI yet; this makes containers first-class in the operation log and
closes the export/import asymmetry the 0.5.32 audit flagged.

- **Container & membership operations.** New operation types `container_create`,
  `container_update`, `container_delete`, `membership_add`, `membership_remove`
  in `operation_type` (validated: itemtype enum node|relation|container,
  int-like sortorder) and handled in `operation_service::mutate`
  (create revives soft-deleted rows; membership_add is an upsert; container_delete
  soft-deletes and drops its memberships). All go through the same shared write
  lock and revision path as node/relation operations.
- **Import now round-trips containers and memberships.** The export already
  emitted them (stable-id based); `import_service` now consumes them, remapping
  container stable ids and member references (nodes, relations and nested
  containers) onto the freshly created elements. Relation stable ids are now also
  tracked in the id map so memberships on relations remap correctly. XML parsing
  gained `containers`/`memberships`; `import_map` returns their counts. No format
  version bump was needed — the format already carried containers; only the
  import consumer was missing.
- New lang string `error:containernotfound` (en/de, 401/401 parity).
- **Template/constraint policy specified** (`docs/design/template_constraint_policy.md`):
  soft constraints at edit time via a shared `constraint_policy` resolver, hard
  gate at submission; template structural locks enforced per-operation via element
  `metadatajson` — implementation scheduled across 0.6.x.
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **186 `mod_vimipad`**
  (incl. new `container_operations_test`: lifecycle + import round-trip) **+ 97
  `vimipadassess`** green; phpcs clean on all changed/new files.

## 0.5.34 (2026072714) — 0.5.x closure part 2: due-date/late, map_updated event, peer scope

Second and final closure slice before 0.6.x, wiring up the two behaviours that
were stored but never evaluated and settling the peer-review scope.

- **Due-date lateness is now evaluated.** `snapshot_service::is_late($instance,
  $submittedtime)` marks a submission late when it was created after the (soft)
  `duedate`; a due date of 0 is never late. The due date only *marks* lateness —
  the hard block remains `cutoffdate` (already enforced at submission). The
  grading detail view shows the submission time and a "Late" badge when
  applicable.
- **New `\mod_vimipad\event\map_updated` event.** Fired once per applied
  operation in `apply_operation` (crud `u`, participating level), so course logs
  and reports see editing activity alongside the existing viewed/submitted/graded
  events.
- **Peer-review scope settled (E3-B).** The core keeps the lean base
  (allocation → reviews → aggregate on snapshots/annotations). The full 5-phase
  *activity* workflow is explicitly deferred to the post-1.0 premium
  `vimipadreview_peerplus`; the roadmap now states this rather than leaving it
  open.
- New lang strings: `event:map_updated`, `gradetab:late`, `gradetab:submittedon`
  (en/de, 400/400 parity).
- **Verified on real Moodle 4.5.12 + PostgreSQL:** full suite **182 `mod_vimipad`**
  (incl. new `duedate_and_events_test`) **+ 97 `vimipadassess`** green; phpcs
  clean on all changed/new files.

## 0.5.33 (2026072713) — 0.5.x closure: workspace concurrency + fail-closed uniqueness

Closure round before the 0.6.x authoring tools: no new features, but the open
0.5 concurrency guarantee is closed and several contracts/docs are re-baselined
against the real state.

- **Shared per-workspace write lock (concurrency gate).** A single
  `\mod_vimipad\local\lock\workspace_writelock` (key `write_<workspaceid>`)
  now serializes *every* semantic mutation and snapshot creation:
  `operation_service::apply`, `import_service`, `workspace_service::reopen` and
  `snapshot_service::begin_submission`/`finalize` all coordinate on the same
  lock. Previously `operation_service::apply` took no real lock (only checked the
  `locked` flag) and submission used a separate `submit_` key, so a snapshot
  could capture a torn read across the node/relation/container tables relative to
  its recorded revision. `apply` was split into a locking wrapper and a lock-free
  `apply_locked` so import (which fans out into many operations) holds the lock
  once without re-entrant acquisition.
- **Workspace creation is now fail-closed.** `workspace_service::create_unique`
  no longer creates a second workspace when the lock cannot be acquired; it
  re-reads and reuses an existing row or raises a concurrency error (the DB does
  not enforce uniqueness on `(vimipadid, userid|groupid)`).
- **Lease contract clarified (advisory).** `lock_service`'s per-element leases
  are documented as advisory collaboration/presence locks; `operation_service`
  does not enforce them. Concurrency correctness rests on the write lock plus
  optimistic revision checks, not on leases.
- **Import atomicity contract stated explicitly:** the semantic import
  (nodes + relations) is atomic; the layout is applied best-effort after commit.
- **Doc re-baseline.** `session-002.md` added (0.4.22→0.5.32 arc); `roadmap.md`
  (real stand + status markers, Privacy/Backup moved to re-audit), `backlog.md`,
  `connector-styles.md` (`vimipadform`, `formconfig` consumed),
  `ui_reorder_plan.md`, `moodle-test-environment-setup.md` (176+97 baseline),
  `visual-maps-requirements.md`, README (React 5.3 framing, public-API claim
  softened) and `assessment_architecture.md` (single reference per activity,
  E1-B) brought in line with the code. `sessionende.txt` fixed so the persisted
  `docs/sessions/session-<NR>.md` is a mandatory, ZIP-shipped, verified step
  (root cause of the previously missing session-002 doc).
- **Verified on a real Moodle 4.5.12 + PostgreSQL PHPUnit environment:** full
  suite **178 `mod_vimipad`** (incl. the new `workspace_writelock_test`) **+ 97
  `vimipadassess`** tests pass (exit 0); every affected service test green in
  isolation; phpcs clean on all changed/new files.

## 0.5.32 (2026072712) — configuration UI for assessment and peer review

- **Peer review settings in the activity form.** "Peer review" turns the feature
  on and "Reviews per submission" (1-5) sets the allocation target, the latter
  hidden until peer review is enabled. Both carry help text explaining that peer
  scores are advisory.
- **Per-activity scorer selection.** A new "Automatic scorers" multi-select lists
  every installed scorer; leaving it empty keeps the previous behaviour of running
  all of them. The choice is stored in `activescorers` and honoured by
  `assess_service` for the automatic run, the single-scorer path and the on-demand
  AI scorer alike.
- **New peer review tab.** Reviewers see their allocated submissions as
  "Submission 1", "Submission 2" — never the author's name — and open one to get
  the map read-only, the automatic scorers' hints, and a form for an optional
  score out of 100 plus written feedback. Opening a review allocated to someone
  else falls back to the reviewer's own list. A new `mod/vimipad:peerreview`
  capability governs access (students by default).
- **Teacher side.** The grading tab gains an "Allocate peer reviews" action, and a
  submission's grading detail shows the aggregated peer verdict (count, mean,
  median, outstanding) plus the review comments, without reviewer identities.
- **Fixed a Moodle schema rule:** `activescorers` was declared CHAR NOT NULL with
  an empty-string default, which Moodle rejects (it rewrites the default and the
  install.xml structure check fails). The column is now nullable with no default;
  null and empty both mean "all scorers".
- **Fixed: the scorer selection was lost on restore.** `activescorers` was not
  included in the backup field list, so a restored activity silently fell back to
  running every scorer. It is now backed up, and the backup/restore roundtrip test
  asserts that the matching mode, scorer selection and both peer-review settings
  survive.
- **Panel rendering is now covered by tests.** `peer_review_panel_test` drives the
  reviewer list, the empty-allocation case, the detail URL and the no-op action
  path with a real `cm_info` — the same guard added for the grading panel in
  0.5.31, so a parameter-type mismatch in a panel fails in PHPUnit instead of only
  in a browser.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** 176 `mod_vimipad` + 97
  `vimipadassess` tests pass on both (exit 0), every file also passing in
  isolation; phpcs, phpdoc, phpcpd, savepoints, validate and mustache clean. Every
  `get_string()` key referenced in the plugin was cross-checked against the
  language files.

## 0.5.31 (2026072711) — peer review backend + description (ROUGE) scorer

- **Behat fix: grading tab crashed in a browser.** `view.php` resolves the course
  module with `get_course_and_cm_from_cmid()`, which returns a `cm_info`, but
  `grading_panel` (and `consensus_notifier`) declared `stdClass $cm` — a fatal
  TypeError on every visit to the grading tab, and the cause of all four failing
  Behat scenarios on every Moodle version. Both now accept `cm_info|stdClass`. A
  new `grading_panel_test` drives `detail_url`, `render` and `handle_action` with
  a real `cm_info`, so this class of mismatch is caught by PHPUnit rather than
  only in a browser run.
- **New `vimipadassess_text` scorer (description comparison).** Concept labels say
  what a student named; descriptions say what they understood. For each described
  reference concept the scorer locates the matching submission concept (through
  the configured matcher, so fuzzy and word-overlap modes apply) and compares the
  two description texts with ROUGE. Thin descriptions are reported with their
  overlap percentage; absent ones as missing.
- **ROUGE core service** (`local\assess\rouge`): ROUGE-N (clipped n-gram overlap)
  and ROUGE-L (longest common subsequence) as F-measures, plus a combined
  similarity. HTML is stripped and case ignored; long texts are bounded so the
  LCS stays cheap.
- **Peer review backend.** New `peerreviewmode` / `peerreviewcount` activity
  settings and a `vimipad_peerreview` table, driven by `peer_review_service`:
  round-robin allocation over submitting students (nobody reviews their own map,
  idempotent so it can run whenever new submissions arrive), review recording
  (refused without an allocation), and aggregation reporting count, mean, median
  and outstanding reviews. Peer scores stay advisory — the service never writes
  to the gradebook.
  - **Fuzzy-capable:** `guidance()` runs the same synchronous scorers a teacher
    sees, through the activity's matcher, so reviewers get fuzzy-tolerant hints
    about what is present or missing instead of judging unaided.
  - Covered by privacy (metadata, per-user and context deletion) and
    backup/restore (reviewer remapped), both verified.
- This step deliberately completes the *functionality*; the activity-form and
  reviewer UI elements for peer review follow in the next step.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** 165 `mod_vimipad` + 97
  `vimipadassess` tests pass on both (exit 0), every test file also passing in
  isolation; phpcs, phpdoc, phpcpd, savepoints, validate and mustache clean.

## 0.5.30 (2026072710) — sub-map scorer + cross-version CI fixes

- **New `vimipadassess_sms` scorer (sub-map comparison).** Reads each container
  as a sub-map (a set of concepts) and matches every reference sub-map to the
  submission's best-overlapping one (concept-set F1 through the injected
  matcher), reporting which expected groupings were reproduced, missed or added.
  When no sub-maps are defined it returns an informational note, not a
  misleading zero. The `submission` model now carries sub-maps, rebuilt from a
  snapshot's containers and node memberships.
- **CI fix — subplugin plugininfo class.** The `vimipadassess` subplugin type
  had no `\mod_vimipad\plugininfo\vimipadassess` class, so a full site install
  emitted a debugging() notice and every PHPUnit/Behat matrix job failed at the
  install step. Added the class (mirroring `vimipadform`).
- **CI fix — Moodle 5.0 subplugins format.** `db/subplugins.json` now declares
  both `subplugintypes` (relative paths, the Moodle 5.0 form, MDL-83705) and
  `plugintypes` (full paths, for Moodle 4.5), matching core modules, so the 5.0
  deprecation notice is gone while 4.5 keeps working.
- **Verified on real Moodle 4.5.12 and 5.0.8 instances:** install is clean on
  both; `mod_vimipad` (150) and `vimipadassess` (81) tests pass on both (exit 0;
  on 5.0 the only issues reported are framework-level PHPUnit deprecations, which
  do not fail the build). phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.29 (2026072709) — configurable matching + hierarchy scorer

- **Choice of label matching for automatic scoring.** A new activity setting
  ("Concept matching") selects how concept and proposition labels are compared:
  *exact* (normalised), *fuzzy* (edit distance, tolerates typos) or *word
  overlap* (Jaccard, ignores order and filler words). Implemented as
  `levenshtein_matcher` and `token_matcher` behind the existing `matcher`
  interface, chosen through a `matcher_factory`; every content scorer benefits.
  The choice (`matchmode`) is stored on the activity, shown in the form, and
  carried through backup/restore.
- **New `vimipadassess_tree` scorer** compares hierarchy rather than wording:
  it reads directed relations as parent → child links, identifies the root and
  measures how well the submission reproduces the reference's root and links
  (precision/recall F1). Suited to tree and mind-map profiles; relation labels
  are ignored. Uses the injected matcher, so fuzzy/word-overlap applies here too.
- The grading tab now shows each scorer's part scores generically (e.g. root and
  hierarchy for the tree scorer, concepts and propositions for the reference
  scorer).
- **Verified on a real Moodle 4.5.12 instance:** 150 `mod_vimipad` + 65
  `vimipadassess` tests pass; phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.28 (2026072708) — AI (LLM) scorer + tuple-to-text

- **New `vimipadassess_llm` scorer** assesses a map through Moodle's AI
  subsystem. It runs *on demand* (a "Run AI assessment" button in the grading
  tab), never automatically, because the call is slow and costs apply. Prompt
  building and reply interpretation are deterministic and unit-tested; only the
  model round-trip needs a configured AI provider.
- **Tuple-to-text core service** renders a map's concepts and propositions as
  plain sentences — the shared bridge between the structured map and any
  prompt-based scorer.
- **Contract additions:** scorers can declare `uses_ai()` (so `score_all` skips
  them on page load) and AI scorers implement a `prompt_scorer` interface
  (`build_prompt` / `interpret`); the assess service orchestrates the AI call
  and reuses `ai_feedback_service` for gating.
- **CI fix:** phpdoc rejected the generic array syntax (`array<...>`) in two
  `@param` tags; changed to plain `array`.
- **Verified on a real Moodle 4.5.12 instance:** 146 `mod_vimipad` + 49
  `vimipadassess` tests pass; phpcs, phpdoc, phpcpd, savepoints, validate and
  mustache are clean.

## 0.5.27 (2026072707) — reference-free structural scorer

- **New scorer `vimipadassess_structure`** — a reference-free structural overview
  of a submission: concept and proposition counts, links per concept, isolated
  concepts and well-connected hubs. It is explicitly *informational* (a new
  `informational` flag and `metrics` list on the assess `result`); the grading
  tab shows the numbers with a clear note that rich structure alone is not a
  grade. It needs no reference solution, so it always applies.
- **Grading tab runs every applicable scorer.** A new `assess_service::score_all`
  runs each scorer that supports the submission's profile — reference-free ones
  always, reference-based ones only when a reference is marked — and the tab
  renders each under its own name (structural metrics for `structure`, the
  match breakdown and suggested grade for `reference`).
- **Verified on a real Moodle 4.5.12 instance:** `mod_vimipad` 145 tests and
  `vimipadassess` 33 tests (incl. the new structure-scorer and score_all tests)
  pass; phpcs, phpcpd, savepoints, validate and mustache are clean.

## 0.5.26 (2026072706) — reference solution + scoring in the grading tab

- **Mark a submission as the activity's reference solution.** A new
  `referencesnapshotid` on the activity records which snapshot is the model
  answer; the grading detail offers "Mark as reference solution" / "Remove
  reference solution" and shows a badge on the reference itself. Backed up and
  restored (the pointer is remapped to the restored snapshot).
- **Automatic scoring suggestion in the grading tab.** When a reference is
  marked, other submissions show the `reference` scorer's suggestion — overall
  match percentage, a rough suggested grade, per-dimension part scores, and the
  matched / missing / extra concepts and propositions. It is explicitly a
  suggestion; the teacher still sets the grade. Wired through a new
  `assess_service` that turns a snapshot into a submission and runs the scorer.
- **Verified on a real Moodle 4.5.12 instance:** `mod_vimipad` 143 tests
  (incl. a new `assess_service` test and a reference-remap assertion in the
  backup/restore roundtrip) and `vimipadassess` 17 tests pass; phpcs, phpcpd,
  savepoints, validate and mustache are clean.

## 0.5.25 (2026072705) — assessment subplugin type + reference scorer

- **New subplugin type `vimipadassess`** for automatic scoring, alongside the
  display-oriented `vimipadform` type. The contract lives in
  `classes/local/assess/`: a `submission` value object (concepts + propositions
  distilled from a snapshot), an exchangeable `matcher` interface with a
  normalised `exact_matcher`, a `result` (suggested score + matched/missing/extra
  breakdown), an abstract `scorer`, and a `registry` that discovers installed
  scorers. Scoring always yields a *suggestion*, never a set grade.
- **First scorer `vimipadassess_reference`** — compares a submission against a
  reference solution by concept and proposition (source–relation–target)
  overlap, with precision/recall F1 per dimension, directional proposition
  matching and a relation-label contribution. The matcher is injected, so the
  same scorer will later work with fuzzy/semantic matching.
- **Verified on a real Moodle 4.5.12 instance**, not just statically: the full
  `mod_vimipad` suite (138 tests) and the `vimipadassess` tests (17, incl. the
  core privacy provider check and registry discovery) pass; phpcs, phpcpd,
  savepoints, validate and mustache are clean.
- Still to come (Ausbaustufe 3, part 2): marking a snapshot as the activity's
  reference solution and surfacing the scorer's suggestion in the grading tab.

## 0.5.24 (2026072704) — advanced grading fixes, verified on a real instance

Set up a real Moodle 4.5.12 + PostgreSQL PHPUnit environment and ran the suite,
which surfaced two bugs that syntax checks alone could not:

- **`gradeitems` now implements the correct interfaces.** 0.5.21/0.5.23 had the
  class *extend* `component_gradeitems`; the grading manager checks for classes
  that *implement* `core_grades\local\gradeitem\itemnumber_mapping` and
  `advancedgrading_mapping`. It now implements both (with the no-argument
  `get_itemname_mapping_for_component()` and `get_advancedgrading_itemnames()`),
  so the advanced-grading debugging notice is genuinely gone. The now-dead
  `grading_areas_list` callback and its string were removed.
- **No duplicate grading backup step.** `backup_activity_task` /
  `restore_activity_task` already add the grading structure step automatically,
  so the manual copy added in 0.5.22 produced a duplicate `grading.xml`
  ("file already exists") and broke backup. Removed; the plugin's own
  `vimipad_gradeinstance` backup/restore and itemid realignment remain.

Full plugin PHPUnit suite: 110 tests, 881 assertions, all passing.

## 0.5.23 (2026072703) — fix advanced grading fatal

- **Correct the `component_gradeitems` signature.** The class added in 0.5.21
  declared `get_itemname_mapping_for_component(): array`, but the core abstract
  method is `get_itemname_mapping_for_component(string $component): array`. The
  mismatched signature was a fatal error that aborted the whole PHPUnit run. The
  method now accepts the `$component` argument.

## 0.5.22 (2026072702) — advanced grading in backup & restore

- **Rubric / marking guide definitions survive backup and restore.** The backup
  now includes the core `backup_activity_grading_structure_step` and the restore
  the matching `restore_activity_grading_structure_step`, so the grading form
  itself is preserved across course backup, restore and import.
- **Per-submission filling links are carried over.** The `vimipad_gradeinstance`
  table is backed up and restored (rater remapped as a user, grading instance
  remapped via the grading_instance mapping), and each restored grading
  instance's itemid is realigned to its new snapshot in `after_execute`. The
  grading step is restored first so its mapping is available. Missing pieces are
  skipped defensively rather than left dangling.
- Needs a real backup → restore → verify test: the definition path follows the
  standard core steps, but the filling relink (instance/itemid remapping) can
  only be confirmed on a live restore. The numeric grade and gradebook value are
  preserved regardless.

## 0.5.21 (2026072701) — advanced grading CI fix

- **Implement `component_gradeitems`.** Enabling advanced grading in 0.5.19 made
  Moodle's grading manager emit a `debugging()` notice ("Components supporting
  advanced grading should be updated to implement the component_gradeitems
  class") whenever a module instance was created — which PHPUnit treats as a
  failure, so every test that created an activity errored. Adding
  `mod_vimipad\grades\gradeitems` (mapping grade item 0 to the `submissions`
  area) satisfies the grading manager and silences the notice. No behaviour
  change to the gradebook item itself.

## 0.5.20 (2026072700) — grading through the rubric

- **Grade with the rubric / marking guide.** When an advanced grading method is
  active, the submission's grading detail now shows the editable rubric (a
  moodleform grading element); saving derives the grade from the rubric filling
  and stores it as the grade. The filling is remembered per submission and
  grader (new `vimipad_gradeinstance` table, itemid = snapshot) so re-opening a
  grade shows the previous filling.
- **Additive and safe by design.** The rubric path engages *only* when a method
  is defined; with no method active the numeric grade path is unchanged. Privacy
  covers the new table (metadata, per-user and context deletion via cleanup).
- **Known limitation:** advanced-grading rubric fillings are not yet included in
  course backup/restore (the numeric grade and gradebook value are). Backup of
  the grading area is a later, separately tested step.
- Needs verification on a live instance (the advanced-grading instance lifecycle
  cannot be exercised without one). Run the DB upgrade and clear caches.

## 0.5.19 (2026072699) — advanced grading (define & preview)

- **Core advanced grading enabled.** The activity now declares
  `FEATURE_ADVANCED_GRADING` and a `submissions` grading area, so Moodle's
  standard "Advanced grading" administration appears and teachers can define a
  rubric or marking guide the usual way.
- **Rubric shown in the grading tab.** When an advanced grading method is active,
  its definition is rendered read-only in the submission's grading detail as a
  reference while grading.
- Grading *through* the rubric (storing the rubric filling and deriving the
  grade from it) is the next step — it needs a stored grading-instance reference
  (a small schema addition) and the grading-form submit lifecycle. For now the
  numeric grade still records the grade.

## 0.5.18 (2026072698) — grading in the tab + CI fixes

- **Grading moved into the Grading tab.** Selecting a submission now opens the
  full grading detail inside the tab — read-only snapshot, existing annotations,
  teacher-visible journal, add-annotation form, AI feedback draft and the grade
  form — with a "Back to submissions" link. The logic lives in a reusable
  `grading_panel`; the legacy `grade.php` now redirects to the tab so old links
  keep working.
- **CI fixes.**
  - phpcpd: removed four clones — the consensus externals now share a
    `consensus_context`/`consensus_result` helper, `get_revision_state` reuses
    `get_workspace`'s node/relation mappers, and the submit cut-off/lock/re-read
    is extracted to `snapshot_service::begin_submission` (shared by direct and
    consensus submission). No clones remain.
  - PHPUnit: the reconstruction test used malformed stable ids; it now uses
    valid `node_`/`rel_` ids so the operation replay validates.

## 0.5.17 (2026072697) — assessment architecture & grading metrics

- **Assessment architecture documented.** `docs/design/assessment_architecture.md`
  evaluates the established automatic concept-map assessment methods (Kit-Build
  FMS/SMS, NLP/LLM, graph metrics, reference-free indices, fuzzy weights,
  OpenIE, peer-matrix, and form-specific methods for mindmaps, argument maps,
  causal loops, knowledge graphs) against the plugin's constraints, and fixes
  the hybrid decision: manual workflow, annotations, AI draft, core
  `gradingform` and structure metrics stay fixed in core; automatic scorers
  become a `vimipadassess` subplugin type with a fuzzy-ready (0..1 weighted),
  profile-aware scorer contract and an exchangeable matcher. Staged: grading
  tab → gradingform → assess registry → `reference` scorer → further scorers.
- **Structure metrics in the grading tab.** The submissions list now shows the
  concept/relation counts per submission (batched queries) as a grading aid —
  deliberately an aid only: structural metrics never set a grade on their own.

## 0.5.16 (2026072696) — journal revision viewer

- **See the map as it stood.** Each journal entry now has a "Show editing state"
  button that renders the map read-only as it was at that entry's revision,
  reconstructed from the operation log. The viewer offers both the canvas and
  list views and lays the graph out automatically (past positions are not
  stored).
- Built as an isolated `mod_vimipad/revision` bootstrap that mounts a read-only
  `RevisionViewer` from the editor bundle, kept separate from the editor
  bootstrap so it cannot affect editing. Reuses the canvas and relation-list
  renderers. New `getRevisionState` client call, covered by tests.

## 0.5.15 (2026072695) — journal revision reconstruction (backend)

- **Rebuild a map at a past revision.** New `reconstruction_service` replays the
  operation log up to a target revision to reproduce the exact node/relation
  topology at that point (the log stores server-assigned stable ids, so the
  replay is faithful). Deleted nodes and their relations drop out.
- **Revision captured per journal entry.** Journal entries now record the
  workspace revision at the time of writing (the `revisionref` field is finally
  populated), and each entry shows which revision it refers to.
- **Web service.** `get_revision_state` returns the reconstructed state in the
  same shape as `get_workspace` (read-only, auto-laid-out), with own-vs-foreign
  access control. Covered by a unit test. The in-editor viewer that renders this
  state for an entry follows in the next stage.

## 0.5.14 (2026072694) — consensus UI & notifications

- **Consensus flow in the Journal & submission tab.** For group activities with
  consensus, the submission area now follows the state machine: when open, a
  "Start submission process" button; while voting, a member overview (avatar,
  profile and message links, confirmed/pending badge) with an "I agree" checkbox
  and a "Confirm submission" button — becoming "Submit for grading" for the last
  member — plus a red outline "Cancel process" button. Direct (non-consensus)
  submission keeps its single button.
- **System notifications.** A new `consensus` message provider notifies group
  members when the process is started, cancelled or completed, via Moodle
  messaging (`db/messages.php` + `consensus_notifier`). This carries the
  "liveness" so the overview itself stays server-rendered.

## 0.5.13 (2026072693) — consensus state machine (backend)

- **Explicit consensus state machine.** New `consensus_service` models group
  submission as `open → voting → submitted`, with cancel returning to `open`.
  The state is derived from existing data (a locked workspace is submitted, an
  existing confirmation means voting), so no schema change is needed. Starting
  records the initiator's confirmation, confirming records a member's and
  finalises the snapshot once everyone has, and cancelling clears confirmations.
- **Web services.** Four AJAX functions — `start_consensus`,
  `confirm_consensus`, `cancel_consensus` and `get_consensus_status` — expose the
  machine, returning the state plus a per-member confirmation list. Guards reject
  acting out of turn, acting as a non-member, or when consensus is not enabled.
- The snapshot-creation core is extracted (`snapshot_service::finalize`) and
  shared by direct submission and the completed consensus flow. Covered by unit
  tests. The member overview UI and system messaging follow in the next stage.

## 0.5.12 (2026072692) — fullscreen canvas height fix

- **Fullscreen uses the full height again.** The viewport cap added in 0.5.9
  (`max-height: 60vh`, so the insert bar and journal stay visible) also applied
  in fullscreen, limiting the canvas to about 60% of the screen. Both fullscreen
  rules (native and the fixed-overlay fallback) now reset `max-height`, so the
  canvas fills the screen as intended.

## 0.5.11 (2026072691) — CI fixes for the tabbed UI

- **Behat updated for the new tabs.** The editor's Canvas/List switch is now a
  server tab, so the editor scenario follows the "List" tab link instead of a
  button; the grading scenarios open the "Grading" tab before acting on
  submissions. The "Submissions" heading is restored on that tab.
- **Generator fix.** The test generator resolved the module context via a
  property that isn't present on a raw instance record, breaking the Behat
  "submissions" setup; it now resolves the course module explicitly.
- **CI matrix.** `MOODLE_503_STABLE` is removed from the PHPUnit, Behat and
  release matrices until that branch is cut upstream (its clone currently fails
  before any plugin code runs). It should be re-added once Moodle 5.3 branches.

## 0.5.10 (2026072690) — Journal & submission tab (stage 1)

- **Journal & submission tab.** The tab now renders the workspace journal
  server-side: entries in collapsible, growing time buckets (this week, last
  week, this month, this year, older), each with the author's avatar, profile
  and message links, and date. Owners see their own entries; teachers inspecting
  a learner see the teacher-visible ones.
- **Submit moved out of the editor.** The submit button is gone from the React
  editor and now lives at the top of this tab (own map only); a locked map shows
  a submitted notice. Group consensus still resolves server-side, with a pending
  notice when not all members have submitted.
- Pure bucketing logic extracted to a tested helper (`journal_buckets`). The
  consensus state machine and the per-entry revision view follow in the next
  stages.

## 0.5.9 (2026072689) — editor surface finalised

- **Full-width insert bar.** The submit button no longer sits among the insert
  controls; the concept/relation controls now use the full width.
- **Capped canvas height.** The canvas is limited to a share of the viewport so
  the insert bar and journal stay visible without scrolling the whole page.
- **Submit relocated.** The submit button moves to the foot of the editor for
  now; it becomes part of the dedicated Journal & submission tab in the next
  step (which is also where the group consensus flow will live).

## 0.5.8 (2026072688) — single-view editor tabs + learner inspection

- **Editor tabs removed from React.** The Canvas/List switch that lived inside
  the editor is gone: each view is now driven purely by the surrounding Moodle
  tab, so the editor renders only the view its tab selected.
- **Teacher inspection of learner maps.** In individual mode, a teacher can pick
  a learner from a user selector and view their map read-only and live. The
  `get_workspace` service accepts a target user (grade capability required) and
  resolves it without creating a workspace (`find_for_user`); a learner with no
  map yet shows an empty read-only view.

## 0.5.7 (2026072687) — read-only foreign viewing

- **Read-only live viewing.** When a user opens a map that is not their own
  (in group mode, a group they do not belong to), the editor loads it read-only:
  the API client blocks every state-mutating web-service call at a single choke
  point, the submit/insert/import/journal affordances are hidden, and a notice is
  shown — while polling keeps the view live. `view.php` determines the read-only
  state and passes it to the editor.
- Test tidy-up: the hook render test now uses `React.act` instead of the
  deprecated `react-dom/test-utils` export.

## 0.5.6 (2026072686) — tabbed activity UI (shell)

First step of the reorganised activity surface: the tabs become the primary
structure, rendered server-side directly under the activity heading and menu.

- **Server-rendered tab bar.** `view.php` now presents role-gated tabs (Canvas,
  List, Journal & submission, Grading, Feedback, Tools). The active tab travels
  in the URL alongside the native group selection, so both persist across tabs
  and are shareable. The Canvas and List tabs mount the editor with the matching
  initial view; Grading keeps the submissions list; the remaining tabs are
  placeholders filled by later steps.
- Groundwork only: the deeper editor rework (foreign read-only viewing via a
  user selector, dynamic canvas height, moved submit button) and the Journal,
  Feedback and Tools tab contents follow in subsequent 0.6.x steps.

## 0.5.5 (2026072685) — deadlines & group consensus submission

- **Due & cut-off dates.** Activities can set an optional due date (submissions
  after it count as late) and cut-off date (submissions are blocked after it).
  New `duedate`/`cutoffdate` settings with validation (cut-off not before due).
- **Group consensus submission.** In group mode, an activity can require every
  group member to submit before the shared map is submitted (as with group
  assignments). Each member's readiness is recorded; the snapshot is created
  only once everyone has submitted, and the editor shows a waiting notice while
  consensus is pending. New `requireallteamsubmit` setting and
  `vimipad_submissionintent` table, covered by backup and the privacy provider.

## 0.5.4 (2026072684) — polling bandwidth, hook extraction, test fixes

- **Layout only when changed.** `poll_changes` now sends the layout JSON only
  when it changed since the client last saw it (the client passes back a
  `layoutsince` timestamp and receives `layouttime`), so an unchanged layout is
  no longer re-sent and re-applied on every poll.
- **Reusable dismiss hook.** The export dropdown's outside-click / Escape
  dismissal was extracted from CanvasView into a tested `useDismiss` hook.
- **Query efficiency follow-through and test fixes.** Corrected the completion
  test setup (the min-nodes rule is now enabled at module creation), simplified
  three PHPDoc parameter types that the doc checker could not parse, and removed
  duplicated setup between the two import round-trip tests.

## 0.5.3 (2026072683) — query efficiency (view, report, grade)

- **No more per-row user lookups.** The submissions list (view), the by-user and
  overview tables (report) and the teacher-visible journal (grade) now fetch all
  the user records they need in a single batched query instead of one query per
  row.
- **SQL aggregation for the workspace report.** `workspace_summary` counts
  operations per type and per user with `GROUP BY` queries rather than loading
  every operation row into memory, so the report scales to large workspaces.

## 0.5.2 (2026072682) — layout import, canvas split, polling scale

- **Layout import.** Import now also restores the layout (node positions and
  sizes), remapped onto the freshly assigned stable ids, for both JSON and XML
  exports. Containers/memberships remain out of scope (a dormant schema feature
  nothing yet produces or consumes).
- **Canvas refactor.** The pure label/shape render helpers were extracted from
  CanvasView into `canvas/shapes.tsx` with their own unit tests, continuing the
  behaviour-preserving decomposition begun in 0.5.1.
- **Polling scalability.** `poll_changes` now returns operations in bounded
  batches with a `hasmore` flag (the client advances only to the last received
  operation and re-polls promptly, never skipping a backlog), and expired-lease
  cleanup runs occasionally rather than on every poll.

## 0.5.1 (2026072681) — import (XML, replace), reopen, refactor

- **XML import.** Import now accepts XML exports as well as JSON (the format is
  auto-detected); shared parsing/creation logic with a round-trip test.
- **Import modes.** An import can *append* (default) or *replace* the current
  map; replace removes the existing nodes/relations first, through the operation
  path. A "Replace existing map" checkbox sits next to the import control.
- **Reopen for revision.** A teacher can unlock a submitted workspace from the
  grading page so its owner can edit and submit again; the existing snapshot is
  kept. New `workspace_service::reopen`.
- **Internal refactor.** The pure canvas geometry helpers (node sizing, edge
  boundary points, connector routing) were extracted from CanvasView into
  `canvas/node_geometry.ts` with their own unit tests, trimming the component.

## 0.5.0 (2026072680) — 0.5.x line begins: import

First feature of the 0.5.x line, on top of the fully hardened 0.4.x base.

- **Import.** The counterpart to export: a JSON export document can be imported
  into a workspace. Nodes and relations are appended through the validated
  operation path (so revisions advance and collaborators see them), get fresh
  stable ids, and relations are remapped onto the imported nodes. The whole
  import is atomic. New `import_service`, `mod_vimipad_import_map` external, an
  "Import" control in the editor, and an export→import round-trip test.

## 0.4.22 (2026072679) — 0.4.x feature-complete, hardened + consolidated

Full editor and collaboration (0.4.3–0.4.16): real-time collaborative canvas and
relation list view, undo/redo, automatic layout, canvas overlays and full-screen
view, group/course workspace switcher, export (JSON, XML, SVG, PNG, PDF), an
edit-activity report, a learner journal with a teacher-visible view, annotations
targetable at the whole map or at individual concepts/relations, and an optional
companion-channel link.

Hardening (0.4.17–0.4.21), following an external review:

- **Security:** AI-feedback drafts can only be accepted scoped to their own
  snapshot; a foreign draft can no longer be overwritten.
- **Backup/restore:** activity grade/completion settings, grades (with snapshot
  remap) and journal entries are now backed up and restored; round-trip tested.
- **Privacy:** the provider discovers, exports and deletes/anonymises every
  personal reference it declares (nodes, relations, operations, snapshots,
  annotations, AI feedback, journal, layout, grades, locks), including shared
  contributions.
- **Concurrency:** workspace creation and snapshot submission are serialized via
  the core lock API; layout saves merge per node so concurrent moves no longer
  clobber each other.
- **Grading/completion:** course-wide grades reach all participants; the submit,
  minimum-concepts and graded completion rules resolve the user's workspace
  uniformly across individual, group and course modes.
- **Operation contracts:** every operation payload is validated per type (field
  types, the relation direction enum, relation metadata JSON) and unknown fields
  are rejected.
- **Consolidation (0.4.22):** version metadata aligned across `version.php` and
  `package.json`; README status updated; the diagram-profile list is now sourced
  from the subplugin registry instead of a hard-coded list; the release CI
  workflow unified with the development one (bundle reproducibility, typecheck
  and Jest gates, the bundle-preserving Grunt step) and the Moodle 4.5–5.3 test
  matrix (adding 5.2 and 5.3).

---


## 0.1.0 (2026072500) — Session 001

Initial installable plugin shell with full project infrastructure.

- Activity module skeleton: `version.php`, `lib.php`, `mod_form.php`, `view.php`,
  `index.php`, `db/install.xml` (instance table), `db/access.php` (capability set
  per Pflichtenheft 3.2).
- Settings form: diagram profile (5 MVP profiles), working mode
  (individual/group/course), AI assistance toggle.
- `course_module_viewed` event, view completion tracking.
- Backup/restore (moodle2) for the instance table incl. intro files and
  link en-/decoding.
- Privacy null provider (placeholder; must become a full provider with the first
  user-data table).
- lang/en + lang/de.
- Tests: generator, lifecycle test (create/delete), privacy test.
- Infrastructure from reference stub, renamed and adapted: makefile, phpcs.xml,
  CI workflows (matrix extended to Moodle 5.2 / PHP 8.3), tools/, docs/ session
  workflow, concept documents in `docs/materials/`.

### Doc-Nachtrag (Session 001)

- `docs/materials/erweiterungsideen_bewertung.md`: Wettbewerbsbefund und
  Machbarkeitsbewertung von vier Erweiterungsideen (qtype/datafield/block/
  format, Peer-Review-Phasenmodell, Journal/Backchannel, Mobile/Vollbild)
  samt Architekturkonsequenzen für M1/M2.

### Konventions-Nachtrag (Session 001)

- Architektur: keine local-Plugins; gemeinsamer Code via
  \mod_vimipad\api\* / \mod_vimipad\profile\* (stabil) und
  \mod_vimipad\local\* (intern). Satelliten via dependency.
- README.md auf das Moodle-an-Hochschulen-README-Template umgestellt;
  Template gilt verbindlich für alle künftigen READMEs des Projekts.

## 0.2.0-start (2026072600) — Session 002 start: phpcs-Fix + M1 (Datenmodell)

### Fixed
- Alle 12 CI-gemeldeten phpcs-Verstöße behoben: Leerzeile nach
  Klassen-öffnender Klammer (PSR12.Classes.OpeningBraceSpace) in 11 Dateien;
  unnötige MOODLE_INTERNAL-Checks in lib.php und den beiden backup-stepslib-
  Dateien entfernt (moodle.Files.MoodleInternal.MoodleInternalNotNeeded).
  Verifiziert mit moodlehq/moodle-cs (Standard "moodle"): 0 Verstöße.

### Added (M1 — Domänenmodell, Schritt 1)
- Vollständiges Domänenschema in db/install.xml: vimipad_workspace, _node,
  _relation, _container, _membership, _layout, _operation, _snapshot,
  _annotation, _aifeedback sowie _journalentry (Journal-Entscheidung aus
  Session 001). Stable-IDs als eigene Spalten neben den DB-IDs; Soft-Delete
  für Knoten/Relationen; Snapshot-Status als Phasenfeld (0=draft .. 4=returned)
  für den späteren Peer-Review-/Workshop-Workflow.
- db/upgrade.php mit idempotentem M1-Schritt (install_one_table_from_xmldb_file
  je Tabelle) ab Vorversion 2026072500.
- Namespace-Architektur etabliert (keine local-Plugins):
  - \mod_vimipad\local\id\stable_id — interner Generator/Validator.
  - \mod_vimipad\api\ids — öffentliche, stabile Fassade für Satelliten-Plugins.
- Unit-Tests: tests/stable_id_test.php (Generierung, Eindeutigkeit,
  Validierung, Fassade).
- version.php auf 2026072600 / release 0.2.0.

## 0.3.0 (2026072601) — Session 002: Domänen-Services + External Functions + Voll-Privacy

### Added (M1 — Domänenmodell, Schritt 2)
- \mod_vimipad\local\service\workspace_service: löst/erzeugt Workspaces je nach
  Bearbeitungsmodus (individuell/Gruppe/Kurs) mit Capability- und
  Gruppenprüfung; get_state() liefert den vollständigen Bearbeitungsstand.
- \mod_vimipad\local\operation\operation_type: typisierter Operationskatalog
  (node/relation create/update/delete, relation_retarget) mit Payload-
  Schemavalidierung.
- \mod_vimipad\local\service\operation_service: serverautorisierte Anwendung in
  einer DB-Transaktion, Revisionsvergabe, optimistische Konfliktprüfung,
  Operation-Log, Soft-Delete inkl. Kaskade auf angehängte Relationen.
- External Functions mit voller Prüfkette (validate_context, Capability,
  Workspace-Zugehörigkeit, Gruppenzugriff, strikte Parametertypen,
  PARAM_RAW-Payload nur schema-validiert):
  mod_vimipad_get_workspace (read), mod_vimipad_apply_operation (write);
  registriert in db/services.php (ajax=true).
- Voll-Privacy-Provider: metadata (alle nutzerbezogenen Tabellen + core_ai-Link),
  get_contexts_for_userid, get_users_in_context, export_user_data,
  delete_data_for_all_users_in_context, delete_data_for_user,
  delete_data_for_users. Eigene Maps werden gelöscht, Beiträge zu geteilten
  Maps anonymisiert, Journaleinträge (persönlich) gelöscht.
- lib.php: vimipad_delete_instance kaskadiert nun über alle Domänentabellen.
- Sprachstrings EN/DE für Fehlermeldungen und alle Privacy-Metadaten.
- Tests: operation_service_test (Revision, Konflikt, Referenzprüfung,
  Node-Delete-Kaskade, Lock, unbekannter Typ).
- version 0.3.0.

### Verified
- moodlehq/moodle-cs (Standard "moodle"): 0 Fehler, 0 Warnungen über das
  gesamte Plugin.

## 0.4.0 (2026072602) — Session 002: Backup/Restore des Domänenmodells

### Added
- Backup/Restore erfasst nun das vollständige Domänenmodell (nicht mehr nur die
  Instanztabelle): workspaces, nodes, relations, containers, memberships,
  layouts, operations, snapshots, annotations, aifeedback, journalentries.
  User-generierte Inhalte nur bei aktivem userinfo-Setting.
- Korrektes ID-Mapping: Parent-FKs über Verschachtelung, User- und Gruppen-
  Remapping, Vorwärtsreferenz workspace.submittedsnapshotid in after_execute
  aufgelöst. Stable-IDs (source/target/targetstableid/itemstableid) bleiben
  bewusst unverändert — ein Kernvorteil des Stable-ID-Designs bei Restore.
- Tests: backup_restore_test (voller Roundtrip inkl. Snapshot-/Annotation-/
  Vorwärtsreferenz-Prüfung; Backup ohne userinfo).
- version 0.4.0.

### Verified
- moodlehq/moodle-cs (Standard "moodle"): 0 Fehler, 0 Warnungen.

## 0.5.0-pre (2026072603) — Session 002: M2 Frontend (Editor-Grundgerüst)

### Added
- React/TypeScript-Editor als einbettbare Komponente mit stabilem
  mount(element, config)-Kontrakt und injizierbarem Transport (der Schnitt für
  spätere qtype/datafield-Satelliten). Quellen unter js/src/:
  types, api/service (ApiClient + fetch-Transport gegen Moodle-AJAX),
  store/reducer (optimistische Anwendung), components/EditorApp +
  RelationListView, mount.tsx (Entry + Selbst-Bootstrap aus #vimipad-editor-root).
- MVP-Funktion: Workspace laden, Begriffe/Relationen anlegen, Relationen löschen
  über get_workspace/apply_operation; optimistische UI mit Server-Revisions-
  abgleich und Rollback (Reload) bei Konflikt/Fehler. Listenansicht als
  gleichberechtigte, tastatur- und mobiltaugliche Editoroberfläche; grafischer
  Canvas als Platzhalter (spätere Milestone).
- Dev-Toolchain (nur Entwicklung, NICHT auf Produktion nötig): package.json,
  tsconfig.json, build.mjs (esbuild → js/build/vimipad-editor.js, React
  gebündelt). Vorgebauter Bundle wird mit ausgeliefert.
- view.php lädt den vorgebauten Bundle via $PAGE->requires->js; #vimipad-editor-root
  trägt data-cmid.
- thirdpartylibs.xml dokumentiert das gebündelte React (MIT).
- Editor-Sprachstrings EN/DE.
- Release-/Dev-Trennung: node_modules + Dev-Quellen via .gitattributes
  export-ignore aus dem Release-ZIP ausgeschlossen; js/build bleibt enthalten.
- version 0.5.0.

### Notes
- Uniformer Ladepfad (vorgebauter Bundle + Selbst-Bootstrap) deckt Moodle
  4.5-5.2 ab. Progressive Enhancement zu nativem React-Autoinit (5.2+) und
  Moodle-React-Runtime (5.3) ist ein späterer, additiver Schritt.
- moodle-cs (PHP): 0 Fehler/0 Warnungen. TypeScript: tsc --noEmit sauber.

## 0.6.0 (2026072604) — Session 002: M3 Canvas + Drag-and-drop-Listenansicht

### Added
- Grafischer Canvas (js/src/components/CanvasView): SVG mit verschiebbaren
  Knoten (Pointer-Drag, Speicherung erst bei Drop) und gerichteten Relationen;
  deterministisches Auto-Layout (js/src/graph/autolayout), gespeicherte
  Positionen haben Vorrang.
- Listenansicht mit Retarget: Relation per Dropdown-Editor (tastaturbedienbar)
  ODER per HTML5-Drag-and-drop (Begriffs-Chip auf Subjekt-/Objektzelle) auf ein
  anderes Subjekt/Objekt umhängen. Erfüllt die Accessibility-Pflicht
  „jede DnD-Operation braucht Tastaturalternative". Nutzt die bestehende
  relation_retarget-Operation.
- View-Umschalter Canvas/Liste in EditorApp; gemeinsamer optimistischer State.
- Layout-Persistenz als eigener, NICHT revisionierter Pfad (Designentscheidung:
  Layout ist Präsentationszustand, gehört nicht ins Operation-Log):
  * \mod_vimipad\local\service\layout_service (Upsert je workspace+profile).
  * External Function mod_vimipad_save_layout; get_workspace liefert nun
    profile + layoutjson.
  * \mod_vimipad\local\access: gemeinsamer Edit-Zugriffs-Helper (apply_operation
    und save_layout nutzen ihn; Duplikat aus apply_operation entfernt).
- Privacy: vimipad_layout in Metadaten und Anonymisierung aufgenommen.
- styles.css für Canvas/Listenansicht (von Moodle automatisch geladen).
- Tests: PHPUnit layout_service_test (save/upsert/per-profile);
  JS/Jest reducer.test (Reducer + Auto-Layout, 6 Tests, grün).
- Dev-Toolchain um Jest erweitert (js/tests, nur Entwicklung).
- version 0.6.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc --noEmit sauber. Jest: 6/6 grün.

## 0.7.0 (2026072605) — Session 002: M4 Abgabe, Bewertung, Gradebook, Completion

### Added
- Snapshot-Abgabe: \mod_vimipad\local\service\snapshot_service erzeugt aus dem
  Workspace einen unveränderlichen, normalisierten Snapshot (nodes, relations,
  containers, layout, profile, revision, metadata), sperrt den Workspace und
  setzt submittedsnapshotid. Statusmodell 0..4 (draft..returned).
- External Function mod_vimipad_create_snapshot (Submit-Button im Editor);
  Completion-Update bei Abgabe.
- Teacher Snapshot Viewer + Bewertung als serverseitige Seite grade.php
  (barrierefrei, ohne JS-Abhängigkeit): Snapshot read-only als Relationstabelle,
  Annotationen hinzufügen, Note + Feedback erfassen. view.php zeigt Lehrenden
  eine Abgabenliste, Lernenden den Editor.
- Gradebook-Integration: FEATURE_GRADE_HAS_GRADE aktiviert; lib.php mit
  vimipad_grade_item_update/_delete, vimipad_get_user_grades, vimipad_update_grades;
  Grade-Item bei add/update/delete_instance. \mod_vimipad\local\service\grading_service
  (Upsert vimipad_grade, Push ins Gradebook, Snapshot-Status graded;
  Gruppenmodus: Note an alle Gruppenmitglieder).
- Completion-on-submit: FEATURE_COMPLETION_HAS_RULES; custom_completion-Klasse;
  mod_form add_completion_rules/completion_rule_enabled; Bewertungssektion via
  standard_grading_coursemodule_elements.
- Schema: Instanzfelder grade + completionsubmit; neue Tabelle vimipad_grade;
  upgrade.php-Schritt 2026072605.
- Privacy: vimipad_grade in Metadaten, Lösch- und Anonymisierungspfaden.
- Backup/Restore: vimipad_grade inkl. User-/Grader-Mapping und snapshotid als
  Vorwärtsreferenz (after_execute).
- Frontend: Submit-Button mit Bestätigung; api.createSnapshot.
- Sprachstrings EN/DE; version 0.7.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc --noEmit sauber. Jest 6/6 grün.

## 0.8.0 (2026072606) — Session 002: M5 KI-Feedback (Teacher-in-the-loop)

### Added
- \mod_vimipad\local\service\ai_feedback_service: erzeugt Feedbackentwürfe
  ausschließlich über das Moodle-AI-Subsystem (\core_ai\manager->process_action
  mit generate_text-Action), nie über Provider direkt. Strikt Teacher-in-the-loop:
  Entwurf wird erst nach aktiver Prüfung und Übernahme durch Lehrende zum Feedback.
  * build_prompt: datenminimierter Prompt (Aufgabe, Profil, kompakte
    Relationstabelle, Punkte, Lehrernotizen, Zieltonalität, explizites
    Halluzinationsverbot); KEINE Lernenden-Namen/-IDs, keine Stable-IDs.
  * generate_text: defensiver Aufruf mit Fehlerbehandlung; extrahiert
    generatedcontent + Provider-Info aus get_response_data().
  * store_draft/accept_draft/get_latest; Prompt-Speicherung nur bei Admin-Opt-in.
  * is_available (core_ai vorhanden, Site-Setting, Aktivitäts-Toggle, useai);
    policy_accepted (AI-User-Policy via \core_ai\manager::get_user_policy_status).
- grade.php: KI-Sektion (Entwurf generieren mit Notizen, editieren, übernehmen);
  übernommenes Feedback belegt das Feedback-Feld der Bewertung vor.
  Alle KI-Aktionen capability- (useai), context- und sesskey-geprüft, Policy-Gate.
- settings.php: Admin-Schalter enableai (global) und storeprompts (Prompt-
  Speicherung, Default aus).
- Sprachstrings EN/DE inkl. Prompt-Bausteine; version 0.8.0.

### Verified
- moodle-cs (PHP): 0 Fehler/0 Warnungen. tsc/Jest unverändert grün.
- AI-API gegen moodledev.io/4.5 verifiziert (process_action → response_base,
  get_response_data, AI-User-Policy at point of use).

### MVP-Status
Der MVP-Kernworkflow ist damit vollständig: Bearbeiten (Canvas + Liste, DnD) →
Abgeben (Snapshot) → Bewerten (Annotation + Note + Gradebook) → KI-Feedback.

## 0.8.1 (2026072607) — Bugfix: Editor wurde nicht geladen

### Fixed
- Der React-Editor erschien nicht: view.php band das Bundle mit
  $PAGE->requires->js(url, true) (in den <head>) NACH dem bereits ausgegebenen
  Header ein — Moodle verwarf das Head-Script, der Platzhalter blieb stehen.
  Fix: js()-Registrierung vor $OUTPUT->header(), ohne inhead-Flag.
- Editor wird jetzt auch Lehrenden angezeigt (Vorschau unter der Abgabenliste),
  nicht nur Lernenden — erleichtert Test/Abnahme.
- Mount robuster: Fehler beim Start werden sichtbar im Platzhalter angezeigt
  (statt stummem Verbleib); fehlende data-cmid wird gemeldet.
- Fallback-Strings vervollständigt (Tab-Labels etc. wurden zuvor als rohe Keys
  angezeigt). Editor liest Moodle-Strings via M.str.mod_vimipad
  (strings_for_js in view.php), mit englischem Fallback → DE-Lokalisierung wirkt.

### Verified
- Bundle real gegen jsdom getestet: montiert, ersetzt Platzhalter, rendert
  Canvas/Tabs/Knoten; DE-Strings werden aufgelöst. moodle-cs 0/0, tsc/Jest grün.

## 0.8.2 (2026072608) — CI-Härtung: alle lokalen Checks grün

### Fixed (auf echter Moodle-4.5-Umgebung mit PostgreSQL verifiziert)
- PHPUnit (3 echte Fehler behoben, jetzt 43 Tests / 791 Assertions grün):
  * privacy_test: an den Voll-Provider angepasst (der alte get_reason()-Test
    stammte noch vom Null-Provider); prüft nun get_metadata,
    get_contexts_for_userid, Export und Löschung. Typrobuster Kontext-Vergleich.
  * backup_restore_test: nach dem kanonischen Core-Muster neu geschrieben
    (restore_dbops::create_new_course, MODE_IMPORT, users-Setting NOT_LOCKED +
    set_value); behebt restore_controller_exception cannot_precheck_wrong_status.
- phpcs (moodle, --severity=1): Lang-Strings EN/DE streng nach SORT_STRING
  sortiert (21 Ordering-Warnungen behoben); Formatierung in den neuen Tests
  bereinigt. 0 Fehler / 0 Warnungen.
- phpcpd: Kaskadenlöschung von Workspaces in \mod_vimipad\local\cleanup
  extrahiert; der 20-Zeilen-Klon zwischen lib.php und provider.php ist entfernt
  ("No clones found").
- phpmd: ungenutzten Parameter $revision aus operation_service::mutate() und
  ungenutzte $course-Variablen aus get_workspace/apply_operation/save_layout
  entfernt.

### Verified toolchain
- Echte moodle-plugin-ci 4.5.10 gegen Moodle 4.5.12+ (PostgreSQL 16):
  phplint, phpcs(severity=1), phpmd, phpcpd, phpdoc, validate, savepoints,
  mustache, PHPUnit — alle ohne build-brechende Befunde.

## 0.9.0 (2026072609) — Behat: End-to-End-Absicherung der Kernworkflows

### Added
- Behat-Feature tests/behat/grading.feature (server-gerendert, ohne @javascript):
  Lehrende sehen die Abgabenliste, bewerten einen Snapshot, fügen Annotationen
  hinzu; Lernende sehen die Bewertungsoberfläche nicht.
- Behat-Feature tests/behat/editor.feature (@javascript): der React-Editor
  montiert (Platzhalter verschwindet), Canvas/Listen-Tabs sichtbar, Begriff
  hinzufügen und in der Liste wiederfinden. Läuft in der CI mit Browser.
- Behat-Datengenerator tests/generator/behat_mod_vimipad_generator.php:
  "the following mod_vimipad > submissions exist" seedet einen abgegebenen
  Snapshot ohne den JS-Editor.
- Generator um create_workspace() erweitert (Nodes + optional gesperrter,
  abgegebener Snapshot); PHPUnit generator_test verifiziert den Seed-Pfad.
- version 0.9.0.

### Verified (echte Moodle-4.5-Umgebung)
- Behat-Konfiguration gebaut, beide Features registriert; Dry-run: 6 Szenarien /
  58 Steps, KEINE undefinierten Steps (alle gegen Core-Steps auflösbar).
- Generator-Seed-Pfad real über PHPUnit getestet.
- PHPUnit 44 Tests / 796 Assertions grün. phpcs severity=1: 0/0.
  phpdoc/validate/phpcpd: sauber. Behat-Tags von moodle-plugin-ci erkannt.

### Hinweis
Die @javascript-Editor-Szenarien brauchen Chrome/Selenium und laufen in der CI;
die server-gerenderten Grading-Szenarien sind hier strukturell/step-validiert.
Der Built-in-PHP-Server ließ sich in der Sandbox nicht stabil für eine
Live-Behat-Ausführung halten — die Ausführung erfolgt in der Projekt-CI.

## 0.9.1 (2026072610) — AMD-Architektur für Nicht-React-Teile; Zielspanne 4.5–5.3

### Changed
- Zielspanne korrigiert: supported = [405, 503] (4.5 LTS bis 5.3). Ab Moodle 5.3
  bringt der Core die React-Runtime mit (react_autoinit); 4.5–5.2 nutzen weiter
  den mitgelieferten Editor-Bundle. Gegen moodle/main gegengecheckt (core/import,
  core/component appendToDom/prependToDom, grunt react/esbuild, "external"
  Runtime-Pakete).
- Idiomatische AMD-Architektur für alle Nicht-React-Teile: neues ES6-Modul
  amd/src/init.js (geladen via $PAGE->requires->js_call_amd), das Strings über
  core/str und einen AJAX-Transport über core/ajax auflöst und dann den separat
  gebündelten React-Editor lädt und montiert. amd/build/init.min.js mit Moodles
  echtem Grunt gebaut (reproduzierbar; CI-Diff-Prüfung besteht).
- view.php nutzt js_call_amd statt requires->js + strings_for_js. React-Bundle
  ohne Selbst-Bootstrap; die Initialisierung steuert nun das AMD-Modul
  (saubere Trennung, kein Doppel-Mount).

### Build & Packaging
- makefile: neue Targets `react` (esbuild → js/build), `build` (React + AMD),
  `lint-react` (tsc --noEmit), `test-react` (Jest); `amd` (Grunt → amd/build)
  greift jetzt, da amd/src gefüllt ist. `fix` baut beide Frontends, `check`
  prüft tsc + Jest + PHPUnit.
- .gitattributes: amd/src, js/src, js/tests und die gesamte Toolchain als
  export-ignore. Im Release verbleiben nur die Laufzeit-Artefakte
  amd/build/ und js/build/.

### Verified (echte Moodle-4.5-Umgebung)
- Grunt ESLint auf amd/src: sauber. Grunt amd-Build reproduzierbar (identisch).
- jsdom: AMD-getriebener Mount montiert den Editor, DE-getString wirkt,
  kein Auto-Mount ohne AMD-Aufruf.
- PHPUnit 44 Tests / 796 Assertions grün; phpcs severity=1: 0/0.
- makefile-Targets react/amd/build/lint-react/test-react real ausgeführt.

## 0.2.0 (2026072611) — MVP

Nominal-Version auf die MVP-Stufe gesetzt. Versionslogik: 0.2 = MVP (vollständiger
Kernworkflow), 1.0 = fertig getestetes, nutzerfreundliches, stabiles Produkt.
Der interne $plugin->version-Integer steigt weiter monoton (saubere Upgrades).

### Added
- MVP-Integrationstest (tests/mvp_integration_test.php): verifiziert reproduzierbar,
  dass Capabilities und External Functions installiert werden, eine Aktivität
  inkl. Gradebook-Item angelegt wird, die MVP-Feature-Flags greifen und das
  Löschen sauber aufräumt.

### MVP-Stand (vollständig, auf echter Moodle-4.5-Umgebung verifiziert)
- Editor (Canvas + Listenansicht, DnD + Tastaturalternative), idiomatisch über
  AMD (mod_vimipad/init) geladen, React separat gebündelt.
- Abgabe als unveränderlicher Snapshot; Lehrenden-Bewertung (Note, Feedback,
  Annotationen) mit Gradebook- und Completion-Anbindung.
- Teacher-in-the-loop KI-Feedback über das Moodle-AI-Subsystem.
- Vollständige Integration: Gruppen, Gradebook, Completion, Privacy, Backup/Restore.
- Tests: PHPUnit 47/809 grün, Jest 6/6, phpcs severity=1 0/0, AMD reproduzierbar,
  phpdoc/validate/savepoints/mustache sauber, Behat-Kernworkflows (Steps validiert).
- CLI-Site-Install lief erfolgreich durch (Plugin installiert sich sauber).

### Bekannte Grenzen bis 1.0
- @javascript-Behat-Editorszenarien laufen in der CI (Browser erforderlich).
- Roadmap 1.x: Rubric-Bewertung, Annotationen an Knoten/Relationen, React-Grading-UI
  (get_snapshot/save_annotation als External Functions), Canvas-Tastaturmodus,
  profil-spezifische Auto-Layouts, Peer-Review.

## 0.2.1 (2026072612) — Linting- und Build-Fixes

### Fixed
- version.php: zu lange Kommentarzeile (158 > 132 Zeichen) umgebrochen; phpcs
  severity=1 jetzt 0/0.
- makefile lint-react/test-react: riefen `npx tsc`/`npx jest` ohne node_modules-
  Prüfung auf. Ohne vorheriges `npm install` zog npx das falsche Fremdpaket
  (tsc@2.0.4) statt des lokalen TypeScript. Jetzt: node_modules wird bei Bedarf
  installiert und die lokalen Binaries (node_modules/.bin/tsc, .../jest) werden
  direkt aufgerufen — nie wieder ein Fremdpaket-Download.
- makefile lint-mustache: überspringt sauber, wenn kein templates/-Verzeichnis
  existiert (statt "directory not found"-Rauschen).

### Verified
- Frisches Auspacken ohne node_modules: `make lint-react` installiert die echten
  Dev-Abhängigkeiten und der Typecheck läuft grün; zweiter Lauf ohne Neuinstall.
- PHPUnit 47/809 grün, phpcs 0/0, AMD reproduzierbar.

## 0.2.2 (2026072613) — Entwickler-Doku: Verifikationsumgebung

### Added
- docs/dev/moodle-test-environment-setup.md: detaillierte, reproduzierbare
  Schritt-für-Schritt-Anleitung zum Aufsetzen einer echten Moodle-4.5-
  Verifikationsumgebung in der Sandbox (Systempakete, PHP/Locale-Konfig,
  PostgreSQL, Moodle-Clone, config.php, PHPUnit-Env, moodle-cs,
  moodle-plugin-ci, Grunt/AMD, Behat, Frontend) inkl. Fallstricke und
  Schnell-Referenz. Anleitung real durchgespielt und verifiziert.

### Changed
- docs/prompt-templates/sessionstart.txt: neuer verpflichtender Abschnitt E
  „Verifikationsumgebung ZUERST aufsetzen" (verweist auf die Anleitung); frühere
  Abschnitte umbenannt (Ziel = jetzt F). Zielspanne/Entwurfsentscheidung 5 auf
  den aktuellen Stand gebracht (4.5–5.3; React ab 5.3 im Core; AMD für alle
  Nicht-React-Teile).

## 0.2.3 (2026072614) — Bugfix: Behat @javascript (Editor lud nicht im Browser)

### Fixed
- CI-Behat-Fail "Javascript code and/or AJAX requests are not ready after 10
  seconds (mod_vimipad/init)": Ursache war das dynamische Nachladen des React-
  Bundles per injiziertem <script>-Tag. Das lief AUSSERHALB von Moodles JS-
  Tracking, sodass wait_for_pending_js nie auflöste. Zusätzlich ein toter
  onload-Selbstbezug (script.onload = script.onload.bind(...)).
- Fix: Das React-Bundle wird jetzt als benanntes AMD-Modul
  (mod_vimipad/editor_lazy) unter amd/build/ ausgeliefert und im init-Modul per
  require(['mod_vimipad/editor_lazy']) über Moodles Loader geladen — vollständig
  im JS-Tracking. mount.tsx exportiert sauber (default export) statt window-Global.
- build.mjs: baut das Bundle als AMD-Modul (esbuild-IIFE + define-Wrapper) nach
  amd/build/editor_lazy.min.js (kein js/build/ mehr). thirdpartylibs.xml und
  .gitattributes entsprechend angepasst.
- editor.feature: explizite Wartebedingungen ("I wait until 'Add concept'
  'button' exists") für robustes Warten auf den React-Mount.

### Verified
- AMD-Ladepfad (require editor_lazy -> mount) via jsdom: Modul registriert,
  mount() verfügbar, Editor montiert, Strings/Knoten gerendert.
- Grunt: init.min.js reproduzierbar, editor_lazy.min.js (third-party) unangetastet,
  voller grunt-Lauf exit 0. Behat dry-run: 6 Szenarien/59 Steps, keine undefinierten.
- PHPUnit 47/809, phpcs 0/0, tsc/Jest grün.
- HINWEIS: Der eigentliche @javascript-Browserlauf ist nur in der CI möglich
  (kein Browser in der Sandbox; Chromium nur als Snap-Wrapper vorhanden).

## 0.2.4 (2026072616) — Kollaboration Schicht 2: External Functions

### Added
- operation_service::get_operations_since(workspaceid, sincerevision): liefert
  Operationen nach einer Revision (aufsteigend) — Delta-Basis fürs Polling.
- External Functions (db/services.php):
  * mod_vimipad_poll_changes (read): Operationen seit Revision N + aktuelles
    Layout + aktive Leases (Presence) in einem Round-Trip.
  * mod_vimipad_acquire_lock / renew_lock / release_lock (write): Element-Leases.
- classes/external/helper.php: gemeinsame Workspace-/Edit-Access-Validierung und
  Lease-TTL-Lookup (hält die External Functions schlank, vermeidet Duplikate).

### Tested (real, Moodle 4.5 + PostgreSQL)
- collaboration_external_test: 6 Tests — inkl. Kernszenario (B abgewiesen, sieht
  Halter A), Renew nur durch Halter, Release gibt frei, poll liefert Delta+Layout+
  Presence, abgelaufene Leases werden ausgefiltert, Zugriffskontrolle greift.
- operation_service_test um get_operations_since erweitert.
- Gesamt: 68 Tests / 885 Assertions grün. phpcs severity=1: 0/0. validate sauber.

### Offen (nächste Schichten)
- Client (js/src): adaptive Poll-Schleife, Positions-Tweening, Lock-on-drag +
  Heartbeat, visuelle Sperr-/Presence-Anzeige; Jest-Tests.
- Behat: Kollaborations-Workflow (server-prüfbare Teile).

## 0.2.5 (2026072617) — CI-Fix: fehlendes Build-Artefakt + falsche @package-Tags

### Fixed
- CI brach in zwei Schritten ab (install/grunt ignorefiles und phpcs), weil
  .gitignore `amd/build/` komplett ausschloss. Dadurch war das eingebundene
  React-Bundle amd/build/editor_lazy.min.js NICHT im Repository, während
  thirdpartylibs.xml darauf verweist. grunt ignorefiles und der moodle-cs-
  Vendors-Check verlangen aber, dass jeder thirdpartylibs-Pfad existiert
  -> ENOENT / "non-existent path".
  Fix: amd/build/ wird nicht mehr ignoriert; die gebauten Laufzeit-Artefakte
  (init.min.js + editor_lazy.min.js) gehören eingecheckt (der Produktivserver
  hat keine Build-Tools; die CI validiert aus dem Repo).
- tools/fix_phpdoc.php und tools/mustache_check.php trugen noch den @package-Tag
  local_instantcoursecompletion (Copy-Paste-Rest der Vorlage). phpcs plugin
  (ohne tools/-Ausschluss) meldete das als Fehler. Auf mod_vimipad korrigiert.

### Verified
- moodle-plugin-ci phpcs --max-warnings 0: 56 Dateien, 0/0 (thirdpartylibs-
  Pfad existiert, @package korrekt).
- grunt ignorefiles/eslint/amd: exit 0, kein ENOENT.
- git check-ignore bestätigt: amd/build-Artefakte werden getrackt, node_modules/
  package-lock.json bleiben ignoriert.
- Regression: PHPUnit 68/885 grün, phpcs severity=1 0/0, validate sauber.

## 0.2.6 (2026072618) — Fix: External-Test-Basisklasse + phpcpd-Klon

### Fixed
- collaboration_external_test brach mit "Class externallib_advanced_testcase not
  found" ab: Diese Basisklasse liegt in webservice/tests/helpers.php und wird
  NICHT autogeladen. Fix nach Core-Muster: require_once(webservice/tests/
  helpers.php) + use externallib_advanced_testcase (globaler Namespace).
  (Der Fehler blieb bei gefiltertem Lauf verborgen, weil eine andere Testdatei
  die helpers zuvor lud — isolierte Läufe decken das auf.)
- phpcpd-Klon (37 Zeilen) zwischen renew_lock.php und release_lock.php beseitigt:
  die gemeinsame Element-Lock-Parameterdefinition liegt jetzt in
  helper::lock_parameters(); acquire/renew/release_lock delegieren dorthin.

### Verified
- ALLE 12 Testdateien EINZELN (isoliert, wie die CI) grün. Gesamt 68/885 grün.
- phpcpd: No clones found. phpcs severity=1 + moodle-plugin-ci phpcs
  --max-warnings 0: 0/0.

### Prozess-Lehre
- Ab jetzt jede Testdatei auch ISOLIERT laufen lassen (nicht nur --filter),
  um Autoload-Reihenfolge-Abhängigkeiten aufzudecken.

## 0.2.7 (2026072619) — CI: Frontend-Build-Schritt + JS-Lint-Fix

### Fixed
- CI-Blocker (phpdoc/phpcs "Vendors.php: non-existent path ... editor_lazy.min.js"):
  Die thirdpartylibs.xml verweist auf das gebaute React-Bundle amd/build/
  editor_lazy.min.js. Fehlt es im ausgecheckten Repo, bricht JEDER Check ab, der
  die Vendors-Validierung durchläuft — und blockiert damit auch Behat.
  Robuster Fix: Der CI-Workflow baut das Bundle jetzt selbst. In allen vier Jobs
  (lint-php, lint-js, phpunit, behat) läuft direkt nach dem Checkout ein Schritt
  "Build front-end bundle (esbuild → amd/build)" (npm install + node build.mjs)
  im plugin/-Verzeichnis, BEVOR moodle-plugin-ci das Plugin kopiert/prüft. Damit
  ist die Pipeline unabhängig davon, ob das Artefakt eingecheckt ist.
- JS-Lint (ESLint, --max-lint-warnings 0): überflüssige
  "eslint-disable-next-line no-undef" vor require() in amd/src/init.js entfernt
  (require ist in Moodles ESLint-Config bereits als Globale bekannt; die
  ungenutzte Direktive war eine Warnung = Fehler bei max-warnings 0). init.min.js
  neu gebaut.

### Verified
- Bewiesen: OHNE Bundle -> "Vendors.php line 67" (der CI-Fehler); nach dem
  Build-Schritt -> phpdoc/phpcs laufen durch. Build in sauberer Umgebung
  (ohne node_modules) real ausgeführt: Bundle wird erzeugt.
- ESLint auf init.js: 0/0. AMD reproduzierbar. phpcs 56/56. PHPUnit 68/885 grün.
- Behat-Dry-run läuft an (6 Szenarien, keine undefinierten Steps).

## 0.2.8 (2026072620) — editor_lazy.min.js in Auslieferung + Kollaborations-Client (Schicht 3)

### Fixed (CI-Dauerproblem an der Wurzel)
- URSACHE des wiederkehrenden "Vendors.php: non-existent path editor_lazy.min.js":
  Das gebaute Bundle amd/build/editor_lazy.min.js lag in den lokalen
  Referenz-Snapshots, weshalb der Patch-Diff es bei JEDER Auslieferung als
  "bereits vorhanden" wertete und ausschloss — es hat die Zielcodebase nie
  erreicht. Diese Auslieferung enthält editor_lazy.min.js daher EXPLIZIT
  (force-include), zusätzlich zum ohnehin vorhandenen CI-Build-Schritt.
- Mitgeliefert werden auch die Quellen zum Selberbauen: build.mjs, package.json,
  package-lock.json, tsconfig.json und der komplette js/src-Baum. Build:
  "npm install && node build.mjs" erzeugt amd/build/editor_lazy.min.js.

### Added (Schicht 3 — Kollaborations-Client, Logik Jest-getestet)
- collab/adaptive.ts    — adaptives Poll-Intervall (RTT/Aktivität), 8 Tests.
- collab/tween.ts       — weiches Positions-Tweening A→B, 6 Tests.
- collab/poll_client.ts — Poll-Schleife, Deltas, Presence, Heartbeat, 7 Tests.
- collab/lock_client.ts — acquire/renew/release + Heartbeat, 8 Tests.
- collab/apply_remote.ts— gepollte Server-Op → Reducer-Action, 7 Tests.
- collab/use_collaboration.ts — React-Hook, verdrahtet Poll/Lock in den Editor.
- Reducer: updateNode/updateRelation ergänzt (ferne Label-Änderungen).
- EditorApp/CanvasView: Poll-Schleife, Lock-on-drag-start, Presence-Anzeige
  ("wird bearbeitet", fremd-gesperrte Knoten visuell markiert).
- get_workspace liefert collab-Settings (Poll-Intervalle in ms, Lease-Timeout,
  Push-Flags); helper::collab_config() bündelt sie.
- Neuer String editor:beingedited (EN/DE).

### Verified
- Jest 42/42 grün, tsc sauber. phpcs (geänderte PHP) 0/0. PHPUnit 68/885 grün.
  ESLint auf init.js 0/0. init.min.js reproduzierbar. Bundle frisch gebaut.
- HINWEIS: Das visuelle Zwei-Browser-Zusammenspiel (Presence/Locking im Look&Feel)
  ist in der Sandbox nicht verifizierbar; die Logik ist hart getestet, die Optik
  bestätigst du im Browser.

## 0.2.9 (2026072621) — Fix: "No define call" im Debug-Modus (editor_lazy.min.js.map)

### Fixed (Laufzeitfehler in der Moodle-Instanz)
- Fehler "No define call for mod_vimipad/editor_lazy" beim Öffnen des Editors
  im Entwickler-/Debug-Modus (jsrev = -1, cachejs aus). Ursache: Moodles
  lib/requirejs.php liefert die minifizierte amd/build/*.min.js im Debug-Modus
  nur dann direkt aus, wenn eine ".map"-Datei daneben liegt. Fehlt sie, rewritet
  Moodle den Pfad auf amd/src/editor_lazy.js (existiert nicht, da React nicht
  über Grunt gebaut wird) -> leere Antwort -> RequireJS meldet "No define call".
- FIX: build.mjs erzeugt jetzt eine echte Sourcemap (editor_lazy.min.js.map).
  Damit bleibt Moodle im Debug- UND Produktionsmodus auf dem Build-Datei-Zweig
  und lädt das Modul korrekt. Die define-Hülle wird über esbuild banner/footer
  gesetzt, sodass die Sourcemap zum gewrappten Output passt.
- Verifiziert: benanntes define('mod_vimipad/editor_lazy') vorhanden;
  Moodle-Auslieferungslogik nachgestellt (map vorhanden -> Build-Datei);
  requirejs_fix_define lässt das benannte define unangetastet; jsdom-Ladeprobe
  registriert das Modul und findet mount().

### Added (Dokumentation)
- docs/dev/visual-maps-requirements.md: geplante Map-Typen (Familienbaum,
  Evolutionsbäume, Organigramme, Strukturgleichungsmodelle, IT-Architektur-
  Modelle, Programmablaufpläne) und Interaktions-Anforderungen (Verbinden,
  Connection-Darstellung/-Beschriftung, Hover/Auswahl/Menüs, Tastatur,
  Text-Edit). Arbeitsdokument, wird ergänzt.

## 0.2.10 (2026072622) — amd/src/editor_lazy.js mitgeliefert (Debug-Modus, robust)

### Added / Fixed
- amd/src/editor_lazy.js wird jetzt ausgeliefert. Moodle serviert im
  Entwickler-Modus (jsrev = -1) je nach Punktrelease entweder die Build-Datei
  (wenn .map vorhanden) ODER amd/src/editor_lazy.js. Mit BEIDEN Dateien plus
  der .map ist jeder Ladeweg abgedeckt -> "No define call" tritt in keinem
  Szenario mehr auf.
- build.mjs erzeugt nun drei Artefakte: amd/build/editor_lazy.min.js (+ .map,
  minifiziert, Produktion) und amd/src/editor_lazy.js (unminifiziert, lesbar
  im Debug-Modus). Beide tragen die benannte define("mod_vimipad/editor_lazy").
- thirdpartylibs.xml deklariert nun beide Dateien, damit ESLint/phpcs sie
  überspringen.

### Verifiziert
- jsdom-Ladeprobe für amd/src/editor_lazy.js: define korrekt, mount() vorhanden.
- Empirisch geprüft: "grunt amd" baut aus amd/src/editor_lazy.js eine
  FUNKTIONIERENDE amd/build/editor_lazy.min.js (define + mount ok) — falls du
  doch mit Moodles Grunt baust, bricht nichts. Mit "node build.mjs" bleibt die
  esbuild-Version maßgeblich.
- phpcs 56/56 (Vendors-Check mit beiden thirdparty-Pfaden grün), PHPUnit 68/885,
  tsc sauber, Jest 42/42.

### Hinweis
- amd/src/editor_lazy.js, amd/build/editor_lazy.min.js und
  amd/build/editor_lazy.min.js.map gehören zusammen — bitte alle drei einchecken.

## 0.2.11 (2026072623) — elang-Vorlagenreste bereinigen + Versionsnummer

### Fixed
- Das Plugin wurde ursprünglich aus dem elang-Plugin ("Hör-Garten") als Vorlage
  erstellt. Im Zielverzeichnis blieben elang-Reste liegen, die die reinen
  Patch-Auslieferungen (cp, nur Überschreiben) NICHT entfernen konnten:
    - version.php mit @package mod_elang und elang-Versionsnummer 2026072531
    - lang/de/elang.php, lang/en/elang.php (mit @package mod_elang)
  Diese lösten die phpcs-Fehler aus und die falsche/rückläufige Versionsnummer
  ("Höhere Version bereits installiert", da 2026072531 < installiertem
  2026072619).
- Der ViMi-Pad-Code selbst ist und war sauber: alle @package-Tags mod_vimipad,
  keine elang-Referenzen, nur lang/*/vimipad.php. Verifiziert mit exakt dem
  gemeldeten Kommando: phpcs --standard=moodle --severity=1 --ignore=tools/ .
  -> 0 Fehler.
- version auf 2026072623 (0.2.11) angehoben, > installiertem 2026072619, damit
  Moodle sauber aktualisiert.

### Auslieferung
- Diesmal VOLLSTÄNDIGES, sauberes Paket (kein Patch), damit ein Clean-Replace
  alle elang-Reste beseitigt. Wichtig: altes Verzeichnis vorher entfernen (siehe
  README), sonst bleiben Reste wie bei einem Patch liegen.

## 0.2.12 (2026072624) — Canvas-Interaktion: Auswahl, Tastatur, Inline-Edit

### Added (Interaktions-Anforderungen, erste Ausbaustufe)
- Interaktions-Zustandsmodell (canvas/interaction.ts, 12 Tests): Auswahl von
  Node/Connection, ESC demarkiert, Entf löscht Markiertes (nie im Edit-Modus),
  Doppelklick öffnet Inline-Edit.
- Connection-Geometrie (canvas/connection_geometry.ts, 13 Tests): getrennte
  Offsets für parallele Connections, Rand-Anker (rectBorderPoint), Label-Position
  am Kurvenscheitel, Marker-Wahl (Pfeil gerichtet / Knubbel ungerichtet).
- CanvasView verdrahtet: Klick markiert (Node/Connection), ESC/Entf per Tastatur,
  Doppelklick auf Node-Text -> Inline-Edit (Enter bestätigt, Shift+Enter neue
  Zeile), Auswahl-Hervorhebung, Connection-Beschriftung mit weißer Outline.
- EditorApp: node_delete, node_update (Umbenennen), relation_update verdrahtet.
- Reducer: updateNode/updateRelation (bereits vorhanden) genutzt.

### Verified
- Jest 67/67 (inkl. 25 neue), tsc sauber, stylelint sauber, Mount-Rauchtest ok.
- HINWEIS: Optik/Look&Feel der Interaktion im Browser zu bestätigen; Logik hart
  getestet.

## 0.2.13 (2026072625) — CI/Build-Architektur bereinigt (Grunt-Konflikt behoben)

### Fixed (Ursache der wiederkehrenden CI-Fehler)
- amd/src/editor_lazy.js ENTFERNT. amd/src ist Moodles eigenes AMD-Quell-
  Verzeichnis; Moodles "grunt amd" (rollup/babel) verarbeitet ALLES darin.
  thirdpartylibs.xml steuert nur die Lint-Ignores, entfernt die Datei aber NICHT
  aus der Rollup-Eingabemenge. Das dort platzierte esbuild-Bundle wurde daher von
  Grunt erneut verarbeitet und amd/build/editor_lazy.min.js überschrieben — der
  eigentliche Auslöser der CI-Fehlerkette (Grunt-Fehler, durch continue-on-error
  verdeckt, danach "Bundle fehlt" bei PHPDoc).
  Empirisch bestätigt: nach dem Entfernen lässt "grunt amd" editor_lazy.min.js
  unverändert und baut nur init.min.js aus init.js.
- build.mjs erzeugt nur noch amd/build/editor_lazy.min.js (+ .map); der zweite
  (dev-)Build nach amd/src ist entfernt. Kommentare korrigiert.
- thirdpartylibs.xml deklariert nur noch amd/build/editor_lazy.min.js.
- Developer-Mode funktioniert weiterhin über die .map: lib/requirejs.php liefert
  die Build-Datei direkt aus, wenn die .map daneben liegt — amd/src/editor_lazy.js
  ist dafür nicht nötig (und war der Konfliktverursacher).

### CI
- Grunt-Step: continue-on-error entfernt — echte Fehler brechen die CI jetzt ab,
  statt verdeckt zu werden.
- Redundante "Build front-end bundle"-Steps aus allen Jobs entfernt (das Bundle
  ist committet und wird von Grunt nicht mehr angefasst).
- Neuer Job "Bundle reproducibility": npm ci + node build.mjs +
  git diff --exit-code stellt sicher, dass das committete Bundle exakt dem
  aktuellen esbuild-Output entspricht. Build ist deterministisch (verifiziert).

### Verified
- grunt amd fasst editor_lazy nicht mehr an; init.min.js reproduzierbar.
- phpcs 0 Fehler (Vendors-Check grün), PHPUnit 68/885, Jest 67/67, tsc sauber,
  Define-Ladeprobe (mount vorhanden), Build reproduzierbar (npm ci == Commit).

## 0.2.14 (2026072626) — CI: mpc-grunt-Löschverhalten umgangen + npm-ci-Lockfile

### Fixed — die WAHRE Wurzel der gesamten CI-Fehlerhistorie
- Im mpc-Quellcode (GruntCommand.php) nachgewiesen und lokal exakt reproduziert:
  "moodle-plugin-ci grunt" LÖSCHT vor dem amd-Task absichtlich amd/build/
  (um Reproduzierbarkeit aus amd/src zu prüfen). Damit verschwindet das
  committete esbuild-Bundle editor_lazy.min.js. Grunts amd-Task beginnt mit
  "ignorefiles", das jeden thirdpartylibs.xml-Pfad per fs.statSync prüft ->
  ENOENT -> Abbruch. Die anschließende Vendors-Prüfung wirft eine Exception,
  wodurch mpc sein Plugin-Backup NIE zurückspielt -> das Bundle bleibt gelöscht
  -> phpdoc und alle Folgeschritte scheitern am "fehlenden" File, obwohl es
  committet war. Ein Neu-Bauen VOR mpc grunt kann das prinzipbedingt nicht
  lösen — mpc grunt löscht die Datei danach wieder.
- FIX im Workflow (lint-js): der amd-Task läuft jetzt DIREKT über
  "npx grunt amd --files=mod/vimipad/amd/src/init.js" (keine Vorlöschung,
  beschränkt auf das echte Moodle-AMD-Modul init.js). gherkinlint/stylelint
  laufen weiter über "moodle-plugin-ci grunt --tasks ..." (diese Tasks löschen
  kein Build-Verzeichnis). Davor/danach "test -f"-Wächter, die sofort zeigen,
  falls je wieder ein Schritt das Bundle entfernt.
- Empirisch verifiziert: alte Sequenz reproduziert exakt den CI-Fehler
  (ENOENT + Vendors + Datei weg); neue Sequenz läuft end-to-end grün und das
  Bundle bleibt byte-identisch erhalten.

### Fixed — npm ci (zweites, unabhängiges Problem)
- package-lock.json stand in .gitignore und fehlte daher im CI-Checkout ->
  "npm ci" bricht ab (EUSAGE). .gitignore-Eintrag entfernt; das Lockfile liegt
  dem Paket bei. WICHTIG beim Committen: einmalig
      git add -f package-lock.json
  falls Git es wegen der alten Regel noch ignoriert. npm ci bleibt der richtige
  Befehl (reproduzierbare Toolchain-Versionen).
- Bundle-Reproduzierbarkeits-Job auf Node 22 (Node 20 ist deprecated).

### Verified
- Neue CI-Sequenz lokal end-to-end: bundle-installed-Check, npx grunt amd
  (init.js gebaut, editor_lazy unangetastet), bundle-survived-Check, mpc grunt
  gherkinlint/stylelint grün, phpdoc ohne Vendors-Fehler, npm ci + build ==
  committeter Stand. Jest 67/67, PHPUnit 68/885, tsc sauber.

## 0.2.15 (2026072627) — Behat: echter Feature-Bug behoben + Poll-Guard

### Fixed
- editor.feature wartete mit `I wait until "Add concept" "button" exists` auf
  einen Button, den es NIE gab: "Add concept" ist die Fieldset-Legende, der
  Button heißt "Add". Der Schritt lief in einen Timeout -> Behat-Fehlschlag.
  Das fiel bisher nie auf, weil der Behat-Job via needs:[lint-php,lint-js] bis
  0.2.14 nie startete (CI brach vorher am Bundle-Problem ab). Jetzt läuft Behat
  erstmals. Fix: Warte auf die existierende `"Add concept" "fieldset"` (beide
  Szenarien). "fieldset" ist ein gültiger Moodle-Behat-Partial-Selektor.
- Kollaborations-Poll-Schleife startet unter Behat nicht mehr
  (M.cfg.behatsiterunning). Die Szenarien sind Einzelnutzer-Tests; dauerhafte
  Hintergrund-fetch-Aufrufe wären reine Flakiness-/Last-Quelle und könnten die
  Seiten-Stabilitätserkennung stören. Locking (beginEdit/endEdit) bleibt aktiv.

### Verified (statisch, da @javascript nur in CI/Chrome läuft)
- Behat-Init erfolgreich (Moodle-Install ok), Dry-Run 6 Szenarien/59 Steps/0
  undefiniert. grading.feature vollständig gegen die UI geprüft: "Submissions",
  "Sam Student", "Submitted" (snapshotstatus_1), "View and grade",
  "View and grade snapshot", Feld "Grade (out of 100)" (grade-Default 100),
  "Feedback", "Save grade", "Grade saved.", Feld "Annotation" (for/id-verknüpft),
  "Add", "Annotation added." — alle Strings/Verknüpfungen stimmen.
- Jest 67/67, tsc sauber, Bundle reproduzierbar.
- HINWEIS: Die @javascript-Editor-Szenarien laufen nur in echtem Chrome (CI);
  Logik/Strings sind statisch verifiziert, das visuelle Verhalten bestätigt der
  CI-Lauf bzw. der Browser.
