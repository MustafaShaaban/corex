# Implementation Plan: Middleware + Security

**Branch**: `005-middleware-security` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/005-middleware-security/spec.md`

## Summary

Deliver declarative, automatic security (Principle VII): a container-resolved middleware **Pipeline**
(onion) that runs an ordered list of middleware around a handler — each middleware returns a `Response`
to short-circuit or calls `next` to pass inward; a throwing middleware is caught → rejection (fail
closed). Four core middleware (Nonce, Capability, Throttle, Sanitize) implement one interface. A
**MiddlewareResolver** maps a declared name (`auth:manage_options`) to a container middleware (fail
closed on unknown). A **SecurityModule** provider registers the standard aliases. All of it is
unit-testable headlessly (WP functions stubbed at the boundary).

## Technical Context

**Language/Version**: PHP 8.3.

**Primary Dependencies**: corex-core (container, `ServiceProvider`, `Config`, `BootLogger`). WP security
functions (`wp_verify_nonce`, `current_user_can`, `get_transient`/`set_transient`, sanitizers) — stubbed
at the boundary in unit tests; never optional-plugin-dependent.

**Storage**: Throttle counts in WP transients (`security.throttle.*` config). No new tables.

**Testing**: Pest + Brain Monkey. Pipeline composition, the resolver, and each middleware are pure/
injectable → unit-tested headlessly (WP funcs stubbed). No integration needed beyond the existing boot.

**Target Platform**: WordPress ≥ 7.0, PHP ≥ 8.3.

**Project Type**: `Corex\Http\Middleware` → `plugins/corex-core/src/Http/Middleware`; the SecurityModule
under `Corex\Security` → `plugins/corex-core/src/Security`.

**Performance Goals**: The pipeline is a function composition (no overhead beyond the middleware);
throttle is one transient read/write per request.

**Constraints**: Fail closed on throw/unknown-name; controllers contain no security checks; everything
injected; works with no optional plugin.

**Scale/Scope**: pipeline + four middleware + declarative resolution + SecurityModule. REST route
registration, a full request/response stack, and CAPTCHA are out of scope. No NEEDS CLARIFICATION
(clarified 2026-06-08).

## Constitution Check

- [x] **I/V/VI/VIII** — N/A (no theme/assets/styling).
- [x] **II. Plugins boot themselves** — PASS: SecurityModule is a provider on the existing boot.
- [x] **III. Thin controllers** — PASS: security moves *out* of controllers into middleware (the point).
- [x] **IV. Everything injected** — PASS: pipeline resolves every middleware from the container.
- [x] **VII. Declarative security** — **PASS (headline)**: routes declare middleware; nonce/cap/throttle/
  sanitize are enforced by middleware, never hand-rolled; fail-closed on throw/unknown.
- [x] **IX. No optional dep is hard** — PASS: only WP-core security functions; SC-005 proves operation
  with ACF/Woo/Polylang absent.
- [x] **X. Spec is source of truth** — PASS: traces to spec 005 (clarified); implements FRAMEWORK §9.
- [x] **Guard Gate + Definition of Done** — guards per task; Pest tests; PROGRESS/DECISIONS updated.

**Result: PASS** — no violations.

## Project Structure

```text
plugins/corex-core/src/
├── Http/Middleware/
│   ├── Middleware.php          # interface: process(Request $r, callable $next): Response
│   ├── Request.php             # minimal request context (method, input, nonce, key)
│   ├── Response.php            # ok($value) | reject($reason, $status) — what a middleware/handler returns
│   ├── Pipeline.php            # compose ordered middleware around a handler; run; catch throw → reject
│   ├── MiddlewareResolver.php  # name (+:param) → container middleware; fail-closed on unknown
│   ├── NonceMiddleware.php     # reject non-GET without a valid nonce
│   ├── CapabilityMiddleware.php# reject when !current_user_can($cap)
│   ├── ThrottleMiddleware.php  # transient count per key vs limit/window
│   └── SanitizeMiddleware.php  # reduce input to the expected, sanitized shape
└── Security/
    └── SecurityModule.php       # ServiceProvider: bind the four aliases (nonce/auth/throttle/sanitize)

plugins/corex-core/config/security.php   # ['throttle' => ['limit' => 60, 'window' => 60]]

tests/Unit/Security/                       # Pipeline, Resolver, each middleware (Brain Monkey boundary stubs)
```

**Structure Decision**: Middleware lives under the existing `Http/` namespace (spec 001 placed
`ControllerMap` there); `SecurityModule` joins `Boot`'s provider list. Declarative attachment reuses the
controller layer — a controller exposes a `middleware(): array` (resolved by the resolver) without REST
registration (out of scope).

## Key design decisions

1. **Onion pipeline, value-typed short-circuit** — `Pipeline::run(Request, handler)` folds the middleware
   list into a nested chain; each middleware returns a `Response` to stop or calls `$next($request)`. A
   throwing middleware is caught → `Response::reject` (fail closed, logged) — never an open pass.
2. **Pure + injectable** — middleware are tiny classes with their WP calls (`wp_verify_nonce`,
   `current_user_can`, transients) at the boundary; unit tests stub those. The pipeline/resolver are pure
   PHP.
3. **Name resolution** — `MiddlewareResolver::resolve('auth:manage_options')` splits on `:`, resolves the
   base alias from the container, and passes the parameter; an unknown alias yields a fail-closed
   middleware (rejects), never a skip (FR-015).
4. **Aliases via the container** — `SecurityModule` binds `nonce`/`auth`/`throttle`/`sanitize` (as named
   container entries) so routes reference them by string; the parameter is applied at resolution.
5. **Controllers stay clean** — a controller declares `middleware()`; the framework builds the pipeline
   and runs the handler. No security calls in the controller (Principle VII, SC-003).

## Phase 0 — Research

See [research.md](./research.md): the onion composition, value-typed responses + fail-closed throw
handling, name+param resolution, the four middleware's WP boundary, throttle transient strategy, and the
headless test approach. No open NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) — Middleware, Request, Response, Pipeline, Resolver, the four
  middleware, SecurityModule.
- [contracts/middleware-contracts.md](./contracts/middleware-contracts.md) — the PHP public API + test
  matrix.
- [quickstart.md](./quickstart.md) — runnable validation scenarios mapped to the success criteria.

## Complexity Tracking

No constitution violations — section intentionally empty.
