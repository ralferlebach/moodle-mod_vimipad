# ViMi Pad — release checklist

A repeatable path from a green branch to a tagged release. The maturity flip from
ALPHA to BETA is a deliberate step here, not something that rides along with a
feature commit.

## 1. Pre-flight (must all be green)

- [ ] `moodle-plugin-ci` matrix green on the target Moodle branches (4.5 LTS and
      the newest supported), all jobs: phplint, phpcs (moodle), phpmd, phpcpd,
      phpdoc, validate, savepoints, mustache, grunt (eslint + AMD), the bundle
      reproducibility job, PHPUnit, and Behat (non-JS and `@javascript`).
- [ ] Behat acceptance suite passes, including the `@accessibility` (axe)
      scenarios.
- [ ] Playwright collaboration job (AP5) green against a live site.
- [ ] Load run (AP6) within budget on a seeded large map; no latency regression
      versus the previous release.
- [ ] `lang/en` and `lang/de` byte-sorted and at parity; no missing strings.
- [ ] `amd/build/*` committed and reproducible; `package-lock.json` present and
      not ignored.
- [ ] CHANGELOG updated; version integer and release string bumped together in
      `version.php`.

## 2. Plugin Checker (moodle.org)

Before submitting to the Moodle plugins directory, run the checker locally to
catch what CI does not, then use the directory's validator on the release ZIP:

```bash
# Local static pass mirroring the directory checks.
php mpc.phar validate --moodle /path/to/moodle /path/to/moodle/mod/vimipad
php mpc.phar phpdoc   --moodle /path/to/moodle /path/to/moodle/mod/vimipad
```

- [ ] No validate/phpdoc errors.
- [ ] `thirdpartylibs.xml` lists every shipped third-party asset (the esbuild
      React bundle) and each referenced path exists.
- [ ] Privacy provider present and complete; every user-data table covered.
- [ ] Backup/restore covers every table, including the append-only histories.
- [ ] Upload the release ZIP to the directory's "Validate" step; resolve any
      findings before publishing.

## 3. Build the release ZIP

A clean full-install package (never a patch), excluding dev artifacts:

```bash
zip -rq vimipad-<version>.zip vimipad \
  -x "vimipad/node_modules/*" -x "*/.git/*" \
  -x "vimipad/js/build/*" -x "*.map"
```

- [ ] ZIP contains `amd/build/*.min.js` and excludes `node_modules`.
- [ ] Fresh install and upgrade-from-previous both succeed on a clean site.

## 4. RC pilot

- [ ] Tag a release candidate (e.g. `v0.9.0-rc1`) and deploy to a pilot course
      with real teachers and learners.
- [ ] Watch the ViMi Pad status checks (Reports) during the pilot: data
      integrity, subplugin registration, history size.
- [ ] Triage pilot feedback (P0–P3); every confirmed reclamation becomes an
      automated test before it is closed.

## 5. Maturity flip ALPHA -> BETA

Only once sections 1–4 are clean and the pilot surfaced no P0/P1 issues:

- [ ] Change `$plugin->maturity` from `MATURITY_ALPHA` to `MATURITY_BETA` in
      `version.php` as its own commit, with a matching version bump and CHANGELOG
      note — not bundled with a feature change.
- [ ] Re-run the CI matrix on that commit.
- [ ] Tag the release and publish.
