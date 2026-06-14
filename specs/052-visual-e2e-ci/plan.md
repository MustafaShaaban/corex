# Implementation Plan: Visual & E2E verification in CI

**Branch**: `feature/052-visual-e2e-ci` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

## Summary

Formalize browser verification into CI, reusing the existing `tests/e2e/` scaffold (Playwright + the 3 smoke flows),
`wp-env.json`, and the GitHub Actions CI. Add a dedicated **`.github/workflows/e2e.yml`** that provisions wp-env,
activates Corex, installs Playwright + a browser, and runs `npm run test:e2e` on PRs + nightly; a **console-error
sweep** spec (`tests/e2e/console.spec.js`) that fails on a console *error* (not warning) on the editor / Corex admin /
a front-end page (the item-20 gate); and a documented Definition-of-Done browser gate. Execution is environment-
dependent by nature — the headless deliverable is the valid workflow + the E2E/console spec + the docs (the gate
itself).

## Constitution Check

PASS — VII (no hard-coded secret; creds from env/wp-env defaults), IX (E2E tooling is dev-only, no runtime dep), X
(traces to 052; reuses the e2e scaffold + wp-env + CI), Guard Gate (test-guard on the E2E spec, docs-guard on the
DoD). Visual execution is the env-gated step this spec establishes as a CI gate.

## Project Structure

```text
.github/workflows/e2e.yml        # NEW — provision wp-env + Playwright + run test:e2e (PR + nightly)
tests/e2e/console.spec.js        # NEW — console-error sweep (editor/admin/front-end), fails on error
tests/e2e/helpers.js             # NEW — login + collectConsoleErrors (shared)
CONTRIBUTING.md / docs           # CHANGE — the DoD browser gate (E2E + console sweep; local run path)
```

**Structure Decision**: A dedicated E2E workflow (heavier browser job, not blocking every push) + a console sweep
spec reusing the existing Playwright config. The console allow-list tolerates warnings + known third-party noise,
fails on real errors. No secret hard-coded.

## Complexity Tracking

> No violations.
