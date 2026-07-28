# Implementation Plan: Every Admin Refusal Is a CoreX Page

**Spec**: `specs/083-admin-error-surface/spec.md` · **Branch**: `spec/083-admin-error-surface`

## Approach

Complete spec 079's Phases 5–7, and widen the one thing 079 deliberately declined to touch.

### The boundary core hands us

`wp_die()` picks its handler by request type before any filter is consulted
(`wp/wp-includes/functions.php:3791-3849`): Ajax → `wp_die_ajax_handler`, JSON/REST →
`wp_die_json_handler`, JSONP, XML-RPC, XML/feeds each to their own, and only the final `else` reaches
`wp_die_handler` at line 3848.

**We filter `wp_die_handler` and nothing else.** FR-002 then holds by construction: there is no
branch in which a machine caller can reach CoreX's presenter, so there is no content negotiation to
get wrong. This is the distinction DECISIONS #174 was actually protecting — it forbade two doors onto
one service, not a branded error page — and this plan does not reopen it. A new decision entry records
the amendment explicitly rather than leaving #174's literal sentence contradicted in silence.

### Classification without reading text

The reproduction found WordPress using at least three different refusal sentences, all translated. So
classification reads only two things:

1. **Which upstream hook fired.** `admin_page_access_denied` → denied. `check_admin_referer` with
   `$result === false` → expired link, plus the action name. Both are recorded on a small
   request-scoped marker, consumed by the handler, and cleared after use.
2. **`$args['response']`**, as the fallback: 403 → denied, 404 → not found, 429 → rate limited,
   503 → unavailable, otherwise failed.

### Two things that break if missed

- **Logout is a prompt, not a refusal.** `wp_nonce_ays('log-out')` renders "Do you really want to log
  out?" through `wp_die()` at 403. The marker carries the action, so `log-out` is passed straight to
  `_default_wp_die_handler`. `wp-login.php` also fails the `is_admin()` guard, so this is fenced twice.
- **Core messages contain markup.** `wp_nonce_ays` builds `</p><p>` and an `<a href>` back-link into
  its message. Pass-through escaping is `wp_kses_post()`, not `esc_html()`. `$message` may also be a
  `WP_Error`, which `wp_die()` accepts.

## Files

**New — `plugins/corex-core/src/Admin/Errors/`**

| File | Role |
|---|---|
| `AdminErrorKind.php` | enum `Denied`, `NotFound`, `Expired`, `Session`, `RateLimited`, `Unavailable`, `Failed` |
| `AdminError.php` | value object: kind, title, message, actions, status, optional request surface. No WP calls |
| `AdminErrorClassifier.php` | `(int $status, ?string $marker) → AdminErrorKind`. Pure |
| `AdminErrorPresenter.php` | one `AdminError` → in-shell card *or* standalone document, one source of copy |
| `AdminDieHandler.php` | the `wp_die_handler` bridge and the `check_admin_referer` marker |

**Changed**

- `plugins/corex-config/src/ConfigServiceProvider.php` — bind the model, register `AdminDieHandler`
  beside `AccessDeniedGate` in `boot()`.
- `plugins/corex-config/src/Access/AccessDeniedGate.php` — keeps its real job (telling *denied* from
  *never existed*, and offering the request form for a known CoreX ability); render through the shared
  presenter; correct the docblock's now-false "It never touches non-CoreX pages".
- `plugins/corex-core/src/Admin/AdminPage.php` — `deniedBody()` names the screen's real capability
  (FR-006); `sectionMeta()` is the map it reads.
- `plugins/corex-core/src/Admin/StandalonePage.php` — `read()` stops returning `''` silently (FR-010).
- `plugins/corex-core/assets/css/corex-admin-standalone.css` — variants for the new kinds.
- `plugins/corex-config/src/admin/components/CorexErrorState.js` — new (FR-011).

## Verification

079 shipped broken because nothing rendered a real refusal; the only Pest test reflected into a
private predicate and the only browser test visited one URL. So the tests come first and are proven
to fail against `main` before anything is built.

- `tests/e2e/admin-errors.spec.js` — the SC-001 matrix, asserting **positively** that each response is
  a CoreX document at the right status. Expired-link coverage uses an action the signed-in user is
  permitted to start, with a stale nonce; `options.php?_wpnonce=deadbeef` does not reach the nonce
  check and is not a valid fixture.
- Logout still works and stays unbranded (SC-003).
- Integration: REST 403 is JSON, AJAX die is `-1`, and the five machine filters have no subscriber
  (SC-004 — 079's unwritten T032).
- Unit: `AdminErrorClassifier`, `AdminError`, `AdminErrorPresenter`, with no WordPress.
- `tests/Integration/Access/AccessDeniedGateTest.php` — replace the reflection with a render.
- Guards: `wp-guard` + `clean-code-guard` on PHP, `test-guard` on tests.
