# Implementation Plan: Transport Error Fidelity & Hidden-Admin Style Parity

**Branch**: `spec/070-transport-error-fidelity` | **Date**: 2026-07-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/070-transport-error-fidelity-and-admin-style-parity/spec.md`

> **Retroactive record.** This plan and its sibling `tasks.md` were written on 2026-07-20 *after*
> the implementation landed in commit `f9c5656`, to close the Spec Kit gap the constitution's
> Pre-Implementation Confirmation Rule requires (`spec.md` shipped alone). It documents the
> approach that was actually taken and the file set that was actually touched — it does not
> reconstruct a plan that was never followed, and it claims no foresight it did not have.
> Logged in `DECISIONS.md` #145.

## Summary

Two owner-reported defects against v0.34.0, neither of which had the cause its symptom suggested:

1. **Email Studio template save returned 404** on templates that plainly existed. Root cause was
   *two stacked bugs* — a route-identity bug that produced the wrong 404, and a transport bug that
   made the 404's message invisible, so the UI showed only "Something went wrong."
2. **A hidden `/wp-admin` rendered unstyled.** Root cause was a core early-return
   (`wp_common_block_scripts_and_styles()` bails on `is_admin()`), which spec 069 had recorded as
   unreachable after checking a *different* pair of functions.

Approach: fix the route-identity bug at the framework level with a reusable value object rather
than patching one controller; fix the transport by handling the rejection path core actually
takes; and fill the block-asset gap by enqueueing directly rather than filtering a flag that
would also pull in editor assets.

## Technical Context

**Language/Version**: PHP 8.3+, JavaScript (ES5-compatible buildless runtime + `@wordpress/element` admin bundle)

**Primary Dependencies**: WordPress 7.0+; no new dependencies

**Storage**: No schema change. No new options.

**Testing**: Pest (unit + integration), Jest (JS), Playwright (E2E)

**Target Platform**: WordPress admin + front end, single-site and multisite

**Project Type**: WordPress framework monorepo

**Performance Goals**: No regression. `RouteParam` reads an array already materialised by
`WP_REST_Server::dispatch()`; the block-style enqueue runs only on the hidden-admin 404 path.

**Constraints**: The hidden-admin fix must not make the 404 carry assets a genuine front-end 404
would never carry — that would trade one fingerprint for another.

**Scale/Scope**: 3 workstreams, 11 source files across `corex-core`, `corex-config`,
`corex-email`, `corex-forms`, `theme`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after implementation.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.2.1).

- [x] **I. Theme is a skin** — PASS. `theme/assets/css/corex-navigation.css` changes one selector's
  scope (`.corex-header__inner` → `.corex-header`). Presentation only; no logic added.
- [x] **II. Plugins boot themselves** — PASS. No boot-order change. `LoginRouteGuard` hooks its new
  enqueue from the existing `dropAdminContext()` path.
- [x] **III. Thin controllers, fat services** — PASS, and improved. Controllers get *less* logic:
  `RouteParam::int()` replaces hand-rolled `(int) $request->get_param(...)` casts.
- [x] **IV. Everything injected** — PASS. `RouteParam` is a stateless static utility over a value
  WordPress hands the controller, not a dependency to inject. No `new` in a method.
- [x] **V. Runtime tokens** — PASS. No colour/size/font introduced.
- [x] **VI. Conditional assets** — PASS. Block styles are enqueued *only* on the hidden-admin 404
  response, not globally. Enqueueing directly rather than filtering
  `should_load_block_editor_scripts_and_styles` is the narrower choice — the filter would also
  satisfy the block-*editor* branch.
- [x] **VII. Declarative security** — PASS. No security surface changes. The hidden-admin response
  becomes *more* faithful to a genuine 404, which is the security goal 069 set.
- [x] **VIII. RTL-first** — PASS. The one CSS change uses no directional properties.
- [x] **IX. No optional dep is hard** — PASS. Nothing optional is touched.
- [x] **X. Spec is source of truth** — **DEVIATION.** `spec.md` was written and the code implemented
  without `plan.md`/`tasks.md`/`checklists/`. This document is the retroactive close-out.
  Recorded in `DECISIONS.md` #145 rather than papered over.
- [x] **Guard Gate + Definition of Done** — met at implementation time: `wp-guard`,
  `clean-code-guard`, `test-guard` clean; tests written; docs/PROGRESS/DECISIONS updated in the
  same commit.

**Environment Gate**: verified before implementation and re-verified 2026-07-20 — `wp theme list`
shows `corex`; `corex-core`/`corex-blocks`/`corex-config` active; boots with no fatals
(`COREX_CORE_VERSION=0.34.0`).

## Project Structure

### Documentation (this feature)

```text
specs/070-transport-error-fidelity-and-admin-style-parity/
├── spec.md              # Written first (the only artifact that shipped with the code)
├── plan.md              # This file — retroactive
├── checklists/
│   └── requirements.md  # Retroactive
└── tasks.md             # Retroactive, marked complete
```

No `research.md`, `data-model.md`, or `contracts/`. This is a defect-correction spec against a
merged codebase: the research *is* the core-source reading recorded inline in `spec.md`
(`get_parameter_order()`, `parseAndThrowError()`, `wp_common_block_scripts_and_styles()`), there
are no new entities, and no REST contract changed shape — only which parameter source a
controller trusts.

### Source Code (repository root)

```text
plugins/corex-core/
├── src/Http/RouteParam.php                     # WS1 — new
└── assets/js/corex-runtime.js                  # WS2 — rejection path

plugins/corex-config/src/
├── Email/useEmailStudio.js                     # WS1 — draftFrom() projection
├── Data/DataManagementController.php           # WS1 — RouteParam (source stays get_param)
├── Submissions/SubmissionsController.php       # WS1 — RouteParam
└── Security/LoginProtection/LoginRouteGuard.php # WS3 — enqueueBlockStyles()

addons/corex-email/src/Studio/EmailStudioController.php  # WS1 — RouteParam
plugins/corex-forms/src/Flow/FlowController.php          # WS1 — RouteParam
theme/assets/css/corex-navigation.css                    # WS3 — selector scope
```

**Structure Decision**: Existing layout; one new file (`RouteParam`) in the framework's existing
`Http` namespace alongside `ResponseEnvelope` and `ControllerMap`.

## Workstreams

### WS1 — A route's identity comes from its path (FR-001, P1)

`WP_REST_Request::get_parameter_order()` resolves JSON body params and the query string *before*
URL params. Any controller reading a route-captured id via `get_param()` can therefore be shadowed
by its own payload.

`Corex\Http\RouteParam::int()` / `::string()` read `get_url_params()` directly, which nothing can
shadow. Applied to every route-captured identifier: 24 `::int` and 1 `::string` across
`EmailStudioController`, `FlowController`, `SubmissionsController`, `DataManagementController`.

`source` in `DataManagementController` deliberately **stays** on `get_param()` — `migrations()`
reads it as a query filter on `/data/migrations`, a route that captures no `source`. Converting it
would have broken a working filter to satisfy a pattern.

Client side, `draftFrom()` projects a version onto the editable fields only, so server-owned
columns (`id`, `template_id`, `version`, `checksum`, `created_by`, `created_at`) never travel back
in the payload. The spread `{ ...EMPTY_DRAFT, ...latest }` was the origin of the shadowing value.

### WS2 — A failed request says what actually failed (FR-002, P1)

`corex-runtime.js` called `wp.apiFetch({ parse: false })` and assumed a non-2xx **resolves**. Core
does the opposite: `parseAndThrowError()` rethrows the raw `Response`. The `response.ok === false`
branch was dead code; every 4xx/5xx fell into the blanket `.catch` and became `genericError()`.

- `viaApiFetch` handles the rejection, reading an error `Response` through the same path as a
  success. Non-`Response` rejections still propagate to the transport catch.
- A non-JSON error body reports its status via `statusMessage()` instead of being
  indistinguishable from a dead network. `status: 0` now means exactly "nothing came back".
- `details.fields` survives to the caller. Nothing "surfaces" it — `normalise()` returns a valid
  envelope **verbatim** (`isEnvelope()` check, L60-62), so the field errors the controller already
  returned were never the problem. The problem was that the rejection path never reached
  `normalise()` at all; routing it through `fromResponse()` is what preserves them, instead of
  replacing the whole envelope with `genericError()`.

### WS3 — A hidden `/wp-admin` is styled, not merely routed (FR-003, P2)

`wp_common_block_scripts_and_styles()` opens with
`if ( is_admin() && ! wp_should_load_block_editor_scripts_and_styles() ) return;`. On a hidden
admin 404 both hold, so the response got no per-block sheets, no `wp-block-library`, no
`wp-block-library-theme`, and `enqueue_block_assets` never fired.

`LoginRouteGuard::enqueueBlockStyles()` fills the gap, hooked from `dropAdminContext()`.
`wp_enqueue_scripts` fires during `wp_head()`, well after `render404()` runs on `wp_loaded`.

**069's claim is corrected, not contradicted.** 069 was right that
`wp_should_load_separate_core_block_assets()` and `wp_should_load_block_assets_on_demand()` return
on `is_admin()` before their own filters. It simply never identified the gate that actually caused
the symptom. `069/spec.md` is amended in place.

**A theme bug the fix exposed**: `.corex-header__inner { max-inline-size: 100% }` had the same
specificity as core's `.is-layout-constrained > :where(…)`, so the winner depended on stylesheet
order. Inline block styles print later on a normal request, so the rule was already inert; when
the sheet loads as a `<link>` the order inverted and the header stretched edge to edge. Scoped to
`.corex-header`, where it cannot conflict.

## Measured outcome

| | before | after | genuine 404 |
|---|---:|---:|---:|
| hidden `/wp-admin` | 46,587 B | **79,711 B** | 79,964 B |

~250 B apart and visually indistinguishable. The residual gap is genuine and deliberate: the
hidden admin gets the monolithic sheet where a front-end 404 gets per-block ones.

## Complexity Tracking

One constitutional deviation, carried honestly: **Principle X** — implementation preceded
`plan.md`/`tasks.md`. Not justified as correct; recorded as debt and closed by this backfill.
No other violations added; WS1 removes a class of parameter-shadowing defect framework-wide.
