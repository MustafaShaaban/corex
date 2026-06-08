# Feature Specification: Middleware + Security

**Feature Branch**: `005-middleware-security`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "Middleware + Security — a container-resolved middleware pipeline (onion); the four core middleware (nonce, auth/capability, throttle, sanitize) as small injectable units behind one interface; declarative attachment (a route declares its middleware by name, applied automatically before the handler; controllers never hand-write security checks — Principle VII); a SecurityModule wiring the standard aliases into the container. Built on corex-core; honors the constitution; the pipeline + each middleware are unit-testable headlessly."

## Overview

This feature makes Corex's security **declarative and automatic** (constitution Principle VII). A
request handler is wrapped by an ordered chain of middleware — each can reject (short-circuit) or pass
through to the next, ending at the handler. The four core middleware (nonce, capability, throttle,
sanitize) are small injectable units behind one interface. A controller/route simply **declares** which
middleware apply, by name, and the framework runs them automatically before the handler — so security
checks live in middleware, never hand-written in controllers. A SecurityModule registers the standard
middleware aliases in the container so routes reference them by name. The "users" are Corex module
developers; the indirect beneficiaries are site visitors (protected requests).

## Clarifications

### Session 2026-06-08

- Q: How does a middleware short-circuit — exception or a returned value? → A: A middleware returns a `Response` value object to short-circuit (the pipeline returns it); passing through calls `next`. A middleware that *throws* is caught and converted to a rejection `Response` (fail-closed, logged) — exceptions are not the normal rejection path.
- Q: Which requests require a nonce by default? → A: Non-GET / state-changing requests require a valid nonce; the policy is configurable. Read-only (GET) requests are not nonce-gated by default.
- Q: How is throttle state stored and what is the default limit? → A: WP transient storage keyed by the throttle key; default 60 requests per 60-second window, both configurable via the Config engine (`security.throttle.*`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Run a handler through a middleware pipeline (Priority: P1)

A developer composes an ordered list of middleware around a handler. When a request runs, each
middleware executes in order; any middleware may short-circuit (return a rejection response) so later
middleware and the handler do not run; otherwise control passes inward to the handler and back out.

**Why this priority**: The pipeline is the irreducible core — every middleware and every declarative
attachment runs through it. Without it nothing composes.

**Independent Test**: build a pipeline with two pass-through middleware and a handler; run a request and
confirm the handler ran and both middleware saw it in order; replace the first middleware with one that
rejects and confirm the handler and the second middleware did not run and the rejection was returned.

**Acceptance Scenarios**:

1. **Given** a pipeline of pass-through middleware around a handler, **When** a request runs, **Then**
   each middleware runs in order and the handler produces the response.
2. **Given** a middleware that short-circuits, **When** a request runs, **Then** later middleware and the
   handler do not run and the middleware's rejection is the response.
3. **Given** an empty middleware list, **When** a request runs, **Then** the handler runs directly.
4. **Given** the pipeline, **When** it is built, **Then** every middleware is resolved from the container
   (dependencies injected), not hand-constructed.

### User Story 2 - The four core middleware enforce security (Priority: P1)

A developer relies on reusable middleware to enforce security: **nonce** rejects a state-changing
request without a valid nonce; **capability** rejects a request from a user lacking a required
capability; **throttle** rejects a request that exceeds a rate limit for its key; **sanitize** cleans
the input shape before the handler sees it. Each is a small class implementing the one middleware
interface.

**Why this priority**: These four are the security primitives Principle VII promises — they are the
reason controllers never hand-write checks. They run on the US1 pipeline.

**Independent Test**: for each middleware, run a request that should pass and one that should be rejected
(missing/invalid nonce; missing capability; over-limit; malformed input) and confirm the pass/reject
outcome and that the handler ran only on pass; confirm sanitize hands cleaned input to the handler.

**Acceptance Scenarios**:

1. **Given** the nonce middleware, **When** a state-changing request lacks a valid nonce, **Then** it is
   rejected before the handler; with a valid nonce it passes.
2. **Given** the capability middleware requiring a capability, **When** the current user lacks it,
   **Then** the request is rejected; when the user has it, it passes.
3. **Given** the throttle middleware with a limit, **When** the limit for a key is exceeded, **Then**
   further requests are rejected until the window resets; within the limit they pass.
4. **Given** the sanitize middleware, **When** a request carries unsafe/extra input, **Then** the
   handler receives only the cleaned, expected shape.
5. **Given** any rejection, **When** it occurs, **Then** the handler never runs and no side effect from
   the handler takes place.

### User Story 3 - Routes declare their middleware (Priority: P2)

A developer attaches middleware to a controller/route by **declaring** their names (e.g.
`['nonce','auth:manage_options']`); the framework resolves each name to a middleware and applies them
automatically before the handler. The controller contains no security checks of its own.

**Why this priority**: Declarative attachment is what makes security automatic and unforgettable
(Principle VII). It depends on US1 (pipeline) and US2 (the named middleware).

**Independent Test**: declare a route with `['nonce','auth:<cap>']`; dispatch a request and confirm both
middleware ran (reject when either fails); confirm the controller method contains no nonce/capability
calls and still the request is protected.

**Acceptance Scenarios**:

1. **Given** a route declaring middleware by name, **When** it is dispatched, **Then** those middleware
   are resolved and applied automatically in declared order before the handler.
2. **Given** a middleware name with a parameter (e.g. `auth:manage_options`), **When** it is resolved,
   **Then** the parameter is passed to the middleware.
3. **Given** a controller method, **When** it runs through declared middleware, **Then** it performs no
   hand-written security check and is still protected.
4. **Given** an unknown middleware name, **When** the route is dispatched, **Then** the problem is
   reported (fail closed) rather than silently skipping protection.

### User Story 4 - Standard middleware available by name (Priority: P2)

A SecurityModule registers the standard middleware aliases (`nonce`, `auth`, `throttle`, `sanitize`) in
the container so any route can reference them by name without wiring them itself.

**Why this priority**: The aliases are what make US3's declarations work out of the box; the module is
the wiring seam.

**Independent Test**: after the module loads, resolve each standard alias by name and confirm it yields
the correct middleware; confirm the framework loads with the module and the aliases are available.

**Acceptance Scenarios**:

1. **Given** the SecurityModule is loaded, **When** a standard alias is resolved by name, **Then** the
   correct middleware instance is returned (from the container).
2. **Given** the framework, **When** it loads, **Then** the standard aliases are registered and a route
   can reference them without bespoke wiring.

### Edge Cases

- **Empty pipeline**: a handler with no middleware runs directly (no error).
- **Middleware throws**: a middleware that throws is treated as a rejection (fail closed) and logged —
  never an open pass-through to the handler.
- **Unknown middleware name**: resolving an unregistered name fails closed (rejects/reports), never
  silently skips.
- **Nonce on a read-only request**: nonce enforcement targets state-changing requests; the policy for
  which requests require a nonce is explicit and testable.
- **Throttle window reset**: after the limit window passes, the key's count resets and requests pass
  again.
- **Sanitize drops unexpected keys**: input not in the declared expected shape is removed, not passed
  through.
- **No optional plugins**: middleware and the pipeline work with ACF/Woo/Polylang absent.

## Requirements *(mandatory)*

### Functional Requirements

**Pipeline**

- **FR-001**: A middleware MUST implement one interface: given the request and a "next" callable, it
  either returns a `Response` value (short-circuit) or calls next to pass control inward. A middleware
  that throws MUST be caught and converted to a rejection `Response` (FR-006), not propagated.
- **FR-002**: The pipeline MUST run an ordered list of middleware around a final handler (onion model):
  outer middleware wrap inner ones; the handler is the innermost.
- **FR-003**: A short-circuiting middleware MUST prevent later middleware and the handler from running;
  its response MUST be returned.
- **FR-004**: An empty middleware list MUST run the handler directly.
- **FR-005**: Every middleware in a pipeline MUST be resolved through the container (injected); none
  hand-constructed.
- **FR-006**: A middleware that throws MUST be treated as a rejection (fail closed) and logged — never an
  open pass to the handler.

**Core middleware**

- **FR-007**: A **nonce** middleware MUST reject a state-changing (non-GET) request without a valid
  nonce and pass one with a valid nonce; which requests require a nonce is configurable.
- **FR-008**: A **capability** (auth) middleware MUST reject a request whose current user lacks a
  required capability and pass one whose user has it.
- **FR-009**: A **throttle** middleware MUST reject requests for a key beyond a configured limit within a
  window and pass within the limit; the count MUST reset after the window. State is stored in WP
  transients; the default is 60 requests per 60s, configurable via Config (`security.throttle.*`).
- **FR-010**: A **sanitize** middleware MUST pass the handler only the cleaned, expected input shape
  (unexpected keys removed; values sanitized).
- **FR-011**: On any rejection, the handler MUST NOT run and no handler side effect MUST occur.

**Declarative attachment**

- **FR-012**: A controller/route MUST be able to declare its middleware as a list of names (optionally
  with a parameter, e.g. `auth:manage_options`); the framework applies them automatically before the
  handler.
- **FR-013**: Controllers MUST NOT contain hand-written security checks; the declared middleware enforce
  them (Principle VII).
- **FR-014**: A middleware name with a parameter MUST pass that parameter to the middleware.
- **FR-015**: An unknown/unregistered middleware name MUST fail closed (reject/report), never silently
  skip protection.

**SecurityModule**

- **FR-016**: A SecurityModule MUST register the standard aliases (`nonce`, `auth`, `throttle`,
  `sanitize`) in the container so routes reference them by name; registered through a corex-core service
  provider.

**Cross-cutting**

- **FR-017**: The pipeline and every middleware MUST be exercisable in headless automated tests (WP
  functions stubbed at the boundary), with no optional plugin installed.
- **FR-018**: Everything MUST be injected via the container; the feature adds no presentation and no
  business logic (it is cross-cutting request handling).

### Key Entities

- **Middleware**: a unit implementing the one middleware interface (`process(request, next)`); may reject
  or pass through.
- **Pipeline**: composes an ordered middleware list around a handler and runs a request through it.
- **Request / Response**: the minimal request context a middleware reads and the response/rejection it
  may return (only as much abstraction as the pipeline needs).
- **Middleware resolver**: maps a declared name (and optional parameter) to a container-resolved
  middleware (fail-closed on unknown).
- **SecurityModule**: registers the standard aliases.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A pipeline of N middleware runs them in declared order and reaches the handler only when
  **none** short-circuit; a short-circuit stops the chain in **100%** of cases.
- **SC-002**: Each of the four core middleware rejects its failing case and passes its valid case in
  **100%** of tested cases; on rejection the handler runs **0** times.
- **SC-003**: A route declaring middleware by name applies them automatically; a controller that declares
  protection contains **zero** hand-written security checks and is still protected.
- **SC-004**: An unknown middleware name and a throwing middleware **both** fail closed (handler does not
  run) in **100%** of cases.
- **SC-005**: With ACF, WooCommerce, and Polylang uninstalled, **100%** of the pipeline/middleware tests
  pass headlessly.
- **SC-006**: Every pipeline and middleware behavior is covered by a headless automated test that passes
  with no optional plugins present.

## Assumptions

- **Audience**: Corex module developers; site visitors benefit indirectly (protected requests).
- **Request abstraction**: the feature introduces only the minimal request/response context the pipeline
  needs (the input array + a way to return a rejection); a full PSR-7-style stack is out of scope.
- **Middleware interface**: a single `process(request, next): response` contract; `next` runs the rest of
  the chain.
- **Nonce policy**: nonce enforcement targets state-changing requests; the rule for which requests
  require a nonce is explicit and configurable, defaulting to non-GET/state-changing actions.
- **Throttle storage**: rate-limit counts use existing WordPress transient/object-cache storage keyed by
  the throttle key; no new tables.
- **Names + parameters**: a declared middleware name may carry one parameter after a colon
  (`auth:manage_options`); resolution maps the base name to a container alias and passes the parameter.
- **Foundation dependency**: built on corex-core (container, providers, config, hook registry); the
  SecurityModule is a service provider; integrates with the controller layer (spec 001 ControllerMap)
  without requiring REST route registration (out of scope here).
- **Scope boundary**: REST route registration details, a full request/response abstraction, REST-specific
  CSRF vs nonce nuances, and CAPTCHA/bot mitigation are **out of scope**; this spec delivers the
  pipeline + four middleware + declarative attachment + SecurityModule wiring.
- **Environment**: developed against the working WordPress install; Environment Gate satisfied.
