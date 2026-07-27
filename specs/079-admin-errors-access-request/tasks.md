# Tasks: Unified Admin Error and Access Request Experience

**Branch**: `spec/079-admin-errors-access-request` | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Phases are ordered so the defect is fixed and proven before anything is generalised. The boundary
tests (Phase 4) are written before the error model (Phase 5) on purpose: they are what stops the
generalisation from becoming a regression.

## Phase 1 — Reconnaissance (done before the spec)

- [x] **T001** Reproduce the defect end-to-end as a real subscriber on `corex.local`.
      → `evidence/before/raw-json-navigation.md`, `access-denied.png`, `after-request-raw-json.png`.
      **Finding**: the request *succeeds*. This is a success rendered as an operation envelope, not
      an error rendered badly — which changes the fix from "a nicer error page" to "a confirmation
      that does not exist yet".
- [x] **T002** Read the surrounding code: `AdminPage::deniedBody()`, `AccessDeniedGate`,
      `AccessController::createRequest()`, `AccessService::requestAccess()`, `AccessRequestRepository`,
      `OperationsModeController`, `StandalonePage`.
      **Finding**: US2 is largely already built — 15 screens render designed denials and the menu
      gate serves a designed 403. The plan shrank accordingly.
- [x] **T003** Verify on the running install what `?page=corex-nonexistent` actually renders, before
      writing anything that claims to fix it. Record the result either way.
      → `evidence/before/nonexistent-page.md`. **The guess was wrong, and the truth is worse.**
      It does not reach WordPress's white box. `AccessDeniedGate` matches on the `corex-` prefix
      alone, so an **administrator** requesting a page that does not exist gets HTTP 403 and
      *"You don't have access to this area — your role doesn't include the `manage_options`
      capability"*. That is a false statement made to someone who holds that capability, and it
      offers them a form to request access to a screen that does not exist. With Phase 2 in place
      that form would create a real access request for a nonexistent ability. Promoted from
      "verify the gap" to a P1 correctness fix: **not-found is not denied**.

## Phase 2 — The request has somewhere to go (US1, P1) — THE FIX

- [x] **T010** `AccessRequestStore::pendingFor()` + `AccessRequestRepository` implementation:
      the open request for a requester and ability/area, or none. Prepared statement, no interpolation.
- [x] **T011** `PendingAccessRequest` value object (core) + `PendingAccessRequestReader` interface.
- [x] **T012** `AccessRequestFlash` — user-bound, single-use, short-lived, non-sensitive.
- [x] **T013** `AccessRequestFormController` — `admin_post_corex_access_request`, `AdminGuard`,
      allow-listed ability, duplicate refusal, `AccessService::requestAccess()`, PRG, `exit`.
- [x] **T014** `AdminPage::deniedBody()` renders form / pending / validation-failed / service-failed.
      Dates through `AdminDate`. Form posts to `admin-post.php`.
- [x] **T015** Bind `AdminPage` explicitly in `ConfigServiceProvider` with the reader; register the
      controller.
- [x] **T016** CSS for the pending and error states, tokens only, logical properties.

## Phase 3 — Prove the fix (US1)

- [ ] **T020** Unit: the controller's decisions — invalid ability, empty reason, duplicate, service
      exception. Real value objects, no mocked state (test-guard Rule 8).
- [ ] **T021** Integration (real WordPress): submitting creates exactly one request; submitting twice
      creates one; the denied surface then shows the pending state read from the database.
- [ ] **T022** Playwright as a real subscriber: submit → the browser stays in wp-admin, the URL is
      not `/wp-json/`, the page shows a confirmation, and refresh creates no second request.
      **This test must fail against `main`.** Verify that it does before trusting it.
- [ ] **T023** Assert no internal field (`operation_id`, `state`, `affected_ids`, `audit_event_id`)
      appears in any rendered page.

## Phase 4 — The boundary holds (US3, P1)

Written before Phase 5, because Phase 5 is what would break it.

- [ ] **T030** Integration: `POST /corex/v1/access/requests` still answers JSON, unchanged status,
      for anonymous / insufficient / valid callers.
- [ ] **T031** Integration: no CoreX code path converts an AJAX, cron, WP-CLI or feed response to HTML.
- [ ] **T032** A test that fails if a global `wp_die_handler` filter is ever added — the specific
      shortcut this plan refused.

## Phase 5 — One error model (US2, P1)

- [ ] **T040** `AdminError`, `AdminErrorKind`, `AdminErrorPresenter`.
- [ ] **T041** Convert the four existing hand-written error call sites. Separate commit from the fix.
- [x] **T042** Cover the gap T003 finds, if it is real.
- [ ] **T043** Unit: no error presentation emits a path, exception, SQL, nonce or internal id.

## Phase 6 — React failure states (US4, P2)

- [ ] **T050** `CorexErrorState` at field / action / panel / page scale.
- [ ] **T051** Wire the existing React screens' failure paths to it.
- [ ] **T052** Jest coverage for each scale.

## Phase 7 — Acceptance, evidence, close

- [ ] **T060** Acceptance matrix on the running install: RTL, 375px, 200% zoom, light and dark, on
      the denied surface in each of its four states. Screenshots to `evidence/after/`.
- [ ] **T061** Full gate: Pest unit + integration, Jest, Playwright, `lint:css`, `lint:js`, token
      inventory.
- [ ] **T062** Guard Gate: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- [ ] **T063** Docs, `PROGRESS.md`, `DECISIONS.md`.
- [ ] **T064** PR, green CI, merge, delete branch.

## Findings log

Recorded as they happen, including mistakes, per the working guide.

- **T001** — the defect is a *successful* operation with no confirmation, not a failure with a bad
  page. Writing the spec from the brief alone would have produced a friendly error page and left
  the real hole open.
- **T010** — `prepare()` casts a null argument to the empty string, so the duplicate-guard query
  could not use a `%s` placeholder for the unused half of the ability/area pair: an area request
  would never have matched itself and the guard would have passed every time, silently. Each side
  is a literal `IS NULL` or a real placeholder, never a placeholder holding null.
- **T015** — constructing the new controller at boot made WordPress report
  `_load_textdomain_just_in_time` on **every** admin page: the graph reaches a translated string
  before `init`. `AccessController` is already resolved lazily inside `rest_api_init` for the same
  reason, so the browser half is resolved inside its `admin_post` action. Found by bisecting the
  working tree against a stash, not by reading it.
- **T042 (twice wrong before right)** — the first implementation asked `$_registered_pages` whether
  a `corex-` page exists. It reported **every real CoreX screen as missing to exactly the people
  this gate exists for**: `add_submenu_page()` records a page the viewer may not open in
  `$_wp_submenu_nopriv` and returns *before* reaching `$_registered_pages`, so registration is not
  viewer-independent. Caught because the live check covered a subscriber on a real page, not only
  an administrator on a fake one. The fix reads the same two globals, in the same order, as
  WordPress's own `user_can_access_admin_page()`.

  The lesson generalises the one this project keeps re-learning: a check verified from one vantage
  point is not verified. Four cases — {administrator, subscriber} x {real page, fake page} — is the
  smallest honest matrix, and three of them pass with the wrong implementation.
- **T002** — the destination problem: the requester cannot open any CoreX screen, so a "request
  status" page would deny them again. The answer is to make the denied surface itself state-aware,
  which also removes the need for a flash on the success path entirely.
