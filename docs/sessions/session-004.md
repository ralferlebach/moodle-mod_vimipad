# Session 004 — 0.7.x Hardening-Arc (Pre-Beta bis Beta-GO)

**Chat:** „004 – Hardening / Security / Produktivitaetsaudits"
**Bogen:** 0.7.28 (2026072767) → **0.7.31** (2026072770)
**Verifikation:** real auf Moodle 4.5 + PostgreSQL 16 + PHP 8.3.6 (PHPUnit als
verlaessliche Basis); Frontend: tsc/Jest/esbuild + Grunt-AMD-Byte-Repro. Kein
visuelles Rendering/Live-@javascript-Behat in der Sandbox (laeuft in der CI).

> Fortsetzung von [`session-003.md`](session-003.md). Arbeitsgrundlage: eine
> Folge externer Sicherheits-/Coding-Standard-/Produktivitaetsaudits (je Release
> ein Bericht), jeweils real gegen den Code gegengeprueft, umgesetzt, getestet,
> paketiert. Diese Session schliesst den code-seitigen 0.7.x-Hardening-Arc ab.

---

### Was wurde erledigt?

Fuenf aufeinander aufbauende Haertungs-Releases als Antwort auf je einen
externen Auditbericht. Jeder Zyklus: Audit gegenpruefen (auch Fehlbefunde des
Berichts markieren) → umsetzen → PHPUnit/Jest → phpcs/phpcbf → Version-Bump →
CHANGELOG → volle Verifikation → Patch-ZIP + Full-ZIP → Frisch-Install-Test.

**0.7.28 (2026072767) — Pre-Beta-Haertung (Security/Performance/Subplugin-Vertrag)**
- P0 `add_journal_entry` erzwingt `require_capability('mod/vimipad:comment')`.
- Text-Limits (`limits::check_text`, `MAX_TEXT`) an 5 Freitext-Grenzen.
- Layout/Viewport/Revision-Validierung; unbekannte Modi via `in_array` strict.
- N+1 beseitigt: peer_review allocate, privacy export, grade save, Gruppennamen.
- Lock-Cleanup als scheduled task (`purge_expired_locks`, */15min).
- Subplugin-Vertrag: 5 form/*/version.php mit `dependencies`; `vimipad_normalise_profile()`.
- O(N)-Replay erste Fassung: `get_operations`-Endpoint + client `reconstruct.ts`.
- `supported=[405,502]`. 287 mod_vimipad + 97 vimipadassess, 286 Jest.

**0.7.29 (2026072768) — Revisionsplayer-Korrektheit & Skalierung**
- **P0 Frame-Aliasing behoben:** `survivors()` pusht Kopien (Frames unveraenderlich).
- `get_operations` paginiert (`fromrevision`/`limit`/`hasmore`/`nextrevision`,
  `MAX_BATCH=500`) + Contract-/IDOR-Test (5 Faelle).
- `ReplayEngine` mit Checkpoints + bounded LRU statt aller Voll-Frames.
- `isHistoryIncomplete` vergleicht Stable-ID-Mengen.
- `limits::check_bytes()` (Bytes statt Zeichen); Layout/Viewport darauf.
- Lock-CAS gegen Takeover-Race; GET→POST fuer `runai`/`allocatereviews`;
  Snapshot-Metriken statt Live-Zaehlung in der Grading-Uebersicht.
- assess_uninstall_safety_test; Uninstall-Semantik-Docblocks. 297+97, 295 Jest.

**0.7.30 (2026072769) — Partial-History-Korrektheit, bounded Checkpoints, AI/Layout-Limits**
- **P0 (still unvollstaendige Historie):** Player fuehrt `highestLoadedRevision`,
  baut die Engine nur bis dahin (`cap`), clampt den Slider, zeigt Truncation-
  Warnbanner (`revision:historytruncated`); keine „vollstaendig"-Aussage bei
  abgeschnittener Ladung.
- **P0 Teil B:** `isHistoryIncomplete` nutzt Content-Fingerprint (type/label/
  content/endpoints/direction/geometry/metadata) statt nur Stable-ID-Mengen —
  erkennt fehlende Aenderungen an bestehenden Elementen (gleiche IDs, anderer
  Inhalt).
- **P1 3.1/3.2:** `ReplayEngine` `maxCheckpoints=64`-Budget (Intervall =
  ceil(maxRevision/budget)); `checkpointOpIndex` (kein Re-Scan); `nearestCheckpoint`
  per Binaersuche.
- **P2 (aus Audit-Replik):** Owner-Renewal-Randfall — `held && expired` laeuft
  jetzt ueber den CAS-Takeover-Pfad statt unbedingtem Update.
- **5.3 AI-Limits:** `MAX_AI_NOTES/PROMPT/DRAFT/PROVIDERINFO`; notes+Gesamtprompt
  in `build_prompt`, Draft+providerinfo in `generate_text`, defensiv in `store_draft`.
- **5.2 layout_policy:** neue Klasse (Objekt-Root, erlaubte Top-Level-Felder,
  endliche Koordinaten im Bereich, positive Groessen, `MAX_LAYOUT_OBJECTS=5000`);
  weist `42`/`true`/Listen/`{"x":"abc"}` ab.
- **10.2/10.3:** Assess-Scorer auf konkrete Parent-Version (2026072766) statt
  ANY_VERSION; geaenderte Form- + Assess-Subplugins mit Version-Bump.
- 306+97, 297 Jest.

**0.7.31 (2026072770) — 0.7.x-Closure (finale Audit-Follow-ups)**
- **Layout-Validierung an die Service-Grenze verschoben:** `layout_policy` laeuft
  jetzt in `layout_service::save()` (deckt den Importpfad ab, der den Service
  direkt aufruft); redundante Endpoint-Pruefung entfernt (Single Source of Truth);
  Service-Level-Reject-Test.
- **security_review.md-Fazit korrigiert:** Layoutschema + AI-Grenzen nicht mehr
  faelschlich als offen gefuehrt.
- **backlog.md** auf 0.7.30-Stand; Rest fuer 0.8.x verortet (renew()-CAS,
  optionale get_operations-Testabdeckung, Gastzugriff-Entscheidung, empirische
  Reifetests/Benchmarks). 307+97, 297 Jest.

---

### Entscheidungen getroffen

| Thema | Entscheidung | Begruendung |
|---|---|---|
| Audit-Fehlbefunde | Bereits behobene Punkte (Lock-Race 4.4, get_operations-Test 4.2 in 0.7.29) im Verfahren als „bereits erledigt" markiert statt doppelt umzusetzen | Vom User per Replik bestaetigt; Doppelarbeit vermeiden |
| Owner-Renewal-CAS | Als P2 aufgenommen und in 0.7.30 umgesetzt | Enger, realer Randfall; klein und sauber schliessbar |
| renew()-CAS | Auf 0.8.x verschoben | Advisory Presence-Lock, keine Datenintegritaet; acquire() bereits CAS-sicher |
| Layout-Validierung | An die Service-Grenze statt Endpoint | Importpfad umging sonst die Policy |
| Release-Schnitt | 0.7.31 als 0.7.x-Closure trotz Audit-GO fuer 0.7.30 | Zwei kleine Konsistenz-Follow-ups sauber einarbeiten, ohne den Audit-Bezug zu 0.7.30 zu verwischen |
| Makefile | Unangetastet (nur `lint-js` in check:, aus fruehem Arc) | Bewusster lokaler Pre-Check; CI ist das verbindliche Gate (vom Audit bestaetigt) |

---

### Entwurfsentscheidungen geaendert / zurueckgestellt

- ReplayEngine-Konstruktor: 3. Parameter-Semantik von `checkpointInterval` auf
  `maxCheckpoints` geaendert (0.7.30). Korrektheit bleibt schema-unabhaengig;
  Tests gruen.
- 0.7.28-CHANGELOG-Aussage „O(N)" praezisiert (Fetch-Reduktion + 0.7.29
  bounded-memory), da die erste Fassung einen vollen Frame-Cache hielt.

---

### Offene Punkte fuer die naechste Session (0.8.x)

- **Heartbeat `renew()` vollstaendig CAS-sicher** (advisory, nicht integritaets-
  kritisch; Backlog-Marker gesetzt).
- **Empirischer Reifenachweis fuer MATURITY_STABLE:** Concurrency-/Last-Tests,
  Replay-Benchmarks (1k/5k/10k/20k/100k Ops), Accessibility-Durchgang, Browser-/
  DB-Matrix, Upgrade-/Backup-Restore-Tests in der CI.
- Optionale `get_operations`-Testabdeckung (Gruppen/Course/Cross-Activity/Guest/
  unenrolled/suspended).
- Gastzugriff auf Course-Workspaces als Produkt-/Datenschutzentscheidung.
- Weitere Darstellungsformen (argument, process/flow, fishbone, timeline,
  vennsets, systems, affinity) als vimipadform-Subplugins.

---

### Roadmap-Abgleich (zentrales Dokument)

- [x] docs/design/roadmap.md: 0.7.x als Hardening-Phase unveraendert gueltig;
      keine Scope-/Reihenfolgeaenderung noetig.
- [x] backlog.md auf 0.7.30-Stand; README + security_review.md auf 0.7.30/0.7.31;
      test-setup unveraendert gueltig.

---

### Testlauf-Ergebnis

```
PHPUnit: OK — 307 mod_vimipad + 97 vimipadassess (real auf Moodle 4.5 / PG16 / PHP 8.3.6)
PHPCS:   OK — 0 warnings (severity 1)
PHPDoc:  OK — 0 · validate 0 · savepoints 0 · phpcpd: no clones
Behat:   SKIP (kein stabiler Browser/HTTP in Sandbox; laeuft in CI)
Frontend:tsc OK · Jest 297/43 Suiten · AMD (init/revision) byte-reproduzierbar
Lang:    EN/DE paritaetisch, 477 Keys, byte-sortiert
```

---

### Verzeichnis-Snapshot (in diesem Arc geaenderte Kernpfade)

```
version.php, CHANGELOG.md, README.md, package.json, package-lock.json
lang/en/vimipad.php, lang/de/vimipad.php
amd/src/revision.js, amd/build/{init,revision,editor_lazy}.min.js(.map)
classes/local/policy/{limits,layout_policy}.php
classes/local/service/{layout_service,lock_service,ai_feedback_service}.php
classes/external/{get_operations,save_layout}.php
classes/plugininfo/{vimipadform,vimipadassess}.php
classes/task/purge_expired_locks.php, db/{services,tasks}.php
js/src/graph/reconstruct.ts, js/src/components/RevisionPlayer.tsx, js/src/api/service.ts
tests/{lock_service,ai_feedback,layout_policy,layout_service,get_operations_contract,
  text_limit,assess_uninstall_safety,...}_test.php
js/tests/{reconstruct,revision_player}.test.ts
form/*/version.php (5), assess/*/version.php (6)
docs/design/{security_review,backlog}.md, docs/sessions/session-004.md
```

---

### Auslieferung

- [x] Version in version.php + package.json + package-lock.json konsistent (0.7.31 / 2026072770).
- [x] CHANGELOG-Eintraege 0.7.28–0.7.31 ergaenzt.
- [x] docs/sessions/session-004.md im ZIP (Gate unten).
- [x] amd/build/ + js/build/ eingecheckt.

---

### Fuer die naechste Session einfuegen in sessionstart.txt

> Diese Zeilen sind eine KURZFASSUNG fuers naechste Startprompt und ersetzen NICHT
> den persistierten docs/sessions/session-004.md.

**Aktueller Entwicklungsstand:**
> 0.7.31 — code-seitiger 0.7.x-Hardening-Arc abgeschlossen. Finales Audit gibt
> GO fuer kontrollierten Beta-/Pilotbetrieb; MATURITY_BETA sachlich vertretbar
> bei gruener Voll-CI. Real verifiziert: 307 mod_vimipad + 97 vimipadassess
> PHPUnit gruen; phpcs/phpdoc/validate/savepoints/phpcpd clean; Lang 477/477;
> tsc 0, Jest 297/43, AMD reproduzierbar.

**Zuletzt abgeschlossen (Session 004):**
> P0 unvollstaendige Revisionshistorie (highestLoadedRevision + Truncation-
> Warnung + Content-Fingerprint), bounded/revisionsindizierte Checkpoints,
> layout_policy an der Service-Grenze, AI-Prompt-/Ausgabegrenzen, Lock- und
> Owner-Renewal-CAS, konkrete Subplugin-Parent-Versionen. Fuenf Audit-Runden
> (0.7.28→0.7.31) je real gegengeprueft und paketiert.

**Als naechstes geplant — 0.8.x (Feldtests & weitere Darstellungsformen):**
> Empirischer Reifenachweis (Concurrency/Last, Replay-Benchmarks, Accessibility,
> Browser-/DB-Matrix, Upgrade-/Backup-Restore-CI) Richtung MATURITY_STABLE;
> renew()-CAS-Vervollstaendigung; weitere vimipadform-Profile; Feldtests in
> echten Kursen.
