# mod_vimipad — Playwright collaboration tests

These browser tests cover what the Behat suite cannot: **multiple clients editing
the same map at once**. Two users open one course-mode ViMi Pad, and a change by
one must reach the other through the polling sync. They need a **running Moodle
site** with mod_vimipad installed — they do not run inside `moodle-plugin-ci`'s
static jobs, and they cannot run in a sandbox without a stable browser.

## What they check

- A concept added by user A appears for user B.
- Both users see each other in the presence list.
- Concurrent edits from both users converge for everyone.

## Prerequisites

- A disposable Moodle site (CI service container or a local dev site) reachable
  over HTTP, with mod_vimipad installed and upgraded.
- Node and the Playwright browsers.

## Run it

```bash
# 1. Install (from this directory).
cd mod/vimipad/tests/playwright
npm install
npm run install-browsers

# 2. Seed the shared course-mode fixture and load the exports it prints.
eval "$(php ../../../../admin/cli/... )"   # see note below
eval "$(php seed.php)"                       # creates course + users, prints exports

# 3. Point the run at the site and go.
export VIMIPAD_BASE_URL="http://localhost:8000"
npm test
```

`seed.php` is a Moodle CLI script (run it from the Moodle root or with the
relative path shown). It creates a course, a course-mode activity and three
users with known passwords, then prints `export VIMIPAD_*` lines that the specs
read via `support/env.ts`.

## Notes

- Collaboration is poll-based, so assertions wait up to ~30s to converge; that is
  expected, not slowness.
- The specs run **serially with one worker** because both clients share one map.
- Passwords here are throwaway fixtures for a disposable site — never point this
  at production.
