# Tasks: Every Admin Refusal Is a CoreX Page

Tests are written before the model on purpose. Spec 079 shipped its error half unbuilt and nothing
noticed, because no test rendered a real refusal. The matrix is what stops that recurring.

## Phase 1 — Reconnaissance

- [x] **T001** Reproduce as a real subscriber on `corex.local` across eleven admin addresses.
      → `evidence/before/refusal-matrix.md`. **Nine of eleven are unbranded, two of those nine are
      CoreX's own post-type screens.**
- [x] **T002** Confirm the boundary: `wp_die()` selects its handler by request type before any filter
      (`wp/wp-includes/functions.php:3791-3849`), so filtering `wp_die_handler` alone cannot reach a
      machine caller.
- [x] **T003** Check what the non-matching rows actually render. **Finding that changed the plan:**
      WordPress uses several different refusal sentences, all translated, so 079's SC-005
      (absence of one literal string) passes today on `users.php` and would pass site-wide in Arabic.
      The assertion must be positive.

## Phase 2 — The tests, proven failing against `main`

- [x] **T010** `tests/e2e/admin-errors.spec.js` — {administrator, editor, subscriber} × {CoreX page,
      `edit.php?post_type=corex_job`, core screen, third-party screen, nonexistent CoreX page}.
      Assert the response is a CoreX document at the right status. Confirm it fails on `main`.
- [x] **T011** Expired-link coverage using an action the user may start, with a stale nonce.
- [x] **T012** Logout still shows WordPress's confirmation and completes (SC-003).
- [x] **T013** Integration: REST 403 is JSON, AJAX die is `-1`.
- [x] **T014** Guard: no CoreX subscriber on the five machine `wp_die_*_handler` filters (SC-004).

## Phase 3 — The error model

- [x] **T020** `AdminErrorKind` enum.
- [x] **T021** `AdminError` value object. Pure, no WordPress.
- [x] **T022** `AdminErrorClassifier` — status + marker → kind. Never reads message text.
- [x] **T023** `AdminErrorPresenter` — in-shell card and standalone document from one source of copy.
- [x] **T024** Unit tests for T020–T023 without WordPress.

## Phase 4 — The bridge

- [x] **T030** `AdminDieHandler`: `is_admin()` required; bail on cron, WP-CLI, `REST_REQUEST`.
- [x] **T031** The `check_admin_referer` marker, request-scoped, with `log-out` passing through.
- [x] **T032** Honour `$args` — `response`, `back_link`, `link_url`/`link_text`, `exit`,
      `text_direction`; accept a `WP_Error` message; `wp_kses_post()` the pass-through.
- [x] **T033** try/catch → `_default_wp_die_handler`, so a throwing presenter cannot blank the page.
- [x] **T034** Register in `ConfigServiceProvider::boot()`.

## Phase 5 — The surrounding truths

- [x] **T040** `AdminPage` denial copy names the screen's real capability (FR-006).
- [x] **T041** `StandalonePage::read()` stops failing silently (FR-010).
- [x] **T042** `AccessDeniedGate` renders through the presenter; docblock corrected.
- [x] **T043** Standalone CSS variants for the new kinds.
- [x] **T044** `CorexErrorState.js` at field/action/panel/page scale, plus Jest coverage (FR-011).
- [x] **T045** Rendering coverage added as `tests/Integration/Admin/DeniedSurfaceCopyTest.php`.
      The reflection-based `AccessDeniedGateTest` is **kept, not replaced**: `intercept()` still
      exits on both branches, so its branch point genuinely cannot be reached any other way, and the
      four cases it covers are real. What it could never see was what each branch *says* — which is
      exactly where the false capability claim lived. The two files now cover the branch and the
      words separately, rather than one file pretending to do both.

## Phase 6 — Close

- [x] **T050** Full matrix green; capture `evidence/after/`.
- [x] **T051** Guards: `wp-guard`, `clean-code-guard`, `test-guard`.
- [x] **T052** `DECISIONS.md` entry amending #174.
- [x] **T053** Correct spec 079's `tasks.md` to show which phases it actually shipped.
- [x] **T054** `PROGRESS.md`.
