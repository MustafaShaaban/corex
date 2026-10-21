# Tasks: Quality checks on every release (spec 116)

Each task is done when its check passes on this branch and, where marked **planted**, fails on a scratch commit that
plants the defect. The planted runs are listed in the pull request.

## Phase 1: Static checks

- [ ] T001 [US-NFR-01] Admin token contrast: `tests/Fixtures/Theme/admin-contrast-matrix.json` and two tests in
  `tests/Unit/Theme/ContrastMatrixTest.php` (pairs meet thresholds or are known failures; the system and pinned light
  blocks agree). **Planted**: dark muted text `#4a4f5c`.
- [ ] T002 [US-NFR-08] Changelog rule in `tests/repo-hygiene.test.js`: a Client impact section after 0.47.0, and under
  Unreleased once it has content, names Muva, Perego and Mizzey or says no client site is affected. **Planted**: a
  section that names none.
- [ ] T003 [US-NFR-06] `npm run audit:completion` and a CI step in the `js` job. **Planted**: "planned" in an admin
  label.
- [ ] T004 [US-NFR-06] `scripts/i18n-lint.php`, `tests/Fixtures/i18n-lint-baseline.json`,
  `tests/Unit/Tooling/I18nLintTest.php`, and a CI step in the `test` job. **Planted**: an untranslated echo.
- [ ] T005 [US-NFR-06] `corex.pot` generated in the `test` job and uploaded; `.github/workflows/release-assets.yml`
  attaches it to a published release.

## Phase 2: Browser harness

- [ ] T010 `@axe-core/playwright` 4.13.0 as a development dependency; `npm run verify:dependencies` stays green.
- [ ] T011 `tests/e2e/quality/playwright.quality.config.js` and `npm run test:quality`; the main config ignores
  `quality/`.
- [ ] T012 [US-NFR-01] `tests/e2e/quality/screens.js`: admin screens and tabs from the menu; template → address map
  with a failing test for an unmapped template.
- [ ] T013 `tests/e2e/quality/findings.js` and `known-failures.json`: signatures, comparison, record mode
  (`COREX_QUALITY_RECORD=1`), stale entries reported.
- [ ] T014 `.github/actions/serve-wordpress` taken out of the `e2e` job; the `quality` job in `ci.yml` (provision, seed,
  apply the Company kit at Full, serve, run, upload the report, the screenshots and the findings).

## Phase 3: Checks

- [ ] T020 [US-NFR-01, 03, 04, 06] `screen-matrix.spec.js`: 8 cells per screen and per template; load, overflow, axe,
  phone rules (375), raw keys, 4 screenshots per screen. **Planted**: 2px overflow in rtl; an unlabelled inbox search;
  a 16px icon button; a 947px table; `corex_owner_key` in a cell.
- [ ] T021 [US-NFR-03] `keyboard.spec.js`.
- [ ] T022 [US-NFR-03, 07] `motion.spec.js`.
- [ ] T023 [US-NFR-04] `text-zoom.spec.js`.
- [ ] T024 [US-NFR-05] `screen-states.json` and `states.spec.js`. **Planted**: a screen with no entry.
- [ ] T025 [US-NFR-06] `corex-e2e-pseudo-locale.php` and `pseudo-locale.spec.js`. **Planted**: a hard-coded label.
- [ ] T026 [US-NFR-03] `public-form.spec.js`.
- [ ] T027 [US-NFR-07] `byte-budget.json` and `front-end-budget.spec.js`. **Planted**: an image without dimensions;
  60KB in a block script.
- [ ] T028 Record the known failures from CI's first run, give each a story, commit.

## Phase 4: Client merge check

- [ ] T030 [US-NFR-08] `scripts/client-merge-check.mjs` and `tests/client-merge-check.test.js` (merge, conflict with a
  recorded local edit, failing suite, never writes to the source). **Planted**: a candidate that edits a recorded local
  edit.
- [ ] T031 [US-NFR-08] CI job `client-merge`: a client site generated from the base, the pull request's head merged into
  it, its suites run.

## Phase 5: Test residue

- [ ] T040 [US-NFR-09] `tests/e2e/residue.js`: production refusal, marker clean-up, submission count.
- [ ] T041 [US-NFR-09] `global-setup.js` prints the site, refuses production, cleans and counts; `global-teardown.js`
  cleans and fails on records left, by name. **Planted**: a spec that leaves an unmarked submission.
- [ ] T042 [US-NFR-09] `seedSubmission()` marks what it submits.
- [ ] T043 [US-NFR-09] Fixture guide labelled "Test fixture".
- [ ] T044 [US-NFR-09] `scripts/clean-test-residue.php` (lists, asks, refuses production).

## Phase 6: Docs and release

- [ ] T050 `CONTRIBUTING.md`: running the quality checks, the known-failures rules, the release steps (NVDA pass,
  screenshot review, `node scripts/quality-known-failures.mjs`, the client merge check against Muva and Perego).
- [ ] T051 `scripts/quality-known-failures.mjs` prints the lists for the release notes.
- [ ] T052 CHANGELOG `[Unreleased]`, PROGRESS "In flight", DECISIONS #410 to #419.
- [ ] T053 Guards: clean-code-guard, test-guard, docs-guard.
