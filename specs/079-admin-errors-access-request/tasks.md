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

- [x] **T020** Unit: the controller's decisions — invalid ability, empty reason, duplicate, service
      exception. Real value objects, no mocked state (test-guard Rule 8).
- [x] **T021** Integration (real WordPress): submitting creates exactly one request; submitting twice
      creates one; the denied surface then shows the pending state read from the database.
- [x] **T022** Playwright as a real subscriber: submit → the browser stays in wp-admin, the URL is
      not `/wp-json/`, the page shows a confirmation, and refresh creates no second request.
      **This test must fail against `main`.** Verify that it does before trusting it.
- [x] **T023** Assert no internal field (`operation_id`, `state`, `affected_ids`, `audit_event_id`)
      appears in any rendered page.

## Phase 3b — The other half of the workflow (found mid-build, P1)

Not in the original plan. Found while writing the browser test's cleanup: the cleanup could not be
written, because the route it needed returns an empty array by construction.

- [x] **T024** `PendingAccessRequests` presenter — one shape for both the screen and the API, so
      they cannot disagree about what a pending request is.
- [x] **T025** `AccessController::requests()` returns real pending requests instead of `[]`.
- [x] **T026** `AccessScreen` localizes real pending requests instead of `[]`.
- [x] **T027** `AccessRequestsPanel` — requester, ability, when (spec 076), reason, and working
      Approve / Deny calling the decision route that already existed.
- [x] **T028** A waiting card on the Overview tab, because the panel lives inside Role matrix and a
      workflow that needs somebody to open the right tab is a workflow that stalls. Renders nothing
      when nobody is waiting.
- [x] **T029** Browser coverage of the whole loop: request → administrator sees it → deny → the
      requester's screen offers the form again.

> **Phases 4b–7 were left open when this spec shipped, and the spec was closed anyway.** Its title
> claims a unified admin error experience; what merged was the access-request fix (US1) plus T042.
> The consequence went unnoticed for a release, because the one browser test touching a refusal
> visited the one URL that could not fail. **Spec 083 completes them** — the boxes below are ticked
> where 083 did the work, and say so. Nothing here was done by 079.

## Phase 4 — The boundary holds (US3, P1)

Written before Phase 5, because Phase 5 is what would break it.

- [x] **T030** Integration: `POST /corex/v1/access/requests` still answers JSON, unchanged status,
      for anonymous / insufficient / valid callers.
- [x] **T031** *(spec 083)* Integration: no CoreX code path converts an AJAX, cron, WP-CLI or feed
      response to HTML. → `tests/Integration/Admin/AdminDieBoundaryTest.php`.
- [x] **T032** *(spec 083, inverted)* The filter this task existed to forbid is now installed
      deliberately, and DECISIONS #187 records why with the measurement that changed the answer.
      The test that replaces it asserts the boundary that actually matters: **no CoreX subscriber on
      any of the five machine `wp_die_*_handler` filters.**

## Phase 5 — One error model (US2, P1)

- [x] **T040** *(spec 083)* `AdminError`, `AdminErrorKind`, `AdminErrorPresenter`, plus
      `AdminErrorClassifier` and `AdminDieHandler`.
- [x] **T041** *(spec 083, partly)* `AccessDeniedGate`'s denial renders through the presenter. The
      other standalone call sites still use `StandalonePage::notice()`, which is the shared
      short-notice helper rather than duplicated assembly — see the note on `notFound()`.
- [x] **T042** Cover the gap T003 finds, if it is real.
- [x] **T043** *(spec 083)* The presenter emits only CoreX copy plus an allow-listed quotation of
      the caller's message; `tests/Unit/Admin/AdminErrorTest.php` covers the model.

## Phase 6 — React failure states (US4, P2)

- [x] **T050** *(spec 083)* `CorexErrorState` at field / action / panel / page scale.
- [ ] **T051** Wire the existing React screens' failure paths to it. **Still open** — the component
      exists and is tested; converting each screen's own failure path is separate work.
- [x] **T052** *(spec 083)* `plugins/corex-config/src/admin/components/__tests__/corexErrorState.test.js`.

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
- **T024–T029 (the bigger half)** — `AccessRequestStore::pending()` had **no production caller**.
  The REST list route returned a hardcoded `[]`, the Access screen localized a hardcoded `[]`, and
  the panel that rendered them printed a sentence about the plumbing and no controls. So an access
  request was written, audited and notified, and then no surface in the product ever read the table
  — while the denied screen told the requester an administrator would review it.
  `evidence/before/requests-go-nowhere.md`.

  It was found by trying to write a test fixture, not by reading the code: the browser spec needed
  to clear pending requests between runs, and the only route that could do that returns nothing by
  construction. A test that needs a capability the product claims to have is a good way to discover
  it does not.

  This is the more serious half of the spec. The original defect made a successful request *look*
  like a failure; this one made it *be* one. Fixing only the requester's side would have delivered
  people into that silence more convincingly than before.
- **T029** — Chromium in headless stops ticking `requestAnimationFrame` on the Access screen after
  a same-document tab navigation, and Playwright's actionability check waits on two animation
  frames — so a click that follows the Overview link hangs on a paint quirk rather than on the
  product. Established by measuring rAF directly on five screens and both navigation paths before
  changing the test, rather than reaching for `force: true`, which would have hidden it. The test
  asserts the link's href and opens the tab directly.
- **T022** — the first cleanup helper swallowed a failed request and returned. It therefore did
  nothing, silently, and the suite failed two tests for a reason nothing reported. Rewritten to
  assert the nonce exists and the list returns 200. A cleanup that can fail quietly is worse than
  no cleanup.
- **T016** — `--corex-admin-space-3xs` does not exist. Stylelint accepts any `var()`, so a typo'd
  token lints clean and renders as nothing. Caught by listing the defined tokens rather than by
  trusting the linter.
- **T002** — the destination problem: the requester cannot open any CoreX screen, so a "request
  status" page would deny them again. The answer is to make the denied surface itself state-aware,
  which also removes the need for a flash on the success path entirely.
