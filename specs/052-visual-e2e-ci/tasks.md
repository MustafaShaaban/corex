# Tasks: Visual & E2E verification in CI

**Feature**: 052-visual-e2e-ci · **Branch**: `feature/052-visual-e2e-ci`

**Story legend**: US1 = E2E in CI (P1, MVP) · US2 = console sweep (P1) · US3 = DoD docs (P2).

- [x] T001 [US2] Add `tests/e2e/helpers.js` (login + collectConsoleErrors with a documented allow-list) + `tests/e2e/console.spec.js` (no-console-error on editor/admin/front-end). Validate the JS.
- [x] T002 [US1] Add `.github/workflows/e2e.yml` — checkout, Node + PHP, composer/npm install, wp-env start, Playwright install, `npm run test:e2e`, stop wp-env (always). Validate the YAML.
- [x] T003 [US3] Document the DoD browser gate (E2E smoke + console sweep; CI + local `npm run test:e2e` + wp-env) in CONTRIBUTING / docs.
- [x] T004 Guard Gate (test-guard on the E2E spec, docs-guard on the DoD) + suites green; DECISIONS #86 + PROGRESS; commit → PR → CI → merge.
