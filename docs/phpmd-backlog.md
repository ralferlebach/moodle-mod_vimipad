# mod_vimipad — outstanding PHPMD findings

`make lint-md` runs PHPMD against `phpmd.xml`, a curated ruleset. Rules that
contradict the Moodle coding standard are excluded there, each with its reason,
so what the run reports is genuinely actionable. This file tracks what is still
reported and why it has not been fixed yet.

As of 0.9.17: **59 findings, all size or complexity**, in older service and
output code. Every other category is at zero. The three satellite plugins
(`mod_vimigallery`, `qtype_vimipad`, `datafield_vimipad`) are at zero.

## What is left

| Category | Count |
| --- | ---: |
| CyclomaticComplexity | 32 |
| NPathComplexity | 13 |
| ExcessiveMethodLength | 10 |
| Superglobals | 2 |
| LongVariable | 1 |
| CouplingBetweenObjects | 1 |

## Priority order

These are ranked by how much a reader (or a reviewer) would struggle with the
method, not by the number the tool prints.

### P1 — methods long enough to hide a bug

| Method | Lines |
| --- | ---: |
| `privacy\provider::export_user_data()` | 257 |
| `local\service\operation_service::mutate()` | 233 |
| `local\service\import_service::apply_data_locked()` | 184 |
| `backup_vimipad_activity_structure_step::define_structure()` | 180 |
| `local\output\grading_panel::handle_action()` | 165 |
| `local\output\grading_panel::render()` | 130 |
| `privacy\provider::anonymise_shared_contributions()` | 126 |
| `local\service\reconstruction_service::apply()` | 116 |
| `local\service\grading_service::save_grade()` | 103 |

`grading_panel.php` is the single worst file (10 findings) and the natural place
to start: `handle_action()` is a dispatcher that could delegate per action, and
`render()` assembles several independent panels.

`define_structure()` is a partial exception: like an upgrade function its shape
is largely dictated by the backup API, though the element groups could still be
built by helper methods.

### P2 — complexity without excessive length

The remaining Cyclomatic/NPath findings sit in `layout_policy`,
`membership_resolver`, `node_style`, `constraint_policy` and the assess scorers.
These are decision-heavy by nature; each needs judging individually rather than
splitting for the metric's sake.

### P3 — the three singletons

- **Superglobals (2)** — `request_policy::is_mutating_request()` and
  `grading_panel::render_ai_assessment()` read `$_SERVER['REQUEST_METHOD']`.
  Both already accept an injected method for testing; the superglobal is the
  fallback. Worth routing through a single accessor.
- **LongVariable** — `$pendinggradinginstances` in the restore stepslib.
- **CouplingBetweenObjects** — one class with many collaborators; worth a look
  when its area is next touched.

## Why this is not simply switched off

Every exclusion in `phpmd.xml` is there because the rule contradicts a rule
Moodle enforces elsewhere — snake_case naming, the `$DB` globals, static core
APIs, API-prescribed boolean parameters. Size and complexity are not in that
category: they are real, they apply to this code, and the fix is to refactor.
The findings stay visible until that happens.
