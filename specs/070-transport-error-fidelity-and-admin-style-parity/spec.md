# Spec 070 — Transport error fidelity & hidden-admin style parity

**Status:** IMPLEMENTATION-COMPLETE
**Branch:** `spec/070-transport-error-fidelity`
**Supersedes claims in:** `specs/069-admin-correctness-and-login-parity/spec.md` §"Known limitation"

## Why

Two owner-reported defects against v0.34.0:

1. Editing an Email Studio template failed with **"Something went wrong. Please try again."**
2. A hidden `/wp-admin` rendered an **unstyled page** instead of the theme's 404.

Both were reproduced on `corex.local` before any code changed. Neither had the cause the
symptom suggested.

## FR-001 — A route's identity comes from its path, never its payload

**The defect.** Saving a draft returned 404 for a template that plainly existed.

`WP_REST_Request::get_parameter_order()` resolves JSON body params and the query string *before*
URL params. `EmailStudioController::saveDraft()` read `get_param('id')`, and the editor was
posting the stored version's own `id` in the body — because `useEmailStudio.js` built the draft
as `{ ...EMPTY_DRAFT, ...latest }`, spreading the whole version record (`id`, `template_id`,
`version`, `checksum`, `created_by`, `created_at`) into the payload. So the controller looked up
version id **3860** as if it were a template, found nothing, and answered 404.

Only templates that already had a saved version could hit it — a template with none used
`emptyDraft()`, which carries no `id`. That is why it looked intermittent.

**The fix, both ends.**
- `Corex\Http\RouteParam` (new) reads `get_url_params()` directly, which nothing can shadow.
  Applied to every route-captured identifier in `EmailStudioController`, `FlowController`,
  `SubmissionsController`, `DataManagementController` (24 `::int`, 1 `::string`).
- `draftFrom()` projects a version onto the editable fields only, so server-owned columns never
  travel back.

`source` in `DataManagementController` deliberately stays on `get_param()`: `migrations()` reads
it as a query filter on `/data/migrations`, a route that captures no `source`.

## FR-002 — A failed request says what actually failed

`corex-runtime.js` called `wp.apiFetch({ parse: false })` and assumed a non-2xx **resolves**.
Core does the opposite — `parseAndThrowError()` rethrows the raw `Response`
(`wp-includes/js/dist/api-fetch.js`). The `response.ok === false` branch was dead code; every
4xx/5xx fell into the blanket `.catch` and became `genericError()` with no message. The real 404
above was invisible for exactly this reason: two bugs, stacked.

- `viaApiFetch` now handles the rejection, reading an error `Response` through the same path as a
  success. Non-`Response` rejections still propagate to the transport catch.
- A non-JSON error body now reports its status ("The server returned an unexpected response
  (500).") instead of being indistinguishable from a dead network. `status: 0` now means exactly
  "nothing came back".
- `failureMessage()` surfaces `details.fields`, which the controller already returned and the UI
  discarded.

Note this screen *does* load `wp-api-fetch` (confirmed live: `hasApiFetch: true`), despite not
declaring it — so the broken path was the one in play.

## FR-003 — A hidden `/wp-admin` is styled, not merely routed

`wp_common_block_scripts_and_styles()` opens with
`if ( is_admin() && ! wp_should_load_block_editor_scripts_and_styles() ) return;`
(`wp-includes/script-loader.php`). On a hidden admin 404 both hold, so the response got **no**
per-block sheets, **no** monolithic `wp-block-library`, **no** `wp-block-library-theme`, and
`enqueue_block_assets` never fired. `wp_enqueue_global_styles` has no such gate, so `theme.json`
tokens still printed — colours and custom properties, no block layout or appearance CSS. On a
block theme with no `functions.php` and a metadata-only `style.css`, that is a visibly broken
page.

`LoginRouteGuard::enqueueBlockStyles()` fills the gap, hooked from `dropAdminContext()`.
`wp_enqueue_scripts` fires during `wp_head()`, well after `render404()` runs on `wp_loaded`, so
the timing is comfortable. Enqueuing directly is deliberate: filtering
`should_load_block_editor_scripts_and_styles` would also satisfy the block-*editor* branch and
pull in editor assets a real front-end 404 never carries.

### Correcting spec 069

069 recorded this gap as unreachable. That is true of `wp_should_load_separate_core_block_assets()`
and `wp_should_load_block_assets_on_demand()` — both return on `is_admin()` before their own
filters, verified in core. It was **not** true of the gate that actually caused it, which 069
never identified. The measured "46,587 vs 79,968 bytes" was read as a fingerprinting risk; nobody
connected it to a visual one.

### A theme bug the fix exposed

`.corex-header__inner { max-inline-size: 100% }` had the same specificity as core's
`.is-layout-constrained > :where(…)`, so which won depended purely on stylesheet order. Inline
block styles print later on a normal front-end request, so core won and the rule was already
inert. When the sheet loads as a `<link>` instead — which is what core does whenever on-demand
block assets are off — the order inverted and the header stretched edge to edge. The guard is now
on `.corex-header` only, where it cannot conflict.

## Measured outcome (`corex.local`, logged out)

| | before | after | genuine 404 |
|---|---:|---:|---:|
| hidden `/wp-admin` | 46,587 B | **79,711 B** | 79,964 B |

Computed `font-family`, `main` max-width, and `.corex-header__inner` geometry (`768px`) now match
the genuine 404 exactly. Template save returns `201` with a new version.

Still not byte-identical, and that part is genuine: the hidden admin gets the monolithic sheet
where a front-end 404 gets per-block ones. Visually indistinguishable; ~250 B apart.

## Test coverage added

- `tests/Integration/Http/RouteParamTest.php` — 5 cases, including the exact 3859/3860 shadowing.
- `tests/corex-runtime.test.js` — 4 cases where `wp.apiFetch` **rejects**. Every prior apiFetch
  test mocked a resolve, which is how this shipped.
- `tests/Integration/Security/HiddenAdminResponseTest.php` — block styles re-enqueued.
- `tests/e2e/security-access.spec.js` — asserts `wp-block-library` present and response size
  within 5% of the control (the gap it replaced was 42%).
- Four integration suites now set route ids via `set_url_params()`, modelling what
  `WP_REST_Server::dispatch()` actually does; `set_param()` put them in the body, which is
  precisely the fidelity gap that hid the bug.

## Out of scope

- `[Corex] WARNING: Mail rejected: Illegal characters in the subject field.` — recurring in
  `wp-content/debug.log`, a separate real defect.
- `WP_DEBUG_DISPLAY` is `true` in `wp/wp-config.php`, printing PHP notices into response bodies.
- `corex-runtime.js` routes all 11 user-facing strings through a `t()` wrapper that `make-pot`
  cannot extract; no POT is generated for `corex-core` yet.
- A pre-existing PHP segfault partway through the unit suite (identical with and without this
  work: 143 PASS blocks, then a crash after `BootLoggerTest`; passes in isolation).
