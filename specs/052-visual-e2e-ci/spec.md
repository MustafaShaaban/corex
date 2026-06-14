# Feature Specification: Visual & E2E verification in CI

**Feature Branch**: `feature/052-visual-e2e-ci`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Every spec since 018 ends 'env-gated — needs a browser.' Formalize the Playwright E2E
smoke + a console-error sweep into CI so browser regressions are caught automatically, and add browser verification
to the Definition of Done. Investigate the block console errors (item 20) with a real assertion. Build on the
existing `tests/e2e/` scaffold + wp-env."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The E2E smoke runs in CI (Priority: P1) 🎯 MVP

A maintainer opening a PR gets the Playwright **E2E smoke** run automatically in CI — a real WordPress (via wp-env)
is provisioned, the Corex theme + plugins activated, and the three core flows exercised in a browser (insert a
`corex/*` block in the editor; submit the front-end contact form; apply a kit) — so a browser-level regression is
caught without a human running it locally.

**Why this priority**: Every spec since 018 is "browser-unverified"; an automated E2E run is the standing
verification that closes that gap permanently.

**Independent Test**: The CI workflow provisions wp-env, activates Corex, and runs the Playwright smoke; a failing
flow fails the workflow; the workflow is defined and valid.

**Acceptance Scenarios**:

1. **Given** a PR, **When** CI runs the E2E job, **Then** it provisions a real WordPress (wp-env), activates the
   Corex theme + plugins, and runs the Playwright smoke (block insert / form submit / kit apply).
2. **Given** a broken flow, **When** the E2E job runs, **Then** it **fails** the workflow (non-zero), surfacing the
   regression.
3. **Given** the workflow, **When** validated, **Then** it is a well-formed CI workflow that installs Playwright +
   the browser and runs `npm run test:e2e`.

---

### User Story 2 - A console-error sweep (Priority: P1)

The E2E run asserts there are **no console errors** on the key surfaces — the block editor (where item-20 block
errors would appear), the Corex admin screens, and a front-end page with Corex blocks — so a JavaScript/asset
regression (a 404 asset, a bad block registration, a React warning escalated to an error) fails CI instead of
hiding.

**Why this priority**: Item 20 (block console errors) is real and unknown until something asserts on the console;
this turns "no one has looked" into "CI looks every run."

**Independent Test**: The E2E spec attaches a console listener and **fails** if a console error is emitted while
loading the editor / admin / a front-end page with Corex blocks.

**Acceptance Scenarios**:

1. **Given** the editor with Corex blocks available, **When** it loads under E2E, **Then** the test asserts **no**
   console error was emitted (a block registration / asset error fails the test, naming it).
2. **Given** a front-end page with Corex blocks + the form runtime, **When** it loads, **Then** no console error is
   emitted.
3. **Given** a real console error, **When** the sweep runs, **Then** it fails and reports the message (so the root
   cause — not a suppressed symptom — is fixed).

---

### User Story 3 - Browser verification in the Definition of Done (Priority: P2)

The Definition of Done documents that a UI change is verified in a browser (the E2E smoke + the console sweep), so
"env-gated" is no longer an open-ended excuse — it is a CI gate with a documented path to run locally.

**Why this priority**: The process change that makes the verification durable. P2 because the CI job (US1/US2) is
the enforcement; this documents it.

**Independent Test**: The contributor docs/DoD state that UI work is browser-verified via the E2E smoke + console
sweep (in CI, runnable locally), and how to run it.

**Acceptance Scenarios**:

1. **Given** the contributor docs, **When** read, **Then** they state that UI changes are verified by the E2E smoke
   + console sweep, with the local run command (`npm run test:e2e`) and the wp-env prerequisite.
2. **Given** the DoD, **When** a UI change is made, **Then** the E2E/console gate is part of "done."

---

### Edge Cases

- A console **warning** (not error) MUST NOT fail the sweep — only errors (so legitimate WP deprecations don't
  block); the threshold is documented.
- A known, unavoidable third-party console error MAY be allow-listed explicitly (documented), so the sweep stays
  meaningful rather than disabled.
- The E2E job MUST be resilient to a slow wp-env boot (a readiness wait), not a flaky immediate failure.
- The E2E job not being able to provision a browser/wp-env (infra outage) is an infra failure, distinct from a real
  regression — surfaced clearly.
- No secret (admin password) is hard-coded; CI credentials come from the workflow env / wp-env defaults.

## Requirements *(mandatory)*

### Functional Requirements

**E2E in CI (US1)**

- **FR-001**: A CI workflow MUST provision a real WordPress (wp-env), activate the Corex theme + plugins, install
  Playwright + a browser, and run the E2E smoke (`npm run test:e2e`) on PRs (and/or a schedule).
- **FR-002**: A failing E2E flow MUST **fail** the workflow (non-zero), surfacing the regression; the workflow MUST
  wait for wp-env readiness (no flaky immediate failure).
- **FR-003**: The E2E smoke MUST cover the three core flows: insert a `corex/*` block in the editor, submit the
  front-end contact form, and apply a kit.

**Console-error sweep (US2)**

- **FR-004**: The E2E run MUST attach a console listener and **fail** on a console **error** (not warning) emitted
  while loading the block editor, a Corex admin screen, or a front-end page with Corex blocks — reporting the
  message.
- **FR-005**: An explicit, documented **allow-list** MAY exempt a known unavoidable third-party error; the default
  is zero tolerated errors.

**Definition of Done (US3)**

- **FR-006**: The contributor docs / DoD MUST state that UI changes are browser-verified via the E2E smoke + the
  console sweep (enforced in CI, runnable locally with `npm run test:e2e` + wp-env), so "env-gated" is a CI gate,
  not an open excuse.

**Cross-cutting**

- **FR-007**: No secret MUST be hard-coded (CI credentials from the workflow env / wp-env defaults); the E2E config
  + workflow MUST be valid and self-contained. No new hard runtime dependency (E2E tooling is dev-only).

### Key Entities *(include if feature involves data)*

- **E2E workflow**: the CI job — provision wp-env, activate Corex, install Playwright + browser, run the smoke,
  fail on regression.
- **E2E smoke**: the Playwright flows (block insert, form submit, kit apply) + the console-error sweep.
- **Console sweep**: a console listener over key pages (editor/admin/front-end) failing on an error, with an
  optional documented allow-list.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A PR automatically runs the Corex E2E smoke in CI against a real WordPress — browser regressions are
  caught **without** a human running it locally.
- **SC-002**: A console **error** on the editor / admin / a Corex front-end page **fails** CI (item-20 class issues
  surface), while warnings do not.
- **SC-003**: The three core flows (block insert / form submit / kit apply) are exercised in a browser on **every**
  E2E run.
- **SC-004**: The Definition of Done documents browser verification (E2E + console sweep) as a CI gate with a local
  run path — "env-gated" is no longer an open-ended excuse.
- **SC-005**: No secret is hard-coded; the workflow + E2E config are valid and self-contained.

## Assumptions

- Builds on and **reuses** the existing `tests/e2e/` scaffold (`playwright.config.js` + `smoke.spec.js`, the three
  flows), the `@playwright/test` dev dependency, wp-env (spec 028's Docker dev env), and the existing GitHub Actions
  CI (`.github/workflows/ci.yml`) — this feature adds the E2E workflow + the console sweep + the DoD docs; it does
  not re-spec them.
- CI uses **wp-env** (Docker) to provision WordPress; the workflow may run E2E on PRs and/or nightly (the heavier
  browser job need not block every push if scoped to a dedicated workflow).
- The console sweep tolerates warnings, fails on errors, with a documented allow-list for known third-party noise.
- Out of scope (explicitly): full visual-regression (screenshot diffing) — a later increment; cross-browser matrix
  beyond one browser; performance/Lighthouse budgets in CI (spec 037 covers insights). The deliverable is the E2E
  smoke + console sweep in CI + the DoD.
- **Execution is environment-dependent by nature**: the headless deliverable is the valid workflow + the E2E/console
  spec + the docs; the actual browser run happens in CI (or locally with wp-env + `npx playwright install`), which is
  exactly the gate this spec establishes.
