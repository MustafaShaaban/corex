# Feature Specification: Request/Response contract + Frontend runtime kit

**Feature Branch**: `feature/043-response-runtime-kit`

**Created**: 2026-06-13

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Establish one canonical HTTP response shape on the server and one tiny vanilla-JS runtime on the client that speaks it, so forms, admin actions, and REST all behave identically. A `ResponseEnvelope` value object + a `window.Corex` runtime (api/forms/loading/notices) with accessible loading states and schema-mirrored validation, no jQuery and no build step; migrate the existing forms and admin scripts onto it."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A form that gives instant, accessible feedback (Priority: P1) 🎯 MVP

A site visitor fills in a Corex form and submits it. Invalid fields are flagged **immediately** (before the
request leaves the browser), with the message attached to the field. While the request is in flight the submit
button is disabled and a spinner shows; assistive technology announces that the form is busy. On a server-side
rejection the same fields show the server's authoritative messages; on success a clear confirmation replaces the
form's status region. The visitor never sees a raw JSON blob, a duplicate submission, or a silent failure.

**Why this priority**: This is the single visible end-to-end proof that the contract works. It is also the most
common real interaction with a Corex site, and the thing today's hand-rolled `view.js` does inconsistently.

**Independent Test**: Submit a Corex form (a) with a client-detectable error → field error shown, no request
sent; (b) valid but server-rejected → server field errors shown; (c) valid + accepted → success status shown.
In all three the button disables during the request and re-enables after, and a status region is updated.

**Acceptance Scenarios**:

1. **Given** a form with a required field left empty, **When** the visitor submits, **Then** the field shows its
   error and **no network request is sent** (client validation mirrors the server rules).
2. **Given** a valid-looking submission the server rejects, **When** the response returns, **Then** each rejected
   field shows the **server's** message and a global status summarises the failure.
3. **Given** any submission, **When** it is in flight, **Then** the submit button is disabled, the form region is
   marked busy for assistive tech, and the state is **restored** on both success and error.
4. **Given** a visitor double-clicks submit, **When** the first request is still pending, **Then** the second
   click is ignored (no duplicate submission).
5. **Given** a successful submission, **When** the response returns, **Then** the form's status region shows the
   success message and the success event fires.

---

### User Story 2 - One predictable response shape for every integration (Priority: P1)

A developer integrating with Corex — whether reading a form submission result, an admin action, or an insights
run — gets the **same** success and error structure every time: a success carries a message and a data payload;
an error carries a stable machine code, a human message, an optional per-field error map, and optional details.
They write their client logic against one contract instead of guessing each endpoint's shape.

**Why this priority**: The contract is the foundation every later surface (admin redesign, data export, REST
resources, headless) stands on. Defining it now prevents each of those from inventing its own response shape and
forcing a later migration.

**Independent Test**: Trigger a success and a failure on each Corex-emitting endpoint and confirm every response
conforms to the documented success/error structure (same keys, same meaning), and that **no secret value** ever
appears in a response.

**Acceptance Scenarios**:

1. **Given** any Corex endpoint succeeds, **When** it responds, **Then** the body says it succeeded and carries a
   human-readable message and a data payload (which may be empty).
2. **Given** any Corex endpoint fails validation, **When** it responds, **Then** the body says it failed, carries
   a message, and carries a **field→message** map naming each invalid field.
3. **Given** any Corex endpoint fails for a non-validation reason (e.g. verification failed), **When** it
   responds, **Then** the body carries a stable machine **code** and a message, with optional safe details.
4. **Given** a failure, **When** the response is built, **Then** it contains **no secret** (keys, tokens, raw
   internal errors) — only the code, message, field errors, and explicitly-safe details.

---

### User Story 3 - Reuse the runtime for any custom form or request (Priority: P2)

A site developer building a custom feature wires their own form or fetch to the Corex runtime with a single call
and gets, for free: the correct nonce header, JSON handling, timeout and network-error handling, loading states,
field/global error rendering against an exported schema, and lifecycle events to hook into — **without jQuery and
without a build step**. They do not re-implement fetch, nonces, spinners, or error plumbing.

**Why this priority**: This is what turns the contract into a developer-experience win and what the future
`make:site` starter slice (spec 049) will showcase. It is P2 because US1/US2 already deliver the working contract
on the shipped forms; this generalises it.

**Independent Test**: Bind a second, custom form (not the built-in one) to the runtime and confirm it gets nonce
handling, loading state, validation rendering, and the success/error events with no bespoke fetch code.

**Acceptance Scenarios**:

1. **Given** a custom form carrying an exported validation schema, **When** the developer binds it to the runtime,
   **Then** client validation, loading, submission, and error rendering all work with no hand-written fetch/nonce
   code.
2. **Given** a developer needs a one-off request, **When** they call the runtime's request helper, **Then** the
   nonce is attached, the response is parsed, the envelope is normalised, and network/timeout errors are surfaced
   as a normal error result rather than an unhandled rejection.
3. **Given** any request, **When** it starts and ends, **Then** start/end lifecycle events fire, and on a form
   submission a success or error event fires carrying the envelope — so other scripts can react.

---

### User Story 4 - Admin screens speak the same contract (Priority: P2)

The existing Corex admin screens (Insights runs, the Data screen's actions) use the **same** runtime and envelope
as the front-end forms — so a `manage_options` admin sees consistent loading states and error messages across the
whole framework, and the duplicated fetch/error code in those scripts is removed.

**Why this priority**: Migration is the proof the contract is genuinely reusable across contexts (front-end +
admin), and it pays down existing duplication. P2 because it follows the contract being defined in US1/US2.

**Independent Test**: Run an Insights check and perform a Data action; both show a loading state, both render
their result/error through the shared runtime, and neither contains its own copy of the fetch/nonce/error logic.

**Acceptance Scenarios**:

1. **Given** the Insights and Data admin scripts, **When** they are migrated, **Then** they issue requests and
   render results/errors through the shared runtime, with the bespoke fetch/error code removed.
2. **Given** an admin action fails, **When** the response returns, **Then** the failure is shown through the same
   envelope-driven rendering as a front-end form, including the busy state and accessible status.

---

### Edge Cases

- **Non-JSON / HTML error response** (e.g. a server 500 or a security wall returns HTML): the runtime surfaces a
  generic, translatable error result — never a parse exception, never a raw HTML dump shown to the user.
- **Network failure or timeout**: surfaced as a normal error result with a translatable message and the end event
  fired; the form/button state is restored.
- **Expired or missing nonce** (security rejection): shown as an actionable error (e.g. "refresh and try again"),
  not a silent failure; the loading state is cleared.
- **JavaScript disabled / runtime fails to load**: the server remains authoritative — the underlying form fields
  and the secured submit route still exist; behaviour degrades to a standard request, and accessible error
  regions are still present in the markup. (Documented limitation: instant client validation requires JS.)
- **Empty field-error map on a failure**: the global status still communicates the failure; no field is wrongly
  flagged.
- **Unknown error `code`**: the runtime falls back to the human `message`; it never shows the raw code to the user.
- **A page with no Corex form or admin screen**: none of the runtime CSS/JS loads (conditional enqueue; Principle
  VI).

## Requirements *(mandatory)*

### Functional Requirements

**Response contract (server)**

- **FR-001**: The framework MUST define a single canonical **success** response shape carrying a success flag, a
  human-readable message, and a data payload (which MAY be empty).
- **FR-002**: The framework MUST define a single canonical **error** response shape carrying a failure flag, a
  human-readable message, a stable machine-readable code, an optional **field→message** error map, and an
  optional safe details object.
- **FR-003**: The response value MUST be **immutable** once built and MUST be independently verifiable (pure,
  unit-testable) without a running web request.
- **FR-004**: A failure response MUST NEVER include secret material (API keys, tokens, raw internal exception
  text); only the code, message, field errors, and explicitly-safe details (Principle VII).
- **FR-005**: Corex endpoints (the form submission route, admin actions, and the insights/data actions) MUST emit
  responses in this contract, routed through the **existing** middleware/security lifecycle — no new auth path and
  no relaxation of nonce/capability checks.
- **FR-006**: The contract MUST be **additive / backward compatible** — existing consumers of current Corex JSON
  responses MUST continue to work after the change.

**Client runtime (`window.Corex`)**

- **FR-007**: The framework MUST expose a client runtime as a global object with no jQuery dependency and no build
  step required to use it.
- **FR-008**: The runtime MUST provide request helpers (read, create, delete intents) that automatically attach
  the required security nonce, parse the response, normalise it to the envelope shape, and handle timeout and
  network errors as ordinary error results (never unhandled rejections).
- **FR-009**: The runtime MUST provide a single **bind** entry point that, given a form carrying an exported
  validation schema, performs client-side validation that **mirrors** the server rules, renders per-field errors
  and a global status, prevents duplicate submission, submits, and then renders the server's authoritative errors.
- **FR-010**: The server MUST remain the **authoritative** validator — client validation is a convenience; a
  submission that passes the client MUST still be validated server-side, and server field errors override.
- **FR-011**: The runtime MUST provide a loading facility that disables the relevant submit control, marks the
  region busy for assistive technology, shows/hides a spinner, deduplicates concurrent submissions, and **restores**
  the prior state on both success and error.
- **FR-012**: The runtime MUST provide a notices facility for rendering a global success/error status in an
  accessible status region.
- **FR-013**: The runtime MUST emit lifecycle custom events for request start and end, and form submission success
  and error, each carrying the relevant envelope, so other scripts can react.

**Styling, accessibility, i18n, loading**

- **FR-014**: All runtime styling MUST be token-only (theme.json / brand.json CSS custom properties; no hardcoded
  colour/size/font) and use logical CSS properties so RTL is correct by default.
- **FR-015**: The runtime MUST expose a documented, stable set of CSS classes for its loading and status surfaces
  (a loading state, a spinner, a form status region, and a form overlay) so themes can style them via tokens.
- **FR-016**: Status and error surfaces MUST meet WCAG 2.2 AA — busy state announced, errors associated with their
  fields, status changes announced via a live region, and keyboard focus never lost.
- **FR-017**: All user-facing strings produced by the runtime and the server messages MUST be translation-ready
  (i18n); no hardcoded user-facing text.

**Conditional loading & migration**

- **FR-018**: The runtime CSS/JS MUST load **only** on pages/screens that actually present a Corex form or a Corex
  admin screen that uses it — never globally (Principle VI).
- **FR-019**: The existing front-end form script and the Insights and Data admin scripts MUST be migrated to
  consume the shared runtime and envelope, with their duplicated fetch/nonce/error code removed, and MUST preserve
  (or improve) their current behaviour.

### Key Entities *(include if feature involves data)*

- **Response envelope**: the canonical shape of every Corex response. Success variant: succeeded-flag + message +
  data. Error variant: failed-flag + message + machine code + field-error map + safe details.
- **Field-error map**: a mapping of field name → human-readable, translatable message, produced by the server's
  validation and rendered by the client against the same fields.
- **Validation schema (existing — reused)**: the per-field rule set already exported from the server form
  definition (spec 020); the runtime mirrors it client-side. This feature consumes it, it does not redefine it.
- **Runtime facilities (conceptual)**: request helper, form binder, loading controller, notices — the named
  capabilities of `window.Corex`, each independently described and testable.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A visitor submitting an invalid Corex form sees the offending field's error **without a full page
  reload**, and cannot trigger a duplicate submission by repeated clicking.
- **SC-002**: **100%** of the framework's own response-emitting endpoints (form submission, admin actions, insights
  and data actions) return bodies that conform to the one documented success/error structure.
- **SC-003**: A developer can wire a new custom form to the runtime with a **single bind call** and **zero**
  hand-written fetch, nonce, spinner, or error-rendering code.
- **SC-004**: Submission status is perceivable to assistive technology — the busy state and the success/error
  outcome are announced via a live region, and keyboard focus is retained throughout (WCAG 2.2 AA).
- **SC-005**: **No regression** — every existing form submission and admin action works the same or better, and a
  page with no Corex form or screen loads **none** of the runtime's CSS/JS.
- **SC-006**: **No secret** value appears in any success or error response across the framework's endpoints.

## Assumptions

- The existing security middleware (spec 005), the secured form-submission REST route (spec 007), and the exported
  per-field validation schema (spec 020) are **reused**, not replaced; this feature standardises their response
  shape and consolidates the client logic.
- Delivery is a global `window.Corex` object first; publishing the runtime as importable npm modules is **out of
  scope** (deferred — a later increment if demanded).
- The security nonce is the existing WordPress REST nonce already used by the current form and admin scripts.
- "Backward compatible / additive" means existing JSON consumers keep working: the envelope keys are a superset of,
  or compatible with, today's shape; any change to an existing endpoint's body is additive.
- Out of scope (explicitly): the REST resource generator and headless mode (spec 046), npm-published runtime
  modules, toast/notification **visual design** polish (spec 051), and JWT/OAuth authentication.
- Browser-visual confirmation of the loading/spinner/announcements requires a running server + browser; per the
  project-wide environment gate, the automated Jest + Pest coverage is authoritative and the live browser smoke is
  run when the environment is available.
