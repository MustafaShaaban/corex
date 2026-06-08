# Phase 1 Data Model: Middleware + Security

Runtime objects of the middleware subsystem. No persisted entities (throttle uses transients).

## Entity map

```text
MiddlewareResolver.resolve('auth:cap') → Middleware (container alias + param; unknown → RejectingMiddleware)
Pipeline.run(Request, handler) → folds Middleware[] around the handler → Response
  each Middleware.process(Request, next) → Response (short-circuit) | next(Request) (pass)
  throw → caught → Response::reject (fail closed)
```

## 1. Middleware (interface)  *(FR-001)*

- `process(Request $request, callable $next): Response` — return a `Response` to short-circuit, or
  `$next($request)` to pass inward. Throwing is caught by the pipeline → reject.

## 2. Request  *(R2)*

- **Fields**: `method` (GET/POST/…), `input` (array), `nonce` (string), `nonceAction` (string),
  `throttleKey` (string). Immutable; `withInput(array $input): self` returns a copy.

## 3. Response  *(FR-003)*

- `Response::ok(mixed $value = null): self`, `Response::reject(string $reason, int $status = 403): self`;
  `isOk(): bool`, plus `value`/`reason`/`status`. What a middleware/handler returns.

## 4. Pipeline  *(FR-002–FR-004, FR-006)*

- `run(Request $request, callable $handler, Middleware ...$middleware): Response` — compose the list
  around `$handler` (onion); run; a throw anywhere → `Response::reject` (logged). Empty list → handler
  directly.
- **Invariant**: a short-circuit stops all inner middleware + the handler (SC-001).

## 5. MiddlewareResolver  *(FR-012, FR-014, FR-015)*

- `resolve(string $name): Middleware` — split `alias:param`; resolve `alias` from the container (a bound
  factory `fn(?string $param): Middleware`); unknown alias → `RejectingMiddleware` (always rejects,
  logged — fail closed).
- `resolveAll(array $names): list<Middleware>`.

## 6. The four middleware  *(FR-007–FR-010)*

| Middleware | Passes | Rejects |
|---|---|---|
| **NonceMiddleware** | GET, or non-GET with a valid `wp_verify_nonce` | non-GET with missing/invalid nonce |
| **CapabilityMiddleware($cap)** | `current_user_can($cap)` | otherwise |
| **ThrottleMiddleware** | count `< limit` in window (transient) | count `>= limit` (until window resets) |
| **SanitizeMiddleware($shape)** | always (passes a cleaned Request) | — (transforms, does not reject) |

## 7. RejectingMiddleware  *(FR-015)*

- Always returns `Response::reject` — used by the resolver for an unknown name (fail closed).

## 8. SecurityModule (ServiceProvider)  *(FR-016)*

- `register()`: bind alias factories `nonce`/`auth`/`throttle`/`sanitize` and the `MiddlewareResolver`
  and `Pipeline`. Added to `Boot`'s provider list. Ships `config/security.php` defaults.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| middleware/handler throws | caught → Response::reject (logged) | FR-006 |
| unknown middleware name | RejectingMiddleware (fail closed) | FR-015 |
| nonce missing on non-GET | reject before handler | FR-007 |
| capability absent | reject | FR-008 |
| over throttle limit | reject until window resets | FR-009 |
| unexpected input keys | dropped by sanitize | FR-010 |
| optional plugin absent | all middleware work | FR-017, SC-005 |
