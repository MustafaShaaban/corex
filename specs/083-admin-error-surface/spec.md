# Feature Specification: Every Admin Refusal Is a CoreX Page

**Feature Branch**: `spec/083-admin-error-surface`

**Created**: 2026-07-28

**Status**: Draft

**Input**: Owner report — "the admin error pages still as it nothing changed", with a screenshot of
WordPress's white box reading *"Sorry, you are not allowed to access this page."*

## Why this spec exists

Spec 079 shipped under the title *Unified Admin Error and Access Request Experience*. The access
request half is genuinely fixed. The unified error half was never built: its `tasks.md` shows
**Phases 5, 6 and 7 unstarted**, and `AdminError`, `AdminErrorKind`, `AdminErrorPresenter` and
`CorexErrorState.js` do not exist on disk. The spec was closed with the work that would have
delivered FR-014 still open.

What CoreX actually installs is one hook with one line of scope
(`plugins/corex-config/src/Access/AccessDeniedGate.php:39`):

```php
if ($page === '' || ! str_starts_with($page, 'corex-')) { return; }
```

Everything that is not `admin.php?page=corex-*` falls through to WordPress. Measured on the running
install as a real subscriber — `evidence/before/refusal-matrix.md` — that is **nine of eleven**
admin addresses, including **both CoreX-registered post-type screens**, `edit.php?post_type=corex_job`
and `edit.php?post_type=corex_project`. Neither carries a `page` parameter, so neither can ever match.

**The reproduction also invalidated the test this spec inherited.** WordPress does not have one
refusal sentence, it has many — *"…not allowed to access this page."*, *"…not allowed to list
users."*, *"…not allowed to manage options for this site."* — and all of them are translated. Spec
079's SC-005 asserts the absence of exactly one of them. That assertion passes today on `users.php`,
and would pass site-wide on an Arabic install, while every refusal remained a white box. This spec
replaces it with a positive assertion: the response **is** a CoreX document.

## Requirements *(mandatory)*

### Functional

- **FR-001** Every human-facing admin HTML refusal MUST render the CoreX error surface: CoreX screens,
  CoreX post-type screens, WordPress core screens, third-party plugin screens, expired links, and
  generic `wp_die()` from any caller inside `wp-admin`.
- **FR-002** Machine responses MUST be untouched. REST, AJAX, JSONP, XML-RPC, XML and feed requests
  keep WordPress's own handlers, and no code anywhere inspects `Accept` to choose a representation.
- **FR-003** The refusal's kind MUST be determined from the HTTP status and from which upstream hook
  fired — **never** by matching the message text, which is translated.
- **FR-004** One error model MUST serve both presentations. The same `AdminError` renders as an
  in-shell card when the admin shell is loaded and as a standalone document when it is not, so the two
  cannot drift in wording, status or affordance.
- **FR-005** A refusal on a CoreX screen with a known ability MUST keep offering the access-request
  form. A refusal with no CoreX ability behind it MUST NOT offer one — there is nothing to request.
- **FR-006** The denial copy MUST name the capability the screen actually requires. It currently says
  `manage_options` on every screen, which is false on `corex-notifications`, `corex-submissions`,
  `corex-data-models` and every `corex-page-*` option page.
- **FR-007** WordPress's logout confirmation MUST continue to work and MUST NOT be presented as an
  error. It reaches `wp_die()` at 403 and is a prompt, not a refusal.
- **FR-008** Markup WordPress puts in its own messages — the "go back" link in an expired-link notice —
  MUST survive presentation.
- **FR-009** A failure inside the error surface MUST fall back to WordPress's handler rather than
  producing a blank page.
- **FR-010** A missing CoreX stylesheet MUST NOT silently degrade a branded page to an unstyled one.
  `StandalonePage::read()` returns an empty string today, which is a second route to the reported
  white box.
- **FR-011** React surfaces MUST have a shared error state at field, action, panel and page scale
  (079 FR-022, unbuilt).

### Success criteria

- **SC-001** Every cell of the {administrator, editor, subscriber} × {CoreX page, CoreX post-type
  screen, core screen, third-party screen, nonexistent CoreX page, expired link} matrix renders a
  CoreX document at the correct status code. Asserted positively, on real requests, by real users.
- **SC-002** A REST 403 still returns JSON; an AJAX die still returns `-1`.
- **SC-003** Logging out still shows WordPress's confirmation and completes.
- **SC-004** The `wp_die_ajax_handler`, `wp_die_json_handler`, `wp_die_jsonp_handler`,
  `wp_die_xmlrpc_handler` and `wp_die_xml_handler` filters have no CoreX subscriber, guarded by a test.

## Assumptions

- `wp/wp-includes/functions.php:3791-3849` selects the die handler by request type *before* any filter
  runs, and `wp_die_handler` is reached only in the final `else`. Filtering that one alone therefore
  satisfies FR-002 by construction rather than by inspection.
- `check_admin_referer` (`wp/wp-includes/pluggable.php:1390`) fires with `$result === false`
  immediately before `wp_nonce_ays()`, and carries the action name. That is how FR-003 and FR-007 are
  met without reading a translated string.
- The gate registers on `plugins_loaded`, long before `wp-admin/admin.php:163` loads the menu. Timing
  was never the problem; scope was.

## Out of scope

- `wp-login.php` and front-end `wp_die()`. Both are outside `is_admin()`, and the login screen is
  spec 069's surface.
- Network-admin screens, which `wp_die()` without firing any hook we could reach earlier.
- Changing any REST route's behaviour. DECISIONS #174 stands: one door per representation.
