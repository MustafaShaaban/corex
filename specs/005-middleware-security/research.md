# Phase 0 Research: Middleware + Security

All decisions resolve the Technical Context. No NEEDS CLARIFICATION (clarified 2026-06-08).

## R1 — Pipeline composition (onion) + value-typed short-circuit

**Decision**: `Pipeline::run(Request $request, callable $handler): Response` reduces the ordered
middleware list (right to left) into a single `callable $next`, starting from the handler, so the first
middleware is outermost. Each middleware `process($request, $next)` returns a `Response` to short-circuit
or returns `$next($request)` to pass inward. The pipeline wraps the whole run in try/catch → a throwing
middleware/handler yields `Response::reject(...)` (fail closed, logged, FR-006).

**Rationale**: The fold is the canonical onion model (Laravel/PSR-15 style); value-typed responses make
short-circuit and assertions trivial without exceptions for normal control flow (the clarified decision).

**Alternatives**: exceptions for rejection (rejected — control-flow-by-exception, harder to test);
a linked-list of handler objects (more types, same behavior).

## R2 — Request / Response

**Decision**: `Request` is a minimal immutable context — method, input array, the nonce value + action,
and a throttle key — with `with*` copies (sanitize returns a new Request with cleaned input). `Response`
is a value object: `Response::ok(mixed $value = null)` and `Response::reject(string $reason, int $status =
403)`, with `isOk()`.

**Rationale**: Only as much abstraction as the pipeline needs (spec scope); immutable Request keeps
sanitize a pure transformation; the typed Response makes pass/reject explicit (SC-001/002).

## R3 — Name + parameter resolution

**Decision**: `MiddlewareResolver::resolve(string $name): Middleware` splits on the first `:` into
`alias` + `parameter`; resolves `alias` from the container (a bound factory keyed by the alias) passing
the parameter; an unknown alias returns a `RejectingMiddleware` that always rejects (fail closed, FR-015)
and is logged.

**Rationale**: One-parameter `auth:manage_options` syntax is the familiar Laravel form; fail-closed on
unknown is the secure default (a typo must never silently drop protection).

## R4 — The four middleware (WP boundary)

**Decision**:
- **NonceMiddleware**: pass GET (read-only) by default; for non-GET, reject unless `wp_verify_nonce($req
  ->nonce, $req->nonceAction)` is truthy. Which methods require a nonce is configurable.
- **CapabilityMiddleware**: constructed with the required capability (the resolution parameter); reject
  unless `current_user_can($cap)`.
- **ThrottleMiddleware**: read a per-key count from a transient; if `>= limit` reject; else increment with
  the window TTL and pass. Limit/window from `config('security.throttle.*')` (default 60/60s).
- **SanitizeMiddleware**: reduce the request input to the declared expected keys, each run through the
  type-correct WP sanitizer; return `$next($request->withInput($clean))`.

**Rationale**: Each wraps exactly one WP-core security primitive at the boundary (Principle VII), keeping
the middleware tiny and unit-testable via Brain Monkey stubs.

## R5 — Headless test approach

**Decision**: Unit-test the pipeline (order, short-circuit, empty, throw→reject), the resolver (alias +
param, unknown→fail-closed), and each middleware (pass/reject cases) with WP functions stubbed via Brain
Monkey (`wp_verify_nonce`, `current_user_can`, `get_transient`/`set_transient`, `sanitize_text_field`).
No WordPress boot required (FR-017, SC-006).

## Summary

| Concern | Choice |
|---|---|
| Pipeline | right-fold onion; middleware returns Response or calls next; throw → reject (fail closed) |
| Request/Response | minimal immutable Request; Response::ok/reject value object |
| Resolution | `alias:param`; container alias; unknown → RejectingMiddleware (fail closed) |
| Nonce | non-GET requires `wp_verify_nonce`; configurable |
| Capability | `current_user_can($cap)` |
| Throttle | transient count vs config limit/window (60/60s) |
| Sanitize | reduce to expected keys via WP sanitizers; new Request |
| Tests | all headless (Brain Monkey boundary stubs) |

No new Composer dependencies; `config/security.php` ships throttle defaults.
