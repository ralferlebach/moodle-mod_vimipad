# Security-Review (0.7.x Hardening)

> Stand: 0.7.30. Dokumentierter Durchgang gemäß Roadmap-Punkt 0.7.x
> "Systematischer Security-Review". Deckt External-Functions (Zugriffskontrolle,
> IDOR), Ausgabe-Sanitisierung (Stored XSS), Parameter-/Ressourcengrenzen und
> CSRF ab. Aktualisiert für den 0.7.28-/0.7.29-Stand (comment-Capability beim
> Journal, `get_operations`-Endpunkt mit Pagination, POST-only-Mutationen,
> byte-genaue Payload-Limits, Compare-and-Swap beim Lock-Takeover).

## 1. Zugriffskontrolle und IDOR: External-Functions

Alle External-Functions validieren Kontext und Capability und binden jede
`workspaceid` an die aktuelle Aktivität (`vimipadid = instance->id`,
`MUST_EXIST`), sodass eine fremde `workspaceid` aus einer anderen Aktivität
abgewiesen wird. Schreibzugriffe laufen zusätzlich durch
`access::require_edit` (Einzelmodus: `editown` + Eigentümer; Gruppenmodus:
`editgroup` + `groups_is_member`, Ausnahme `site:accessallgroups`). Lesezugriff
auf fremde Maps erfordert `mod/vimipad:grade`.

| External function | Validierungspfad | Schreibt? | Capability |
|---|---|---|---|
| acquire_lock | validate_workspace_for_edit | ja | editown/editgroup |
| add_journal_entry | validate_workspace_for_edit + require comment | ja | editown/editgroup **+ comment** |
| apply_operation | inline (context + workspace-bind + require_edit) | ja | editown/editgroup |
| cancel_consensus | consensus_context | ja | submit |
| confirm_consensus | consensus_context | ja | submit |
| create_snapshot | inline (context + workspace-bind + require_edit) | ja | submit |
| get_consensus_status | consensus_context | nein | submit |
| get_constraint_status | validate_workspace_for_read | nein | view (+grade fremd) |
| get_journal_entries | validate_workspace_for_edit | nein | editown/editgroup |
| get_operations | validate_workspace_for_read | nein | view (+grade fremd) |
| get_revision_state | validate_workspace_for_read | nein | view (+grade fremd) |
| get_workspace | inline (context + view, +grade fremd) | nein | view (+grade fremd) |
| import_map | validate_workspace_for_edit | ja | editown/editgroup |
| poll_changes | validate_workspace_for_edit | nein | editown/editgroup |
| release_lock | validate_workspace_for_edit | ja | editown/editgroup |
| renew_lock | validate_workspace_for_edit | ja | editown/editgroup |
| save_layout | inline (context + workspace-bind + require_edit) | ja | editown/editgroup |
| start_consensus | consensus_context | ja | submit |

Befund: keine IDOR-Lücke. Die vier inline validierenden Funktionen
(`apply_operation`, `create_snapshot`, `get_workspace`, `save_layout`) führen
dieselben Prüfungen wie die Helper-Wrapper aus; eine Vereinheitlichung über
`validate_workspace_for_*` ist rein strukturell (Code-Hardening), kein
Sicherheitsmangel. `add_journal_entry` erzwingt seit 0.7.28 zusätzlich zur
Edit-Zugriffsprüfung explizit `mod/vimipad:comment` (Rollenmatrix-Test).
`get_operations` (0.7.28, paginiert seit 0.7.29) nutzt denselben
`validate_workspace_for_read`-Pfad wie `get_revision_state` und ist durch einen
eigenen Vertrags-/IDOR-Test abgedeckt (eigener/fremder Workspace, Clamping,
Pagination, Batch-Kappe).

## 2. Parameter-Validierung

Alle Parameter laufen durch `validate_parameters()` mit passenden PARAM-Typen.
`PARAM_RAW` wird bewusst nur für (a) JSON-Payloads und (b) Rich-Content/Labels
genutzt:

- **JSON-Payloads** (`payloadjson`, `layoutjson`, `metadatajson`,
  `geometryjson`): serverseitig `json_decode` + Strukturprüfung. Nur die
  **Operations-Payload** wird zusätzlich per `operation_type::validate_payload()`
  vollständig schema-validiert (unbekannte Typen abgewiesen, Referenzen via
  `assert_node_exists`, stable ids via `stable_id::is_valid` = Präfix + 12 Hex).
  `layoutjson`/`viewportjson` werden auf JSON-Wohlgeformtheit, eine
  **Byte-Grenze** (`MAX_LAYOUT_BYTES` via `limits::check_bytes`, byte-genau seit
  0.7.29) und seit 0.7.30 gegen ein **Feldschema** (`layout_policy`: Objekt-Root,
  erlaubte Top-Level-Felder, endliche Koordinaten im Bereich, positive Größen,
  Objektanzahl-Kappe) geprüft; `save_layout` weist zudem unbekannte Modi ab
  (0.7.28). Payloads werden nie blind gespeichert.
- **Rich-Content/Labels** (`entrytext`, `content`): als `PARAM_RAW` gespeichert,
  aber bei der Ausgabe konsequent sanitisiert (siehe 3). Freitextfelder (Journal,
  Peer-Review-Kommentar, Grade-Feedback, akzeptiertes KI-Feedback, Annotation)
  sind seit 0.7.28 an der Service-Grenze längenbegrenzt (`MAX_TEXT`, multibyte
  über `core_text::strlen`).

**Ressourcengrenzen:** Element-/Textlängen (`limits::check_text`), Payload-Bytes
(`limits::check_bytes`), Import-Größe (`MAX_IMPORT_BYTES`), das Layout-/Viewport-
Feldschema (`layout_policy`, seit 0.7.30) und der paginierte `get_operations`-
Abruf (serverseitige Batch-Kappe `MAX_BATCH`) begrenzen die pro Request
verarbeitete Datenmenge. AI-Eingaben/-Ausgaben sind seit 0.7.30 begrenzt
(`MAX_AI_NOTES`/`MAX_AI_PROMPT`/`MAX_AI_DRAFT`/`MAX_AI_PROVIDERINFO` in
`ai_feedback_service`: Notes und Gesamtprompt vor dem Provider-Aufruf, Draft und
`providerinfo` vor der Speicherung). Offen (0.7.x-Backlog, nicht
sicherheitskritisch): unpaginierte Lehrenden-/Historienlisten, Gradebook-Sync-
Chunking bei sehr großen Kohorten.

## 3. Stored XSS: Ausgabe-Sanitisierung

- **Frontend (React):** kein einziges `dangerouslySetInnerHTML`/`innerHTML` im
  gesamten `js/src`. Alle Labels/Texte werden als React-Textknoten gerendert und
  damit automatisch escaped. Das ist der stärkste Schutz gegen Stored XSS im
  Client.
- **PHP-Ausgabe:** Nutzerdaten werden nie roh ausgegeben. Journal-Text über
  `format_text(..., FORMAT_PLAIN)` bzw. `s()`, Feedback über
  `format_text($feedback, $format, ['context' => $context])`. Sichtbare
  Button-Beschriftungen stammen aus `get_string()`, nicht aus Nutzereingaben.

## 4. CSRF

Alle zustandsändernden POST-Formulare in `view.php`/`grading_panel.php` betten
`sesskey()` ein und prüfen es serverseitig. Die zuvor GET-basierten, zustands-
bzw. kostenverändernden Aktionen `runai` und `allocatereviews` sind seit 0.7.29
auf POST-Formulare mit `sesskey` umgestellt und prüfen zusätzlich die
Request-Methode (`REQUEST_METHOD === 'POST'`), sodass sie nicht durch Prefetch,
Browserverlauf oder erneutes Öffnen der URL ausgelöst werden können.
External-Functions sind über Moodles Webservice-Token-/Session-Mechanismus
abgesichert.

## Ergebnis

Kein offener Sicherheitsbefund in Zugriffskontrolle, IDOR, Stored XSS,
Parameter-Validierung oder CSRF nach dem 0.7.30-Stand. Die zuvor gemeldeten
Punkte (Journal-comment-Capability, GET-Mutationen, Lock-Takeover-Race inkl.
Owner-Renewal-CAS, byte- vs. zeichenbasierte Payload-Grenze, Layout-/Viewport-
Feldschema via `layout_policy` an der Service-Grenze, AI-Eingabe-/Ausgabegrenzen)
sind behoben und durch Tests abgesichert.

**0.8.19 — 0.7.30-Audit-Reste geschlossen:**

- Der Heartbeat-`renew()` (`lock_service::renew`) ist jetzt vollständig
  **CAS-abgesichert**: ein bedingtes `UPDATE … WHERE id AND userid AND
  timeexpires` plus Re-Read; ein Takeover zwischen Read und Write wird nicht mehr
  überschrieben, und der alte Halter erhält korrekt `acquired=false` mit dem
  echten neuen Halter (Test `test_renew_after_takeover_reports_new_holder`).
- Die `get_operations`-Zugriffsabdeckung ist um Gruppen-, Course-Workspace-,
  Cross-Activity-, Guest-, unenrolled- und suspended-Fälle erweitert.
- **Gastzugriff — bewusste Entscheidung, dokumentiert und erzwungen:** Das Lesen
  jedes Workspaces (auch des geteilten Course-Workspaces) erfordert
  `mod/vimipad:view` im Modul-Kontext. Gäste, nicht eingeschriebene und
  suspendierte Nutzer werden abgewiesen — eine Kurs-Map ist Kursinhalt, kein
  öffentliches Datum. Course-Workspaces sind ausschließlich unter eingeschriebenen
  Teilnehmenden geteilt (Kommentar in `helper::validate_workspace_for_read`,
  Tests in `get_operations_contract_test`).

Verbleibende, **nicht sicherheitskritische** Punkte im Backlog (0.8.x):
unpaginierte Lehrenden-/Historienansichten, Gradebook-Sync-Chunking bei sehr
großen Kohorten, sowie die strukturelle Vereinheitlichung der inline
validierenden External-Functions auf die `validate_workspace_for_*`-Helper.

