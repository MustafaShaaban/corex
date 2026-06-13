# Implementation Plan: Request/Response contract + Frontend runtime kit

**Branch**: `feature/043-response-runtime-kit` | **Date**: 2026-06-13 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/043-response-runtime-kit/spec.md`

## Summary

Define one canonical JSON **response envelope** as a pure value object in corex-core (`Corex\Http\ResponseEnvelope`)
plus a thin `EnvelopeResponder` that maps it to a `WP_REST_Response` through the **existing** spec-005 middleware
lifecycle — and one buildless, jQuery-free **client runtime** (`window.Corex`) that speaks it: `Corex.api`
(nonce-attaching request helper that wraps `wp.apiFetch` when present, else `fetch`; normalises every response to
the envelope; handles timeout/network as ordinary error results), `Corex.forms.bind` (schema-mirrored client
validation + server-authoritative error rendering), `Corex.loading` (disable/spinner/`aria-busy`/dedupe/restore),
`Corex.notices`, and the four lifecycle events. The runtime is shipped from corex-core and **conditionally
enqueued** only as a declared dependency of the surfaces that use it. The existing forms `view.js`, the Insights
card script, and the Data React app are migrated onto it, removing their duplicated fetch/nonce/error code. The
change is **additive**: today's `{ ok, message, errors, values }` bodies become envelope-conformant supersets, so
existing consumers keep working.

## Technical Context

**Language/Version**: PHP 8.3 (corex-core); browser JavaScript (ES2017, no build step required to consume).

**Primary Dependencies**: existing only — spec-005 middleware `Pipeline`/`Request`/`Response`, spec-007
`SubmitController`, spec-020 `SchemaExporter` + the embedded `data-corex-schema`, WordPress core globals
`wp.apiFetch` / `wp.i18n` (feature-detected, never required). No new runtime/build dependency. No jQuery.

**Storage**: N/A (this feature shapes responses + client behaviour; it persists nothing new).

**Testing**: Pest (envelope + responder + SubmitController mapping, via Brain Monkey) and Jest
(`@wordpress/scripts test-unit-js` — runtime api/forms/loading/events). Playwright smoke for the live submit/admin
flows is environment-gated (run when Apache + a browser are available).

**Target Platform**: WordPress 7.0+ front-end (form pages) and wp-admin (Insights/Data/Settings screens).

**Project Type**: WordPress framework monorepo (engine plugin `corex-core` + feature plugins `corex-forms`,
`corex-config`).

**Performance Goals**: no measurable page-weight regression — the runtime is a single small static script + style,
loaded only where a Corex form/screen is present (Principle VI); zero bytes on unrelated pages.

**Constraints**: token-only styling with a wp-admin palette fallback (admin context does not load theme tokens —
precedent: spec 037 / DECISIONS #71); logical CSS / RTL; WCAG 2.2 AA (live-region status, `aria-busy`, field-error
association, focus retention); i18n via `wp.i18n` global; no secret ever in a response body (Principle VII).

**Scale/Scope**: one envelope value object + one responder + one runtime script (~4 facilities) + one token CSS
file; migrate 3 existing client scripts and 1 controller mapping. ~3 PHP classes touched/added, ~4 JS files.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.2.1). PASS / N/A / VIOLATION.

- [x] **I. Theme is a skin** — N/A. No business logic/CPT/bootstrapping in the theme. The theme only *consumes* the
  runtime's token-styled classes (`.corex-spinner`, `.corex-form__status`, …) via CSS variables; it owns no logic.
- [x] **II. Plugins boot themselves** — PASS. The envelope + runtime asset registration live in corex-core, wired by
  a service provider on `plugins_loaded`/`init`; the contract works in REST and admin with no theme active.
- [x] **III. Thin controllers, fat services** — PASS. `SubmitController` stays thin (route → service → envelope);
  the envelope is a value object, the responder a pure mapper. No DB/business logic added to controllers.
- [x] **IV. Everything injected** — PASS. The asset registrar/responder are container-wired services; the envelope
  is a pure **value object** (static factories, no dependencies — the same justified exception as the existing
  `Corex\Http\Middleware\Response`). No `new` of a dependency inside a method.
- [x] **V. Runtime tokens** — PASS. Runtime CSS uses `theme.json`/`brand.json` CSS custom properties; the only
  hardcoded literals are documented **admin-palette fallbacks** inside `var(--corex-…, <fallback>)` for the wp-admin
  context where Corex theme tokens are not loaded (spec-037 precedent). No build-time token system.
- [x] **VI. Conditional assets** — PASS. The runtime handle is enqueued **only** as a declared dependency of the
  form viewScript and the admin screen scripts; it never loads globally. A page with no Corex form/screen loads
  none of it.
- [x] **VII. Declarative security** — PASS. No new auth path: the submit route stays behind the spec-005 middleware
  (nonce/cap/sanitize); admin runs stay `manage_options` + REST nonce. The envelope **carries no secret**
  (FR-004/SC-006); output is escaped; the client attaches the existing WP REST nonce.
- [x] **VIII. RTL-first** — PASS. Runtime CSS uses logical properties (`inset-inline`, `margin-inline`, …); spinner
  and overlay are direction-agnostic.
- [x] **IX. No optional dep is hard** — PASS. `wp.apiFetch`/`wp.i18n` are feature-detected; `Corex.api` falls back to
  `fetch` and an identity translator if absent. The framework runs fully without them.
- [x] **X. Spec is source of truth** — PASS. This plan traces to the approved spec 043; no scope beyond it.
- [x] **Guard Gate + Definition of Done** — acknowledged: wp-guard (enqueue/nonce/escape) + clean-code on PHP/JS,
  test-guard on Pest/Jest, docs-guard on the docs-app guide + READMEs; i18n, RTL, WCAG 2.2 AA, PROGRESS/DECISIONS
  updated; NEXT STEP block each response.

**Result: PASS — no violations. Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/043-response-runtime-kit/
├── plan.md              # This file
├── research.md          # Phase 0 — design decisions resolved
├── data-model.md        # Phase 1 — the envelope + runtime entities
├── quickstart.md        # Phase 1 — runnable validation scenarios
├── contracts/
│   ├── response-envelope.md   # the JSON success/error contract
│   └── runtime-api.md         # the window.Corex surface contract
└── tasks.md             # Phase 2 — created by /speckit-tasks
```

### Source Code (repository root)

```text
plugins/corex-core/
├── src/Http/
│   ├── ResponseEnvelope.php        # NEW — pure value object: success()/error()/validation()
│   └── EnvelopeResponder.php       # NEW — maps a ResponseEnvelope → WP_REST_Response (status + body)
├── src/Foundation/                 # (existing providers) wire RuntimeAssets registration
│   └── HttpServiceProvider.php     # NEW (or fold into CoreServiceProvider) — registers `corex-runtime`
├── assets/
│   ├── js/corex-runtime.js         # NEW — window.Corex (api/forms/loading/notices + events), buildless IIFE
│   └── css/corex-runtime.css       # NEW — token-styled .corex-is-loading/.corex-spinner/.corex-form__status/__overlay
└── tests/Unit/Http/                # NEW — ResponseEnvelopeTest, EnvelopeResponderTest (Pest)

plugins/corex-forms/
├── src/Submission/SubmitController.php   # CHANGE — toRest() emits the envelope (additive)
├── src/Block/FormBlockRenderer.php       # CHANGE — viewScript declares `corex-runtime` dependency
└── src/Block/blocks/corex-form/
    ├── view.js          # CHANGE — thin bootstrap delegating to window.Corex.forms.bind
    └── validation.js    # MOVE — rule-mirroring logic relocates into the runtime's forms module
                         #        (kept as a re-export or removed; tasks.md decides)

plugins/corex-config/
├── assets/insights.js                    # CHANGE — fetch/error plumbing via Corex.api + envelope
├── src/admin/index.js                    # CHANGE — Data React app's apiFetch calls via Corex.api + envelope
├── src/Insights/InsightsScreen.php       # CHANGE — enqueue declares `corex-runtime` dependency
└── src/Data/DataAdminScreen.php          # CHANGE — enqueue declares `corex-runtime` dependency

tests/ (JS)
└── corex-runtime.test.js (+ forms.bind/loading/events specs)   # NEW — Jest

docs-app/src/content/docs/guides/frontend-runtime.md            # NEW — the runtime + envelope guide
```

**Structure Decision**: The contract is a **framework primitive**, so the envelope (PHP) and the runtime (JS+CSS)
ship from **corex-core** — the plugin every other Corex plugin already depends on — and the feature plugins
(`corex-forms`, `corex-config`) **consume** it. This keeps the single source of truth in the engine and prevents a
core→add-on dependency. The runtime is a **plain, buildless IIFE** modelled on the existing `assets/insights.js`
(IIFE over `window.wp`), not a `@wordpress/scripts` bundle — satisfying "no build step to consume" (FR-007) while
still using the `wp.i18n` global for translation.

## Complexity Tracking

> No Constitution Check violations — this section intentionally empty.
