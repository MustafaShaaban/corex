---

description: "Task list for Spec 070 — Transport Error Fidelity & Hidden-Admin Style Parity"
---

# Tasks: Transport Error Fidelity & Hidden-Admin Style Parity

**Input**: Design documents from `/specs/070-transport-error-fidelity-and-admin-style-parity/`

**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md). No `research.md`/`data-model.md`/`contracts/` — see plan.md §Project Structure for why.

**Tests**: REQUIRED. The constitution's Definition of Done mandates unit + E2E tests (Pest / Jest / Playwright) that pass, plus the Guard Gate, i18n-readiness, RTL verification, and WCAG 2.2 AA for UI.

> **Retroactive record.** Written 2026-07-20 after the work landed in commit `f9c5656`, to close the
> Spec Kit gap described in plan.md and `DECISIONS.md` #145. Every task below is marked complete
> because every task below was verifiably done — the file set and test set are reconciled against
> the commit, not asserted. Tasks are numbered in the order the work was actually performed.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to

---

## Phase 1: Setup

- [x] **T001** Verify the Environment Gate: `wp theme list` shows `corex`; `wp plugin list` shows `corex-core`, `corex-blocks`, `corex-config` active; `http://corex.local` boots with no PHP fatals. **Blocks everything** — constitution mandate.
- [x] **T002** Reproduce both owner-reported defects on `corex.local` *before* changing any code. Neither cause may be assumed from the symptom. **Blocks T004-T020.**
- [x] **T003** Capture a baseline suite count so any later red is attributable to this branch.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish *why* the save 404'd before changing any controller, since the symptom named the wrong subsystem.

- [x] **T004** [US1] Read `WP_REST_Request::get_parameter_order()` in core and confirm JSON body params and the query string resolve **before** URL params. Confirm the editor payload actually carries an `id`.
- [x] **T005** [US1] Trace the payload origin: `useEmailStudio.js` builds the draft as `{ ...EMPTY_DRAFT, ...latest }`, spreading the whole version record. Confirm templates with no saved version use `emptyDraft()` and carry no `id` — which is why the defect looked intermittent.
- [x] **T006** [US2] Read `parseAndThrowError()` in `wp-includes/js/dist/api-fetch.js` and confirm `wp.apiFetch({ parse: false })` **rejects** with the raw `Response` on a non-2xx, making `corex-runtime.js`'s `response.ok === false` branch dead code.

**Checkpoint**: both causes identified from core source rather than inferred from symptoms.

---

## Phase 3: US1 — A route's identity comes from its path (P1) 🎯 MVP

**Goal**: A route-captured identifier can never be shadowed by the request payload. **Independent test**: post a body carrying a conflicting `id` to a route that captures `id`, and assert the route's value wins.

### Tests first

- [x] **T007** [US1] Failing Pest integration tests for `Corex\Http\RouteParam`, including the exact 3859/3860 shadowing that produced the owner's 404. `tests/Integration/Http/RouteParamTest.php` — 5 cases.

### Implementation

- [x] **T008** [US1] Add `Corex\Http\RouteParam` reading `get_url_params()` directly. `plugins/corex-core/src/Http/RouteParam.php`.
- [x] **T009** [P] [US1] Apply `RouteParam` to every route-captured identifier in `addons/corex-email/src/Studio/EmailStudioController.php`.
- [x] **T010** [P] [US1] Apply `RouteParam` in `plugins/corex-forms/src/Flow/FlowController.php`.
- [x] **T011** [P] [US1] Apply `RouteParam` in `plugins/corex-config/src/Submissions/SubmissionsController.php`.
- [x] **T012** [US1] Apply `RouteParam` in `plugins/corex-config/src/Data/DataManagementController.php`. **Leave `source` on `get_param()`** — `migrations()` reads it as a query filter on a route that captures no `source`; converting it would break a working filter. 24 `::int` + 1 `::string` across T009-T012.
- [x] **T013** [US1] Project the draft onto editable fields only via `draftFrom()`, so server-owned columns never travel back in the payload. `plugins/corex-config/src/Email/useEmailStudio.js`.
- [x] **T014** [US1] Correct four integration suites to set route ids via `set_url_params()`, modelling what `WP_REST_Server::dispatch()` actually does. `set_param()` put them in the body — precisely the fidelity gap that hid the bug. `tests/Integration/{Data/DataManagementControllerTest,Forms/FlowControllerTest,Forms/FlowLifecycleTest,Submissions/SubmissionsControllerTest}.php`.

**Checkpoint**: template save returns `201` with a new version. US1 independently shippable.

---

## Phase 4: US2 — A failed request says what actually failed (P1)

**Goal**: A server error reaches the user with its own message. **Independent test**: make the API return a 404 with a message and assert the UI shows that message, not a generic one.

### Tests first

- [x] **T015** [US2] Failing Jest tests for four cases where `wp.apiFetch` **rejects**. Every prior apiFetch test mocked a resolve, which is how this shipped. `tests/corex-runtime.test.js`.

### Implementation

- [x] **T016** [US2] Handle the rejection in `viaApiFetch`, reading an error `Response` through the same path as a success; let non-`Response` rejections propagate to the transport catch.
- [x] **T017** [US2] Report the status on a non-JSON error body via `statusMessage()` ("The server returned an unexpected response (500).") so it is distinguishable from a dead network. `status: 0` now means exactly "nothing came back".
- [x] **T018** [US2] Preserve `details.fields` end to end. `normalise()` already returns a valid envelope verbatim (`isEnvelope()`, L60-62), so no new code "surfaces" the fields — routing the rejection through `fromResponse()` (T016) is what stops `genericError()` from discarding the whole envelope. Covered by the "keeps field details from a rejected validation response" case. `plugins/corex-core/assets/js/corex-runtime.js`.

**Checkpoint**: the 404 from US1 would now have been visible on its own. Two bugs, unstacked.

---

## Phase 5: US3 — A hidden `/wp-admin` is styled, not merely routed (P2)

**Goal**: The hidden-admin 404 is visually indistinguishable from a genuine 404. **Independent test**: fetch both logged out and compare response size and computed geometry.

### Tests first

- [x] **T019** [US3] Failing Pest integration test asserting block styles are re-enqueued on the hidden-admin response. `tests/Integration/Security/HiddenAdminResponseTest.php`.
- [x] **T020** [P] [US3] Playwright assertion that `wp-block-library` is present and the response size is within 5% of the control 404 — the gap it replaced was 42%. `tests/e2e/security-access.spec.js`.

### Implementation

- [x] **T021** [US3] Add `LoginRouteGuard::enqueueBlockStyles()`, hooked from `dropAdminContext()`. Enqueue directly rather than filtering `should_load_block_editor_scripts_and_styles`, which would also satisfy the block-*editor* branch and pull in assets a real front-end 404 never carries. `plugins/corex-config/src/Security/LoginProtection/LoginRouteGuard.php`.
- [x] **T022** [US3] Scope `.corex-header__inner { max-inline-size: 100% }` to `.corex-header`. The rule tied core's `.is-layout-constrained > :where(…)` on specificity and only ever won when the sheet loaded as a `<link>` — which is exactly what T021 caused. `theme/assets/css/corex-navigation.css`.
- [x] **T023** [US3] Correct `specs/069-admin-correctness-and-login-parity/spec.md` in place: 069 was right about `wp_should_load_separate_core_block_assets()` and `wp_should_load_block_assets_on_demand()`, but never identified the gate that actually caused the symptom.

**Checkpoint**: 46,587 B → 79,711 B against a 79,964 B control; computed font-family, `main` max-width, and header geometry match exactly.

---

## Phase 6: Polish & Close-out

- [x] **T024** Run the full suites and record exact counts: 164/164 integration, 298/298 Jest, 8/8 security-access E2E. *(Counts as recorded in `PROGRESS.md` at implementation time; re-verified independently at the spec 071 gate, not by this backfill.)*
- [x] **T025** Guard Gate: `wp-guard`, `clean-code-guard`, `test-guard` clean.
- [x] **T026** Update `CHANGELOG.md`, `PROGRESS.md`, and `DECISIONS.md` (#143, #144) in the same commit.
- [x] **T027** Regenerate `specs/057-brand-tokens-logo-system/inventories/consumers.json`.
- [ ] **T028** Backfill `plan.md`, `tasks.md`, `checklists/requirements.md` — this artifact set. Recorded as `DECISIONS.md` #145. *(Completed 2026-07-20 during spec 071 startup.)*

---

## Known open items (carried, not absorbed)

These are recorded in `spec.md` §Out of scope and remain open after 070:

- `[Corex] WARNING: Mail rejected: Illegal characters in the subject field.` — recurring in `debug.log`, a separate real defect.
- `WP_DEBUG_DISPLAY` is `true` in `wp/wp-config.php`, printing PHP notices into response bodies.
- `corex-runtime.js` routes 11 user-facing strings through a `t()` wrapper `make-pot` cannot extract; no POT is generated for `corex-core` yet.
- A pre-existing PHP segfault partway through the unit suite — identical with and without this work (143 PASS blocks, crash after `BootLoggerTest`, passes in isolation).
