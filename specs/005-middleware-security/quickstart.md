# Quickstart & Validation: Middleware + Security

Runnable scenarios. Types live in [contracts/middleware-contracts.md](./contracts/middleware-contracts.md)
and [data-model.md](./data-model.md).

## Prerequisites

- corex-core active (spec 001). `composer install`.

## Run the tests

```bash
composer test   # headless unit: Pipeline, MiddlewareResolver, the four middleware (Brain Monkey stubs)
```

## Scenario 1 — Pipeline order + short-circuit (US1, SC-001)

```php
$response = $pipeline->run($request, $handler, $outer, $inner);   // both pass → handler runs
$response = $pipeline->run($request, $handler, $rejecting, $inner); // rejecting stops inner + handler
$response = $pipeline->run($request, $handler);                    // empty → handler directly
```
**Expected**: middleware run outer→inner; a short-circuit returns its rejection and the handler never
runs; a throwing middleware → a fail-closed reject (logged), handler side effect absent.

## Scenario 2 — The four middleware (US2, SC-002)

```text
nonce:     non-GET without a valid nonce → reject; with valid nonce → pass.
auth:      current_user_can($cap) false → reject; true → pass.
throttle:  count >= limit → reject (until window resets); within limit → pass.
sanitize:  handler receives only the cleaned, expected keys.
```
**Expected**: each rejects its failing case and passes its valid case; on reject the handler runs 0 times.

## Scenario 3 — Declarative attachment (US3, SC-003)

```php
// a controller declares: middleware(): array { return ['nonce', 'auth:manage_options']; }
$middleware = $resolver->resolveAll($controller->middleware());
$pipeline->run($request, $handler, ...$middleware);
```
**Expected**: declared middleware resolve (with the `:param`) and run automatically; the controller has
zero hand-written security checks and is still protected; an unknown name fails closed.

## Scenario 4 — Aliases by name (US4, SC-004)

```php
$resolver->resolve('throttle');   // → ThrottleMiddleware (from the SecurityModule alias)
$resolver->resolve('nope');       // → a RejectingMiddleware (fail closed)
```
**Expected**: standard aliases resolve to the right middleware; an unknown name yields a rejecting
middleware (never a silent skip).

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 order + short-circuit | 1 |
| SC-002 four middleware pass/reject | 2 |
| SC-003 declarative, no hand-written checks | 3 |
| SC-004 unknown + throw fail closed | 1, 4 |
| SC-005 passes with no optional plugins | `composer test` |
| SC-006 headless coverage | `composer test` |
