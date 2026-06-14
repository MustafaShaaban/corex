# Phase 0 — Research & Design Decisions: 043 Response contract + Runtime kit

All decisions below resolve the spec's Assumptions into concrete design choices. No `NEEDS CLARIFICATION`
remained from the spec; these record *why* each default was taken, grounded in the existing code.

## D1 — Envelope shape and backward compatibility

**Decision**: `ResponseEnvelope` carries `ok: bool`, `message: string`, and either `data: array` (success) or
`code: string` + `errors: array<string,string>` + `details: array` (error). On the wire:
- success → `{ "ok": true, "message": "...", "data": { ... } }`
- error → `{ "ok": false, "code": "validation_failed", "message": "...", "errors": { "email": "..." }, "details": {} }`

The existing `SubmitController::toRest()` returns `['ok'=>true,'values'=>…]` / `['ok'=>false,'message'=>…,'errors'=>…]`.
To stay **additive** (FR-006), the success envelope places the old `values` **inside `data`** *and* keeps a top-level
`values` mirror for one release (the current `view.js` ignores it on success anyway); the error envelope keeps
`ok`/`message`/`errors` and **adds** `code`/`details`. No existing consumer breaks.

**Rationale**: the current shape is already 80% of the target; formalising it as a value object removes the ad-hoc
array assembly and gives every future endpoint (044/045/046) one constructor to call.

**Alternatives considered**: a brand-new shape (`{status,payload}`) — rejected, gratuitous break of the working
forms contract. JSON:API — rejected as over-structured for WordPress admin/forms and not what consumers expect.

## D2 — Where the envelope lives, and injection

**Decision**: `Corex\Http\ResponseEnvelope` as a **pure, immutable value object** with named static factories
`success(array $data = [], string $message = '')`, `error(string $code, string $message, array $details = [])`,
`validation(array $errors, string $message = '')`. A separate `Corex\Http\EnvelopeResponder::toRest(ResponseEnvelope
$e): WP_REST_Response` maps it (status from a code→HTTP table: `validation_failed`→422, generic→400/403, success→200)
and is the thin WP boundary.

**Rationale**: mirrors the existing `Corex\Http\Middleware\Response` value-object precedent (static factories, no DI)
— Principle IV's value-object exception. The responder is the only piece that touches `WP_REST_Response`, keeping the
envelope headlessly unit-testable.

**Alternatives**: a single class doing both shape + WP mapping — rejected (couples the pure value to WordPress,
breaks headless testing).

## D3 — Runtime delivery: global vs module; build vs buildless

**Decision**: ship `window.Corex` as a **buildless IIFE** from `plugins/corex-core/assets/js/corex-runtime.js`,
modelled exactly on the existing `plugins/corex-config/assets/insights.js` (IIFE over `window.wp`, `wp.i18n.__` with
an identity fallback). Registered once as script handle `corex-runtime` (deps: `wp-i18n`; `wp-api-fetch` listed so
`wp.apiFetch` is present in admin) + style handle `corex-runtime`.

**Rationale**: FR-007 requires "no jQuery, no build step **to use it**." A global IIFE is consumable by the built
React Data app, the vanilla Insights script, and the form viewScript alike, with zero bundler coupling. npm modules
are explicitly deferred (spec scope).

**Alternatives**: `@wordpress/scripts` bundle exposing an npm package — rejected for now (adds a build dependency for
consumers; deferred to a later increment). A `wp.hooks`-only API — rejected (we need a stable callable surface).

## D4 — `Corex.api`: apiFetch vs fetch

**Decision**: `Corex.api.{get,post,delete}` uses **`wp.apiFetch` when available** (admin screens — it already
threads the REST nonce + middleware), and falls back to **`window.fetch`** with an explicit `X-WP-Nonce` header on
the front end (where `wp.apiFetch` is not enqueued). Both paths: parse JSON, **normalise to the envelope** (wrap a
bare/legacy body so `ok` is always present), convert a non-2xx or non-JSON/HTML body into a normalised **error
result** (never throw to the caller), enforce a timeout (AbortController) and map network/abort to a translatable
generic error, and fire `corex:request:start` / `corex:request:end`.

**Rationale**: the two contexts genuinely differ (front-end pages don't load `wp-api-fetch`); feature-detection
unifies them under one helper and honours Principle IX. The form's current raw `fetch` + `X-WP-Nonce` (view.js
L131-138) is the proven front-end path.

**Alternatives**: force `wp.apiFetch` everywhere — rejected (would enqueue `wp-api-fetch` on every form page,
heavier + unnecessary). Force raw `fetch` everywhere — rejected (loses apiFetch's admin middleware/nonce refresh).

## D5 — `Corex.forms.bind`: validation source and authority

**Decision**: the rule-mirroring logic currently in `corex-form/validation.js` (`required/email/numeric/max/min`,
bail-per-field) **moves into the runtime** as the forms module's validator, driven by the same `data-corex-schema`
the server embeds (spec 020 `SchemaExporter`). `bind(form)` reuses the existing DOM contract from `view.js`
(`[data-corex-field]`, `.corex-form__error`, `.corex-form__status`, `aria-invalid`, focus-first) so **markup does
not change**. The server re-validates the same schema and its errors override (FR-010). `view.js` becomes a thin
bootstrap that calls `Corex.forms.bind` on each `.corex-form` (or the runtime auto-binds them).

**Rationale**: zero markup churn (the renderer already emits the hooks), single validator implementation, server
stays authoritative. Keeps the spec-020 "one schema, front + back" guarantee.

**Alternatives**: keep validation.js in the block and have the runtime call into it — rejected (leaves the
duplication FR-019 targets; the runtime should own the reusable logic).

## D6 — `Corex.loading` and accessibility

**Decision**: `Corex.loading` toggles `.corex-is-loading` on the form/region, disables the submit control, sets
`aria-busy="true"` on the region, injects/owns a `.corex-spinner`, and **dedupes** by refusing a second submit while
a request token is active; on settle it restores `disabled`, removes `aria-busy`, hides the spinner. Status text is
written to the existing `.corex-form__status` which carries `role="status"` (polite live region); field errors keep
the spec-020 `role="alert"` regions + `aria-describedby`. Focus moves to the first invalid control (existing
behaviour) and is never lost on settle.

**Rationale**: satisfies FR-011/FR-016/SC-004 reusing the markup already rendered; `aria-busy` + a polite live region
is the WCAG-correct pattern for async status.

## D7 — Conditional enqueue (Principle VI)

**Decision**: corex-core **registers** (not enqueues) `corex-runtime` script+style. Each consumer that already
enqueues conditionally adds it as a **dependency**: the form block's `viewScript`
(`FormBlockRenderer`/`block.json`), `InsightsScreen` (`['corex-runtime']` dep on its existing handle), and
`DataAdminScreen`. Because WordPress only prints a registered script when something enqueued depends on it, the
runtime loads exactly where a form/screen is present and nowhere else.

**Rationale**: preserves "load only what renders" without a global enqueue; uses WP's dependency graph as the gate.

## D8 — Migration scope for the Data React app

**Decision**: the Data screen (`src/admin/index.js`, a `@wordpress/element` + `apiFetch` + DataViews app) keeps its
React structure; only its **data/delete calls** are routed through `Corex.api` and its responses read as envelopes.
No React rewrite. Insights (vanilla) and the form (vanilla) are fuller migrations since they own raw plumbing.

**Rationale**: FR-019 targets the *duplicated fetch/nonce/error plumbing*, not the UI framework; a React rewrite is
out of scope and risk. `window.Corex` is callable from within React effects.

## D9 — wp-admin token fallback

**Decision**: runtime CSS expresses every value as `var(--corex-…, <admin-palette fallback>)`. On the front end the
theme tokens resolve; in wp-admin (no theme tokens) the fallback renders. Same approach DECISIONS #71 took for the
Insights cards.

**Rationale**: Principle V (token-only) is honoured while the admin context — which legitimately lacks theme tokens
— still styles correctly.
