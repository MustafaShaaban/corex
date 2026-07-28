# Implementation Plan: Unified Admin Error and Access Request Experience

**Branch**: `spec/079-admin-errors-access-request` | **Date**: 2026-07-28 | **Spec**: [spec.md](spec.md)

## Summary

Stop posting a browser at a REST endpoint. Give the request a destination. Then generalise the one
designed refusal CoreX already has into the one every refusal uses.

## What already exists — read before planning the work

The reconnaissance shrank this feature considerably, and the shrinking is the plan.

- **Every CoreX screen already renders a designed denial.** Fifteen screens call
  `AdminPage::permissionDenied()`; `AccessDeniedGate` intercepts `admin_page_access_denied` for
  `corex-*` pages and serves a real 403 as a fully styled standalone document. US2 is *mostly done*
  and was done before this spec existed.
- **`StandalonePage::notice()` exists** and is already used by `OperationsModeController` for its
  403. The shared error surface is not a new invention; it is an existing method that needs a model
  behind it instead of four hand-written call sites.
- **`OperationsModeController` (spec 077) is the exact pattern** the access request needs: one
  `admin_post` action, `AdminGuard::verifiedPost()`, allow-listed input, `wp_safe_redirect` with a
  status, `exit`. This plan copies its shape deliberately rather than inventing a second one.

So the real work is narrower than the spec's four stories suggest: **one missing controller, one
missing state on an existing surface, one model behind four existing error call sites, and the
boundary tests that keep the fix from becoming a regression.**

## The destination problem, and the answer that avoids inventing a screen

The obvious redirect target for "your request was sent" is a status page. The requester cannot open
one — they hold no CoreX ability, which is the entire reason they are here. Sending them to
`corex-access` would deny them again: the same defect, one page later.

**So the destination is the page they were denied from.** `AccessDeniedGate` already renders a full
designed 403 there. The denied surface becomes state-aware: where a pending request exists for this
user, it renders *"Requested 28 July 2026 at 4:12 PM — waiting for an administrator"* in place of
the form.

That has a property worth stating plainly: **the confirmation is read from stored state, not from a
redirect flag.** Refreshing shows it. Coming back tomorrow shows it. A forged `?corex_access=sent`
shows nothing, because nothing is read from the query string. FR-003, FR-005 and half of FR-011 fall
out of the data model rather than out of flash-message discipline — which is the version that cannot
be got wrong later.

Flash state is still needed for the two paths that must carry text back: a validation failure
(preserve the typed reason) and a service failure (carry a correlation reference). Those use a
short-lived, user-bound, single-use transient keyed by user ID — never the query string.

## Technical Context

**Language/Version**: PHP 8.3, JavaScript (`@wordpress/scripts`), CSS

**Primary Dependencies**: WordPress 7.0+ (`admin_post`, `wp_safe_redirect`, transients), `AdminGuard`
(nonce + capability), `StandalonePage`, `AdminDateTime` (spec 076) for every date shown. No new
runtime dependency, no new npm package.

**Storage**: the existing `AccessRequestRepository` table. One new read (`pendingFor`) — no schema
change, no migration.

**Testing**: Pest (unit + integration against real WordPress), Jest, Playwright.

**Target Platform**: wp-admin, including with JavaScript disabled — which is the path that must work.

**Constraints**: no REST behaviour change; no content negotiation; no `wp_die` handler installed
globally; nothing shown that was not read from state.

**Scale/Scope**: 1 new admin-post controller, 1 new store method, 1 error model + presenter, 4
existing error call sites converted, 3 React error components.

## Constitution Check

- [x] **I. Theme is a skin** — plugin-side only.
- [x] **II. Plugins boot themselves** — the controller registers in `ConfigServiceProvider`.
- [x] **III. Thin controllers, fat services** — the controller validates and redirects; every
      decision about whether a request may be created stays in `AccessService`, unchanged.
- [x] **IV. Everything injected** — controller, store reader and presenter resolve through the
      container. `AdminPage` gains an explicit binding so its new reader is injected, not `new`ed.
- [x] **V. Runtime tokens** — the new states reuse the existing `corex-denied__*` and
      `corex-standalone__*` rules; any new rule is tokens only.
- [x] **VI. Conditional assets** — no new bundle. The standalone document already inlines its sheet.
- [x] **VII. Declarative security** — `AdminGuard::verifiedPost()`, allow-listed ability keys,
      `wp_safe_redirect` only.
- [x] **VIII. RTL-first** — logical properties.
- [x] **IX. No optional dep is hard** — no JavaScript is required for any path in this feature.
- [x] **X. Spec is source of truth** — traces to spec.md.
- [x] **Guard Gate + DoD** — `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.

**No violations.**

## Project Structure

```text
plugins/corex-core/src/Access/
├── AccessRequestStore.php            # MODIFIED: + pendingFor( requester, ability, area )
└── PendingAccessRequest.php          # NEW: the value object the denied surface renders

plugins/corex-core/src/Admin/
├── AdminPage.php                     # MODIFIED: deniedBody renders form OR pending state OR error
└── Errors/
    ├── AdminError.php                # NEW: the one error model (FR-012)
    ├── AdminErrorKind.php            # NEW: enum — denied, missing, expired, rate-limited, ...
    └── AdminErrorPresenter.php       # NEW: model -> StandalonePage document / in-page section

plugins/corex-config/src/Access/
├── AccessRequestFormController.php   # NEW: the admin_post handler. THE FIX.
├── AccessRequestFlash.php            # NEW: user-bound, single-use, non-sensitive
└── AccessRequestRepository.php       # MODIFIED: pendingFor query

plugins/corex-config/src/admin/components/
└── CorexErrorState.js                # NEW: field / action / panel / page scales (FR-022)
```

## Approach

### 1. The controller (FR-001 … FR-008) — the mandatory fix

`admin_post_corex_access_request`, shaped exactly like `OperationsModeController`:

1. `AdminGuard::verifiedPost()` — signed in, nonce valid. Failure renders the standalone 403 notice
   and exits, as the operations controller does.
2. Read `ability` and `reason` from `$_POST`, unslashed and sanitised.
3. **The ability is allow-listed against the catalog**, not trusted. A posted `ability` the catalog
   does not know is a validation failure, not a service exception.
4. Refuse a duplicate: if `pendingFor()` returns a request, redirect with `already-pending` — no
   second row created. Server-side, so a second tab and a double-click are both covered (FR-008).
5. Call `AccessService::requestAccess()` — **the same method the REST route calls**, so the two
   paths cannot drift.
6. `wp_safe_redirect()` back to `admin.php?page=<denied page>`, then `exit`.

`InvalidArgumentException` from the service is caught and turned into the validation state with the
reason preserved in the flash. Any other `Throwable` is caught, logged with a correlation reference,
and turned into the error state — the reference is shown, the exception is not (FR-015).

The form's `action` becomes `admin-post.php` and its nonce becomes the controller's own, not
`wp_rest`. **The REST route is not touched** (FR-002).

### 2. The denied surface becomes state-aware (FR-004, FR-005, FR-006, FR-007)

`AdminPage::deniedBody()` renders one of four things:

| State | Rendered |
|---|---|
| no request | the form (as today, with the corrected action) |
| pending | "Requested <date> — waiting for an administrator", no form |
| validation failed | the form, the field error, focus on the field, the reason preserved |
| service failed | the error state, a reference, and a retry |

The pending date goes through `AdminDate` (spec 076), so it reads *"28 July 2026 at 4:12 PM"* and
not the ISO string the current JSON leaks.

Reading the pending request needs a corex-config repository from a corex-core presentation class.
`AdminPage` takes a nullable `PendingAccessRequestReader` — the FR-008a pattern from spec 076 — and
`ConfigServiceProvider` binds `AdminPage` explicitly so the real reader is injected. With no reader
bound, the surface renders exactly what it renders today.

### 3. The error model (FR-012 … FR-016)

`AdminError` carries what FR-012 lists; `AdminErrorPresenter` turns it into either a standalone
document or an in-page section. The four hand-written error call sites that exist today
(`OperationsModeController`, `AccessDeniedGate`, and the two retention/import controllers) are
converted to build a model and hand it to the presenter.

**Two things this deliberately does not do**, because they are how this feature would cause harm:

- **No `wp_die_handler` filter.** A global handler catches REST, AJAX, cron and installer paths as
  well, and its failure mode is silent and total. CoreX converts refusals it *owns*, at the point it
  owns them.
- **No content negotiation anywhere.** Nothing in this feature inspects `Accept` to decide between
  HTML and JSON. The defect being fixed is a browser reaching a JSON endpoint; making the JSON
  endpoint return HTML would hide it rather than fix it.

**T003 changed this section.** The assumption written here first — that `?page=corex-nonexistent`
reaches WordPress's white box — is wrong, and checking it found a worse defect
(`evidence/before/nonexistent-page.md`). `AccessDeniedGate` matches the `corex-` prefix alone, so a
full administrator asking for a page that does not exist is told at HTTP 403 that their role lacks
`manage_options`. It is false, the status is wrong, and it offers a request form for an ability that
does not exist — which Phase 2 would turn from broken into actively harmful, since the form would
then succeed.

`admin_page_access_denied` fires for both causes: a registered page the user cannot open, and a page
that was never registered. The gate must ask `$GLOBALS['_registered_pages']` which one it is, and
render a **404 not-found** state — no capability sentence, no request form — for the second. That is
the first `AdminErrorKind` the model earns, and it is why the model is in this spec at all rather
than being deferred as tidying.

### 4. The boundary (FR-017, FR-018) — US3

Tests, not code. The point of US3 is that nothing changes, and the way to hold that is a suite that
fails if it does: the REST route still answers JSON at its original status for anonymous,
insufficient and valid callers; AJAX, WP-CLI and cron paths are untouched; login and installer are
untouched. These tests exist to fail loudly if a future contributor reaches for the `wp_die` handler
this plan refused.

### 5. React (FR-022) — US4

`CorexErrorState` at four scales, used by the existing React screens' failure paths. Scoped to
what exists: no new screens, no new data fetching, no retry infrastructure beyond re-invoking the
loader the screen already has.

## Complexity Tracking

No violations.

One judgement recorded: `deniedBody()` gains a state parameter rather than being split into four
render methods. Four methods would be tidier by line count and worse in the way that matters — the
lock mark, heading, capability sentence and back link are identical in all four states, and copying
them four times is exactly the duplication that lets three of them drift while one is updated.

One risk accepted: converting the four existing error call sites touches working code to make it
uniform, which is a refactor riding along with a fix. It is included because leaving them means
shipping a shared error model that the code which most needs it does not use — but it lands as its
own commit, separate from the defect fix, so a revert of one is not a revert of the other.
