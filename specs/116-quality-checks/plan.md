# Implementation Plan: Quality checks on every release

**Branch**: `claude/spec-116-quality-checks-a779c0` | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

## Summary

Spec 116 adds checks and no product code. The browser checks live in `tests/e2e/quality/`, run by their own Playwright
config, in a new CI job with its own WordPress (seeded as the browser job seeds it, plus the Company kit at Full). The
static checks join the existing `test` and `js` jobs: admin contrast, the PHP translation lint, the translation
template, the completion audit and the changelog rule. The client merge check is a script with a CI job of its own
that proves it on a generated client site. The test-residue work changes the browser suite's setup and teardown, its
seed helper and the fixture plug-ins, and adds a clean-up script.

## Technical Context

**Language/Version**: JavaScript (Node 22, Playwright 1.63, Jest 30), PHP 8.3 (Pest 3, PHP tokenizer)
**New dependency**: `@axe-core/playwright` 4.13.0, development only (decision NFR-D2; never in a shipped package, since
`scripts/build-shared-host-dist.mjs` ships no root `node_modules`)
**Storage**: none. The known-failure lists, the state inventory and the byte budget are committed JSON.
**Testing**: each check is proved twice, once passing on this branch and once failing on a planted defect (shown in
the pull request)
**Target**: GitHub Actions `ubuntu-latest`, WordPress `latest`, nginx and php-fpm as the browser job serves it
**Constraints**: the new browser job ends within the existing browser job's time (about 8 minutes); no check fails on
code a later pull request did not change, unless that pull request made the failure

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| I to VI (architecture, tokens, assets) | No runtime code. FR-022 enforces principle VI on every pull request. |
| VII Security | The clean-up script and the residue teardown refuse a production site; fixtures stay test-only. |
| VIII RTL | Every screen is checked in both directions on every pull request. |
| IX Optional dependencies | Nothing new at runtime; axe is a development dependency. |
| X Spec first | This spec, plan and tasks before the code. |
| Guard Gate | clean-code-guard on the scripts, test-guard on the specs and tests, docs-guard on the docs. |

## Structure

```text
tests/e2e/quality/
  playwright.quality.config.js   # own config: global setup/teardown, 1 worker, one project
  screens.js                     # discovers admin screens and tabs from the menu; template → address map
  findings.js                    # signatures, the known-failures comparison, report writing
  known-failures.json            # dated entries: check, screen, rule, signature, since, story, note
  screen-states.json             # state inventory, one entry per screen
  byte-budget.json               # CoreX bytes per front-end page
  screen-matrix.spec.js          # US-NFR-01/03/04/06: 8 cells: load, overflow, axe, phone, raw keys, screenshots
  keyboard.spec.js               # US-NFR-03: focus walk, Escape
  motion.spec.js                 # US-NFR-03/07: animations at rest, reduced motion
  text-zoom.spec.js              # US-NFR-04: 200% text at 375px
  states.spec.js                 # US-NFR-05
  pseudo-locale.spec.js          # US-NFR-06
  public-form.spec.js            # US-NFR-03: labels, error summary
  front-end-budget.spec.js       # US-NFR-07
tests/e2e/fixtures/corex-e2e-pseudo-locale.php   # marks corex strings for a request with its cookie
tests/e2e/global-setup.js, global-teardown.js, residue.js   # US-NFR-09
tests/Unit/Theme/ContrastMatrixTest.php (+ tests/Fixtures/Theme/admin-contrast-matrix.json)
scripts/i18n-lint.php (+ tests/Fixtures/i18n-lint-baseline.json, tests/Unit/Tooling/I18nLintTest.php)
scripts/client-merge-check.mjs (+ tests/client-merge-check.test.js)
scripts/clean-test-residue.php
scripts/quality-known-failures.mjs   # prints the known failures for the release notes
.github/actions/serve-wordpress/action.yml   # nginx + php-fpm, shared by the two browser jobs
.github/workflows/ci.yml             # quality job, client merge job, lint/pot/audit steps
.github/workflows/release-assets.yml # attaches corex.pot to a published release
```

## Phases

1. **Static checks**: admin contrast, changelog rule, completion audit in CI, PHP translation lint and baseline,
   translation template.
2. **Browser harness**: the config, screen discovery, findings and known failures, the shared serve action and the CI
   job.
3. **Checks over the harness**: matrix, keyboard, motion, text zoom, states, pseudo-locale, public form, front-end
   budget. The first CI run records the known failures; they are reviewed and committed with their stories.
4. **Client merge check**: the script, its Jest test and its CI job.
5. **Residue**: setup refusal and print, marker clean-up and count, seed helper, fixture labels, clean-up script.
6. **Planted defects**: one scratch commit per check, run in CI, shown in the pull request, then reverted.
7. **Docs**: CONTRIBUTING release steps (NVDA pass, screenshots review, known-failures print, client merge check),
   CHANGELOG, PROGRESS, DECISIONS #410 to #419.

## Decisions to record

#410 the quality job and its site; #411 axe-core (NFR-D2); #412 the known-failures lists and stale entries; #413
screen discovery and the template map; #414 the state inventory in one file; #415 PHP translation lint; #416 the
front-end budget; #417 the client merge check; #418 test residue and the production refusal; #419 what stays a manual
release step.
