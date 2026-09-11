# ViMi Pad ecosystem — open items

This file exists because open items used to live only in session summaries and
were lost when the next instruction arrived. Anything not finished belongs here,
with enough context to pick it up cold. Close an item by deleting it and noting
the change in the plugin's CHANGELOG.

Last reviewed: 2026-08-12 (after the third external review).

Closed since: group-map provenance (contributors recorded, deletion unlinks and
keeps the shared map) and the datafield profile lock.

---

## P1 — before a joint beta release

### mod_vimigallery

- **Server-side single-item loading.** `get_item` resolves through
  `gallery::get_items()`, which loads every visible item (and, for a live quiz
  source, up to 200 question usages) to return one map. The page output is lazy;
  the server work is not. Needs `get_descriptors()` / `get_item($sourceid)` on
  the source interface, and a stored-source query selecting one row.
- **Source adapters should validate through `api\value`.** Uploads already do.
  The qtype, datafield and vimipad adapters still only check `json_decode()` and
  `isset($decoded['nodes'])`, which is weaker than the boundary contract the
  shared API exists to enforce — and old data, restores and hand-edited
  databases all reach these paths.
- **MAX_ITEMS truncation is silent.** A course with 300 submissions yields a
  gallery of 200 with no indication that 100 are missing. Needs at minimum a
  visible "showing the newest 200 of 327" line, better a pager.
- **Rebuild vs foreign keys.** `delete_instance` removes comments before items
  because a FK could block the delete; `rebuild_items` deletes items while
  comments still reference them. The two paths assume different things. Needs an
  integration test on **both** MariaDB and PostgreSQL.

### qtype_vimipad

- **Oversized response through the real question engine.** `is_complete_response()`
  is tested directly, but not whether the Moodle question engine persists a huge
  `PARAM_RAW` value as step data *before* the policy runs. To be verified, not
  yet a confirmed bug.

### datafield_vimipad


---

## P2 — before stable

- **Localise user-reachable validation messages.** `mod_vimipad` still throws
  English text for errors reachable from AJAX input ("Unknown operation type",
  "revision out of range", "Malformed stable id"). Internal `coding_exception`
  messages may stay English; user-reachable ones should be language strings.
- **Remaining PHPMD findings in `mod_vimipad`** — see `docs/phpmd-backlog.md`
  (59 findings, all size or complexity in older service and output code). The
  satellites are at zero.
- **Datafield list-view payload.** Every visible row writes its whole map into
  the page. Partly imposed by the `mod_data` field API, but worth benchmarking
  (10/50/100 records × small/medium/near-limit maps) and eventually moving to a
  summary in list view with the full map only in single view.
- **Gradebook at scale.** `vimipad_update_grades()` is linear in recipients;
  benchmark at 500 and 1000.
- **Pre-release "legacy" code.** Apply the rule consistently, once per plugin:
  was the old state ever in a shipped release? If yes the compatibility path
  stays; if no it goes. Do not decide by the word "legacy" alone — a documented
  file format such as the legacy layout envelope is a real contract.

---

## Test gaps worth closing

### mod_vimigallery
- Backup/restore of a gallery with a **real source activity**, verifying the
  remap.
- `userinfo=false` with materialised learner items.
- A live source changing between the initial render and the lazy fetch.
- Query budget: `get_item` must load one item.

### qtype_vimipad
- A full quiz attempt through Behat: attempt, edit, submit, auto-grade, review.

### datafield_vimipad
- The full entry workflow: add, save, list view, single view, reopen, save again.
- Backup/restore with real stored maps.

### Browser and load
- Playwright for the gallery is the highest-value target: source changes during
  lazy loading, comments, hidden curation, compare, group visibility.
- k6 for the gallery at 10/50/200 attempts, measuring initial page, single lazy
  slide, question-usage loads and p95.
